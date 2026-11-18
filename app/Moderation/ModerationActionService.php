<?php

declare(strict_types=1);

namespace App\Moderation;

use App\Core\Database;
use PDO;
use Throwable;

final class ModerationActionService implements ModerationActionStore
{
    public function __construct(private readonly Database $database) {}

    public function suspendFromReport(int $reportId, int $adminUserId, SuspensionDuration $duration, ModerationNote $note): ModerationActionResult
    {
        return $this->transaction(function (PDO $pdo) use ($reportId, $adminUserId, $duration, $note): ModerationActionResult {
            $report = $this->lockPendingReport($pdo, $reportId);
            if ($report === null || $report['reported_user_id'] === null) { return ModerationActionResult::NotFound; }
            $targetId = (int) $report['reported_user_id'];
            $target = $this->lockUser($pdo, $targetId);
            if ($target === null) { return ModerationActionResult::NotFound; }
            if ($targetId === $adminUserId || (int) $target['is_admin'] === 1) { return ModerationActionResult::ProtectedTarget; }

            $previous = is_string($target['suspended_until']) ? $target['suspended_until'] : null;
            $update = $pdo->prepare(
                'UPDATE users SET suspended_until=DATE_ADD('
                . 'CASE WHEN suspended_until>CURRENT_TIMESTAMP THEN suspended_until ELSE CURRENT_TIMESTAMP END,'
                . 'INTERVAL :duration MINUTE) WHERE id=:id',
            );
            $update->bindValue(':duration', $duration->minutes(), PDO::PARAM_INT);
            $update->bindValue(':id', $targetId, PDO::PARAM_INT);
            $update->execute();
            $effective = $this->lockedSuspensionUntil($pdo, $targetId);
            if ($effective === null) { throw new \RuntimeException('Suspension update did not produce an effective timestamp.'); }

            $this->insertAction($pdo, ModerationActionType::SuspendUser, $report, $targetId, $adminUserId, $duration->minutes(), $previous, $effective, $note->value);
            $this->resolveReport($pdo, $reportId, $adminUserId, $note->value);
            return ModerationActionResult::Applied;
        });
    }

    public function unsuspend(string $username, int $adminUserId, ModerationNote $note): ModerationActionResult
    {
        return $this->transaction(function (PDO $pdo) use ($username, $adminUserId, $note): ModerationActionResult {
            $statement = $pdo->prepare(
                'SELECT u.id,u.username,u.is_admin,u.suspended_until,p.display_name FROM users u '
                . 'INNER JOIN user_profiles p ON p.user_id=u.id WHERE u.username=:username FOR UPDATE',
            );
            $statement->execute(['username' => $username]);
            $target = $statement->fetch(PDO::FETCH_ASSOC);
            if (!is_array($target) || !is_string($target['suspended_until']) || $target['suspended_until'] <= $this->databaseNow($pdo)) {
                return ModerationActionResult::NotFound;
            }
            $targetId = (int) $target['id'];
            if ($targetId === $adminUserId) { return ModerationActionResult::ProtectedTarget; }
            $previous = (string) $target['suspended_until'];
            $pdo->prepare('UPDATE users SET suspended_until=NULL WHERE id=:id')->execute(['id' => $targetId]);
            $report = [
                'id' => null, 'target_type' => 'profile', 'target_id' => $targetId,
                'target_username_snapshot' => (string) $target['username'],
                'target_display_name_snapshot' => (string) $target['display_name'], 'content_snapshot' => null,
            ];
            $this->insertAction($pdo, ModerationActionType::UnsuspendUser, $report, $targetId, $adminUserId, null, $previous, null, $note->value);
            return ModerationActionResult::Applied;
        });
    }

    public function removeReportedComment(int $reportId, int $adminUserId, ModerationNote $note): ModerationActionResult
    {
        return $this->transaction(function (PDO $pdo) use ($reportId, $adminUserId, $note): ModerationActionResult {
            $report = $this->lockPendingReport($pdo, $reportId);
            if ($report === null || $report['target_type'] !== ReportTargetType::Comment->value) { return ModerationActionResult::NotFound; }
            $comment = $pdo->prepare('SELECT id,user_id FROM social_activity_comments WHERE id=:id FOR UPDATE');
            $comment->execute(['id' => (int) $report['target_id']]);
            $live = $comment->fetch(PDO::FETCH_ASSOC);
            if (!is_array($live) || ($report['reported_user_id'] !== null && (int) $live['user_id'] !== (int) $report['reported_user_id'])) {
                return ModerationActionResult::NotFound;
            }
            $pdo->prepare('DELETE FROM social_activity_comments WHERE id=:id')->execute(['id' => (int) $live['id']]);
            $this->insertAction($pdo, ModerationActionType::RemoveComment, $report, (int) $live['user_id'], $adminUserId, null, null, null, $note->value);
            $this->resolveReport($pdo, $reportId, $adminUserId, $note->value);
            return ModerationActionResult::Applied;
        });
    }

