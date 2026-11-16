<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Social\FollowRequest;
use App\Social\FollowRequestPage;
use App\Social\FollowRequestStore;
use App\Social\PublicProfile;
use PDO;
use Throwable;

final class UserFollowRequestRepository implements FollowRequestStore
{
    public function __construct(private readonly Database $database)
    {
    }

    public function request(int $requesterUserId, int $targetUserId): void
    {
        if ($requesterUserId === $targetUserId) {
            return;
        }
        $statement = $this->database->connection()->prepare(
            'INSERT IGNORE INTO user_follow_requests (requester_user_id,target_user_id) '
            . 'SELECT :requester,:target WHERE NOT EXISTS ('
            . 'SELECT 1 FROM user_follows WHERE follower_user_id=:requester_follow AND followed_user_id=:target_follow)',
        );
        $statement->execute([
            'requester'=>$requesterUserId, 'target'=>$targetUserId,
            'requester_follow'=>$requesterUserId, 'target_follow'=>$targetUserId,
        ]);
    }

    public function cancel(int $requesterUserId, int $targetUserId): void
    {
        $statement = $this->database->connection()->prepare(
            'DELETE FROM user_follow_requests WHERE requester_user_id=:requester AND target_user_id=:target',
        );
        $statement->execute(['requester'=>$requesterUserId,'target'=>$targetUserId]);
    }

    public function isPending(int $requesterUserId, int $targetUserId): bool
    {
        $statement = $this->database->connection()->prepare(
            'SELECT 1 FROM user_follow_requests WHERE requester_user_id=:requester AND target_user_id=:target LIMIT 1',
        );
        $statement->execute(['requester'=>$requesterUserId,'target'=>$targetUserId]);
        return $statement->fetchColumn() !== false;
    }

    public function countIncoming(int $targetUserId): int
    {
        $statement = $this->database->connection()->prepare(
            'SELECT COUNT(*) FROM user_follow_requests WHERE target_user_id=:target',
        );
        $statement->execute(['target'=>$targetUserId]);
        return (int) $statement->fetchColumn();
    }

    public function paginateIncoming(int $targetUserId, int $page, int $perPage): FollowRequestPage
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $statement = $this->database->connection()->prepare(
            'SELECT p.user_id,u.username,p.display_name,p.bio,p.avatar_url,p.cover_url,p.is_private,u.created_at,r.created_at request_created_at '
            . 'FROM user_follow_requests r INNER JOIN users u ON u.id=r.requester_user_id '
            . 'INNER JOIN user_profiles p ON p.user_id=u.id WHERE r.target_user_id=:target '
            . 'ORDER BY r.created_at DESC,r.requester_user_id DESC LIMIT :limit OFFSET :offset',
        );
        $statement->bindValue(':target', $targetUserId, PDO::PARAM_INT);
        $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $statement->execute();
        $items = array_map(static fn (array $row): FollowRequest => new FollowRequest(
            PublicProfile::fromRow($row),
            (string) $row['request_created_at'],
        ), $statement->fetchAll(PDO::FETCH_ASSOC));
        return new FollowRequestPage($items, $this->countIncoming($targetUserId), $page, $perPage);
    }

    public function accept(int $requesterUserId, int $targetUserId): bool
    {
        $pdo = $this->database->connection();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }
        try {
            $select = $pdo->prepare(
                'SELECT created_at FROM user_follow_requests WHERE requester_user_id=:requester AND target_user_id=:target FOR UPDATE',
            );
            $select->execute(['requester'=>$requesterUserId,'target'=>$targetUserId]);
            if ($select->fetchColumn() === false) {
                if ($ownsTransaction) {
                    $pdo->commit();
                }
                return false;
            }
            $insert = $pdo->prepare(
                'INSERT IGNORE INTO user_follows (follower_user_id,followed_user_id) VALUES (:requester,:target)',
            );
            $insert->execute(['requester'=>$requesterUserId,'target'=>$targetUserId]);
            $this->cancel($requesterUserId, $targetUserId);
            if ($ownsTransaction) {
                $pdo->commit();
            }
            return true;
        } catch (Throwable $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function reject(int $requesterUserId, int $targetUserId): bool
    {
        $statement = $this->database->connection()->prepare(
            'DELETE FROM user_follow_requests WHERE requester_user_id=:requester AND target_user_id=:target',
        );
        $statement->execute(['requester'=>$requesterUserId,'target'=>$targetUserId]);
        return $statement->rowCount() > 0;
    }

    public function acceptAllForTarget(int $targetUserId): void
    {
        $pdo = $this->database->connection();
        $insert = $pdo->prepare(
            'INSERT IGNORE INTO user_follows (follower_user_id,followed_user_id) '
            . 'SELECT requester_user_id,target_user_id FROM user_follow_requests WHERE target_user_id=:target',
        );
        $insert->execute(['target'=>$targetUserId]);
        $delete = $pdo->prepare('DELETE FROM user_follow_requests WHERE target_user_id=:target');
        $delete->execute(['target'=>$targetUserId]);
    }
}
