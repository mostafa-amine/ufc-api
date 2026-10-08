<?php

namespace App\Services\Landing;

use App\Http\Resources\FightResource;
use App\Models\Fight;
use App\Support\Format;

/**
 * Turns one fight into everything the landing page's "tale of the tape" draws.
 *
 * It reads the fight through FightResource on purpose: the page claims every number
 * on it came from one API request, so it must use exactly what GET /v1/fights/{id} returns.
 */
final readonly class BuildTaleOfTheTape
{
    private const TARGETS = ['head' => ['Head', 'Head'], 'body' => ['Body', 'Body'], 'leg' => ['Leg', 'Leg']];

    private const POSITIONS = ['distance' => ['Distance', 'Dist'], 'clinch' => ['Clinch', 'Clinch'], 'ground' => ['Ground', 'Ground']];

    public function __invoke(Fight $fight): array
    {
        $fight->loadMissing(['event', 'redFighter', 'blueFighter', 'scorecards', 'roundStats']);
        // Through JSON so nested resources come out exactly as the API serialises them.
        $api = json_decode((new FightResource($fight))->toJson(), true);

        $red = $api['stats']['red'];
        $blue = $api['stats']['blue'];
        // Keyed by round number: a round that failed to scrape for one corner is missing
        // from its list, so list positions can't be trusted to line the corners up.
        $redRounds = array_column($red['rounds'], null, 'round');
        $blueRounds = array_column($blue['rounds'], null, 'round');
        $roundCount = max([0, ...array_keys($redRounds), ...array_keys($blueRounds)]);
        $redName = $this->name($api['red']);
        $blueName = $this->name($api['blue']);

        $periods = [$this->period('Full fight', 'full fight', $red['total'], $blue['total'], $redName, $blueName)];
        for ($n = 1; $n <= $roundCount; $n++) {
            $periods[] = $this->period("Round {$n}", "round {$n}", $redRounds[$n] ?? null, $blueRounds[$n] ?? null, $redName, $blueName);
        }

        return [
            'id' => $api['id'],
            'red' => $redName + ['result' => $this->result($api['red']['outcome'])],
            'blue' => $blueName + ['result' => $this->result($api['blue']['outcome'])],
            'decision' => $this->decisionLabel($api['method']),
            'scores' => implode(' · ', array_map(fn ($c) => "{$c['red']}–{$c['blue']}", $api['scorecards'])),
            'meta' => $this->meta($api),
            'periods' => $periods,
            'rounds' => $this->roundChart($redRounds, $blueRounds, $roundCount),
            'ufcstats_printed' => $this->ufcstatsPrinted($api),
            'request' => $this->requestExcerpt($api),
        ];
    }

    /** "Brunno Ferreira" + "The Hulk" -> first "Brunno “The Hulk”", last "Ferreira". */
    private function name(array $corner): array
    {
        $full = trim($corner['fighter']['name'] ?? '');
        $parts = preg_split('/\s+/', $full) ?: [];
        $last = count($parts) > 1 ? array_pop($parts) : $full;
        $first = count($parts) > 0 && $last !== $full ? implode(' ', $parts) : '';
        $nickname = trim((string) ($corner['fighter']['nickname'] ?? ''), " \"'“”");

        return [
            'first' => trim($first.($nickname !== '' ? " “{$nickname}”" : '')),
            'last' => $last,
        ];
    }

    private function result(?string $outcome): string
    {
        return match ($outcome) {
            'win' => 'W',
            'loss' => 'L',
            'draw' => 'D',
            'nc' => 'NC',
            default => '',
        };
    }

    /** "Decision - Unanimous" -> "Unanimous decision". */
    private function decisionLabel(?string $method): string
    {
        $kind = trim((string) preg_replace('/^Decision\s*-?\s*/i', '', (string) $method));

        return $kind === '' ? 'Decision' : ucfirst(strtolower($kind)).' decision';
    }

    /** @return list<string> */
    private function meta(array $api): array
    {
        $format = $api['time_format'];
        if ($format !== null && preg_match('/^(\d+) Rnd \((\d+)(?:-\2)*\)$/', $format, $m)) {
            $format = "{$m[1]} × {$m[2]} min";
        }
        $judges = array_map(fn ($c) => last(preg_split('/\s+/', trim($c['judge']))), $api['scorecards']);

        return array_values(array_filter([
            $api['weight_class'],
            $format,
            $api['referee'] ? "Referee {$api['referee']}" : null,
            $judges ? 'Judges '.implode(', ', $judges) : null,
        ]));
    }

    /** A null corner means that corner has no stats for this period: every value is unknown. */
    private function period(string $label, string $phrase, ?array $r, ?array $b, array $redName, array $blueName): array
    {
        // Unknown stays null (shown as "–", no bar); it is never turned into 0.
        $get = fn (?array $stats, string $path) => $stats === null ? null : data_get($stats, $path);
        $count = fn (?int $value) => $value === null ? null : (string) $value;
        $pct = fn (?array $s) => $s !== null && $s['sig_str']['attempted'] > 0 ? $s['sig_str']['pct'].'%' : '–';
        $tally = fn (?array $s, string $key) => $s === null ? null : "{$s[$key]['landed']}/{$s[$key]['attempted']}";
        $clock = fn (?int $seconds) => Format::clock($seconds);

        $row = fn (string $name, string $path, callable $show, string $note = '') => $this->row(
            $name, $get($r, $path), $get($b, $path), $show($r, $get($r, $path)), $show($b, $get($b, $path)), $note,
        );
        $plain = fn (?array $s, ?int $v) => $count($v);

        $rows = [
            $row('Significant strikes', 'sig_str.landed', $plain, $pct($r).' vs '.$pct($b).' accuracy'),
            $row('Total strikes', 'total_str.landed', $plain),
            $row('Takedowns', 'takedowns.landed', fn (?array $s) => $tally($s, 'takedowns')),
            $row('Control time', 'control_time_sec', fn (?array $s, ?int $v) => $clock($v)),
            $row('Submission attempts', 'sub_attempts', $plain),
            $row('Knockdowns', 'knockdowns', $plain),
        ];

        $corners = fn (string $group, array $kinds) => [
            ['name' => $redName['last'], 'corner' => 'red', 'known' => $r !== null, 'parts' => $this->parts($r[$group] ?? null, $kinds)],
            ['name' => $blueName['last'], 'corner' => 'blue', 'known' => $b !== null, 'parts' => $this->parts($b[$group] ?? null, $kinds)],
        ];

        return [
            'label' => $label,
            'phrase' => $phrase,
            'rows' => $rows,
            'maps' => [
                ['title' => 'By target', 'corners' => $corners('targets', self::TARGETS)],
                ['title' => 'By position', 'corners' => $corners('positions', self::POSITIONS)],
            ],
        ];
    }

    /**
     * One stat row. Bars are each side's share of the larger known value; an unknown side
     * shows "–" and no bar. Only a strictly larger number is drawn dark, so a tie (0–0
     * included) or a comparison against an unknown side has no leader.
     */
    private function row(string $label, ?int $red, ?int $blue, ?string $redShown, ?string $blueShown, string $note = ''): array
    {
        $max = max($red ?? 0, $blue ?? 0) ?: 1;
        $bothKnown = $red !== null && $blue !== null;

        return [
            'label' => $label,
            'note' => $note,
            'red' => $redShown ?? '–',
            'blue' => $blueShown ?? '–',
            'red_leads' => $bothKnown && $red > $blue,
            'blue_leads' => $bothKnown && $blue > $red,
            'red_width' => $red === null ? 0 : round($red / $max * 100, 2),
            'blue_width' => $blue === null ? 0 : round($blue / $max * 100, 2),
        ];
    }

    /** Segments of a stacked bar; a segment too thin to read gets no inline label. A null group is unknown. */
    private function parts(?array $group, array $kinds): array
    {
        $values = array_map(fn ($key) => $group === null ? null : (int) $group[$key]['landed'], array_keys($kinds));
        $total = array_sum(array_map(fn ($v) => $v ?? 0, $values)) ?: 1;

        return array_map(fn ($key, $value, $names) => [
            'label' => $names[0],
            'value' => $value,
            'shown' => $value === null ? '–' : (string) $value,
            'short' => $value !== null && $value / $total > 0.12 ? "{$names[1]} {$value}" : '',
        ], array_keys($kinds), $values, array_values($kinds));
    }

    /** @param  array<int, array>  $red  rounds keyed by round number (same for $blue); a missing round is unknown */
    private function roundChart(array $red, array $blue, int $count): array
    {
        $landed = fn (array $rounds, int $n) => isset($rounds[$n]) ? (int) $rounds[$n]['sig_str']['landed'] : null;
        $peak = 1;
        for ($n = 1; $n <= $count; $n++) {
            $peak = max($peak, $landed($red, $n) ?? 0, $landed($blue, $n) ?? 0);
        }
        $height = fn (?int $value) => $value === null ? 0 : (int) round($value / $peak * 130);

        $chart = [];
        for ($n = 1; $n <= $count; $n++) {
            $chart[] = [
                'n' => $n,
                'red' => $landed($red, $n),
                'blue' => $landed($blue, $n),
                'red_height' => $height($landed($red, $n)),
                'blue_height' => $height($landed($blue, $n)),
            ];
        }

        return $chart;
    }

    /**
     * How ufcstats prints the first card. It lists loser-then-winner, so only when the red
     * corner wins does that read backwards; otherwise there is nothing to point out (null).
     */
    private function ufcstatsPrinted(array $api): ?string
    {
        $card = $api['scorecards'][0] ?? null;
        if ($card === null || $api['red']['outcome'] !== 'win') {
            return null;
        }

        return "{$card['blue']}–{$card['red']}";
    }

    /** A trimmed copy of the real API response, shown as the closing example. */
    private function requestExcerpt(array $api): array
    {
        $card = $api['scorecards'][0] ?? null;
        $red = $api['stats']['red'];

        return [
            'method' => $api['method'],
            'judge' => $card['judge'] ?? null,
            'judge_red' => $card['red'] ?? null,
            'judge_blue' => $card['blue'] ?? null,
            'control_time_sec' => $red['total']['control_time_sec'] ?? null,
            'round_one_sig' => $red['rounds'][0]['sig_str']['landed'] ?? null,
        ];
    }
}
