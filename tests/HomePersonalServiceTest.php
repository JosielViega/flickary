<?php

declare(strict_types=1);

namespace Tests;

use App\History\WatchHistoryEntry;
use App\History\WatchHistoryEvent;
use App\History\WatchHistoryPage;
use App\History\WatchHistoryStore;
use App\Home\HomeImageService;
use App\Home\HomePersonalService;
use App\Integrations\Tmdb\TmdbCatalog;
use App\Integrations\Tmdb\TmdbImageConfiguration;
use App\Integrations\Tmdb\TmdbMediaDetails;
use App\Integrations\Tmdb\TmdbSeasonDetails;
use App\Media\UserMediaItem;
use App\Media\UserMediaPage;
use App\Media\UserMediaStatus;
use App\Media\UserMediaStore;
use App\Schedule\ScheduleEntry;
use App\Schedule\ScheduleItem;
use App\Schedule\SchedulePage;
use App\Schedule\ScheduleStore;
use PHPUnit\Framework\TestCase;

final class HomePersonalServiceTest extends TestCase
{
    public function testReadsOnlyBoundedSnapshotPages(): void
    {
        $media = new HomeMediaStoreFake();
        $schedule = new HomeScheduleStoreFake();
        $history = new HomeHistoryStoreFake();
        $catalog = new HomePersonalCatalogFake();

        $page = (new HomePersonalService($media, $schedule, $history, new HomeImageService($catalog)))->page(42);

        self::assertSame([], $page->watching);
        self::assertSame([], $page->agenda);
        self::assertSame([], $page->history);
        self::assertSame([[42, UserMediaStatus::WATCHING, null, 1, 10]], $media->calls);
        self::assertCount(1, $schedule->calls);
        self::assertSame([42, null], array_slice($schedule->calls[0], 0, 2));
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $schedule->calls[0][2]);
        self::assertSame([1, 6], array_slice($schedule->calls[0], 3));
        self::assertSame([[42, null, 1, 8]], $history->calls);
        self::assertSame(0, $catalog->configurationCalls);
    }
}

final class HomeMediaStoreFake implements UserMediaStore
{
    public array $calls = [];
    public function findForUser(int $userId, string $source, string $mediaType, int $sourceId): ?UserMediaItem { return null; }
    public function create(int $userId, TmdbMediaDetails $details, string $status, ?bool $isAnime = null): bool { return false; }
    public function updateStatus(int $userId, string $source, string $mediaType, int $sourceId, string $status): bool { return false; }
    public function delete(int $userId, string $source, string $mediaType, int $sourceId): bool { return false; }
    public function paginateForUser(int $userId, ?string $status, ?string $mediaType, int $page, int $perPage, bool $animeOnly = false): UserMediaPage
    {
        $this->calls[] = func_get_args();
        return new UserMediaPage([], 0, $page, $perPage);
    }
    public function countForUser(int $userId): int { return 0; }
}

final class HomeScheduleStoreFake implements ScheduleStore
{
    public array $calls = [];
    public function findForUserIdentity(int $userId, string $source, string $entryType, int $sourceId, int $seasonNumber = 0, int $episodeNumber = 0): ?ScheduleEntry { return null; }
    public function findForUser(int $userId, int $id): ?ScheduleEntry { return null; }
    public function create(int $userId, ScheduleItem $item): bool { return false; }
    public function updateDate(int $userId, int $id, string $scheduledOn): bool { return false; }
    public function delete(int $userId, int $id): bool { return false; }
    public function paginateForUser(int $userId, ?string $entryType, string $today, int $page, int $perPage, bool $animeOnly = false): SchedulePage
    {
        $this->calls[] = func_get_args();
        return new SchedulePage([], 0, $page, $perPage);
    }
    public function countForUser(int $userId): int { return 0; }
    public function episodeSchedulesForSeason(int $userId, string $source, int $seriesId, int $seasonNumber): array { return []; }
}

final class HomeHistoryStoreFake implements WatchHistoryStore
{
    public array $calls = [];
    public function createMovie(int $userId, WatchHistoryEvent $event): bool { return false; }
    public function createEpisode(int $userId, WatchHistoryEvent $event): bool { return false; }
    public function findForUser(int $userId, int $id): ?WatchHistoryEntry { return null; }
    public function updateDate(int $userId, int $id, string $watchedOn): bool { return false; }
    public function delete(int $userId, int $id): bool { return false; }
    public function paginateForUser(int $userId, ?string $entryType, int $page, int $perPage, bool $animeOnly = false): WatchHistoryPage
    {
        $this->calls[] = func_get_args();
        return new WatchHistoryPage([], 0, $page, $perPage);
    }
    public function countForUser(int $userId): int { return 0; }
}

final class HomePersonalCatalogFake implements TmdbCatalog
{
    public int $configurationCalls = 0;
    public function configured(): bool { return true; }
    public function configuration(): TmdbImageConfiguration { $this->configurationCalls++; return new TmdbImageConfiguration('https://image.tmdb.test/t/p/', ['w342'], ['w780']); }
    public function movieDetails(int $id): TmdbMediaDetails { throw new \LogicException(); }
    public function seriesDetails(int $id): TmdbMediaDetails { throw new \LogicException(); }
    public function seasonDetails(int $seriesId, int $seasonNumber): TmdbSeasonDetails { throw new \LogicException(); }
    public function searchMovies(string $query, int $page = 1): array { return $this->page(); }
    public function searchSeries(string $query, int $page = 1): array { return $this->page(); }
    public function discoverAnimeMovies(int $page = 1): array { return $this->page(); }
    public function discoverAnimeSeries(int $page = 1): array { return $this->page(); }
    private function page(): array { return ['page' => 1, 'total_pages' => 1, 'total_results' => 0, 'results' => []]; }
}
