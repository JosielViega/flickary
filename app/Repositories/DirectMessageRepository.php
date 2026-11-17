<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Messaging\DirectConversation;
use App\Messaging\DirectConversationPage;
use App\Messaging\DirectConversationSummary;
use App\Messaging\DirectMessage;
use App\Messaging\DirectMessageBody;
use App\Messaging\DirectMessagePage;
use App\Messaging\DirectMessageReader;
use App\Messaging\DirectMessageStore;
use PDO;
use Throwable;

final class DirectMessageRepository implements DirectMessageReader, DirectMessageStore
{
    public function __construct(private readonly Database $database) {}

    public function conversationExists(int $userA, int $userB): bool
    {
        return $this->conversationForUsers($userA, $userB) !== null;
    }

    public function conversationForUsers(int $currentUserId, int $otherUserId): ?DirectConversation
    {
        [$low, $high] = $this->pair($currentUserId, $otherUserId);
        $statement = $this->database->connection()->prepare(
            'SELECT id,user_low_id,user_high_id,created_at,last_message_at FROM direct_conversations '
            . 'WHERE user_low_id=:low AND user_high_id=:high '
            . 'AND (:participant=user_low_id OR :participant_high=user_high_id) LIMIT 1',
        );
        $statement->execute([
            'low' => $low,
            'high' => $high,
            'participant' => $currentUserId,
            'participant_high' => $currentUserId,
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $this->mapConversation($row) : null;
    }

    public function paginateConversations(int $userId, int $page, int $perPage): DirectConversationPage
    {
        $this->assertPagination($userId, $page, $perPage);
        $pdo = $this->database->connection();
        $count = $pdo->prepare(
            'SELECT COUNT(*) FROM direct_conversations WHERE user_low_id=:low OR user_high_id=:high',
        );
        $count->execute(['low' => $userId, 'high' => $userId]);
        $total = (int) $count->fetchColumn();

        $statement = $pdo->prepare(
            'SELECT c.id AS conversation_id,u.id AS other_user_id,u.username,p.display_name,p.avatar_url,'
            . 'latest.body AS latest_message,latest.sender_user_id AS latest_sender_user_id,'
            . 'latest.created_at AS latest_message_at,'
            . '(SELECT COUNT(*) FROM direct_messages unread WHERE unread.conversation_id=c.id '
            . 'AND unread.sender_user_id<>:viewer_unread AND unread.read_at IS NULL) AS unread_count,'
            . 'CASE WHEN outgoing.follower_user_id IS NULL OR incoming.follower_user_id IS NULL OR pair_block.blocker_user_id IS NOT NULL THEN 0 ELSE 1 END AS can_send_now,'
            . 'CASE WHEN pair_block.blocker_user_id IS NULL THEN 0 ELSE 1 END AS pair_blocked '
            . 'FROM (SELECT id,user_low_id,user_high_id,last_message_at FROM direct_conversations '
            . 'WHERE user_low_id=:viewer_low OR user_high_id=:viewer_high '
            . 'ORDER BY last_message_at DESC,id DESC LIMIT :limit OFFSET :offset) c '
            . 'INNER JOIN users u ON u.id=CASE WHEN c.user_low_id=:viewer_other THEN c.user_high_id ELSE c.user_low_id END '
            . 'INNER JOIN user_profiles p ON p.user_id=u.id '
            . 'INNER JOIN direct_messages latest ON latest.id=(SELECT MAX(newest.id) FROM direct_messages newest WHERE newest.conversation_id=c.id) '
            . 'LEFT JOIN user_follows outgoing ON outgoing.follower_user_id=:viewer_out AND outgoing.followed_user_id=u.id '
            . 'LEFT JOIN user_follows incoming ON incoming.follower_user_id=u.id AND incoming.followed_user_id=:viewer_in '
            . 'LEFT JOIN user_blocks pair_block ON (pair_block.blocker_user_id=:viewer_block AND pair_block.blocked_user_id=u.id) OR (pair_block.blocker_user_id=u.id AND pair_block.blocked_user_id=:viewer_block_reverse) '
            . 'ORDER BY c.last_message_at DESC,c.id DESC',
        );
        foreach (['viewer_other','viewer_unread','viewer_out','viewer_in','viewer_low','viewer_high','viewer_block','viewer_block_reverse'] as $name) {
            $statement->bindValue(':' . $name, $userId, PDO::PARAM_INT);
        }
        $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $statement->execute();
        $items = array_map(static fn (array $row): DirectConversationSummary => new DirectConversationSummary(
            (int) $row['conversation_id'],
            (int) $row['other_user_id'],
            (string) $row['username'],
            (string) $row['display_name'],
            is_string($row['avatar_url']) ? $row['avatar_url'] : null,
            (string) $row['latest_message'],
            (int) $row['latest_sender_user_id'],
            (string) $row['latest_message_at'],
            (int) $row['unread_count'],
            (bool) (int) $row['can_send_now'],
            (bool) (int) $row['pair_blocked'],
        ), $statement->fetchAll(PDO::FETCH_ASSOC));

        return new DirectConversationPage($items, $total, $page, $perPage);
    }

    public function paginateMessages(int $currentUserId, int $otherUserId, int $page, int $perPage): DirectMessagePage
    {
        $this->assertPagination($currentUserId, $page, $perPage);
        $conversation = $this->conversationForUsers($currentUserId, $otherUserId);
        if ($conversation === null) {
            return new DirectMessagePage([], 0, $page, $perPage);
        }
        $pdo = $this->database->connection();
        $count = $pdo->prepare('SELECT COUNT(*) FROM direct_messages WHERE conversation_id=:conversation');
        $count->execute(['conversation' => $conversation->id]);
        $statement = $pdo->prepare(
            'SELECT id,conversation_id,sender_user_id,body,read_at,created_at FROM direct_messages '
            . 'WHERE conversation_id=:conversation ORDER BY id DESC LIMIT :limit OFFSET :offset',
        );
        $statement->bindValue(':conversation', $conversation->id, PDO::PARAM_INT);
        $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $statement->execute();
        $items = array_map([$this, 'mapMessage'], $statement->fetchAll(PDO::FETCH_ASSOC));

        return new DirectMessagePage(array_reverse($items), (int) $count->fetchColumn(), $page, $perPage);
    }

    public function send(int $senderUserId, int $otherUserId, DirectMessageBody $body): bool
    {
        [$low, $high] = $this->pair($senderUserId, $otherUserId);
        $pdo = $this->database->connection();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) { $pdo->beginTransaction(); }
        try {
            $users=$pdo->prepare('SELECT id FROM users WHERE id IN (:low,:high) ORDER BY id FOR UPDATE');$users->execute(['low'=>$low,'high'=>$high]);
            $mutual = $pdo->prepare(
                'SELECT follower_user_id FROM user_follows WHERE '
                . '(follower_user_id=:sender AND followed_user_id=:other) OR '
                . '(follower_user_id=:other_reverse AND followed_user_id=:sender_reverse) FOR UPDATE',
            );
            $mutual->execute([
                'sender' => $senderUserId,
                'other' => $otherUserId,
                'other_reverse' => $otherUserId,
                'sender_reverse' => $senderUserId,
            ]);
            if (count($mutual->fetchAll(PDO::FETCH_COLUMN)) !== 2) {
                if ($ownsTransaction) { $pdo->rollBack(); }
                return false;
            }
            $blocked=$pdo->prepare('SELECT 1 FROM user_blocks WHERE (blocker_user_id=:sender AND blocked_user_id=:other) OR (blocker_user_id=:other_reverse AND blocked_user_id=:sender_reverse) LIMIT 1 FOR UPDATE');
            $blocked->execute(['sender'=>$senderUserId,'other'=>$otherUserId,'other_reverse'=>$otherUserId,'sender_reverse'=>$senderUserId]);
            if($blocked->fetchColumn()!==false){if($ownsTransaction){$pdo->rollBack();}return false;}
            $create = $pdo->prepare(
                'INSERT IGNORE INTO direct_conversations(user_low_id,user_high_id) VALUES (:low,:high)',
            );
            $create->execute(['low' => $low, 'high' => $high]);
            $find = $pdo->prepare(
                'SELECT id FROM direct_conversations WHERE user_low_id=:low AND user_high_id=:high FOR UPDATE',
            );
            $find->execute(['low' => $low, 'high' => $high]);
            $conversationId = (int) $find->fetchColumn();
            if ($conversationId < 1) {
                throw new \RuntimeException('Direct conversation could not be resolved.');
            }
            $insert = $pdo->prepare(
                'INSERT INTO direct_messages(conversation_id,sender_user_id,body) VALUES (:conversation,:sender,:body)',
            );
            $insert->execute(['conversation' => $conversationId, 'sender' => $senderUserId, 'body' => $body->value]);
            $messageId = (int) $pdo->lastInsertId();
            $update = $pdo->prepare(
                'UPDATE direct_conversations c INNER JOIN direct_messages m ON m.id=:message '
                . 'SET c.last_message_at=m.created_at WHERE c.id=:conversation',
            );
            $update->execute(['message' => $messageId, 'conversation' => $conversationId]);
            if ($ownsTransaction) { $pdo->commit(); }
            return true;
        } catch (Throwable $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) { $pdo->rollBack(); }
            throw $exception;
        }
    }

