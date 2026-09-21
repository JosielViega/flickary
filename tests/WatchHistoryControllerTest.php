<?php

declare(strict_types=1);

namespace Tests;

use App\Controllers\WatchHistoryController;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\History\WatchHistoryEntry;
use App\History\WatchHistoryEvent;
use App\History\WatchHistoryPage;
use App\History\WatchHistoryStore;
use App\Integrations\Tmdb\TmdbCatalog;
use App\Integrations\Tmdb\TmdbEpisode;
use App\Integrations\Tmdb\TmdbException;
use App\Integrations\Tmdb\TmdbImageConfiguration;
use App\Integrations\Tmdb\TmdbMediaDetails;
use App\Integrations\Tmdb\TmdbSeasonDetails;
use PHPUnit\Framework\TestCase;

final class WatchHistoryControllerTest extends TestCase
{
    protected function setUp(): void { $_SESSION = []; }

    public function testVisitorCannotReadOrCreate(): void
    {
        [$controller, $store, $catalog] = $this->fixture(false);
        self::assertSame(303, $controller->index(new Request())->status());
        self::assertSame(303, $controller->createMovie(new Request(), '603')->status());
        self::assertSame([], $store->entries);
        self::assertSame([], $catalog->calls);
    }

    public function testMovieUsesServerSnapshotAllowsRewatchAndMakesDuplicateSubmitIdempotent(): void
    {
        [$controller, $store, $catalog, $csrf] = $this->fixture();
        $body = [
            '_token' => $csrf->token(), 'watched_on' => date('Y-m-d'),
            'request_key' => str_repeat('a', 32), 'title' => 'Falso', 'source' => 'anilist',
            'user_id' => 999, 'poster_path' => '/fake.jpg',
            'duration_minutes' => 1,
        ];
        self::assertSame(303, $controller->createMovie(new Request(parsedBody: $body), '603')->status());
        self::assertSame(303, $controller->createMovie(new Request(parsedBody: $body), '603')->status());
        $body['request_key'] = str_repeat('b', 32);
        self::assertSame(303, $controller->createMovie(new Request(parsedBody: $body), '603')->status());

        self::assertCount(2, $store->entries);
        self::assertSame('Matrix', $store->entries[0]->title);
        self::assertSame('The Matrix', $store->entries[0]->originalTitle);
        self::assertSame('/matrix.jpg', $store->entries[0]->posterPath);
        self::assertSame(7, $store->entries[0]->userId);
        self::assertSame(136, $store->entries[0]->durationMinutes);
        self::assertSame([['movie', 603], ['movie', 603], ['movie', 603]], $catalog->calls);
    }

    public function testEpisodeUsesServerSnapshotWithoutListOrProgressDependency(): void
    {
        [$controller, $store, $catalog, $csrf] = $this->fixture();
        $response = $controller->createEpisode(new Request(parsedBody: [
            '_token' => $csrf->token(), 'watched_on' => date('Y-m-d'),
            'request_key' => str_repeat('c', 32), 'title' => 'Falso', 'episode_title' => 'Falso',
        ]), '1396', '1', '1');

        self::assertSame(303, $response->status());
        self::assertSame('/series/1396/temporadas/1', $response->headers()['Location']);
        self::assertCount(1, $store->entries);
        $entry = $store->entries[0];
        self::assertSame('episode', $entry->entryType);
        self::assertSame('Breaking Bad', $entry->title);
        self::assertSame('Breaking Bad Original', $entry->originalTitle);
        self::assertSame('Piloto', $entry->episodeTitle);
        self::assertSame('2008-01-20', $entry->contentDate);
        self::assertSame('/breaking-bad.jpg', $entry->posterPath);
        self::assertSame(58, $entry->durationMinutes);
        self::assertSame([['season', 1396, 1], ['series', 1396]], $catalog->calls);
    }

    public function testMissingProviderRuntimeKeepsValidEventsWithNullDuration(): void
    {
        [$controller, $store, $catalog, $csrf] = $this->fixture();
        $catalog->movieRuntime = null;
        $catalog->episodeRuntime = null;
        $movie = ['_token' => $csrf->token(), 'watched_on' => date('Y-m-d'), 'request_key' => str_repeat('e', 32)];
        $episode = ['_token' => $csrf->token(), 'watched_on' => date('Y-m-d'), 'request_key' => str_repeat('f', 32)];

        self::assertSame(303, $controller->createMovie(new Request(parsedBody: $movie), '603')->status());
        self::assertSame(303, $controller->createEpisode(new Request(parsedBody: $episode), '1396', '1', '1')->status());
        self::assertNull($store->entries[0]->durationMinutes);
        self::assertNull($store->entries[1]->durationMinutes);
    }

