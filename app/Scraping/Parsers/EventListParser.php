<?php

namespace App\Scraping\Parsers;

use App\Scraping\Support\Parse;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Parses the ufcstats completed/upcoming events listing.
 *
 * @return array<int, array{ufcstats_id:string,name:string,date:?string,location_raw:?string,url:string}>
 */
class EventListParser
{
    public function parse(string $html): array
    {
        $crawler = new Crawler($html);
        $events = [];

        $crawler->filter('tr.b-statistics__table-row')->each(function (Crawler $row) use (&$events) {
            $link = $row->filter('a.b-link');
            if ($link->count() === 0) {
                return; // header / spacer rows
            }
            $url = (string) $link->attr('href');
            $id = Parse::idFromUrl($url);
            if ($id === null) {
                return;
            }

            $date = $row->filter('span.b-statistics__date');
            $cells = $row->filter('td');
            $location = $cells->count() > 1 ? Parse::nullable($cells->eq(1)->text('')) : null;

            $events[] = [
                'ufcstats_id' => $id,
                'name' => Parse::clean($link->text('')),
                'date' => $date->count() ? Parse::date($date->text('')) : null,
                'location_raw' => $location,
                'url' => $url,
            ];
        });

        return $events;
    }
}
