<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use App\Repositories\SocialFeedRepository;
use App\Repositories\UserProfileRepository;
use App\Repositories\UserRepository;
use PHPUnit\Framework\TestCase;

final class SocialFeedRepositoryDatabaseTest extends TestCase
{
    private Database $database;
    private UserRepository $users;
    private UserProfileRepository $profiles;
    private SocialFeedRepository $feed;

    protected function setUp(): void
    {
        $name = getenv('FLICKARY_TEST_DB_DATABASE');
        if (!is_string($name) || !str_ends_with($name, '_test')) {
            self::markTestSkipped('Disposable Flickary database was not configured.');
        }
        $this->database = new Database([
            'host' => getenv('FLICKARY_TEST_DB_HOST') ?: '127.0.0.1',
            'port' => (int) (getenv('FLICKARY_TEST_DB_PORT') ?: 3306),
            'database' => $name,
            'username' => getenv('FLICKARY_TEST_DB_USERNAME') ?: '',
            'password' => getenv('FLICKARY_TEST_DB_PASSWORD') ?: '',
            'charset' => 'utf8mb4',
        ]);
        $this->users = new UserRepository($this->database);
        $this->profiles = new UserProfileRepository($this->database);
        $this->feed = new SocialFeedRepository($this->database);
        $this->clear();
    }

    protected function tearDown(): void
    {
        if (isset($this->database)) {
            $this->clear();
        }
    }

    public function testSharingTransitionsPreserveOrResetCutoffWithoutSleep(): void
    {
        $actor = $this->createUser('sharing.actor', 'Sharing Actor');
        $initial = $this->profileRow($actor);
        self::assertSame(0, (int) $initial['share_watch_activity']);
        self::assertNull($initial['share_watch_activity_since']);

        $this->profiles->setEnabled($actor, true);
        $enabled = $this->profileRow($actor);
        self::assertSame(1, (int) $enabled['share_watch_activity']);
        self::assertNotNull($enabled['share_watch_activity_since']);

        $this->database->connection()->exec("UPDATE user_profiles SET share_watch_activity_since='2026-11-01 08:00:00' WHERE user_id={$actor}");
        $this->profiles->setEnabled($actor, true);
        self::assertSame('2026-11-01 08:00:00', $this->profileRow($actor)['share_watch_activity_since']);

        $this->profiles->setEnabled($actor, false);
        $disabled = $this->profileRow($actor);
        self::assertSame(0, (int) $disabled['share_watch_activity']);
        self::assertNull($disabled['share_watch_activity_since']);

        $this->profiles->setEnabled($actor, true);
        $reenabled = $this->profileRow($actor);
        self::assertSame(1, (int) $reenabled['share_watch_activity']);
        self::assertNotSame('2026-11-01 08:00:00', $reenabled['share_watch_activity_since']);
    }

    public function testFeedAppliesAcceptedFollowConsentAndFollowCutoffsWithLocalSnapshots(): void
    {
        $actor = $this->createUser('actor.user', '<Actor>', true);
        $viewer = $this->createUser('viewer.user', 'Viewer');
        $control = $this->createUser('control.user', 'Control');
        $pendingActor = $this->createUser('pending.actor', 'Pending Actor', true);
        $disabledActor = $this->createUser('disabled.actor', 'Disabled Actor');

        $this->sharingAt($actor, true, '2026-11-10 10:00:00');
        $this->sharingAt($pendingActor, true, '2026-11-01 10:00:00');
        $this->sharingAt($disabledActor, false, null);
        $this->followAt($viewer, $actor, '2026-11-11 10:00:00');
        $this->followAt($viewer, $disabledActor, '2026-11-01 10:00:00');
        $this->requestAt($viewer, $pendingActor, '2026-11-01 10:00:00');

        $this->history($actor, 'Old consent', 'movie', '2026-11-09 11:00:00', '1999-05-21');
        $this->history($actor, 'Before follow', 'movie', '2026-11-10 12:00:00', '1999-05-21');
        $movieId = $this->history($actor, '<script>Matrix</script>', 'movie', '2026-11-12 12:00:00', '1999-05-21', anime: true);
        $episodeId = $this->history($actor, 'Breaking Bad', 'episode', '2026-11-13 12:00:00', '2010-01-01', 1, 1, '<Pilot>');
        $this->history($pendingActor, 'Pending secret', 'movie', '2026-11-14 12:00:00', '2026-11-14');
        $this->history($disabledActor, 'Disabled secret', 'movie', '2026-11-15 12:00:00', '2026-11-15');

        $page = $this->feed->paginateForViewer($viewer, 1, 30);
        self::assertSame(2, $page->total);
        self::assertSame([$episodeId, $movieId], array_map(static fn ($item): int => $item->historyId, $page->items));
        self::assertSame('actor.user', $page->items[0]->actorUsername);
        self::assertSame('<Actor>', $page->items[0]->actorDisplayName);
        self::assertSame('episode', $page->items[0]->entryType);
        self::assertSame('<Pilot>', $page->items[0]->episodeTitle);
        self::assertFalse($page->items[0]->isAnime);
        self::assertTrue($page->items[1]->isAnime);
        self::assertSame('1999-05-21', $page->items[1]->watchedOn);
        self::assertSame(0, $this->feed->paginateForViewer($control, 1, 30)->total);

        $first = $this->feed->paginateForViewer($viewer, 1, 1);
        $second = $this->feed->paginateForViewer($viewer, 2, 1);
        self::assertSame(2, $first->total);
        self::assertSame($episodeId, $first->items[0]->historyId);
        self::assertSame($movieId, $second->items[0]->historyId);
    }

