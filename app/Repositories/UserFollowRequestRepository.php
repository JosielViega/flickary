<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Social\FollowRequest;
use App\Social\FollowRequestPage;
use App\Social\FollowRequestStore;
use App\Social\PublicProfile;
use App\Notifications\SocialNotificationStore;
use PDO;
use Throwable;

final class UserFollowRequestRepository implements FollowRequestStore
{
    public function __construct(
        private readonly Database $database,
        private readonly ?SocialNotificationStore $notifications = null,
    )
    {
    }

    public function request(int $requesterUserId, int $targetUserId): void
    {
        if ($requesterUserId === $targetUserId) {
            return;
        }
        $pdo = $this->database->connection();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }
        try {
            $this->lockPair($pdo,$requesterUserId,$targetUserId);
            $statement = $pdo->prepare(
                'INSERT IGNORE INTO user_follow_requests (requester_user_id,target_user_id) '
                . 'SELECT :requester,:target WHERE NOT EXISTS ('
                . 'SELECT 1 FROM user_follows '
                . 'WHERE follower_user_id=:requester_follow AND followed_user_id=:target_follow) '
                . 'AND NOT EXISTS (SELECT 1 FROM user_blocks WHERE '
                . '(blocker_user_id=:requester_block AND blocked_user_id=:target_block) OR '
                . '(blocker_user_id=:target_reverse AND blocked_user_id=:requester_reverse))',
            );
            $statement->execute([
                'requester' => $requesterUserId,
                'target' => $targetUserId,
                'requester_follow' => $requesterUserId,
                'target_follow' => $targetUserId,
                'requester_block'=>$requesterUserId,'target_block'=>$targetUserId,
                'target_reverse'=>$targetUserId,'requester_reverse'=>$requesterUserId,
            ]);
            if ($statement->rowCount() === 1) {
                $this->notifications?->followRequest($targetUserId, $requesterUserId);
            }
            if ($ownsTransaction) {
                $pdo->commit();
            }
        } catch (Throwable $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function cancel(int $requesterUserId, int $targetUserId): void
    {
        $pdo = $this->database->connection();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }
        try {
            $statement = $pdo->prepare(
                'DELETE FROM user_follow_requests WHERE requester_user_id=:requester AND target_user_id=:target',
            );
            $statement->execute(['requester' => $requesterUserId, 'target' => $targetUserId]);
            $this->notifications?->removeFollowRequest($targetUserId, $requesterUserId);
            if ($ownsTransaction) {
                $pdo->commit();
            }
        } catch (Throwable $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
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
            $this->lockPair($pdo,$requesterUserId,$targetUserId);
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
                'INSERT IGNORE INTO user_follows (follower_user_id,followed_user_id) SELECT :requester,:target '
                . 'WHERE NOT EXISTS (SELECT 1 FROM user_blocks WHERE (blocker_user_id=:requester_block AND blocked_user_id=:target_block) OR (blocker_user_id=:target_reverse AND blocked_user_id=:requester_reverse))',
            );
            $insert->execute(['requester'=>$requesterUserId,'target'=>$targetUserId,'requester_block'=>$requesterUserId,'target_block'=>$targetUserId,'target_reverse'=>$targetUserId,'requester_reverse'=>$requesterUserId]);
            $delete = $pdo->prepare(
                'DELETE FROM user_follow_requests WHERE requester_user_id=:requester AND target_user_id=:target',
            );
            $delete->execute(['requester' => $requesterUserId, 'target' => $targetUserId]);
            $this->notifications?->removeFollowRequest($targetUserId, $requesterUserId);
            if($insert->rowCount()===1){$this->notifications?->followAccepted($requesterUserId, $targetUserId);}
            if ($ownsTransaction) {
                $pdo->commit();
            }
            return $insert->rowCount() === 1;
        } catch (Throwable $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function reject(int $requesterUserId, int $targetUserId): bool
    {
        $pdo = $this->database->connection();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }
        try {
            $statement = $pdo->prepare(
                'DELETE FROM user_follow_requests WHERE requester_user_id=:requester AND target_user_id=:target',
            );
            $statement->execute(['requester' => $requesterUserId, 'target' => $targetUserId]);
            $changed = $statement->rowCount() > 0;
            if ($changed) {
                $this->notifications?->removeFollowRequest($targetUserId, $requesterUserId);
            }
            if ($ownsTransaction) {
                $pdo->commit();
            }
            return $changed;
        } catch (Throwable $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $exception;
        }
    }

    public function acceptAllForTarget(int $targetUserId): void
    {
        $pdo = $this->database->connection();
        $blocked=$pdo->prepare('DELETE r FROM user_follow_requests r WHERE r.target_user_id=:target AND EXISTS (SELECT 1 FROM user_blocks b WHERE (b.blocker_user_id=r.requester_user_id AND b.blocked_user_id=r.target_user_id) OR (b.blocker_user_id=r.target_user_id AND b.blocked_user_id=r.requester_user_id))');
        $blocked->execute(['target'=>$targetUserId]);
        $this->notifications?->followAcceptedForPendingTarget($targetUserId);
        $this->notifications?->removeFollowRequestsForTarget($targetUserId);
        $insert = $pdo->prepare(
            'INSERT IGNORE INTO user_follows (follower_user_id,followed_user_id) '
            . 'SELECT r.requester_user_id,r.target_user_id FROM user_follow_requests r WHERE r.target_user_id=:target '
            . 'AND NOT EXISTS (SELECT 1 FROM user_blocks b WHERE (b.blocker_user_id=r.requester_user_id AND b.blocked_user_id=r.target_user_id) OR (b.blocker_user_id=r.target_user_id AND b.blocked_user_id=r.requester_user_id))',
        );
        $insert->execute(['target'=>$targetUserId]);
        $delete = $pdo->prepare('DELETE FROM user_follow_requests WHERE target_user_id=:target');
        $delete->execute(['target'=>$targetUserId]);
    }

    private function lockPair(PDO$pdo,int$userA,int$userB):void{$statement=$pdo->prepare('SELECT id FROM users WHERE id IN (:low,:high) ORDER BY id FOR UPDATE');$statement->execute(['low'=>min($userA,$userB),'high'=>max($userA,$userB)]);}
}