    public function markRead(int $currentUserId, int $otherUserId): bool
    {
        $conversation = $this->conversationForUsers($currentUserId, $otherUserId);
        if ($conversation === null) { return false; }
        $statement = $this->database->connection()->prepare(
            'UPDATE direct_messages SET read_at=CURRENT_TIMESTAMP WHERE conversation_id=:conversation '
            . 'AND sender_user_id<>:viewer AND read_at IS NULL',
        );
        $statement->execute(['conversation' => $conversation->id, 'viewer' => $currentUserId]);
        return true;
    }

    public function countUnreadForUser(int $userId): int
    {
        if ($userId < 1) { throw new \InvalidArgumentException('Invalid message user identity.'); }
        $statement = $this->database->connection()->prepare(
            'SELECT COUNT(*) FROM direct_messages m INNER JOIN direct_conversations c ON c.id=m.conversation_id '
            . 'WHERE (c.user_low_id=:low OR c.user_high_id=:high) AND m.sender_user_id<>:viewer AND m.read_at IS NULL',
        );
        $statement->execute(['low' => $userId, 'high' => $userId, 'viewer' => $userId]);
        return (int) $statement->fetchColumn();
    }

    public function countUnreadForConversation(int $currentUserId, int $otherUserId): int
    {
        $conversation = $this->conversationForUsers($currentUserId, $otherUserId);
        if ($conversation === null) { return 0; }
        $statement = $this->database->connection()->prepare(
            'SELECT COUNT(*) FROM direct_messages WHERE conversation_id=:conversation '
            . 'AND sender_user_id<>:viewer AND read_at IS NULL',
        );
        $statement->execute(['conversation' => $conversation->id, 'viewer' => $currentUserId]);
        return (int) $statement->fetchColumn();
    }

    /** @return array{0:int,1:int} */
    private function pair(int $userA, int $userB): array
    {
        if ($userA < 1 || $userB < 1 || $userA === $userB) {
            throw new \InvalidArgumentException('Invalid direct conversation pair.');
        }
        return [min($userA, $userB), max($userA, $userB)];
    }

    /** @param array<string,mixed> $row */
    private function mapConversation(array $row): DirectConversation
    {
        return new DirectConversation((int) $row['id'], (int) $row['user_low_id'], (int) $row['user_high_id'], (string) $row['created_at'], (string) $row['last_message_at']);
    }

    /** @param array<string,mixed> $row */
    private function mapMessage(array $row): DirectMessage
    {
        return new DirectMessage((int) $row['id'], (int) $row['conversation_id'], (int) $row['sender_user_id'], (string) $row['body'], is_string($row['read_at']) ? $row['read_at'] : null, (string) $row['created_at']);
    }

    private function assertPagination(int $userId, int $page, int $perPage): void
    {
        if ($userId < 1 || $page < 1 || $perPage < 1) { throw new \InvalidArgumentException('Invalid direct message pagination.'); }
    }
}