    public function testInvalidCsrfDateEpisodeAdultAndTmdbFailureNeverPersist(): void
    {
        [$controller, $store, $catalog, $csrf] = $this->fixture();
        $valid = ['_token' => $csrf->token(), 'watched_on' => date('Y-m-d'), 'request_key' => str_repeat('d', 32)];
        self::assertSame(403, $controller->createMovie(new Request(parsedBody: array_diff_key($valid, ['_token' => true])), '603')->status());
        self::assertSame(422, $controller->createMovie(new Request(parsedBody: [...$valid, 'watched_on' => date('Y-m-d', strtotime('+1 day'))]), '603')->status());
        self::assertSame(404, $controller->createEpisode(new Request(parsedBody: $valid), '1396', '1', '99')->status());
        $catalog->adult = true;
        self::assertSame(404, $controller->createMovie(new Request(parsedBody: $valid), '603')->status());
        $catalog->adult = false;
        $catalog->fail = true;
        self::assertSame(303, $controller->createMovie(new Request(parsedBody: $valid), '603')->status());
        self::assertSame([], $store->entries);
    }

    public function testIndexIsOfflineUsefulFiltersPaginatesAndEscapesSnapshots(): void
    {
        [$controller, $store, $catalog] = $this->fixture();
        $store->entries[] = new WatchHistoryEntry(1, 7, 'tmdb', 'episode', 1396, 1, 1, '<script>alert(1)</script>', '<b>x</b>', '<img src=x>', '2008-01-20', '/poster.jpg', 47, date('Y-m-d'), '2026-09-18 10:00:00', '2026-09-18 10:00:00');
        $catalog->fail = true;
        $response = $controller->index(new Request(queryParams: ['tipo' => 'episodios', 'page' => '1']));

        self::assertSame(200, $response->status());
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $response->body());
        self::assertStringContainsString('&lt;img src=x&gt;', $response->body());
        self::assertStringNotContainsString('<script>alert(1)</script>', $response->body());
        self::assertSame([['configuration']], $catalog->calls);
        self::assertSame('episode', $store->lastFilter);
        self::assertSame(30, $store->lastPerPage);
    }

    public function testUpdateAndDeleteAreOwnedOfflineAndCsrfProtected(): void
    {
        [$controller, $store, $catalog, $csrf] = $this->fixture();
        $store->entries[] = $this->entry(4, 7);
        $store->entries[] = $this->entry(5, 8);
        $catalog->fail = true;

        self::assertSame(403, $controller->update(new Request(parsedBody: ['watched_on' => '2020-01-01']), '4')->status());
        self::assertSame(404, $controller->update(new Request(parsedBody: ['_token' => $csrf->token(), 'watched_on' => '2020-01-01']), '5')->status());
        self::assertSame(303, $controller->update(new Request(parsedBody: ['_token' => $csrf->token(), 'watched_on' => '2020-01-01']), '4')->status());
        self::assertSame('2020-01-01', $store->entries[0]->watchedOn);
        self::assertSame(303, $controller->remove(new Request(parsedBody: ['_token' => $csrf->token()]), '4')->status());
        self::assertCount(1, $store->entries);
        self::assertSame([], $catalog->calls);
    }

    private function fixture(bool $authenticated = true): array
    {
        $session = new Session(false);
        $auth = new Auth($session);
        if ($authenticated) { $auth->login(7); }
        $csrf = new Csrf($session);
        $store = new MemoryWatchHistoryStore();
        $catalog = new WatchHistoryCatalog();
        return [new WatchHistoryController(new View(dirname(__DIR__) . '/resources/views'), $auth, $csrf, $session, $store, $catalog), $store, $catalog, $csrf];
    }

    private function entry(int $id, int $userId): WatchHistoryEntry
    {
        return new WatchHistoryEntry($id, $userId, 'tmdb', 'movie', 603, null, null, 'Matrix', 'The Matrix', null, '1999-03-31', '/matrix.jpg', 136, date('Y-m-d'), '2026-09-18 10:00:00', '2026-09-18 10:00:00');
    }
}

