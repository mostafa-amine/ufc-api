<?php

namespace App\Services\Landing;

use App\Http\Resources\FightResource;
use App\Models\Fight;
use App\Support\Format;

/**
 * Turns one fight into everything the landing page's fight card and response panel draw.
 *
 * It reads the fight through FightResource on purpose: the page claims every number
 * on it came from one API request, so it must use exactly what GET /v1/fights/{id} returns.
 */
final readonly class BuildTaleOfTheTape
{
    public function __invoke(Fight $fight): array
    {
        $fight->loadMissing(['event', 'redFighter', 'blueFighter', 'scorecards', 'roundStats']);
        // Through JSON so nested resources come out exactly as the API serialises them.
        $api = json_decode((new FightResource($fight))->toJson(), true);

        $red = $api['stats']['red'];
        $blue = $api['stats']['blue'];

        return [
            'id' => $api['id'],
            'red' => $this->name($api['red']) + ['result' => $this->result($api['red']['outcome'])],
            'blue' => $this->name($api['blue']) + ['result' => $this->result($api['blue']['outcome'])],
            'decision' => $this->decisionLabel($api['method']),
            'scores' => implode(' · ', array_map(fn ($c) => "{$c['red']}–{$c['blue']}", $api['scorecards'])),
            'meta' => $this->meta($api),
            'rows' => $this->rows($red['total'], $blue['total']),
            'response' => $this->response($api),
            'ufcstats_printed' => $this->ufcstatsPrinted($api),
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

    /**
     * The card's stat rows for the whole fight. A null corner has no totals: its values are
     * unknown. Each row's group names the lines of the response it comes from.
     */
    private function rows(?array $r, ?array $b): array
    {
        // Unknown stays null (shown as "–", no bar); it is never turned into 0.
        $get = fn (?array $stats, string $path) => $stats === null ? null : data_get($stats, $path);
        $tally = fn (?array $s) => $s === null ? null : "{$s['takedowns']['landed']}/{$s['takedowns']['attempted']}";
        $pct = fn (?array $s) => $s !== null && $s['sig_str']['attempted'] > 0 ? $s['sig_str']['pct'].'%' : '–';

        $row = fn (string $group, string $label, string $path, callable $show, string $note = '') => ['group' => $group] + $this->row(
            $label, $get($r, $path), $get($b, $path), $show($r, $get($r, $path)), $show($b, $get($b, $path)), $note,
        );
        $plain = fn (?array $s, ?int $v) => $v === null ? null : (string) $v;

        return [
            $row('sig', 'Significant strikes', 'sig_str.landed', $plain, $pct($r).' vs '.$pct($b).' accuracy'),
            $row('total', 'Total strikes', 'total_str.landed', $plain),
            $row('td', 'Takedowns', 'takedowns.landed', fn (?array $s) => $tally($s)),
            $row('ctrl', 'Control time', 'control_time_sec', fn (?array $s, ?int $v) => Format::clock($v)),
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

    /**
     * The real response of GET /v1/fights/{id}, trimmed to what the card shows, one line at a
     * time. Each line carries the group of the card row it feeds ('' for none), so picking a
     * row can light up its lines. Values are the API's own, JSON-encoded.
     *
     * @return list<array{text: string, group: string}>
     */
    private function response(array $api): array
    {
        $json = fn ($value) => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $tally = fn (array $s) => '{ "landed": '.$json($s['landed']).', "attempted": '.$json($s['attempted']).', "pct": '.$json($s['pct']).' }';
        $line = fn (string $text, string $group = '') => ['text' => $text, 'group' => $group];

        $cards = $api['scorecards'];
        $lines = [
            $line('{'),
            $line('  "id": '.$json($api['id']).','),
            $line('  "method": '.$json($api['method']).',', 'scores'),
            $line('  "scorecards": [', 'scores'),
        ];
        foreach ($cards as $i => $card) {
            $comma = $i < count($cards) - 1 ? ',' : '';
            $lines[] = $line('    { "judge": '.$json($card['judge']).', "red": '.$json($card['red']).', "blue": '.$json($card['blue']).' }'.$comma, 'scores');
        }
        $lines[] = $line('  ],', 'scores');
        $lines[] = $line('  "stats": {');

        foreach (['red', 'blue'] as $corner) {
            $total = $api['stats'][$corner]['total'];
            $lines[] = $line('    "'.$corner.'": {');
            if ($total === null) {
                $lines[] = $line('      "total": null,');
            } else {
                $lines[] = $line('      "total": {');
                $lines[] = $line('        "sig_str": '.$tally($total['sig_str']).',', 'sig');
                $lines[] = $line('        "total_str": '.$tally($total['total_str']).',', 'total');
                $lines[] = $line('        "takedowns": '.$tally($total['takedowns']).',', 'td');
                $lines[] = $line('        "control_time_sec": '.$json($total['control_time_sec']).',', 'ctrl');
                $lines[] = $line('        …');
                $lines[] = $line('      },');
            }
            $lines[] = $line('      "rounds": [ … ]');
            $lines[] = $line('    }'.($corner === 'red' ? ',' : ''));
        }

        $lines[] = $line('  },');
        $lines[] = $line('  …');
        $lines[] = $line('}');

        return $lines;
    }
}
