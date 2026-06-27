<?php

namespace App\Scraping\Actions;

use App\Models\Event;
use App\Models\Fight;
use App\Models\Fighter;
use App\Models\RoundStat;
use App\Models\Scorecard;
use Illuminate\Support\Facades\DB;

class UpsertFight
{
    public function __construct(private UpsertFighter $fighters) {}

    /**
     * Upsert a bout and, when fight-page detail is available, its stats and scorecards.
     *
     * @param  array<string, mixed>  $bout  from EventDetailParser
     * @param  array<string, mixed>|null  $detail  from FightDetailParser
     */
    public function upsert(Event $event, array $bout, ?array $detail = null): Fight
    {
        return DB::transaction(function () use ($event, $bout, $detail) {
            [$red, $blue] = $this->resolveCorners($bout, $detail);

            $winnerId = null;
            if ($detail && $detail['winner_corner'] === 'red') {
                $winnerId = $red?->id;
            } elseif ($detail && $detail['winner_corner'] === 'blue') {
                $winnerId = $blue?->id;
            }

            $attributes = [
                'event_id' => $event->id,
                'bout_order' => $bout['bout_order'] ?? null,
                'weight_class' => $bout['weight_class'] ?? ($detail['weight_class'] ?? null),
                'is_title_bout' => ($bout['is_title_bout'] ?? false) || ($detail['is_title_bout'] ?? false),
                'red_fighter_id' => $red?->id,
                'blue_fighter_id' => $blue?->id,
                'winner_fighter_id' => $winnerId,
                'outcome' => $detail['outcome'] ?? 'pending',
                'method' => $detail['method'] ?? null,
                'method_detail' => $detail['method_detail'] ?? null,
                'end_round' => $detail['end_round'] ?? null,
                'end_time_sec' => $detail['end_time_sec'] ?? null,
                'time_format_raw' => $detail['time_format_raw'] ?? null,
                'scheduled_rounds' => $detail['scheduled_rounds'] ?? null,
                'referee' => $detail['referee'] ?? null,
            ];

            $fight = Fight::updateOrCreate(['ufcstats_id' => $bout['ufcstats_id']], $attributes);

            if ($detail !== null) {
                $this->syncStats($fight, $detail, $red, $blue);
                $this->syncScorecards($fight, $detail['scorecards'] ?? []);
            }

            return $fight;
        });
    }

    /**
     * @return array{0: ?Fighter, 1: ?Fighter} [red, blue]
     */
    private function resolveCorners(array $bout, ?array $detail): array
    {
        // Fight-page persons are authoritative for corners; fall back to event-page order.
        if ($detail && count($detail['persons'] ?? []) === 2) {
            $red = $this->fighterFromPerson($detail['persons'][0]);
            $blue = $this->fighterFromPerson($detail['persons'][1]);

            if ($red && $blue) {
                return [$red, $blue];
            }
        }

        $fighters = $bout['fighters'] ?? [];
        $red = isset($fighters[0]) ? $this->fighters->shell($fighters[0]['ufcstats_id'], $fighters[0]['name']) : null;
        $blue = isset($fighters[1]) ? $this->fighters->shell($fighters[1]['ufcstats_id'], $fighters[1]['name']) : null;

        return [$red, $blue];
    }

    private function fighterFromPerson(array $person): ?Fighter
    {
        if (empty($person['fighter_ufcstats_id'])) {
            return null;
        }

        return $this->fighters->shell($person['fighter_ufcstats_id'], $person['name'] ?? null);
    }

    private function syncStats(Fight $fight, array $detail, ?Fighter $red, ?Fighter $blue): void
    {
        $corners = ['red' => $red, 'blue' => $blue];

        $rows = [];
        foreach ($corners as $corner => $fighter) {
            if (! $fighter) {
                continue;
            }
            foreach ($detail['stats'][$corner] ?? [] as $round) {
                $rows[] = array_merge($round, [
                    'fight_id' => $fight->id,
                    'fighter_id' => $fighter->id,
                ]);
            }
        }

        // Idempotent: replace the fight's stat rows wholesale.
        RoundStat::where('fight_id', $fight->id)->delete();
        foreach ($rows as $row) {
            RoundStat::create($row);
        }
    }

    private function syncScorecards(Fight $fight, array $scorecards): void
    {
        Scorecard::where('fight_id', $fight->id)->delete();
        foreach ($scorecards as $card) {
            Scorecard::create([
                'fight_id' => $fight->id,
                'judge_name' => $card['judge'],
                'red_score' => $card['red_score'],
                'blue_score' => $card['blue_score'],
            ]);
        }
    }
}
