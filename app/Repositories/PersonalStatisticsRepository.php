<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Media\UserMediaStatus;
use App\Statistics\MonthlyStatistic;
use App\Statistics\PersonalStatistics;
use App\Statistics\StatisticsReader;
use DateTimeImmutable;
use PDO;

final class PersonalStatisticsRepository implements StatisticsReader
{
    public function __construct(private readonly Database $database)
    {
    }

    public function readForUser(int $userId, string $today): PersonalStatistics
    {
        $todayDate = DateTimeImmutable::createFromFormat('!Y-m-d', $today);
        if ($userId < 1 || $todayDate === false || $todayDate->format('Y-m-d') !== $today) {
            throw new \InvalidArgumentException('Invalid statistics scope.');
        }
        $monthStart = $todayDate->modify('first day of this month');
        $yearStart = $todayDate->setDate((int) $todayDate->format('Y'), 1, 1);
        $nextMonth = $monthStart->modify('+1 month');
        $nextYear = $yearStart->modify('+1 year');
        $seriesStart = $monthStart->modify('-11 months');

        $history = $this->history($userId, $monthStart->format('Y-m-d'), $nextMonth->format('Y-m-d'), $yearStart->format('Y-m-d'), $nextYear->format('Y-m-d'));
        $monthly = $this->monthly($userId, $seriesStart, $nextMonth);
        [$libraryByStatus, $libraryTotal] = $this->library($userId);
        $progress = $this->count('user_series_episode_progress', $userId);
        $schedule = $this->schedule($userId, $today);

        return new PersonalStatistics(
            (int) $history['total_views'],
            (int) $history['movie_views'],
            (int) $history['episode_views'],
            (int) $history['unique_movies'],
            (int) $history['unique_series'],
            (int) $history['active_days'],
            max(0, (int) $history['total_views'] - (int) $history['unique_content']),
            (int) $history['known_minutes'],
            (int) $history['unknown_duration_events'],
            (int) $history['current_month_views'],
            (int) $history['current_year_views'],
            $monthly,
            $libraryByStatus,
            $libraryTotal,
            $progress,
            (int) $schedule['total'],
            (int) $schedule['today'],
            (int) $schedule['future'],
            (int) $schedule['overdue'],
        );
    }

    /** @return array<string,mixed> */
    private function history(int $userId, string $monthStart, string $nextMonth, string $yearStart, string $nextYear): array
    {
        $statement = $this->database->connection()->prepare(
            "SELECT COUNT(*) AS total_views,
                COALESCE(SUM(entry_type = 'movie'), 0) AS movie_views,
                COALESCE(SUM(entry_type = 'episode'), 0) AS episode_views,
                COUNT(DISTINCT CASE WHEN entry_type = 'movie' THEN CONCAT(source, ':', source_id) END) AS unique_movies,
                COUNT(DISTINCT CASE WHEN entry_type = 'episode' THEN CONCAT(source, ':', source_id) END) AS unique_series,
                COUNT(DISTINCT watched_on) AS active_days,
                COUNT(DISTINCT CONCAT(source, ':', entry_type, ':', source_id, ':', COALESCE(season_number, 0), ':', COALESCE(episode_number, 0))) AS unique_content,
                COALESCE(SUM(duration_minutes), 0) AS known_minutes,
                COALESCE(SUM(duration_minutes IS NULL), 0) AS unknown_duration_events,
                COALESCE(SUM(watched_on >= :month_start AND watched_on < :next_month), 0) AS current_month_views,
                COALESCE(SUM(watched_on >= :year_start AND watched_on < :next_year), 0) AS current_year_views
             FROM watch_history WHERE user_id = :user_id",
        );
        $statement->execute([
            'month_start' => $monthStart,
            'next_month' => $nextMonth,
            'year_start' => $yearStart,
            'next_year' => $nextYear,
            'user_id' => $userId,
        ]);
        return $statement->fetch(PDO::FETCH_ASSOC) ?: [];
    }

    /** @return list<MonthlyStatistic> */
    private function monthly(int $userId, DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $statement = $this->database->connection()->prepare(
            "SELECT DATE_FORMAT(watched_on, '%Y-%m') AS month_key, COUNT(*) AS total
             FROM watch_history WHERE user_id = :user_id AND watched_on >= :start AND watched_on < :end
             GROUP BY month_key ORDER BY month_key",
        );
        $statement->execute(['user_id' => $userId, 'start' => $start->format('Y-m-d'), 'end' => $end->format('Y-m-d')]);
        $counts = [];
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $counts[(string) $row['month_key']] = (int) $row['total'];
        }
        $labels = [1=>'jan',2=>'fev',3=>'mar',4=>'abr',5=>'mai',6=>'jun',7=>'jul',8=>'ago',9=>'set',10=>'out',11=>'nov',12=>'dez'];
        $result = [];
        for ($month = $start; $month < $end; $month = $month->modify('+1 month')) {
            $key = $month->format('Y-m');
            $result[] = new MonthlyStatistic($key, $labels[(int) $month->format('n')] . '/' . $month->format('y'), $counts[$key] ?? 0);
        }
        return $result;
    }

    /** @return array{array<string,int>,int} */
    private function library(int $userId): array
    {
        $counts = array_fill_keys(array_keys(UserMediaStatus::options()), 0);
        $statement = $this->database->connection()->prepare('SELECT status, COUNT(*) AS total FROM user_media WHERE user_id = :user_id GROUP BY status');
        $statement->execute(['user_id' => $userId]);
        foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (array_key_exists((string) $row['status'], $counts)) {
                $counts[(string) $row['status']] = (int) $row['total'];
            }
        }
        return [$counts, array_sum($counts)];
    }

    private function count(string $table, int $userId): int
    {
        $statement = $this->database->connection()->prepare('SELECT COUNT(*) FROM ' . $table . ' WHERE user_id = :user_id');
        $statement->execute(['user_id' => $userId]);
        return (int) $statement->fetchColumn();
    }

    /** @return array<string,int> */
    private function schedule(int $userId, string $today): array
    {
        $statement = $this->database->connection()->prepare(
            'SELECT COUNT(*) AS total, COALESCE(SUM(scheduled_on = :today_equal), 0) AS today, '
            . 'COALESCE(SUM(scheduled_on > :today_future), 0) AS future, '
            . 'COALESCE(SUM(scheduled_on < :today_overdue), 0) AS overdue '
            . 'FROM user_schedule WHERE user_id = :user_id',
        );
        $statement->execute(['today_equal'=>$today,'today_future'=>$today,'today_overdue'=>$today,'user_id'=>$userId]);
        $row = $statement->fetch(PDO::FETCH_ASSOC) ?: [];
        return ['total'=>(int)($row['total']??0),'today'=>(int)($row['today']??0),'future'=>(int)($row['future']??0),'overdue'=>(int)($row['overdue']??0)];
    }
}
