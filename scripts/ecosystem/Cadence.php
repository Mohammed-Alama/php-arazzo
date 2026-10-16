<?php

declare(strict_types=1);

namespace Ecosystem;

/**
 * Poll cadence (§5.4, fixes F12).
 *
 * A source declares `poll: daily|weekly`. Daily sources are polled every run;
 * weekly sources are polled at most once per seven days, keyed off the stored
 * `lastPolledAt` date (never the run clock in the state we persist — the caller
 * supplies `now`).
 */
final class Cadence
{
    /** Number of days between polls for a `weekly` source. */
    public const WEEKLY_DAYS = 7;

    /**
     * Should this source be polled on `$now` given the last poll date?
     *
     * Everything that is not `weekly` is treated as daily (poll every run).
     */
    public static function isDue(string $poll, ?string $lastPolledAt, string $now): bool
    {
        if ($poll !== 'weekly') {
            return true;
        }
        if ($lastPolledAt === null || $lastPolledAt === '') {
            return true; // never polled
        }

        $last = strtotime(substr($lastPolledAt, 0, 10));
        $today = strtotime(substr($now, 0, 10));
        if ($last === false || $today === false) {
            return true; // unparseable watermark: poll rather than silently skip
        }

        return ($today - $last) >= self::WEEKLY_DAYS * 86400;
    }
}