    /** @return array<string,mixed>|null */
    private function lockPendingReport(PDO $pdo, int $reportId): ?array
    {
        if ($reportId < 1) { return null; }
        $statement = $pdo->prepare(
            'SELECT * FROM social_reports WHERE id=:id AND status=:status FOR UPDATE',
        );
        $statement->execute(['id' => $reportId, 'status' => ReportStatus::Pending->value]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    /** @return array<string,mixed>|null */
    private function lockUser(PDO $pdo, int $userId): ?array
    {
        $statement = $pdo->prepare('SELECT id,is_admin,suspended_until FROM users WHERE id=:id FOR UPDATE');
        $statement->execute(['id' => $userId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : null;
    }

    private function lockedSuspensionUntil(PDO $pdo, int $userId): ?string
    {
        $statement = $pdo->prepare('SELECT suspended_until FROM users WHERE id=:id');
        $statement->execute(['id' => $userId]);
        $value = $statement->fetchColumn();
        return is_string($value) ? $value : null;
    }

    private function databaseNow(PDO $pdo): string
    {
        return (string) $pdo->query('SELECT CURRENT_TIMESTAMP')->fetchColumn();
    }

    /** @param array<string,mixed> $report */
    private function insertAction(PDO $pdo, ModerationActionType $type, array $report, ?int $targetUserId, int $adminUserId, ?int $duration, ?string $previous, ?string $effective, string $note): void
    {
        $statement = $pdo->prepare(
            'INSERT INTO moderation_actions(action_type,report_id,target_user_id,performed_by_user_id,target_type,target_id,'
            . 'target_username_snapshot,target_display_name_snapshot,content_snapshot,duration_minutes,previous_suspended_until,effective_until,note) '
            . 'VALUES(:action_type,:report_id,:target_user_id,:admin,:target_type,:target_id,:username,:display_name,:content,:duration,:previous,:effective,:note)',
        );
        $statement->execute([
            'action_type' => $type->value, 'report_id' => $report['id'] ?? null, 'target_user_id' => $targetUserId,
            'admin' => $adminUserId, 'target_type' => $report['target_type'] ?? null, 'target_id' => $report['target_id'] ?? null,
            'username' => $report['target_username_snapshot'] ?? null, 'display_name' => $report['target_display_name_snapshot'] ?? null,
            'content' => $report['content_snapshot'] ?? null, 'duration' => $duration, 'previous' => $previous,
            'effective' => $effective, 'note' => $note,
        ]);
    }

    private function resolveReport(PDO $pdo, int $reportId, int $adminUserId, string $note): void
    {
        $statement = $pdo->prepare(
            'UPDATE social_reports SET status=:resolved,reviewed_by_user_id=:admin,reviewed_at=CURRENT_TIMESTAMP,'
            . 'moderator_note=:note,dedupe_key=NULL WHERE id=:id AND status=:pending',
        );
        $statement->execute(['resolved' => ReportStatus::Resolved->value, 'admin' => $adminUserId, 'note' => $note, 'id' => $reportId, 'pending' => ReportStatus::Pending->value]);
        if ($statement->rowCount() !== 1) { throw new \RuntimeException('Pending report could not be resolved atomically.'); }
    }

    /** @template T @param callable(PDO):T $operation @return T */
    private function transaction(callable $operation): mixed
    {
        $pdo = $this->database->connection();
        if ($pdo->inTransaction()) { throw new \RuntimeException('Moderation actions require an owned transaction.'); }
        $pdo->beginTransaction();
        try {
            $result = $operation($pdo);
            if ($result !== ModerationActionResult::Applied) { $pdo->rollBack(); return $result; }
            $pdo->commit();
            return $result;
        } catch (Throwable $exception) {
            if ($pdo->inTransaction()) { $pdo->rollBack(); }
            throw $exception;
        }
    }
}
