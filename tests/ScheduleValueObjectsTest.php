<?php

declare(strict_types=1);

namespace Tests;

use App\Schedule\ScheduleDate;
use App\Schedule\ScheduleEntry;
use App\Schedule\ScheduleId;
use PHPUnit\Framework\TestCase;

final class ScheduleValueObjectsTest extends TestCase
{
    public function testDateAcceptsTodayAndFutureButRejectsPastAndInvalidValues(): void
    {
        self::assertSame('2026-09-21', ScheduleDate::parse('2026-09-21', '2026-09-21'));
        self::assertSame('2026-09-22', ScheduleDate::parse('2026-09-22', '2026-09-21'));
        self::assertNull(ScheduleDate::parse('2026-09-20', '2026-09-21'));
        self::assertNull(ScheduleDate::parse('2026-02-31', '2026-01-01'));
        self::assertNull(ScheduleDate::parse('21/09/2026', '2026-01-01'));
    }

    public function testInternalIdAndDerivedStatesAreStrict(): void
    {
        self::assertSame(1, ScheduleId::parse('1'));
        self::assertNull(ScheduleId::parse('0'));
        self::assertNull(ScheduleId::parse('-1'));
        $entry = new ScheduleEntry(1, 2, 'tmdb', 'movie', 603, 0, 0, 'Matrix', null, null, '1999-03-31', null, '2026-09-21', '', '');
        self::assertSame('today', $entry->state('2026-09-21'));
        self::assertSame('upcoming', $entry->state('2026-09-20'));
        self::assertSame('overdue', $entry->state('2026-09-22'));
    }
}
