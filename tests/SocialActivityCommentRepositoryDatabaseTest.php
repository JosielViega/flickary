<?php

declare(strict_types=1);

namespace Tests;

use App\Core\Database;
use App\Repositories\SocialActivityCommentRepository;
use App\Repositories\UserProfileRepository;
use App\Repositories\UserRepository;
use App\Social\SocialActivityAccess;
use App\Social\SocialActivityCommentCreateResult;
use App\Social\SocialCommentBody;
use PHPUnit\Framework\TestCase;

final class SocialActivityCommentRepositoryDatabaseTest extends TestCase
{
    private Database $database;
    private UserRepository $users;
    private UserProfileRepository $profiles;
    private SocialActivityCommentRepository $comments;

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
        $this->comments = new SocialActivityCommentRepository($this->database);
        $this->clear();
    }

    protected function tearDown(): void
    {
        if (isset($this->database)) { $this->clear(); }
    }

    public function testDetailAndCreationApplyOwnerFollowerAndCutoffRules(): void
    {
        $actor = $this->user('comment.actor');
        $follower = $this->user('comment.follower');
        $outsider = $this->user('comment.outsider');
        $pending = $this->user('comment.pending');
        $this->share($actor, '2026-11-02 00:00:00');
        $this->follow($follower, $actor, '2026-11-03 00:00:00');
        $this->database->connection()->prepare('INSERT INTO user_follow_requests(requester_user_id,target_user_id) VALUES (?,?)')->execute([$pending, $actor]);
        $old = $this->history($actor, '2026-11-01 00:00:00');
        $visible = $this->history($actor, '2026-11-04 00:00:00');

        self::assertNull($this->comments->detailForViewer($follower, $old));
        self::assertNull($this->comments->detailForViewer($outsider, $visible));
        self::assertNull($this->comments->detailForViewer($pending, $visible));
        $ownerDetail = $this->comments->detailForViewer($actor, $visible);
        self::assertSame(SocialActivityAccess::Owner, $ownerDetail?->access);
        self::assertTrue($ownerDetail?->canComment);
        self::assertSame(SocialActivityAccess::Follower, $this->comments->detailForViewer($follower, $visible)?->access);

        self::assertSame(SocialActivityCommentCreateResult::Hidden, $this->create($outsider, $visible, 'hidden'));
        self::assertSame(SocialActivityCommentCreateResult::Hidden, $this->create($pending, $visible, 'pending'));
        self::assertSame(SocialActivityCommentCreateResult::Created, $this->create($follower, $visible, 'follower'));
        self::assertSame(SocialActivityCommentCreateResult::Created, $this->create($actor, $visible, 'owner'));
        self::assertSame(2, $this->comments->paginateForActivity($visible, 1, 30)->total);

        $this->share($actor, null);
        $archived = $this->comments->detailForViewer($actor, $visible);
        self::assertNotNull($archived);
        self::assertFalse($archived->canComment);
        self::assertNull($this->comments->detailForViewer($follower, $visible));
        self::assertSame(SocialActivityCommentCreateResult::Hidden, $this->create($actor, $visible, 'after disable'));
    }

    public function testPaginationOrderingDeletionModerationAndPrivateHistorySurviveAccessLoss(): void
    {
        $actor = $this->user('thread.actor');
        $author = $this->user('thread.author');
        $other = $this->user('thread.other');
        $this->share($actor, '2026-11-01 00:00:00');
        $this->follow($author, $actor, '2026-11-02 00:00:00');
        $this->follow($other, $actor, '2026-11-02 00:00:00');
        $history = $this->history($actor, '2026-11-03 00:00:00');
        for ($index = 1; $index <= 31; $index++) {
            self::assertSame(SocialActivityCommentCreateResult::Created, $this->create($author, $history, 'Comment ' . $index));
        }
        $first = $this->comments->paginateForActivity($history, 1, 30);
        self::assertSame(31, $first->total);
        self::assertSame(2, $first->totalPages());
        self::assertSame('Comment 31', $first->items[0]->body);
        self::assertSame('Comment 1', $this->comments->paginateForActivity($history, 2, 30)->items[0]->body);

        $newest = $first->items[0]->id;
        self::assertNull($this->comments->deleteAuthorized($other, $newest));
        self::assertSame($history, $this->comments->deleteAuthorized($actor, $newest));

        $own = $this->comments->paginateForAuthor($author, 1, 30);
        self::assertSame(30, $own->total);
        $this->database->connection()->prepare('DELETE FROM user_follows WHERE follower_user_id=? AND followed_user_id=?')->execute([$author, $actor]);
        self::assertSame(30, $this->comments->paginateForAuthor($author, 1, 30)->total);
        self::assertSame($history, $this->comments->deleteAuthorized($author, $own->items[0]->id));
        self::assertSame(29, $this->comments->paginateForAuthor($author, 1, 30)->total);
    }

    public function testDeletingHistoryOrAuthorCascadesComments(): void
    {
        $actor = $this->user('cascade.comment.actor');
        $author = $this->user('cascade.comment.author');
        $this->share($actor, '2026-11-01 00:00:00');
        $this->follow($author, $actor, '2026-11-01 00:00:00');
        $history = $this->history($actor, '2026-11-02 00:00:00');
        $this->create($author, $history, 'cascade');
        $this->database->connection()->prepare('DELETE FROM users WHERE id=?')->execute([$author]);
        self::assertSame(0, $this->comments->paginateForActivity($history, 1, 30)->total);
        $this->create($actor, $history, 'owner');
        $this->database->connection()->prepare('DELETE FROM watch_history WHERE id=?')->execute([$history]);
        self::assertSame(0, (int) $this->database->connection()->query('SELECT COUNT(*) FROM social_activity_comments')->fetchColumn());
    }

    private function create(int $viewer, int $history, string $text): SocialActivityCommentCreateResult
    {
        [$body] = SocialCommentBody::validate($text);
        self::assertInstanceOf(SocialCommentBody::class, $body);
        return $this->comments->createAuthorized($viewer, $history, $body);
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

    private function clear(): void
    {
        $pdo = $this->database->connection();
        $pdo->exec('DELETE FROM social_activity_comments');
        $pdo->exec('DELETE FROM social_activity_likes');
        $pdo->exec('DELETE FROM user_follow_requests');
        $pdo->exec('DELETE FROM user_follows');
        $pdo->exec('DELETE FROM watch_history');
        $pdo->exec('DELETE FROM user_external_identities');
        $pdo->exec('DELETE FROM user_profiles');
        $pdo->exec('DELETE FROM users');
    }
}
