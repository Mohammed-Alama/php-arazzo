<?php

declare(strict_types=1);

use Ecosystem\Cadence;

require_once dirname(__DIR__, 4).'/scripts/ecosystem/Cadence.php';

it('polls daily sources on every run', function (): void {
    expect(Cadence::isDue('daily', null, '2026-10-16'))->toBeTrue()
        ->and(Cadence::isDue('daily', '2026-10-15', '2026-10-16'))->toBeTrue();
});

it('polls a weekly source at most once every seven days', function (): void {
    expect(Cadence::isDue('weekly', null, '2026-10-16'))->toBeTrue()          // never polled
        ->and(Cadence::isDue('weekly', '2026-10-16', '2026-10-16'))->toBeFalse() // same day
        ->and(Cadence::isDue('weekly', '2026-10-10', '2026-10-16'))->toBeFalse() // 6 days
        ->and(Cadence::isDue('weekly', '2026-10-09', '2026-10-16'))->toBeTrue()  // 7 days
        ->and(Cadence::isDue('weekly', '2026-10-01', '2026-10-16'))->toBeTrue(); // overdue
});

it('honours a full ISO timestamp as the last-poll watermark', function (): void {
    expect(Cadence::isDue('weekly', '2026-10-09T06:17:00Z', '2026-10-16'))->toBeTrue()
        ->and(Cadence::isDue('weekly', '2026-10-10T06:17:00Z', '2026-10-16'))->toBeFalse();
});
