<?php

declare(strict_types=1);

namespace App\Home;

use App\History\WatchHistoryStore;
use App\Media\UserMediaStatus;
use App\Media\UserMediaStore;
use App\Schedule\ScheduleStore;

final class HomePersonalService
{
    public function __construct(
        private readonly UserMediaStore $media,
        private readonly ScheduleStore $schedule,
        private readonly WatchHistoryStore $history,
        private readonly HomeImageService $images,
    ) {
    }

    public function page(int $userId): HomePersonalPage
    {
        $watching = $this->media->paginateForUser($userId, UserMediaStatus::WATCHING, null, 1, 10)->items;
        $agenda = $this->schedule->paginateForUser($userId, null, date('Y-m-d'), 1, 6)->items;
        $history = $this->history->paginateForUser($userId, null, 1, 8)->items;
        $watchingPosters = [];
        $watchingBackdrops = [];
        $agendaPosters = [];
        $historyPosters = [];
        foreach ($watching as $item) {
            $poster = $this->images->poster($item->posterPath);
            $backdrop = $this->images->backdrop($item->backdropPath);
            if ($poster !== null) { $watchingPosters[$item->id] = $poster; }
            if ($backdrop !== null) { $watchingBackdrops[$item->id] = $backdrop; }
        }
        foreach ($agenda as $item) {
            $url = $this->images->poster($item->posterPath);
            if ($url !== null) { $agendaPosters[$item->id] = $url; }
        }
        foreach ($history as $item) {
            $url = $this->images->poster($item->posterPath);
            if ($url !== null) { $historyPosters[$item->id] = $url; }
        }
        return new HomePersonalPage($watching, $agenda, $history, $watchingPosters, $watchingBackdrops, $agendaPosters, $historyPosters);
    }
}
