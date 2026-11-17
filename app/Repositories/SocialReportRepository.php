<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Moderation\AdminReportReader;
use App\Moderation\ReportCreateResult;
use App\Moderation\ReportReader;
use App\Moderation\ReportStatus;
use App\Moderation\ReportStore;
use App\Moderation\ReportSubmission;
use App\Moderation\ReportTarget;
use App\Moderation\ReportTargetType;
use App\Moderation\ReportReason;
use App\Moderation\SocialReport;
use App\Moderation\SocialReportPage;
use PDO;
use PDOException;
use Throwable;

final class SocialReportRepository implements ReportStore, ReportReader, AdminReportReader
{
    private const DAILY_LIMIT = 20;

    public function __construct(private readonly Database $database)
    {
    }

    public function reportableComment(int $viewerUserId, int $commentId): ?ReportTarget
    {
        if ($viewerUserId < 1 || $commentId < 1) {
            return null;
        }
        $statement = $this->database->connection()->prepare(
            'SELECT c.id,c.user_id,u.username,p.display_name,p.avatar_url,c.body '
            . 'FROM social_activity_comments c '
            . 'INNER JOIN users u ON u.id=c.user_id '
            . 'INNER JOIN user_profiles p ON p.user_id=c.user_id '
            . 'INNER JOIN watch_history wh ON wh.id=c.history_id '
            . 'INNER JOIN user_profiles owner_profile ON owner_profile.user_id=wh.user_id '
            . 'LEFT JOIN user_follows f ON f.followed_user_id=wh.user_id AND f.follower_user_id=:follow_viewer '
            . 'WHERE c.id=:comment_id AND (wh.user_id=:owner_viewer OR ('
            . 'f.follower_user_id=:visible_viewer AND owner_profile.share_watch_activity=1 '
            . 'AND owner_profile.share_watch_activity_since IS NOT NULL '
            . 'AND wh.created_at>=owner_profile.share_watch_activity_since AND wh.created_at>=f.created_at '
            . 'AND NOT EXISTS (SELECT 1 FROM user_blocks b WHERE '
            . '(b.blocker_user_id=:viewer_block_owner AND b.blocked_user_id=wh.user_id) OR '
            . '(b.blocker_user_id=wh.user_id AND b.blocked_user_id=:viewer_block_owner_reverse)) '
            . 'AND NOT EXISTS (SELECT 1 FROM user_blocks cb WHERE '
            . '(cb.blocker_user_id=:viewer_block_author AND cb.blocked_user_id=c.user_id) OR '
            . '(cb.blocker_user_id=c.user_id AND cb.blocked_user_id=:viewer_block_author_reverse))'
            . ')) LIMIT 1',
        );
        $statement->execute([
            'follow_viewer' => $viewerUserId,
            'comment_id' => $commentId,
            'owner_viewer' => $viewerUserId,
            'visible_viewer' => $viewerUserId,
            'viewer_block_owner' => $viewerUserId,
            'viewer_block_owner_reverse' => $viewerUserId,
            'viewer_block_author' => $viewerUserId,
            'viewer_block_author_reverse' => $viewerUserId,
        ]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->targetFromRow(ReportTargetType::Comment, $row, 'body') : null;
    }

    public function reportableMessage(int $viewerUserId, int $messageId): ?ReportTarget
    {
        if ($viewerUserId < 1 || $messageId < 1) {
            return null;
        }
        $statement = $this->database->connection()->prepare(
            'SELECT m.id,m.sender_user_id AS user_id,u.username,p.display_name,p.avatar_url,m.body '
            . 'FROM direct_messages m INNER JOIN direct_conversations c ON c.id=m.conversation_id '
            . 'INNER JOIN users u ON u.id=m.sender_user_id INNER JOIN user_profiles p ON p.user_id=m.sender_user_id '
            . 'WHERE m.id=:message_id AND (c.user_low_id=:viewer_low OR c.user_high_id=:viewer_high) LIMIT 1',
        );
        $statement->execute(['message_id' => $messageId, 'viewer_low' => $viewerUserId, 'viewer_high' => $viewerUserId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->targetFromRow(ReportTargetType::DirectMessage, $row, 'body') : null;
    }

    public function create(ReportSubmission $submission): ReportCreateResult
    {
        $pdo = $this->database->connection();
        $ownsTransaction = !$pdo->inTransaction();
        if ($ownsTransaction) {
            $pdo->beginTransaction();
        }
        try {
            $duplicate = $pdo->prepare(
                'SELECT 1 FROM social_reports WHERE reporter_user_id=:reporter AND dedupe_key=:dedupe LIMIT 1',
            );
            $duplicate->execute([
                'reporter' => $submission->reporterUserId,
                'dedupe' => $submission->target->dedupeKey(),
            ]);
            if ($duplicate->fetchColumn() !== false) {
                if ($ownsTransaction) { $pdo->rollBack(); }
                return ReportCreateResult::Duplicate;
            }

            $rate = $pdo->prepare(
                'SELECT COUNT(*) FROM social_reports WHERE reporter_user_id=:reporter '
                . 'AND created_at>=DATE_SUB(CURRENT_TIMESTAMP,INTERVAL 24 HOUR)',
            );
            $rate->execute(['reporter' => $submission->reporterUserId]);
            if ((int) $rate->fetchColumn() >= self::DAILY_LIMIT) {
                if ($ownsTransaction) { $pdo->rollBack(); }
                return ReportCreateResult::RateLimited;
            }

            $insert = $pdo->prepare(
                'INSERT INTO social_reports('
                . 'reporter_user_id,reported_user_id,target_type,target_id,reason,details,'
                . 'target_username_snapshot,target_display_name_snapshot,content_snapshot,dedupe_key'
                . ') VALUES ('
                . ':reporter,:reported,:target_type,:target_id,:reason,:details,'
                . ':username,:display_name,:content,:dedupe)',
            );
            $insert->execute([
                'reporter' => $submission->reporterUserId,
                'reported' => $submission->target->reportedUserId,
                'target_type' => $submission->target->type->value,
                'target_id' => $submission->target->id,
                'reason' => $submission->reason->value,
                'details' => $submission->details,
                'username' => $submission->target->username,
                'display_name' => $submission->target->displayName,
                'content' => $submission->target->contentSnapshot,
                'dedupe' => $submission->target->dedupeKey(),
            ]);
            if ($ownsTransaction) { $pdo->commit(); }
            return ReportCreateResult::Created;
        } catch (PDOException $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) { $pdo->rollBack(); }
            if ((int) ($exception->errorInfo[1] ?? 0) === 1062) {
                return ReportCreateResult::Duplicate;
            }
            throw $exception;
        } catch (Throwable $exception) {
            if ($ownsTransaction && $pdo->inTransaction()) { $pdo->rollBack(); }
            throw $exception;
        }
    }

    public function paginateOwn(int $reporterUserId, int $page, int $perPage): SocialReportPage
    {
        return $this->paginate('r.reporter_user_id=:owner', ['owner' => $reporterUserId], $page, $perPage, 'r.created_at DESC,r.id DESC');
    }

    public function paginateAdmin(?ReportStatus $status, int $page, int $perPage): SocialReportPage
    {
        $where = $status === null ? '1=1' : 'r.status=:status';
        $params = $status === null ? [] : ['status' => $status->value];
        $order = $status === ReportStatus::Pending ? 'r.created_at ASC,r.id ASC' : 'r.created_at DESC,r.id DESC';

        return $this->paginate($where, $params, $page, $perPage, $order);
    }

    public function findAdmin(int $reportId): ?SocialReport
    {
        if ($reportId < 1) { return null; }
        $statement = $this->database->connection()->prepare($this->selectSql() . ' WHERE r.id=:id LIMIT 1');
        $statement->execute(['id' => $reportId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $this->mapReport($row) : null;
    }

    public function review(int $reportId, int $reviewerUserId, ReportStatus $status, ?string $moderatorNote): bool
    {
        if ($status === ReportStatus::Pending) {
            throw new \InvalidArgumentException('A review must close a pending report.');
        }
        $statement = $this->database->connection()->prepare(
            'UPDATE social_reports SET status=:status,reviewed_by_user_id=:reviewer,'
            . 'reviewed_at=CURRENT_TIMESTAMP,moderator_note=:note,dedupe_key=NULL '
            . 'WHERE id=:id AND status=:pending',
        );
        $statement->execute([
            'status' => $status->value,
            'reviewer' => $reviewerUserId,
            'note' => $moderatorNote,
            'id' => $reportId,
            'pending' => ReportStatus::Pending->value,
        ]);

        return $statement->rowCount() === 1;
    }

    /** @param array<string,mixed> $row */
    private function targetFromRow(ReportTargetType $type, array $row, string $contentKey): ReportTarget
    {
        return new ReportTarget(
            $type,
            (int) $row['id'],
            (int) $row['user_id'],
            (string) $row['username'],
            (string) $row['display_name'],
            $this->safeHttpsUrl($row['avatar_url'] ?? null),
            is_string($row[$contentKey] ?? null) ? $row[$contentKey] : null,
        );
    }

    /** @param array<string,int|string> $params */
    private function paginate(string $where, array $params, int $page, int $perPage, string $order): SocialReportPage
    {
        $page = max(1, $page);
        $perPage = max(1, min(100, $perPage));
        $pdo = $this->database->connection();
        $count = $pdo->prepare('SELECT COUNT(*) FROM social_reports r WHERE ' . $where);
        $count->execute($params);
        $statement = $pdo->prepare(
            $this->selectSql() . ' WHERE ' . $where . ' ORDER BY ' . $order . ' LIMIT :limit OFFSET :offset',
        );
        foreach ($params as $key => $value) {
            $statement->bindValue(':' . $key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $statement->execute();
        $items = array_map([$this, 'mapReport'], $statement->fetchAll(PDO::FETCH_ASSOC));

        return new SocialReportPage($items, (int) $count->fetchColumn(), $page, $perPage);
    }

    private function selectSql(): string
    {
        return 'SELECT r.*,reporter.username AS reporter_username FROM social_reports r '
            . 'INNER JOIN users reporter ON reporter.id=r.reporter_user_id';
    }

    private function safeHttpsUrl(mixed $value): ?string
    {
        if (!is_string($value) || strlen($value) > 2048 || filter_var($value, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        return strtolower((string) parse_url($value, PHP_URL_SCHEME)) === 'https' ? $value : null;
    }

    /** @param array<string,mixed> $row */
    private function mapReport(array $row): SocialReport
    {
        return new SocialReport(
            (int) $row['id'],
            (int) $row['reporter_user_id'],
            (string) $row['reporter_username'],
            $row['reported_user_id'] === null ? null : (int) $row['reported_user_id'],
            ReportTargetType::from((string) $row['target_type']),
            (int) $row['target_id'],
            ReportReason::from((string) $row['reason']),
            is_string($row['details']) ? $row['details'] : null,
            is_string($row['target_username_snapshot']) ? $row['target_username_snapshot'] : null,
            is_string($row['target_display_name_snapshot']) ? $row['target_display_name_snapshot'] : null,
            is_string($row['content_snapshot']) ? $row['content_snapshot'] : null,
            ReportStatus::from((string) $row['status']),
            $row['reviewed_by_user_id'] === null ? null : (int) $row['reviewed_by_user_id'],
            is_string($row['reviewed_at']) ? $row['reviewed_at'] : null,
            is_string($row['moderator_note']) ? $row['moderator_note'] : null,
            (string) $row['created_at'],
        );
    }
}
