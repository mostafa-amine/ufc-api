<?php

namespace App\Scraping\Parsers;

use App\Scraping\Support\Parse;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Parses a ufcstats fight page: corners, result, finish info, judge scorecards,
 * and the full totals + per-round statistics (including the significant-strike
 * target/position breakdown).
 *
 * Corner convention: person 0 / left / first stat column = "red"; person 1 = "blue".
 *
 * Scorecard convention (verified empirically across unanimous + split decisions):
 * ufcstats orders each judge's "a - b" score as [loser corner, winner corner] -- the
 * SECOND number is the bout winner's score, regardless of red/blue position. We map
 * scores to corners via the bout winner, NOT by column position.
 */
class FightDetailParser
{
    private const TOTALS_KEYS = ['knockdowns', 'sig', 'total', 'td', 'sub', 'rev', 'ctrl'];

    public function parse(string $html): array
    {
        $crawler = new Crawler($html);

        $persons = $this->persons($crawler);
        $winnerCorner = $this->winnerCorner($persons);
        [$texts] = $this->textBlocks($crawler);

        return [
            'persons' => $persons,
            'outcome' => $this->outcome($persons),
            'winner_corner' => $winnerCorner,
            'weight_class' => $this->weightClass($crawler),
            'is_title_bout' => $this->isTitle($crawler),
            'method' => $texts['Method'] ?? null,
            'method_detail' => $this->methodDetail($crawler),
            'end_round' => isset($texts['Round']) ? (int) $texts['Round'] : null,
            'end_time_sec' => Parse::clockToSeconds($texts['Time'] ?? null),
            'time_format_raw' => $texts['Time format'] ?? null,
            'scheduled_rounds' => Parse::scheduledRounds($texts['Time format'] ?? null),
            'referee' => Parse::nullable($texts['Referee'] ?? null),
            'scorecards' => $this->scorecards($crawler, $winnerCorner),
            'stats' => $this->stats($crawler),
        ];
    }

    /** @return array<int, array{corner:string,status:string,name:string,fighter_ufcstats_id:?string,nickname:?string}> */
    private function persons(Crawler $crawler): array
    {
        return $crawler->filter('div.b-fight-details__person')->each(function (Crawler $p, int $i) {
            $nameNode = $p->filter('h3.b-fight-details__person-name');
            $link = $nameNode->filter('a');
            $nickname = $p->filter('p.b-fight-details__person-title');

            return [
                'corner' => $i === 0 ? 'red' : 'blue',
                'status' => Parse::clean($p->filter('i.b-fight-details__person-status')->text('')),
                'name' => Parse::clean($nameNode->text('')),
                'fighter_ufcstats_id' => $link->count() ? Parse::idFromUrl((string) $link->attr('href')) : null,
                'nickname' => $nickname->count() ? Parse::nullable($nickname->text('')) : null,
            ];
        });
    }

    private function winnerCorner(array $persons): ?string
    {
        foreach ($persons as $p) {
            if (strtoupper($p['status']) === 'W') {
                return $p['corner'];
            }
        }

        return null;
    }

    private function outcome(array $persons): string
    {
        $statuses = array_map(fn ($p) => strtoupper($p['status']), $persons);
        if (in_array('W', $statuses, true)) {
            return 'win';
        }
        if (in_array('NC', $statuses, true)) {
            return 'nc';
        }
        if (in_array('D', $statuses, true)) {
            return 'draw';
        }

        return 'pending';
    }

    private function weightClass(Crawler $crawler): ?string
    {
        $title = $crawler->filter('i.b-fight-details__fight-title');
        if ($title->count() === 0) {
            return null;
        }
        $text = Parse::clean($title->text(''));
        $text = preg_replace('/\b(UFC|Ultimate Fighting Championship)\b/i', '', $text);
        $text = preg_replace('/\b(Title|Interim|Bout)\b/i', '', $text);

        return Parse::nullable($text);
    }

    private function isTitle(Crawler $crawler): bool
    {
        $title = $crawler->filter('i.b-fight-details__fight-title');
        if ($title->count() === 0) {
            return false;
        }

        return $title->filter('img')->count() > 0
            || stripos($title->text(''), 'Title') !== false;
    }

