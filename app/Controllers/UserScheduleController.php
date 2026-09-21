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
use App\Integrations\Tmdb\TmdbEpisode;
use App\Integrations\Tmdb\TmdbEpisodeNumber;
use App\Integrations\Tmdb\TmdbException;
use App\Integrations\Tmdb\TmdbImageSizeSelector;
use App\Integrations\Tmdb\TmdbImageUrlBuilder;
use App\Integrations\Tmdb\TmdbMediaId;
use App\Integrations\Tmdb\TmdbSeasonNumber;
use App\Schedule\ScheduleDate;
use App\Schedule\ScheduleEntry;
use App\Schedule\ScheduleId;
use App\Schedule\ScheduleItem;
use App\Schedule\ScheduleStore;

final class UserScheduleController
{
    public function __construct(
        private readonly View $view,
        private readonly Auth $auth,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly ScheduleStore $schedule,
        private readonly TmdbCatalog $tmdb,
    ) {
    }

    public function index(Request $request): Response
    {
        $userId = $this->auth->id();
        if ($userId === null) {
            return Response::redirect('/login', 303);
        }
        $rawType = $request->query('tipo');
        $type = match ($rawType) { 'filmes' => 'movie', 'series' => 'series', 'episodios' => 'episode', default => null };
        $typeFilter = in_array($rawType, ['filmes', 'series', 'episodios'], true) ? $rawType : 'todos';
        $rawPage = $request->query('page');
        $page = is_string($rawPage) && preg_match('/^[1-9]\d{0,4}$/D', $rawPage) === 1 ? (int) $rawPage : 1;
        $today = date('Y-m-d');
        $pageData = $this->schedule->paginateForUser($userId, $type, $today, $page, 30);
        if ($page > $pageData->lastPage()) {
            $pageData = $this->schedule->paginateForUser($userId, $type, $today, $pageData->lastPage(), 30);
        }

        return Response::html($this->view->render('pages/schedule', [
            'title' => 'Agenda — Flickary', 'currentRoute' => 'agenda', 'auth' => $this->auth,
            'csrf' => $this->csrf, 'searchQuery' => '', 'pageData' => $pageData,
            'hasAnyItems' => $this->schedule->countForUser($userId) > 0,
            'typeFilter' => $typeFilter, 'today' => $today,
            'posterUrls' => $this->posterUrls($pageData->items),
            'messages' => $this->session->consumeFlash(),
        ]));
    }

    public function createMedia(Request $request, string $rawId, string $type): Response
    {
        $userId = $this->auth->id();
        if ($userId === null) { return Response::redirect('/login', 303); }
        $id = TmdbMediaId::parse($rawId);
        if ($id === null || !in_array($type, ['movie', 'series'], true)) { return Response::html('Título não encontrado.', 404); }
        if (!$this->csrf->verify($request->input('_token'))) { return Response::html('Ação não autorizada.', 403); }
        $date = ScheduleDate::parse($request->input('scheduled_on'));
        if ($date === null) { return Response::html('Data inválida.', 422); }
        $existing = $this->schedule->findForUserIdentity($userId, 'tmdb', $type, $id);
        if ($existing !== null) {
            $this->schedule->updateDate($userId, $existing->id, $date);
            $this->session->flash('success', 'Agendamento atualizado.');
            return Response::redirect($this->mediaPath($type, $id), 303);
        }
        try {
            $details = $type === 'movie' ? $this->tmdb->movieDetails($id) : $this->tmdb->seriesDetails($id);
        } catch (TmdbException) {
            $this->session->flash('error', 'Não foi possível validar este título agora.');
            return Response::redirect($this->mediaPath($type, $id), 303);
        }
        if ($details->adult) { return Response::html('Título não encontrado.', 404); }
        $this->schedule->create($userId, new ScheduleItem('tmdb', $type, $details->sourceId, 0, 0, $details->title, $details->originalTitle, null, $details->releaseDate, $details->posterPath, $date));
        $this->session->flash('success', 'Título adicionado à Agenda.');
        return Response::redirect($this->mediaPath($type, $id), 303);
    }

