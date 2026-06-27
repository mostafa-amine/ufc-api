<?php

namespace App\Scraping\Parsers;

use App\Scraping\Support\Parse;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Parses a ufcstats fighter page into a profile + career averages.
 *
 * @return array<string, mixed>
 */
class FighterParser
{
    public function parse(string $html): array
    {
        $crawler = new Crawler($html);

        $name = $crawler->filter('span.b-content__title-highlight')->first();
        $nickname = $crawler->filter('p.b-content__Nickname')->first();
        $record = $crawler->filter('span.b-content__title-record')->first();

        $info = $this->infoMap($crawler);
        $rec = Parse::record($record->count() ? $record->text('') : '');

        return [
            'name' => $name->count() ? Parse::clean($name->text('')) : '',
            'nickname' => $nickname->count() ? Parse::nullable($nickname->text('')) : null,

            'height_in' => Parse::heightToInches($info['Height'] ?? null),
            'height_raw' => Parse::nullable($info['Height'] ?? null),
            'weight_lb' => Parse::weightToLbs($info['Weight'] ?? null),
            'weight_raw' => Parse::nullable($info['Weight'] ?? null),
            'reach_in' => Parse::reachToInches($info['Reach'] ?? null),
            'reach_raw' => Parse::nullable($info['Reach'] ?? null),
            'stance' => Parse::stance($info['STANCE'] ?? null),
            'dob' => Parse::date($info['DOB'] ?? null),
            'dob_raw' => Parse::nullable($info['DOB'] ?? null),

            'wins' => $rec['wins'],
            'losses' => $rec['losses'],
            'draws' => $rec['draws'],
            'no_contests' => $rec['no_contests'],

            'slpm' => Parse::decimal($info['SLpM'] ?? null),
            'str_acc' => Parse::percent($info['Str. Acc.'] ?? null),
            'sapm' => Parse::decimal($info['SApM'] ?? null),
            'str_def' => Parse::percent($info['Str. Def'] ?? null),
            'td_avg' => Parse::decimal($info['TD Avg.'] ?? null),
            'td_acc' => Parse::percent($info['TD Acc.'] ?? null),
            'td_def' => Parse::percent($info['TD Def.'] ?? null),
            'sub_avg' => Parse::decimal($info['Sub. Avg.'] ?? null),
        ];
    }

    /**
     * Build a label => value map from the fighter info list items
     * ("Height: 5' 8"", "SLpM: 4.71", ...).
     *
     * @return array<string, string>
     */
    private function infoMap(Crawler $crawler): array
    {
        $map = [];
        $crawler->filter('li.b-list__box-list-item')->each(function (Crawler $li) use (&$map) {
            $text = Parse::clean($li->text(''));
            $pos = strpos($text, ':');
            if ($pos === false) {
                return;
            }
            $label = trim(substr($text, 0, $pos));
            $value = trim(substr($text, $pos + 1));
            if ($label !== '') {
                $map[$label] = $value;
            }
        });

        return $map;
    }
}