final class MemoryWatchHistoryStore implements WatchHistoryStore
{
    /** @var list<WatchHistoryEntry> */
    public array $entries = [];
    public ?string $lastFilter = null;
    public int $lastPerPage = 0;
    public function createMovie(int $userId, WatchHistoryEvent $event): bool { return $this->create($userId, $event); }
    public function createEpisode(int $userId, WatchHistoryEvent $event): bool { return $this->create($userId, $event); }
    public function findForUser(int $userId, int $id): ?WatchHistoryEntry { foreach ($this->entries as $entry) { if ($entry->id === $id && $entry->userId === $userId) return $entry; } return null; }
    public function updateDate(int $userId, int $id, string $watchedOn): bool { foreach ($this->entries as $key => $entry) { if ($entry->id === $id && $entry->userId === $userId) { $this->entries[$key] = new WatchHistoryEntry($entry->id,$entry->userId,$entry->source,$entry->entryType,$entry->sourceId,$entry->seasonNumber,$entry->episodeNumber,$entry->title,$entry->originalTitle,$entry->episodeTitle,$entry->contentDate,$entry->posterPath,$entry->durationMinutes,$watchedOn,$entry->createdAt,$entry->updatedAt); return true; } } return false; }
    public function delete(int $userId, int $id): bool { foreach ($this->entries as $key => $entry) { if ($entry->id === $id && $entry->userId === $userId) { unset($this->entries[$key]); $this->entries = array_values($this->entries); return true; } } return false; }
    public function paginateForUser(int $userId, ?string $entryType, int $page, int $perPage): WatchHistoryPage { $this->lastFilter=$entryType;$this->lastPerPage=$perPage;$items=array_values(array_filter($this->entries,fn($e)=>$e->userId===$userId&&($entryType===null||$e->entryType===$entryType)));return new WatchHistoryPage($items,count($items),$page,$perPage); }
    public function countForUser(int $userId): int { return count(array_filter($this->entries, fn($e) => $e->userId === $userId)); }
    private function create(int $userId, WatchHistoryEvent $event): bool { foreach ($this->entries as $entry) { if ($entry->userId === $userId && $entry->createdAt === $event->requestKey) return false; } $id=count($this->entries)+1;$this->entries[]=new WatchHistoryEntry($id,$userId,$event->source,$event->entryType,$event->sourceId,$event->seasonNumber,$event->episodeNumber,$event->title,$event->originalTitle,$event->episodeTitle,$event->contentDate,$event->posterPath,$event->durationMinutes,$event->watchedOn,$event->requestKey,$event->requestKey);return true; }
}

final class WatchHistoryCatalog implements TmdbCatalog
{
    public array $calls = [];
    public bool $fail = false;
    public bool $adult = false;
    public ?int $movieRuntime = 136;
    public ?int $episodeRuntime = 58;
    public function configured(): bool { return true; }
    public function movieDetails(int $id): TmdbMediaDetails { $this->calls[]=['movie',$id];$this->guard();return $this->details('movie',$id); }
    public function seriesDetails(int $id): TmdbMediaDetails { $this->calls[]=['series',$id];$this->guard();return $this->details('series',$id); }
    public function seasonDetails(int $seriesId, int $seasonNumber): TmdbSeasonDetails { $this->calls[]=['season',$seriesId,$seasonNumber];$this->guard();return new TmdbSeasonDetails('tmdb',$seriesId,1,$seasonNumber,'Temporada 1','Resumo','2008-01-20',null,[new TmdbEpisode(1,$seasonNumber,1,'Piloto','Resumo','2008-01-20',$this->episodeRuntime,null)]); }
    public function configuration(): TmdbImageConfiguration { $this->calls[]=['configuration'];$this->guard();return new TmdbImageConfiguration('https://image.tmdb.org/t/p/',['w500'],['w1280']); }
    public function searchMovies(string $query, int $page = 1): array { return []; }
    public function searchSeries(string $query, int $page = 1): array { return []; }
    private function guard(): void { if ($this->fail) throw new TmdbException('transport'); }
    private function details(string $type, int $id): TmdbMediaDetails { $movie=$type==='movie';return new TmdbMediaDetails('tmdb',$type,$id,$movie?'Matrix':'Breaking Bad',$movie?'The Matrix':'Breaking Bad Original',null,'Resumo',$movie?'1999-03-31':'2008-01-20',$movie?1999:2008,$movie?'/matrix.jpg':'/breaking-bad.jpg',null,[],8.0,1,null,'en',$this->adult,null,$movie?$this->movieRuntime:null,$movie?null:5,$movie?null:62,null,null); }
}