    public function createEpisode(Request $request, string $rawId, string $rawSeason, string $rawEpisode): Response
    {
        $userId = $this->auth->id();
        if ($userId === null) { return Response::redirect('/login', 303); }
        $id = TmdbMediaId::parse($rawId); $season = TmdbSeasonNumber::parse($rawSeason); $episode = TmdbEpisodeNumber::parse($rawEpisode);
        if ($id === null || $season === null || $episode === null) { return Response::html('Episódio não encontrado.', 404); }
        if (!$this->csrf->verify($request->input('_token'))) { return Response::html('Ação não autorizada.', 403); }
        $date = ScheduleDate::parse($request->input('scheduled_on'));
        if ($date === null) { return Response::html('Data inválida.', 422); }
        $existing = $this->schedule->findForUserIdentity($userId, 'tmdb', 'episode', $id, $season, $episode);
        if ($existing !== null) {
            $this->schedule->updateDate($userId, $existing->id, $date);
            $this->session->flash('success', 'Agendamento atualizado.');
            return Response::redirect($this->seasonPath($id, $season), 303);
        }
        try {
            $seasonDetails = $this->tmdb->seasonDetails($id, $season);
            $episodeDetails = $this->episode($seasonDetails->episodes, $episode);
            if ($episodeDetails === null) { return Response::html('Episódio não encontrado.', 404); }
            $series = $this->tmdb->seriesDetails($id);
        } catch (TmdbException) {
            $this->session->flash('error', 'Não foi possível validar este episódio agora.');
            return Response::redirect($this->seasonPath($id, $season), 303);
        }
        if ($series->adult) { return Response::html('Episódio não encontrado.', 404); }
        $this->schedule->create($userId, new ScheduleItem('tmdb', 'episode', $id, $season, $episode, $series->title, $series->originalTitle, $episodeDetails->name, $episodeDetails->airDate, $series->posterPath, $date));
        $this->session->flash('success', 'Episódio adicionado à Agenda.');
        return Response::redirect($this->seasonPath($id, $season), 303);
    }

    public function update(Request $request, string $rawId): Response
    {
        $userId = $this->auth->id(); if ($userId === null) { return Response::redirect('/login', 303); }
        $id = ScheduleId::parse($rawId);
        if ($id === null || $this->schedule->findForUser($userId, $id) === null) { return Response::html('Agendamento não encontrado.', 404); }
        if (!$this->csrf->verify($request->input('_token'))) { return Response::html('Ação não autorizada.', 403); }
        $date = ScheduleDate::parse($request->input('scheduled_on'));
        if ($date === null) { return Response::html('Data inválida.', 422); }
        $this->schedule->updateDate($userId, $id, $date); $this->session->flash('success', 'Agendamento atualizado.');
        return Response::redirect('/agenda', 303);
    }

    public function remove(Request $request, string $rawId): Response
    {
        $userId = $this->auth->id(); if ($userId === null) { return Response::redirect('/login', 303); }
        $id = ScheduleId::parse($rawId);
        if ($id === null || $this->schedule->findForUser($userId, $id) === null) { return Response::html('Agendamento não encontrado.', 404); }
        if (!$this->csrf->verify($request->input('_token'))) { return Response::html('Ação não autorizada.', 403); }
        $this->schedule->delete($userId, $id); $this->session->flash('success', 'Agendamento removido.');
        return Response::redirect('/agenda', 303);
    }

    /** @param list<TmdbEpisode> $episodes */
    private function episode(array $episodes, int $number): ?TmdbEpisode
    { foreach ($episodes as $episode) { if ($episode->episodeNumber === $number) { return $episode; } } return null; }
    private function mediaPath(string $type, int $id): string { return ($type === 'movie' ? '/filmes/' : '/series/') . $id; }
    private function seasonPath(int $id, int $season): string { return '/series/' . $id . '/temporadas/' . $season; }

    /** @param list<ScheduleEntry> $items @return array<int,string> */
    private function posterUrls(array $items): array
    {
        if (!array_filter($items, static fn (ScheduleEntry $item): bool => $item->posterPath !== null)) { return []; }
        try { $configuration = $this->tmdb->configuration(); } catch (TmdbException) { return []; }
        $size = (new TmdbImageSizeSelector())->poster($configuration->posterSizes); if ($size === null) { return []; }
        $builder = new TmdbImageUrlBuilder($configuration); $urls = [];
        foreach ($items as $item) { $url = $builder->posterUrl($item->posterPath, $size); if ($url !== null) { $urls[$item->id] = $url; } }
        return $urls;
    }
}
