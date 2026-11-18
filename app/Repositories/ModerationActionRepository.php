<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Moderation\ModeratedUser;
use App\Moderation\ModerationAction;
use App\Moderation\ModerationActionPage;
use App\Moderation\ModerationActionReader;
use App\Moderation\ModerationActionType;
use App\Moderation\SocialReport;
use App\Moderation\ReportTargetType;
use PDO;

final class ModerationActionRepository implements ModerationActionReader
{
    public function __construct(private readonly Database $database) {}

    public function paginateAll(int $page, int $perPage): ModerationActionPage
    {
        return $this->paginate('1=1', [], $page, $perPage);
    }

    public function paginateForUser(int $userId, int $page, int $perPage): ModerationActionPage
    {
        return $this->paginate('a.target_user_id=:target', ['target' => $userId], $page, $perPage);
    }

    public function findUserByUsername(string $username): ?ModeratedUser
    {
        $statement = $this->database->connection()->prepare(
            'SELECT u.id,u.username,u.is_admin,u.suspended_until,p.display_name,'
            . 'CASE WHEN u.suspended_until>CURRENT_TIMESTAMP THEN 1 ELSE 0 END AS is_suspended '
            . 'FROM users u INNER JOIN user_profiles p ON p.user_id=u.id WHERE u.username=:username LIMIT 1',
        );
        $statement->execute(['username' => $username]);
        return $this->mapUser($statement->fetch(PDO::FETCH_ASSOC));
    }

    public function findUserById(int $userId): ?ModeratedUser
    {
        if ($userId < 1) { return null; }
        $statement = $this->database->connection()->prepare(
            'SELECT u.id,u.username,u.is_admin,u.suspended_until,p.display_name,'
            . 'CASE WHEN u.suspended_until>CURRENT_TIMESTAMP THEN 1 ELSE 0 END AS is_suspended '
            . 'FROM users u INNER JOIN user_profiles p ON p.user_id=u.id WHERE u.id=:id LIMIT 1',
        );
        $statement->execute(['id' => $userId]);
        return $this->mapUser($statement->fetch(PDO::FETCH_ASSOC));
    }

    public function liveCommentMatchesReport(SocialReport $report): bool
    {
        if ($report->targetType !== ReportTargetType::Comment || $report->targetId < 1) { return false; }
        $statement = $this->database->connection()->prepare(
            'SELECT user_id FROM social_activity_comments WHERE id=:id LIMIT 1',
        );
        $statement->execute(['id' => $report->targetId]);
        $author = $statement->fetchColumn();
        return $author !== false && ($report->reportedUserId === null || (int) $author === $report->reportedUserId);
    }

    /** @param array<string,int> $params */
    private function paginate(string $where, array $params, int $page, int $perPage): ModerationActionPage
    {
        $page = max(1, $page); $perPage = max(1, min(100, $perPage));
        $pdo = $this->database->connection();
        $count = $pdo->prepare('SELECT COUNT(*) FROM moderation_actions a WHERE ' . $where);
        $count->execute($params);
        $statement = $pdo->prepare(
            'SELECT a.*,admin.username AS performed_by_username FROM moderation_actions a '
            . 'LEFT JOIN users admin ON admin.id=a.performed_by_user_id WHERE ' . $where
            . ' ORDER BY a.created_at DESC,a.id DESC LIMIT :limit OFFSET :offset',
        );
        foreach ($params as $key => $value) { $statement->bindValue(':' . $key, $value, PDO::PARAM_INT); }
        $statement->bindValue(':limit', $perPage, PDO::PARAM_INT);
        $statement->bindValue(':offset', ($page - 1) * $perPage, PDO::PARAM_INT);
        $statement->execute();
        $items = array_map([$this, 'mapAction'], $statement->fetchAll(PDO::FETCH_ASSOC));
        return new ModerationActionPage($items, (int) $count->fetchColumn(), $page, $perPage);
    }

    /** @param array<string,mixed>|false $row */
    private function mapUser(array|false $row): ?ModeratedUser
    {
        if (!is_array($row)) { return null; }
        return new ModeratedUser(
            (int) $row['id'], (string) $row['username'], (string) $row['display_name'],
            (bool) (int) $row['is_admin'], is_string($row['suspended_until']) ? $row['suspended_until'] : null,
            (bool) (int) $row['is_suspended'],
        );
    }

    /** @param array<string,mixed> $row */
    private function mapAction(array $row): ModerationAction
    {
        return new ModerationAction(
            (int) $row['id'], ModerationActionType::from((string) $row['action_type']),
            $row['report_id'] === null ? null : (int) $row['report_id'],
            $row['target_user_id'] === null ? null : (int) $row['target_user_id'],
            $row['performed_by_user_id'] === null ? null : (int) $row['performed_by_user_id'],
            is_string($row['performed_by_username']) ? $row['performed_by_username'] : null,
            is_string($row['target_type']) ? $row['target_type'] : null,
            $row['target_id'] === null ? null : (int) $row['target_id'],
            is_string($row['target_username_snapshot']) ? $row['target_username_snapshot'] : null,
            is_string($row['target_display_name_snapshot']) ? $row['target_display_name_snapshot'] : null,
            is_string($row['content_snapshot']) ? $row['content_snapshot'] : null,
            $row['duration_minutes'] === null ? null : (int) $row['duration_minutes'],
            is_string($row['previous_suspended_until']) ? $row['previous_suspended_until'] : null,
            is_string($row['effective_until']) ? $row['effective_until'] : null,
            (string) $row['note'], (string) $row['created_at'],
        );
    }
}
