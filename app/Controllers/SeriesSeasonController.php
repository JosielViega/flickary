<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Integrations\Tmdb\TmdbCatalog;
use App\Integrations\Tmdb\TmdbEpisodeNumber;
use App\Integrations\Tmdb\TmdbException;
use App\Integrations\Tmdb\TmdbImageSizeSelector;
use App\Integrations\Tmdb\TmdbImageUrlBuilder;
use App\Integrations\Tmdb\TmdbMediaId;
use App\Integrations\Tmdb\TmdbSeasonNumber;
use App\Media\UserMediaStore;
use App\Media\UserSeriesProgressStore;
use App\History\WatchHistoryRequestKey;
use App\Schedule\ScheduleStore;

final class SeriesSeasonController
{
    public function __construct(
        private readonly View $view,
        private readonly TmdbCatalog $tmdb,
        private readonly Auth $auth,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly UserMediaStore $userMedia,
        private readonly UserSeriesProgressStore $progress,
        private readonly ?ScheduleStore $schedule = null,
    ) {
    }

    public function show(string $rawId, string $rawSeason): Response
    {
        $seriesId = TmdbMediaId::parse($rawId);
        $seasonNumber = TmdbSeasonNumber::parse($rawSeason);

        if ($seriesId === null || $seasonNumber === null) {
            return $this->error(404, 'Temporada não encontrada');
        }

        try {
            $details = $this->tmdb->seasonDetails($seriesId, $seasonNumber);
        } catch (TmdbException $exception) {
            $notFound = $exception->category === 'not_found';

            return $this->error(
                $notFound ? 404 : 503,
                $notFound ? 'Temporada não encontrada' : 'Temporada temporariamente indisponível',
            );
        }

        $userId = $this->auth->id();
        $savedMedia = $userId === null
            ? null
            : $this->userMedia->findForUser($userId, 'tmdb', 'series', $seriesId);
        $watched = $userId === null
            ? []
            : $this->progress->watchedEpisodeNumbersForSeason(
                $userId,
                'tmdb',
                $seriesId,
                $seasonNumber,
            );
        $episodeSchedules = $userId === null || $this->schedule === null
            ? []
            : $this->schedule->episodeSchedulesForSeason($userId, 'tmdb', $seriesId, $seasonNumber);

        $posterUrl = null;

        if ($details->posterPath !== null) {
            try {
                $configuration = $this->tmdb->configuration();
                $size = (new TmdbImageSizeSelector())->poster($configuration->posterSizes);
                $posterUrl = $size === null
                    ? null
                    : (new TmdbImageUrlBuilder($configuration))->posterUrl($details->posterPath, $size);
            } catch (TmdbException) {
                // Season content remains available with the visual fallback.
            }
        }

        $nextEpisode = null;
        $today = date('Y-m-d');
        $episodeHistoryKeys = [];

        foreach ($details->episodes as $episode) {
            if ($userId !== null) {
                $episodeHistoryKeys[$episode->episodeNumber] = WatchHistoryRequestKey::generate();
            }
            if (
                $nextEpisode === null
                && !in_array($episode->episodeNumber, $watched, true)
                && $episode->airDate !== null
                && $episode->airDate <= $today
            ) {
                $nextEpisode = $episode->episodeNumber;
            }
        }

        return Response::html($this->view->render('pages/series-season', [
            'title' => $details->name . ' — Flickary',
            'currentRoute' => 'search',
            'auth' => $this->auth,
            'csrf' => $this->csrf,
            'searchQuery' => '',
            'details' => $details,
            'posterUrl' => $posterUrl,
            'watched' => $watched,
            'canTrack' => $savedMedia !== null,
            'nextEpisode' => $nextEpisode,
            'messages' => $this->session->consumeFlash(),
            'today' => $today,
            'episodeHistoryKeys' => $episodeHistoryKeys,
            'episodeSchedules' => $episodeSchedules,
        ]));
    }

    public function mark(
        Request $request,
        string $rawId,
        string $rawSeason,
        string $rawEpisode,
    ): Response {
        return $this->changeEpisode($request, $rawId, $rawSeason, $rawEpisode, true);
    }

    public function unmark(
        Request $request,
        string $rawId,
        string $rawSeason,
        string $rawEpisode,
    ): Response {
        return $this->changeEpisode($request, $rawId, $rawSeason, $rawEpisode, false);
    }

