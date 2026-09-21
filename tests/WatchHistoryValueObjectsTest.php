<?php

declare(strict_types=1);

namespace Tests;

use App\History\WatchHistoryDate;
use App\History\WatchHistoryEvent;
use App\History\WatchHistoryId;
use App\History\WatchHistoryRequestKey;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WatchHistoryValueObjectsTest extends TestCase
{
    public function testAcceptsTodayYesterdayAndOldValidDates(): void
    {
        self::assertSame(date('Y-m-d'), WatchHistoryDate::parse(date('Y-m-d')));
        self::assertSame(date('Y-m-d', strtotime('-1 day')), WatchHistoryDate::parse(date('Y-m-d', strtotime('-1 day'))));
        self::assertSame('1000-01-01', WatchHistoryDate::parse('1000-01-01'));
    }

    #[DataProvider('invalidDates')]
    public function testRejectsInvalidOrFutureDates(mixed $value): void
    {
        self::assertNull(WatchHistoryDate::parse($value));
    }

    public static function invalidDates(): array
    {
        return [
            'future' => [date('Y-m-d', strtotime('+1 day'))],
            'calendar' => ['2026-02-31'],
            'format' => ['18/09/2026'],
            'timestamp' => ['2026-09-18 10:00:00'],
            'empty' => [''],
            'non-string' => [123],
        ];
    }

    public function testRequestKeysAreExactlyLowercaseHex(): void
    {
        $generated = WatchHistoryRequestKey::generate();
        self::assertMatchesRegularExpression('/^[a-f0-9]{32}$/D', $generated);
        self::assertSame(str_repeat('a', 32), WatchHistoryRequestKey::parse(str_repeat('a', 32)));
        foreach ([str_repeat('a', 31), str_repeat('a', 33), str_repeat('g', 32), str_repeat('A', 32), ''] as $invalid) {
            self::assertNull(WatchHistoryRequestKey::parse($invalid));
        }
    }

    public function testFactoriesEnforceMovieAndEpisodeStructures(): void
    {
        $key = str_repeat('1', 32);
        $movie = WatchHistoryEvent::movie(603, 'Matrix', 'The Matrix', '1999-03-31', '/matrix.jpg', date('Y-m-d'), $key);
        self::assertSame('movie', $movie->entryType);
        self::assertNull($movie->seasonNumber);
        self::assertNull($movie->episodeNumber);
        self::assertNull($movie->episodeTitle);

        $episode = WatchHistoryEvent::episode(1396, 0, 1, 'Breaking Bad', null, 'Pilot', '2008-01-20', null, date('Y-m-d'), $key);
        self::assertSame('episode', $episode->entryType);
        self::assertSame(0, $episode->seasonNumber);
        self::assertSame(1, $episode->episodeNumber);
        self::assertSame('Pilot', $episode->episodeTitle);
    }

    public function testInvalidEpisodeStructureThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        WatchHistoryEvent::episode(1396, -1, 0, 'Breaking Bad', null, '', null, null, date('Y-m-d'), str_repeat('1', 32));
    }

    public function testInternalIdsUsePositivePlatformBigints(): void
    {
        self::assertSame(PHP_INT_MAX, WatchHistoryId::parse((string) PHP_INT_MAX));
        foreach (['0', '-1', '01', 'abc', '9223372036854775808'] as $invalid) {
            self::assertNull(WatchHistoryId::parse($invalid));
        }
    }
}
