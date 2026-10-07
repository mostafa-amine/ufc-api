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
        $roundCount = max(count($red['rounds']), count($blue['rounds']));
        $redName = $this->name($api['red']);
        $blueName = $this->name($api['blue']);

        $periods = [$this->period('Full fight', 'full fight', $red['total'], $blue['total'], $redName, $blueName)];
        for ($n = 1; $n <= $roundCount; $n++) {
            $periods[] = $this->period("Round {$n}", "round {$n}", $red['rounds'][$n - 1] ?? null, $blue['rounds'][$n - 1] ?? null, $redName, $blueName);
        }

        return [
            'id' => $api['id'],
            'red' => $redName + ['result' => $this->result($api['red']['outcome'])],
            'blue' => $blueName + ['result' => $this->result($api['blue']['outcome'])],
            'decision' => $this->decisionLabel($api['method']),
            'scores' => implode(' · ', array_map(fn ($c) => "{$c['red']}–{$c['blue']}", $api['scorecards'])),
            'meta' => $this->meta($api),
            'periods' => $periods,
            'rounds' => $this->roundChart($red['rounds'], $blue['rounds'], $roundCount),
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

    private function period(string $label, string $phrase, ?array $r, ?array $b, array $redName, array $blueName): array
    {
        $r ??= $this->emptyStats();
        $b ??= $this->emptyStats();
        $pct = fn (array $s) => $s['attempted'] > 0 ? $s['pct'].'%' : '–';

        $rows = [
            $this->row('Significant strikes', $r['sig_str']['landed'], $b['sig_str']['landed'], (string) $r['sig_str']['landed'], (string) $b['sig_str']['landed'],
                $pct($r['sig_str']).' vs '.$pct($b['sig_str']).' accuracy'),
            $this->row('Total strikes', $r['total_str']['landed'], $b['total_str']['landed'], (string) $r['total_str']['landed'], (string) $b['total_str']['landed']),
            $this->row('Takedowns', $r['takedowns']['landed'], $b['takedowns']['landed'],
                "{$r['takedowns']['landed']}/{$r['takedowns']['attempted']}", "{$b['takedowns']['landed']}/{$b['takedowns']['attempted']}"),
            $this->row('Control time', (int) $r['control_time_sec'], (int) $b['control_time_sec'],
                Format::clock((int) $r['control_time_sec']), Format::clock((int) $b['control_time_sec'])),
            $this->row('Submission attempts', $r['sub_attempts'], $b['sub_attempts'], (string) $r['sub_attempts'], (string) $b['sub_attempts']),
            $this->row('Knockdowns', $r['knockdowns'], $b['knockdowns'], (string) $r['knockdowns'], (string) $b['knockdowns']),
        ];

        $corners = fn (string $group, array $kinds) => [
            ['name' => $redName['last'], 'corner' => 'red', 'parts' => $this->parts($r[$group], $kinds)],
            ['name' => $blueName['last'], 'corner' => 'blue', 'parts' => $this->parts($b[$group], $kinds)],
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

    /** One stat row; bars are each side's share of the larger value, the leader is drawn dark. */
    private function row(string $label, int $red, int $blue, string $redShown, string $blueShown, string $note = ''): array
    {
        $max = max($red, $blue) ?: 1;

        return [
            'label' => $label,
            'note' => $note,
            'red' => $redShown,
            'blue' => $blueShown,
            'red_leads' => $red >= $blue,
            'blue_leads' => $blue >= $red,
            'red_width' => round($red / $max * 100, 2),
            'blue_width' => round($blue / $max * 100, 2),
        ];
    }

    /** Segments of a stacked bar; a segment too thin to read gets no inline label. */
    private function parts(array $group, array $kinds): array
    {
        $values = array_map(fn ($key) => (int) ($group[$key]['landed'] ?? 0), array_keys($kinds));
        $total = array_sum($values) ?: 1;

        return array_map(fn ($key, $value, $names) => [
            'label' => $names[0],
            'value' => $value,
            'short' => $value / $total > 0.12 ? "{$names[1]} {$value}" : '',
        ], array_keys($kinds), $values, array_values($kinds));
    }

    private function roundChart(array $red, array $blue, int $count): array
    {
        $landed = fn (array $rounds, int $i) => (int) ($rounds[$i]['sig_str']['landed'] ?? 0);
        $peak = 1;
        for ($i = 0; $i < $count; $i++) {
            $peak = max($peak, $landed($red, $i), $landed($blue, $i));
        }

        $chart = [];
        for ($i = 0; $i < $count; $i++) {
            $chart[] = [
                'n' => $i + 1,
                'red' => $landed($red, $i),
                'blue' => $landed($blue, $i),
                'red_height' => (int) round($landed($red, $i) / $peak * 130),
                'blue_height' => (int) round($landed($blue, $i) / $peak * 130),
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

    private function emptyStats(): array
    {
        $zero = ['landed' => 0, 'attempted' => 0, 'pct' => 0];

        return [
            'knockdowns' => 0, 'sig_str' => $zero, 'total_str' => $zero, 'takedowns' => $zero,
            'sub_attempts' => 0, 'control_time_sec' => 0,
            'targets' => ['head' => $zero, 'body' => $zero, 'leg' => $zero],
            'positions' => ['distance' => $zero, 'clinch' => $zero, 'ground' => $zero],
        ];
    }
}