    public function markSeason(Request $request, string $rawId, string $rawSeason): Response
    {
        return $this->changeSeason($request, $rawId, $rawSeason, true);
    }

    public function clearSeason(Request $request, string $rawId, string $rawSeason): Response
    {
        return $this->changeSeason($request, $rawId, $rawSeason, false);
    }

    private function changeEpisode(
        Request $request,
        string $rawId,
        string $rawSeason,
        string $rawEpisode,
        bool $mark,
    ): Response {
        $userId = $this->auth->id();

        if ($userId === null) {
            return Response::redirect('/login', 303);
        }

        $seriesId = TmdbMediaId::parse($rawId);
        $seasonNumber = TmdbSeasonNumber::parse($rawSeason);
        $episodeNumber = TmdbEpisodeNumber::parse($rawEpisode);

        if ($seriesId === null || $seasonNumber === null || $episodeNumber === null) {
            return Response::html('Não encontrado.', 404);
        }

        if (!$this->csrf->verify($request->input('_token'))) {
            return Response::html('Ação não autorizada.', 403);
        }

        if ($this->userMedia->findForUser($userId, 'tmdb', 'series', $seriesId) === null) {
            return Response::html('Adicione a série à Minha Lista para acompanhar episódios.', 409);
        }

        if ($mark) {
            try {
                $details = $this->tmdb->seasonDetails($seriesId, $seasonNumber);
            } catch (TmdbException) {
                $this->session->flash('error', 'Não foi possível validar o episódio agora.');

                return Response::redirect($this->seasonPath($seriesId, $seasonNumber), 303);
            }

            $episodeExists = array_filter(
                $details->episodes,
                static fn ($episode): bool => $episode->episodeNumber === $episodeNumber,
            ) !== [];

            if (!$episodeExists) {
                return Response::html('Episódio não encontrado.', 404);
            }

            $this->progress->markWatched(
                $userId,
                'tmdb',
                $seriesId,
                $seasonNumber,
                $episodeNumber,
            );
            $message = 'Episódio marcado como assistido.';
        } else {
            $this->progress->unmarkWatched(
                $userId,
                'tmdb',
                $seriesId,
                $seasonNumber,
                $episodeNumber,
            );
            $message = 'Episódio desmarcado.';
        }

        $this->session->flash('success', $message);

        return Response::redirect($this->seasonPath($seriesId, $seasonNumber), 303);
    }

    private function changeSeason(
        Request $request,
        string $rawId,
        string $rawSeason,
        bool $mark,
    ): Response {
        $userId = $this->auth->id();

        if ($userId === null) {
            return Response::redirect('/login', 303);
        }

        $seriesId = TmdbMediaId::parse($rawId);
        $seasonNumber = TmdbSeasonNumber::parse($rawSeason);

        if ($seriesId === null || $seasonNumber === null) {
            return Response::html('Não encontrado.', 404);
        }

        if (!$this->csrf->verify($request->input('_token'))) {
            return Response::html('Ação não autorizada.', 403);
        }

        if ($this->userMedia->findForUser($userId, 'tmdb', 'series', $seriesId) === null) {
            return Response::html('Adicione a série à Minha Lista.', 409);
        }

        if ($mark) {
            try {
                $details = $this->tmdb->seasonDetails($seriesId, $seasonNumber);
            } catch (TmdbException) {
                $this->session->flash('error', 'Não foi possível validar a temporada agora.');

                return Response::redirect($this->seasonPath($seriesId, $seasonNumber), 303);
            }

            $this->progress->markSeasonWatched(
                $userId,
                'tmdb',
                $seriesId,
                $seasonNumber,
                array_map(
                    static fn ($episode): int => $episode->episodeNumber,
                    $details->episodes,
                ),
            );
            $message = 'Temporada marcada como assistida.';
        } else {
            $this->progress->clearSeason($userId, 'tmdb', $seriesId, $seasonNumber);
            $message = 'Progresso da temporada limpo.';
        }

        $this->session->flash('success', $message);

        return Response::redirect($this->seasonPath($seriesId, $seasonNumber), 303);
    }

    private function seasonPath(int $seriesId, int $seasonNumber): string
    {
        return '/series/' . $seriesId . '/temporadas/' . $seasonNumber;
    }

    private function error(int $status, string $message): Response
    {
        return Response::html($this->view->render('pages/404', [
            'title' => $message,
            'path' => '',
            'currentRoute' => 'search',
            'auth' => $this->auth,
            'csrf' => $this->csrf,
        ]), $status);
    }
}