    public function testFeedReflectsEditDeleteUnfollowAndNewRelationshipCutoffsImmediately(): void
    {
        $actor = $this->createUser('lifecycle.actor', 'Lifecycle Actor');
        $viewer = $this->createUser('lifecycle.viewer', 'Lifecycle Viewer');
        $this->sharingAt($actor, true, '2026-11-01 00:00:00');
        $this->followAt($viewer, $actor, '2026-11-02 00:00:00');
        $first = $this->history($actor, 'First', 'movie', '2026-11-03 10:00:00', '1999-01-01');
        $second = $this->history($actor, 'Second', 'movie', '2026-11-04 10:00:00', '2000-01-01');

        $pdo = $this->database->connection();
        $pdo->exec("UPDATE watch_history SET watched_on='1988-08-08' WHERE id={$first}");
        $page = $this->feed->paginateForViewer($viewer, 1, 30);
        self::assertSame([$second, $first], array_map(static fn ($item): int => $item->historyId, $page->items));
        self::assertSame('1988-08-08', $page->items[1]->watchedOn);

        $pdo->exec("DELETE FROM watch_history WHERE id={$second}");
        self::assertSame([$first], array_map(static fn ($item): int => $item->historyId, $this->feed->paginateForViewer($viewer, 1, 30)->items));

        $pdo->exec("DELETE FROM user_follows WHERE follower_user_id={$viewer} AND followed_user_id={$actor}");
        self::assertSame(0, $this->feed->paginateForViewer($viewer, 1, 30)->total);
        $this->followAt($viewer, $actor, '2026-11-05 00:00:00');
        self::assertSame(0, $this->feed->paginateForViewer($viewer, 1, 30)->total);
        $new = $this->history($actor, 'After refollow', 'movie', '2026-11-06 10:00:00', '2026-11-06');
        self::assertSame([$new], array_map(static fn ($item): int => $item->historyId, $this->feed->paginateForViewer($viewer, 1, 30)->items));

        $this->sharingAt($actor, false, null);
        self::assertSame(0, $this->feed->paginateForViewer($viewer, 1, 30)->total);
        $this->sharingAt($actor, true, '2026-11-07 00:00:00');
        self::assertSame(0, $this->feed->paginateForViewer($viewer, 1, 30)->total);
        $latest = $this->history($actor, 'After re-enable', 'movie', '2026-11-08 10:00:00', '2026-11-08');
        self::assertSame([$latest], array_map(static fn ($item): int => $item->historyId, $this->feed->paginateForViewer($viewer, 1, 30)->items));
    }

    public function testMultipleActorsAndRewatchesRemainIndependentChronologicalEvents(): void
    {
        $viewer = $this->createUser('multi.viewer', 'Multi Viewer');
        $actorA = $this->createUser('multi.actor.a', 'Actor A');
        $actorB = $this->createUser('multi.actor.b', 'Actor B');
        foreach ([$actorA, $actorB] as $actor) {
            $this->sharingAt($actor, true, '2026-11-01 00:00:00');
            $this->followAt($viewer, $actor, '2026-11-02 00:00:00');
        }

        $firstWatch = $this->history($actorA, 'Matrix', 'movie', '2026-11-03 10:00:00', '1999-05-21');
        $secondWatch = $this->history($actorA, 'Matrix', 'movie', '2026-11-04 10:00:00', '2026-11-04');
        $otherActor = $this->history($actorB, 'Arrival', 'movie', '2026-11-05 10:00:00', '2026-11-05');

        $page = $this->feed->paginateForViewer($viewer, 1, 30);
        self::assertSame(3, $page->total);
        self::assertSame([$otherActor, $secondWatch, $firstWatch], array_map(static fn ($item): int => $item->historyId, $page->items));
        self::assertSame([$actorB, $actorA, $actorA], array_map(static fn ($item): int => $item->actorUserId, $page->items));
        self::assertSame(['Arrival', 'Matrix', 'Matrix'], array_map(static fn ($item): string => $item->title, $page->items));
    }