    /**
     * Parse the two "b-fight-details__text" paragraphs into a label => value map.
     * The first paragraph holds Method/Round/Time/Time format/Referee.
     *
     * @return array{0: array<string,string>}
     */
    private function textBlocks(Crawler $crawler): array
    {
        $map = [];
        $first = $crawler->filter('p.b-fight-details__text')->first();
        if ($first->count()) {
            $first->filter('i.b-fight-details__text-item, i.b-fight-details__text-item_first')
                ->each(function (Crawler $item) use (&$map) {
                    $text = Parse::clean($item->text(''));
                    $pos = strpos($text, ':');
                    if ($pos === false) {
                        return;
                    }
                    $label = trim(substr($text, 0, $pos));
                    $value = trim(substr($text, $pos + 1));
                    if ($label !== '' && $value !== '') {
                        $map[$label] = $value;
                    }
                });
        }

        return [$map];
    }

    /** Finish detail (e.g. "Rear Naked Choke", "Punch to Head At Distance"); null for decisions. */
    private function methodDetail(Crawler $crawler): ?string
    {
        $paras = $crawler->filter('p.b-fight-details__text');
        if ($paras->count() < 2) {
            return null;
        }
        $text = Parse::clean($paras->eq(1)->text(''));
        // Decisions list judges here ("Name a - b."); not a finish detail.
        if (preg_match('/\d+\s*-\s*\d+/', $text)) {
            return null;
        }
        $text = preg_replace('/^Details:\s*/i', '', $text);

        return Parse::nullable($text);
    }

    /** @return array<int, array{judge:string,red_score:?int,blue_score:?int}> */
    private function scorecards(Crawler $crawler, ?string $winnerCorner): array
    {
        $paras = $crawler->filter('p.b-fight-details__text');
        if ($paras->count() < 2) {
            return [];
        }

        $cards = [];
        $paras->eq(1)->filter('i.b-fight-details__text-item')->each(function (Crawler $item) use (&$cards, $winnerCorner) {
            $text = Parse::clean($item->text(''));
            if (! preg_match('/(\d+)\s*-\s*(\d+)/', $text, $m)) {
                return;
            }
            $first = (int) $m[1];
            $second = (int) $m[2];

            $span = $item->filter('span');
            $judge = $span->count()
                ? Parse::clean($span->text(''))
                : Parse::clean(preg_replace('/\d+\s*-\s*\d+.*/', '', $text));

            // Second number = bout winner's corner. Fall back to [red, blue] for draws/NC.
            if ($winnerCorner === 'red') {
                $red = $second;
                $blue = $first;
            } elseif ($winnerCorner === 'blue') {
                $red = $first;
                $blue = $second;
            } else {
                $red = $first;
                $blue = $second;
            }

            $cards[] = ['judge' => $judge, 'red_score' => $red, 'blue_score' => $blue];
        });

        return $cards;
    }

    /**
     * Merge the totals + significant-strike tables into per-corner, per-round metrics.
     * Round 0 is the fight total.
     *
     * @return array{red: array<int, array<string,mixed>>, blue: array<int, array<string,mixed>>}
     */
    private function stats(Crawler $crawler): array
    {
        $totalsTables = [];
        $sigTables = [];

        $crawler->filter('table')->each(function (Crawler $t) use (&$totalsTables, &$sigTables) {
            $header = $t->filter('thead')->count() ? Parse::clean($t->filter('thead')->first()->text('')) : '';
            if (stripos($header, 'Ctrl') !== false) {
                $totalsTables[] = $t;
            } elseif (stripos($header, 'Head') !== false) {
                $sigTables[] = $t;
            }
        });

        $stats = ['red' => [], 'blue' => []];

        // Totals-type: [0] = round 0 total, [1] = per-round rows.
        foreach ($this->dataRows($totalsTables[0] ?? null) as $row) {
            $this->applyTotalsRow($stats, 0, $row);
        }
        foreach ($this->dataRows($totalsTables[1] ?? null) as $i => $row) {
            $this->applyTotalsRow($stats, $i + 1, $row);
        }

        // Sig-type: [0] = round 0 total, [1] = per-round rows.
        foreach ($this->dataRows($sigTables[0] ?? null) as $row) {
            $this->applySigRow($stats, 0, $row);
        }
        foreach ($this->dataRows($sigTables[1] ?? null) as $i => $row) {
            $this->applySigRow($stats, $i + 1, $row);
        }

        // Normalise: drop empty rounds, ensure each present round has all keys.
        foreach (['red', 'blue'] as $corner) {
            ksort($stats[$corner]);
            $stats[$corner] = array_map(fn ($r) => $this->fillDefaults($r), $stats[$corner]);
        }

        return $stats;
    }

