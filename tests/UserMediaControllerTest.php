<?php

declare(strict_types=1);

namespace Tests;

use App\Controllers\UserMediaController;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Session;
use App\Core\View;
use App\Integrations\Tmdb\TmdbCatalog;
use App\Integrations\Tmdb\TmdbException;
use App\Integrations\Tmdb\TmdbImageConfiguration;
use App\Integrations\Tmdb\TmdbMediaDetails;
use App\Media\UserMediaItem;
use App\Media\UserMediaPage;
use App\Media\UserMediaStore;
use PHPUnit\Framework\TestCase;

final class UserMediaControllerTest extends TestCase
{
    protected function setUp(): void { $_SESSION = []; }

    public function testVisitorCannotReadOrMutate(): void
    {
        [$controller, $store, $catalog] = $this->fixture(false);
        self::assertSame(303, $controller->index(new Request())->status());
        self::assertSame(303, $controller->save(new Request(parsedBody: ['status' => 'planned']), '603', 'movie')->status());
        self::assertSame(303, $controller->remove(new Request(), '603', 'movie')->status());
        self::assertSame([], $store->calls);
        self::assertSame([], $catalog->calls);
    }

    public function testCsrfAndStatusAreValidatedBeforeMutation(): void
    {
        [$controller, $store, $catalog, $csrf] = $this->fixture();
        self::assertSame(403, $controller->save(new Request(parsedBody: ['status' => 'planned']), '603', 'movie')->status());
        self::assertSame(422, $controller->save(new Request(parsedBody: ['_token' => $csrf->token(), 'status' => 'favorite']), '603', 'movie')->status());
        self::assertSame([], $store->calls);
        self::assertSame([], $catalog->calls);
    }

    public function testFirstInclusionUsesServerSnapshotAndIgnoresTampering(): void
    {
        [$controller, $store, $catalog, $csrf] = $this->fixture();
        $response = $controller->save(new Request(parsedBody: [
            '_token' => $csrf->token(), 'status' => 'planned', 'title' => 'Filme Falso',
            'poster_path' => '/fake.jpg', 'user_id' => 999, 'source' => 'anilist',
        ]), '603', 'movie');

        self::assertSame(303, $response->status());
        self::assertSame('/filmes/603', $response->headers()['Location']);
        self::assertSame([['movie', 603]], $catalog->calls);
        self::assertSame('Matrix', $store->created?->title);
        self::assertSame('/matrix.jpg', $store->created?->posterPath);
        self::assertSame(7, $store->createdUserId);
        self::assertSame('planned', $store->createdStatus);
    }

    public function testExistingStatusUpdateDoesNotCallTmdbOrChangeSnapshot(): void
    {
        [$controller, $store, $catalog, $csrf] = $this->fixture();
        $store->existing = $this->item(status: 'planned');
        $catalog->failIfCalled = true;
        $controller->save(new Request(parsedBody: ['_token' => $csrf->token(), 'status' => 'watching']), '603', 'movie');

        self::assertSame([], $catalog->calls);
        self::assertSame('watching', $store->updatedStatus);
        self::assertSame('Matrix', $store->existing->title);
        self::assertNull($store->created);
    }

    public function testConcurrentFirstInclusionFallsBackToStatusUpdate(): void
    {
        [$controller, $store, $catalog, $csrf] = $this->fixture();
        $store->createResult = false;
        $controller->save(new Request(parsedBody: ['_token' => $csrf->token(), 'status' => 'paused']), '603', 'movie');

        self::assertSame([['movie', 603]], $catalog->calls);
        self::assertSame('paused', $store->updatedStatus);
    }

    public function testRemovalDoesNotCallTmdb(): void
    {
        [$controller, $store, $catalog, $csrf] = $this->fixture();
        $catalog->failIfCalled = true;
        $response = $controller->remove(new Request(parsedBody: ['_token' => $csrf->token()]), '603', 'movie');
        self::assertSame(303, $response->status());
        self::assertContains(['delete', 7, 'tmdb', 'movie', 603], $store->calls);
        self::assertSame([], $catalog->calls);
    }

    public function testAdultAndUnavailableTmdbNeverPersistFirstItem(): void
    {
        [$controller, $store, $catalog, $csrf] = $this->fixture();
        $catalog->details = $this->details(adult: true);
        self::assertSame(404, $controller->save(new Request(parsedBody: ['_token' => $csrf->token(), 'status' => 'planned']), '603', 'movie')->status());
        self::assertNull($store->created);

        $catalog->details = null;
        $catalog->exception = new TmdbException('transport');
        self::assertSame(303, $controller->save(new Request(parsedBody: ['_token' => $csrf->token(), 'status' => 'planned']), '603', 'movie')->status());
        self::assertNull($store->created);
    }

