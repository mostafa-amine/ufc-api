<?php

namespace App\Scraping\Actions;

use App\Models\Fighter;

class UpsertFighter
{
    /**
     * Ensure a fighter row exists (id + name only). Does not overwrite a richer
     * profile if one is already present.
     */
    public function shell(string $ufcstatsId, ?string $name = null, ?string $url = null): Fighter
    {
        return Fighter::firstOrCreate(
            ['ufcstats_id' => $ufcstatsId],
            ['name' => $name ?: 'Unknown', 'url' => $url],
        );
    }

    /**
     * Upsert a full fighter profile parsed from the fighter page.
     *
     * @param  array<string, mixed>  $data
     */
    public function fromProfile(string $ufcstatsId, array $data, ?string $url = null): Fighter
    {
        return Fighter::updateOrCreate(
            ['ufcstats_id' => $ufcstatsId],
            array_merge($data, [
                'url' => $url,
                'last_scraped_at' => now(),
            ]),
        );
    }
}