    /** Data rows of a stats table (skips header / round-label rows). */
    private function dataRows(?Crawler $table): array
    {
        if ($table === null) {
            return [];
        }
        $rows = [];
        $table->filter('tr')->each(function (Crawler $tr) use (&$rows) {
            if ($tr->filter('th')->count() > 0) {
                return; // header / "Round N" label
            }
            if ($tr->filter('td')->count() === 0) {
                return;
            }
            $rows[] = $tr;
        });

        return array_values($rows);
    }

    /** @return array{0:string,1:string} red/blue text of a stat cell's two <p>. */
    private function pair(Crawler $row, int $col): array
    {
        $td = $row->filter('td')->eq($col);
        $ps = $td->filter('p')->each(fn (Crawler $p) => Parse::clean($p->text('')));

        return [$ps[0] ?? '', $ps[1] ?? ''];
    }

    private function applyTotalsRow(array &$stats, int $round, Crawler $row): void
    {
        $kd = $this->pair($row, 1);
        $sig = $this->pair($row, 2);
        $total = $this->pair($row, 4);
        $td = $this->pair($row, 5);
        $sub = $this->pair($row, 7);
        $rev = $this->pair($row, 8);
        $ctrl = $this->pair($row, 9);

        foreach ([0 => 'red', 1 => 'blue'] as $idx => $corner) {
            $sigPair = Parse::landedAttempted($sig[$idx]);
            $totalPair = Parse::landedAttempted($total[$idx]);
            $tdPair = Parse::landedAttempted($td[$idx]);

            $stats[$corner][$round] = array_merge($stats[$corner][$round] ?? [], [
                'round' => $round,
                'knockdowns' => (int) Parse::decimal($kd[$idx]),
                'sig_str_landed' => $sigPair['landed'],
                'sig_str_attempted' => $sigPair['attempted'],
                'total_str_landed' => $totalPair['landed'],
                'total_str_attempted' => $totalPair['attempted'],
                'takedowns_landed' => $tdPair['landed'],
                'takedowns_attempted' => $tdPair['attempted'],
                'sub_attempts' => (int) Parse::decimal($sub[$idx]),
                'reversals' => (int) Parse::decimal($rev[$idx]),
                'control_time_sec' => Parse::clockToSeconds($ctrl[$idx]),
            ]);
        }
    }

    private function applySigRow(array &$stats, int $round, Crawler $row): void
    {
        $head = $this->pair($row, 3);
        $body = $this->pair($row, 4);
        $leg = $this->pair($row, 5);
        $distance = $this->pair($row, 6);
        $clinch = $this->pair($row, 7);
        $ground = $this->pair($row, 8);

        foreach ([0 => 'red', 1 => 'blue'] as $idx => $corner) {
            $h = Parse::landedAttempted($head[$idx]);
            $b = Parse::landedAttempted($body[$idx]);
            $l = Parse::landedAttempted($leg[$idx]);
            $d = Parse::landedAttempted($distance[$idx]);
            $c = Parse::landedAttempted($clinch[$idx]);
            $g = Parse::landedAttempted($ground[$idx]);

            $stats[$corner][$round] = array_merge($stats[$corner][$round] ?? ['round' => $round], [
                'round' => $round,
                'head_landed' => $h['landed'], 'head_attempted' => $h['attempted'],
                'body_landed' => $b['landed'], 'body_attempted' => $b['attempted'],
                'leg_landed' => $l['landed'], 'leg_attempted' => $l['attempted'],
                'distance_landed' => $d['landed'], 'distance_attempted' => $d['attempted'],
                'clinch_landed' => $c['landed'], 'clinch_attempted' => $c['attempted'],
                'ground_landed' => $g['landed'], 'ground_attempted' => $g['attempted'],
            ]);
        }
    }

    private function fillDefaults(array $row): array
    {
        $defaults = [
            'knockdowns' => 0, 'sig_str_landed' => 0, 'sig_str_attempted' => 0,
            'total_str_landed' => 0, 'total_str_attempted' => 0,
            'takedowns_landed' => 0, 'takedowns_attempted' => 0,
            'sub_attempts' => 0, 'reversals' => 0, 'control_time_sec' => null,
            'head_landed' => 0, 'head_attempted' => 0, 'body_landed' => 0, 'body_attempted' => 0,
            'leg_landed' => 0, 'leg_attempted' => 0, 'distance_landed' => 0, 'distance_attempted' => 0,
            'clinch_landed' => 0, 'clinch_attempted' => 0, 'ground_landed' => 0, 'ground_attempted' => 0,
        ];

        return array_merge($defaults, $row);
    }
}