    public function testLibraryUsesFiltersEscapesSnapshotAndSurvivesConfigurationFailure(): void
    {
        [$controller, $store, $catalog] = $this->fixture();
        $store->existing = $this->item(title: '<script>alert(1)</script>', status: 'watching');
        $store->page = new UserMediaPage([$store->existing], 1, 1, 24);
        $catalog->exception = new TmdbException('transport');
        $response = $controller->index(new Request(queryParams: ['status' => 'watching', 'tipo' => 'filmes']));

        self::assertSame(200, $response->status());
        self::assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $response->body());
        self::assertStringNotContainsString('<script>alert(1)</script>', $response->body());
        self::assertStringContainsString('Assistindo', $response->body());
        self::assertContains(['paginate', 7, 'watching', 'movie', 1, 24], $store->calls);
        self::assertSame([['configuration']], $catalog->calls);
    }

    /** @return array{UserMediaController,FakeUserMediaStore,FakeUserMediaCatalog,Csrf} */
    private function fixture(bool $authenticated = true): array
    {
        $session = new Session(false);
        $auth = new Auth($session);
        if ($authenticated) { $auth->login(7); }
        $csrf = new Csrf($session);
        $store = new FakeUserMediaStore();
        $catalog = new FakeUserMediaCatalog($this->details());
        return [new UserMediaController(new View(dirname(__DIR__) . '/resources/views'), $auth, $csrf, $session, $store, $catalog), $store, $catalog, $csrf];
    }

    private function details(bool $adult = false): TmdbMediaDetails
    {
        return new TmdbMediaDetails('tmdb', 'movie', 603, 'Matrix', 'The Matrix', null, 'Overview', '1999-03-31', 1999, '/matrix.jpg', '/backdrop.jpg', [], 8.2, 10, null, 'en', $adult, null, 136, null, null, null, null);
    }

    private function item(string $title = 'Matrix', string $status = 'planned'): UserMediaItem
    {
        return new UserMediaItem(1, 7, 'tmdb', 'movie', 603, $status, $title, 'The Matrix', '1999-03-31', '/matrix.jpg', '/backdrop.jpg', '2026-09-18 10:00:00', '2026-09-18 10:00:00');
    }
}

final class FakeUserMediaStore implements UserMediaStore
{
    public array $calls = [];
    public ?UserMediaItem $existing = null;
    public ?TmdbMediaDetails $created = null;
    public ?int $createdUserId = null;
    public ?string $createdStatus = null;
    public ?string $updatedStatus = null;
    public bool $createResult = true;
    public ?UserMediaPage $page = null;
    public function findForUser(int $userId, string $source, string $mediaType, int $sourceId): ?UserMediaItem { $this->calls[] = ['find', $userId, $source, $mediaType, $sourceId]; return $this->existing; }
    public function create(int $userId, TmdbMediaDetails $details, string $status): bool { $this->calls[] = ['create']; $this->createdUserId = $userId; $this->created = $details; $this->createdStatus = $status; return $this->createResult; }
    public function updateStatus(int $userId, string $source, string $mediaType, int $sourceId, string $status): bool { $this->calls[] = ['update', $userId, $source, $mediaType, $sourceId]; $this->updatedStatus = $status; return true; }
    public function delete(int $userId, string $source, string $mediaType, int $sourceId): bool { $this->calls[] = ['delete', $userId, $source, $mediaType, $sourceId]; return true; }
    public function paginateForUser(int $userId, ?string $status, ?string $mediaType, int $page, int $perPage): UserMediaPage { $this->calls[] = ['paginate', $userId, $status, $mediaType, $page, $perPage]; return $this->page ?? new UserMediaPage([], 0, $page, $perPage); }
    public function countForUser(int $userId): int { return $this->existing === null ? 0 : 1; }
}

final class FakeUserMediaCatalog implements TmdbCatalog
{
    public array $calls = [];
    public bool $failIfCalled = false;
    public ?TmdbException $exception = null;
    public function __construct(public ?TmdbMediaDetails $details) {}
    public function configured(): bool { return true; }
    public function movieDetails(int $id): TmdbMediaDetails { $this->calls[] = ['movie', $id]; if ($this->failIfCalled) throw new \LogicException('TMDB must not be called'); if ($this->exception) throw $this->exception; return $this->details ?? throw new TmdbException('not_found'); }
    public function seriesDetails(int $id): TmdbMediaDetails { $this->calls[] = ['series', $id]; if ($this->failIfCalled) throw new \LogicException('TMDB must not be called'); if ($this->exception) throw $this->exception; return $this->details ?? throw new TmdbException('not_found'); }
    public function seasonDetails(int $seriesId,int $seasonNumber): \App\Integrations\Tmdb\TmdbSeasonDetails { throw new \LogicException('Not used.'); }
    public function configuration(): TmdbImageConfiguration { $this->calls[] = ['configuration']; if ($this->exception) throw $this->exception; return new TmdbImageConfiguration('https://image.tmdb.org/t/p/', ['w500'], ['w1280']); }
    public function searchMovies(string $query, int $page = 1): array { return []; }
    public function searchSeries(string $query, int $page = 1): array { return []; }
}