    public function testLikesAreAggregatedWithoutDuplicatingPaginationOrChangingOrder(): void
    {
        $viewer = $this->createUser('aggregate.viewer', 'Viewer');
        $other = $this->createUser('aggregate.other', 'Other');
        $actor = $this->createUser('aggregate.actor', 'Actor');
        $this->sharingAt($actor, true, '2026-11-01 00:00:00');
        $this->followAt($viewer, $actor, '2026-11-02 00:00:00');
        $older = $this->history($actor, 'Older', 'movie', '2026-11-03 00:00:00', '2026-11-03');
        $newer = $this->history($actor, 'Newer', 'episode', '2026-11-04 00:00:00', '2026-11-04', 1, 1);
        $likers = [$viewer, $other];
        for ($index = 1; $index <= 18; $index++) {
            $likers[] = $this->createUser('aggregate.liker.' . $index, 'Liker ' . $index);
        }
        $statement = $this->database->connection()->prepare('INSERT INTO social_activity_likes(history_id,user_id) VALUES (?,?)');
        foreach ($likers as $liker) {
            $statement->execute([$newer, $liker]);
        }

        $page = $this->feed->paginateForViewer($viewer, 1, 1);
        self::assertSame(2, $page->total);
        self::assertSame($newer, $page->items[0]->historyId);
        self::assertSame(20, $page->items[0]->likeCount);
        self::assertTrue($page->items[0]->viewerLiked);
        $second = $this->feed->paginateForViewer($viewer, 2, 1);
        self::assertSame($older, $second->items[0]->historyId);
        self::assertSame(0, $second->items[0]->likeCount);
        self::assertFalse($second->items[0]->viewerLiked);
    }

    private function createUser(string $username, string $displayName, bool $private = false): int
    {
        $id = $this->users->create($username, null);
        $this->profiles->create($id, $displayName, null);
        if ($private) {
            $this->profiles->updateProfile($id, $displayName, null, true);
        }
        return $id;
    }

    private function profileRow(int $userId): array
    {
        $statement = $this->database->connection()->prepare('SELECT share_watch_activity,share_watch_activity_since FROM user_profiles WHERE user_id=?');
        $statement->execute([$userId]);
        return (array) $statement->fetch(\PDO::FETCH_ASSOC);
    }

    private function sharingAt(int $userId, bool $enabled, ?string $since): void
    {
        $statement = $this->database->connection()->prepare('UPDATE user_profiles SET share_watch_activity=?,share_watch_activity_since=? WHERE user_id=?');
        $statement->execute([$enabled ? 1 : 0, $since, $userId]);
    }

    private function followAt(int $viewer, int $actor, string $createdAt): void
    {
        $statement = $this->database->connection()->prepare('INSERT INTO user_follows(follower_user_id,followed_user_id,created_at) VALUES (?,?,?)');
        $statement->execute([$viewer, $actor, $createdAt]);
    }

    private function requestAt(int $viewer, int $actor, string $createdAt): void
    {
        $statement = $this->database->connection()->prepare('INSERT INTO user_follow_requests(requester_user_id,target_user_id,created_at) VALUES (?,?,?)');
        $statement->execute([$viewer, $actor, $createdAt]);
    }

    private function history(
        int $actor,
        string $title,
        string $type,
        string $createdAt,
        string $watchedOn,
        ?int $season = null,
        ?int $episode = null,
        ?string $episodeTitle = null,
        bool $anime = false,
    ): int {
        $statement = $this->database->connection()->prepare(
            'INSERT INTO watch_history(user_id,source,entry_type,source_id,season_number,episode_number,title,episode_title,content_date,poster_path,is_anime,watched_on,request_key,created_at) '
            . 'VALUES (?,\'tmdb\',?,603,?,?,?,?,\'1999-03-31\',\'/poster.jpg\',?,?,?,?)',
        );
        $statement->execute([
            $actor, $type, $season, $episode, $title, $episodeTitle, $anime ? 1 : 0, $watchedOn,
            md5($actor . $title . $createdAt . random_int(1, PHP_INT_MAX)), $createdAt,
        ]);
        return (int) $this->database->connection()->lastInsertId();
    }

    private function clear(): void
    {
        $pdo = $this->database->connection();
        $pdo->exec('DELETE FROM social_activity_likes');
        $pdo->exec('DELETE FROM user_follow_requests');
        $pdo->exec('DELETE FROM user_follows');
        $pdo->exec('DELETE FROM watch_history');
        $pdo->exec('DELETE FROM user_external_identities');
        $pdo->exec('DELETE FROM user_profiles');
        $pdo->exec('DELETE FROM users');
    }
}
