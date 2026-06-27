<?php

namespace App\Scraping\Actions;

use App\Models\Event;

class UpsertEvent
{
    /**
     * Upsert an event by its ufcstats id. Null attributes are dropped so a
     * sparse source (e.g. the list page) never clobbers richer existing data.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function upsert(string $ufcstatsId, array $attributes): Event
    {
        $attributes = array_filter(
            $attributes,
            fn ($v) => $v !== null,
        );

        return Event::updateOrCreate(
            ['ufcstats_id' => $ufcstatsId],
            $attributes,
        );
    }
}
