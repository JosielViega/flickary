<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use App\Repositories\SocialActivityLikeRepository;
use App\Repositories\UserProfileRepository;
use App\Repositories\UserRepository;
use App\Social\SocialActivityLikeResult;
use PHPUnit\Framework\TestCase;

final class SocialActivityLikeRepositoryDatabaseTest extends TestCase
{
    private Database $database;
    private UserRepository $users;
    private UserProfileRepository $profiles;
    private SocialActivityLikeRepository $likes;

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
        $this->likes = new SocialActivityLikeRepository($this->database);
        $this->clear();
    }

    protected function tearDown(): void
    {
        if (isset($this->database)) { $this->clear(); }
    }

    public function testVisibleLikeIsIdempotentAndUnlikeIsIdempotent(): void
    {
        [$actor, $viewer] = $this->visiblePair();
        $history = $this->history($actor, '2026-11-05 10:00:00');
        self::assertSame(SocialActivityLikeResult::Liked, $this->likes->likeVisibleActivity($viewer, $history));
        self::assertSame(SocialActivityLikeResult::AlreadyLiked, $this->likes->likeVisibleActivity($viewer, $history));
        self::assertSame(1, $this->countLikes($history));
        $this->likes->unlike($viewer, $history);
        $this->likes->unlike($viewer, $history);
        self::assertSame(0, $this->countLikes($history));
    }

    public function testVisibilityRulesRejectPendingUnrelatedDisabledOldAndOwnActivities(): void
    {
        $viewer = $this->user('visibility.viewer');
        $unrelated = $this->user('visibility.unrelated');
        $pending = $this->user('visibility.pending');
        $disabled = $this->user('visibility.disabled');
        $accepted = $this->user('visibility.accepted');
        $this->share($unrelated, '2026-11-01 00:00:00');
        $this->share($pending, '2026-11-01 00:00:00');
        $this->database->connection()->prepare('INSERT INTO user_follow_requests(requester_user_id,target_user_id) VALUES (?,?)')->execute([$viewer, $pending]);
        $this->follow($viewer, $disabled, '2026-11-01 00:00:00');
        $this->follow($viewer, $accepted, '2026-11-03 00:00:00');
        $this->share($accepted, '2026-11-02 00:00:00');

        $ids = [
            $this->history($unrelated, '2026-11-05 00:00:00'),
            $this->history($pending, '2026-11-05 00:00:00'),
            $this->history($disabled, '2026-11-05 00:00:00'),
            $this->history($accepted, '2026-11-01 00:00:00'),
            $this->history($accepted, '2026-11-02 12:00:00'),
            $this->history($viewer, '2026-11-05 00:00:00'),
        ];
        foreach ($ids as $historyId) {
            self::assertSame(SocialActivityLikeResult::Hidden, $this->likes->likeVisibleActivity($viewer, $historyId));
        }
        self::assertSame(SocialActivityLikeResult::Hidden, $this->likes->likeVisibleActivity($viewer, PHP_INT_MAX));
        self::assertSame(0, $this->countLikes());
    }

    public function testUnlikeRemovesOnlyViewerLikeAfterVisibilityIsLost(): void
    {
        [$actor, $viewer] = $this->visiblePair();
        $other = $this->user('unlike.other');
        $this->follow($other, $actor, '2026-11-02 00:00:00');
        $history = $this->history($actor, '2026-11-05 00:00:00');
        $this->likes->likeVisibleActivity($viewer, $history);
        $this->likes->likeVisibleActivity($other, $history);
        $this->database->connection()->prepare('DELETE FROM user_follows WHERE follower_user_id=? AND followed_user_id=?')->execute([$viewer, $actor]);
        $this->likes->unlike($viewer, $history);
        self::assertSame(1, $this->countLikes($history));
    }

    public function testDeletingLikerOrActorCascadesOnlyTheExpectedRows(): void
    {
        [$actor, $viewer] = $this->visiblePair();
        $other = $this->user('cascade.other');
        $this->follow($other, $actor, '2026-11-02 00:00:00');
        $history = $this->history($actor, '2026-11-05 00:00:00');
        $this->likes->likeVisibleActivity($viewer, $history);
        $this->likes->likeVisibleActivity($other, $history);

        $this->database->connection()->prepare('DELETE FROM users WHERE id=?')->execute([$viewer]);
        self::assertSame(1, $this->countLikes($history));
        self::assertSame(1, (int) $this->database->connection()->query('SELECT COUNT(*) FROM watch_history')->fetchColumn());

        $this->database->connection()->prepare('DELETE FROM users WHERE id=?')->execute([$actor]);
        self::assertSame(0, $this->countLikes());
        self::assertSame(0, (int) $this->database->connection()->query('SELECT COUNT(*) FROM watch_history')->fetchColumn());
    }

    private function visiblePair(): array
    {
        $actor = $this->user('like.actor');
        $viewer = $this->user('like.viewer');
        $this->share($actor, '2026-11-01 00:00:00');
        $this->follow($viewer, $actor, '2026-11-02 00:00:00');
        return [$actor, $viewer];
    }
    private function user(string $username): int
    {
        $id = $this->users->create($username, null);
        $this->profiles->create($id, $username, null);
        return $id;
    }
    private function share(int $userId, ?string $since): void
    {
        $this->database->connection()->prepare('UPDATE user_profiles SET share_watch_activity=?,share_watch_activity_since=? WHERE user_id=?')->execute([$since === null ? 0 : 1, $since, $userId]);
    }
    private function follow(int $viewer, int $actor, string $createdAt): void
    {
        $this->database->connection()->prepare('INSERT INTO user_follows(follower_user_id,followed_user_id,created_at) VALUES (?,?,?)')->execute([$viewer, $actor, $createdAt]);
    }
    private function history(int $actor, string $createdAt): int
    {
        $statement = $this->database->connection()->prepare("INSERT INTO watch_history(user_id,source,entry_type,source_id,title,watched_on,request_key,created_at) VALUES (?,'tmdb','movie',603,'Matrix','2026-01-01',?,?)");
        $statement->execute([$actor, md5($actor . $createdAt . random_int(1, PHP_INT_MAX)), $createdAt]);
        return (int) $this->database->connection()->lastInsertId();
    }
    private function countLikes(?int $historyId = null): int
    {
        if ($historyId === null) { return (int) $this->database->connection()->query('SELECT COUNT(*) FROM social_activity_likes')->fetchColumn(); }
        $statement = $this->database->connection()->prepare('SELECT COUNT(*) FROM social_activity_likes WHERE history_id=?');
        $statement->execute([$historyId]);
        return (int) $statement->fetchColumn();
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
