<?php

namespace App\Scraping\Support;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Pure, tolerant field parsers for ufcstats text.
 *
 * Every helper accepts the raw cell text and returns a normalized value or null.
 * ufcstats uses "--" / "---" for missing data; all helpers treat those as null/zero.
 */
class Parse
{
    /** Collapse runs of whitespace and trim. */
    public static function clean(?string $s): string
    {
        return trim(preg_replace('/\s+/', ' ', (string) $s));
    }

    /** True when the value is a ufcstats "missing" placeholder. */
    public static function isBlank(?string $s): bool
    {
        $s = self::clean($s);

        return $s === '' || $s === '--' || $s === '---' || $s === 'N/A';
    }

    /** Extract the 16-char hex id from any ufcstats *-details/{id} URL. */
    public static function idFromUrl(?string $url): ?string
    {
        if (preg_match('#-details/([a-f0-9]{16})#', (string) $url, $m)) {
            return $m[1];
        }

        return null;
    }

    /** "5' 8\"" -> 68 inches. */
    public static function heightToInches(?string $s): ?int
    {
        if (self::isBlank($s)) {
            return null;
        }
        if (preg_match("/(\d+)'\s*(\d+)/", $s, $m)) {
            return ((int) $m[1] * 12) + (int) $m[2];
        }

        return null;
    }

    /** "155 lbs." -> 155. */
    public static function weightToLbs(?string $s): ?int
    {
        if (self::isBlank($s)) {
            return null;
        }

        return preg_match('/(\d+)/', $s, $m) ? (int) $m[1] : null;
    }

    /** "71\"" -> 71.0. */
    public static function reachToInches(?string $s): ?float
    {
        if (self::isBlank($s)) {
            return null;
        }

        return preg_match('/(\d+(?:\.\d+)?)/', $s, $m) ? (float) $m[1] : null;
    }

    /** "Mar 05, 1993" / "June 27, 2026" -> "1993-03-05". */
    public static function date(?string $s): ?string
    {
        $s = self::clean($s);
        if (self::isBlank($s)) {
            return null;
        }
        try {
            return CarbonImmutable::parse($s)->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * "Record: 13-5-0" / "24-1-0 (1 NC)" -> ['wins','losses','draws','no_contests'].
     */
    public static function record(?string $s): array
    {
        $out = ['wins' => 0, 'losses' => 0, 'draws' => 0, 'no_contests' => 0];
        if (preg_match('/(\d+)\s*-\s*(\d+)\s*-\s*(\d+)/', (string) $s, $m)) {
            $out['wins'] = (int) $m[1];
            $out['losses'] = (int) $m[2];
            $out['draws'] = (int) $m[3];
        }
        if (preg_match('/\((\d+)\s*NC\)/i', (string) $s, $m)) {
            $out['no_contests'] = (int) $m[1];
        }

        return $out;
    }

    /** "47 of 98" -> ['landed' => 47, 'attempted' => 98]; "---" -> [0, 0]. */
    public static function landedAttempted(?string $s): array
    {
        if (preg_match('/(\d+)\s*of\s*(\d+)/', (string) $s, $m)) {
            return ['landed' => (int) $m[1], 'attempted' => (int) $m[2]];
        }

        return ['landed' => 0, 'attempted' => 0];
    }

    /** "52%" -> 52.0; "---" -> null. */
    public static function percent(?string $s): ?float
    {
        if (self::isBlank($s)) {
            return null;
        }

        return preg_match('/(\d+(?:\.\d+)?)/', $s, $m) ? (float) $m[1] : null;
    }

    /** "0.83" -> 0.83; "---" -> null. */
    public static function decimal(?string $s): ?float
    {
        if (self::isBlank($s)) {
            return null;
        }

        return preg_match('/(\d+(?:\.\d+)?)/', $s, $m) ? (float) $m[1] : null;
    }

    /** "8:53" -> 533 seconds; "--" -> null; "0:00" -> 0. */
    public static function clockToSeconds(?string $s): ?int
    {
        $s = self::clean($s);
        if (self::isBlank($s)) {
            return null;
        }
        if (preg_match('/(\d+):(\d{1,2})/', $s, $m)) {
            return ((int) $m[1] * 60) + (int) $m[2];
        }

        return null;
    }

    /** "3 Rnd (5-5-5)" -> 3; "No Time Limit" -> null; "1 Rnd + OT (12-3)" -> 1. */
    public static function scheduledRounds(?string $s): ?int
    {
        $s = self::clean($s);
        if (preg_match('/(\d+)\s*Rnd/i', $s, $m)) {
            return (int) $m[1];
        }

        return null;
    }

    public static function stance(?string $s): ?string
    {
        $s = self::clean($s);

        return self::isBlank($s) ? null : $s;
    }

    public static function nullable(?string $s): ?string
    {
        $s = self::clean($s);

        return self::isBlank($s) ? null : $s;
    }
}
