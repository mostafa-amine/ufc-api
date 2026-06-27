<?php

namespace App\Scraping\Parsers;

use App\Scraping\Support\Parse;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Parses a single ufcstats event page: event meta + the bout list.
 *
 * Bouts here are "shells" used for discovery (fight id, the two fighters, weight
 * class, title flag, card order). Authoritative result/stats come from the fight page.
 *
 * @return array{
 *   event: array{name:string,date:?string,location_raw:?string},
 *   bouts: array<int, array{
 *     ufcstats_id:string, bout_order:int, weight_class:?string, is_title_bout:bool,
 *     method_abbr:?string, fighters: array<int, array{ufcstats_id:?string,name:string}>
 *   }>
 * }
 */
class EventDetailParser
{
    public function parse(string $html): array
    {
        $crawler = new Crawler($html);

        return [
            'event' => $this->event($crawler),
            'bouts' => $this->bouts($crawler),
        ];
    }

    private function event(Crawler $crawler): array
    {
        $name = $crawler->filter('h2.b-content__title, span.b-content__title-highlight')->first();
        $date = null;
        $location = null;

        $crawler->filter('li.b-list__box-list-item')->each(function (Crawler $li) use (&$date, &$location) {
            $text = Parse::clean($li->text(''));
            if (str_starts_with($text, 'Date:')) {
                $date = Parse::date(trim(substr($text, 5)));
            } elseif (str_starts_with($text, 'Location:')) {
                $location = Parse::nullable(trim(substr($text, 9)));
            }
        });

        return [
            'name' => $name->count() ? Parse::clean($name->text('')) : '',
            'date' => $date,
            'location_raw' => $location,
        ];
    }

    private function bouts(Crawler $crawler): array
    {
        $bouts = [];
        $order = 0;

        $crawler->filter('tr.b-fight-details__table-row')->each(function (Crawler $row) use (&$bouts, &$order) {
            $fightId = Parse::idFromUrl((string) $row->attr('data-link'));
            if ($fightId === null) {
                return; // header / placeholder rows have no data-link
            }
            $order++;

            // Two fighter links (col 1).
            $fighters = $row->filter('a.b-link')->each(function (Crawler $a) {
                return [
                    'ufcstats_id' => Parse::idFromUrl((string) $a->attr('href')),
                    'name' => Parse::clean($a->text('')),
                ];
            });
            $fighters = array_values(array_filter($fighters, fn ($f) => $f['ufcstats_id'] !== null));

            $cells = $row->filter('td');
            $weightCell = $cells->count() > 6 ? $cells->eq(6) : null;
            $weightClass = $weightCell ? Parse::nullable($weightCell->text('')) : null;
            $isTitle = $weightCell ? $weightCell->filter('img')->count() > 0 : false;
            $methodAbbr = $cells->count() > 7 ? Parse::nullable($cells->eq(7)->text('')) : null;

            $bouts[] = [
                'ufcstats_id' => $fightId,
                'bout_order' => $order,
                'weight_class' => $weightClass,
                'is_title_bout' => $isTitle,
                'method_abbr' => $methodAbbr,
                'fighters' => $fighters,
            ];
        });

        return $bouts;
    }
}
