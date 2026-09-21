<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\History\WatchHistoryDate;
use App\History\WatchHistoryEntry;
use App\History\WatchHistoryEvent;
use App\History\WatchHistoryId;
use App\History\WatchHistoryRequestKey;
use App\History\WatchHistoryStore;
use App\Integrations\Tmdb\TmdbCatalog;
use App\Integrations\Tmdb\TmdbEpisode;
use App\Integrations\Tmdb\TmdbEpisodeNumber;
use App\Integrations\Tmdb\TmdbException;
use App\Integrations\Tmdb\TmdbImageSizeSelector;
use App\Integrations\Tmdb\TmdbImageUrlBuilder;
use App\Integrations\Tmdb\TmdbMediaId;
use App\Integrations\Tmdb\TmdbSeasonNumber;

final class WatchHistoryController
{
    public function __construct(
        private readonly View $view,
        private readonly Auth $auth,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly WatchHistoryStore $history,
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
        $entryType = match ($rawType) {
            'filmes' => 'movie',
            'episodios' => 'episode',
            default => null,
        };
        $typeFilter = in_array($rawType, ['filmes', 'episodios'], true) ? $rawType : null;
        $rawPage = $request->query('page');
        $page = is_string($rawPage) && preg_match('/^[1-9]\d{0,4}$/D', $rawPage) === 1
            ? (int) $rawPage
            : 1;
        $pageData = $this->history->paginateForUser($userId, $entryType, $page, 30);

        if ($page > $pageData->lastPage()) {
            $page = $pageData->lastPage();
            $pageData = $this->history->paginateForUser($userId, $entryType, $page, 30);
        }

        return Response::html($this->view->render('pages/history', [
            'title' => 'Histórico — Flickary',
            'currentRoute' => 'library',
            'auth' => $this->auth,
            'csrf' => $this->csrf,
            'searchQuery' => '',
            'pageData' => $pageData,
            'hasAnyItems' => $this->history->countForUser($userId) > 0,
            'typeFilter' => $typeFilter,
            'posterUrls' => $this->posterUrls($pageData->items),
            'messages' => $this->session->consumeFlash(),
            'today' => date('Y-m-d'),
        ]));
    }

    public function createMovie(Request $request, string $rawId): Response
    {
        $userId = $this->auth->id();
        if ($userId === null) {
            return Response::redirect('/login', 303);
        }

        $movieId = TmdbMediaId::parse($rawId);
        if ($movieId === null) {
            return Response::html('Filme não encontrado.', 404);
        }
        if (!$this->csrf->verify($request->input('_token'))) {
            return Response::html('Ação não autorizada.', 403);
        }

        [$watchedOn, $requestKey] = $this->validatedCreationInput($request);
        if ($watchedOn === null || $requestKey === null) {
            return Response::html('Data ou chave de envio inválida.', 422);
        }

        try {
            $details = $this->tmdb->movieDetails($movieId);
        } catch (TmdbException) {
            $this->session->flash('error', 'Não foi possível validar este filme agora.');
            return Response::redirect('/filmes/' . $movieId, 303);
        }

        if ($details->adult === true) {
            return Response::html('Filme não encontrado.', 404);
        }

        $created = $this->history->createMovie($userId, WatchHistoryEvent::movie(
            $details->sourceId,
            $details->title,
            $details->originalTitle,
            $details->releaseDate,
            $details->posterPath,
            $details->runtime,
            $watchedOn,
            $requestKey,
        ));
        $this->session->flash(
            'success',
            $created ? 'Visualização registrada.' : 'Esta visualização já havia sido registrada por este envio.',
        );

        return Response::redirect('/filmes/' . $movieId, 303);
    }

    public function createEpisode(
        Request $request,
        string $rawId,
        string $rawSeason,
        string $rawEpisode,
    ): Response {
        $userId = $this->auth->id();
        if ($userId === null) {
            return Response::redirect('/login', 303);
        }

        $seriesId = TmdbMediaId::parse($rawId);
        $seasonNumber = TmdbSeasonNumber::parse($rawSeason);
        $episodeNumber = TmdbEpisodeNumber::parse($rawEpisode);
        if ($seriesId === null || $seasonNumber === null || $episodeNumber === null) {
            return Response::html('Episódio não encontrado.', 404);
        }
        if (!$this->csrf->verify($request->input('_token'))) {
            return Response::html('Ação não autorizada.', 403);
        }

        [$watchedOn, $requestKey] = $this->validatedCreationInput($request);
        if ($watchedOn === null || $requestKey === null) {
            return Response::html('Data ou chave de envio inválida.', 422);
        }

        try {
            $season = $this->tmdb->seasonDetails($seriesId, $seasonNumber);
            $episode = $this->findEpisode($season->episodes, $episodeNumber);
            if ($episode === null) {
                return Response::html('Episódio não encontrado.', 404);
            }
            $series = $this->tmdb->seriesDetails($seriesId);
        } catch (TmdbException) {
            $this->session->flash('error', 'Não foi possível validar este episódio agora.');
            return Response::redirect($this->seasonPath($seriesId, $seasonNumber), 303);
        }

        if ($series->adult === true) {
            return Response::html('Episódio não encontrado.', 404);
        }

        $created = $this->history->createEpisode($userId, WatchHistoryEvent::episode(
            $series->sourceId,
            $seasonNumber,
            $episodeNumber,
            $series->title,
            $series->originalTitle,
            $episode->name,
            $episode->airDate,
            $series->posterPath,
            $episode->runtime,
            $watchedOn,
            $requestKey,
        ));
        $this->session->flash(
            'success',
            $created ? 'Visualização registrada.' : 'Esta visualização já havia sido registrada por este envio.',
        );

        return Response::redirect($this->seasonPath($seriesId, $seasonNumber), 303);
    }

    public function update(Request $request, string $rawId): Response
    {
        $userId = $this->auth->id();
        if ($userId === null) {
            return Response::redirect('/login', 303);
        }

        $id = WatchHistoryId::parse($rawId);
        if ($id === null || $this->history->findForUser($userId, $id) === null) {
            return Response::html('Registro não encontrado.', 404);
        }
        if (!$this->csrf->verify($request->input('_token'))) {
            return Response::html('Ação não autorizada.', 403);
        }

        $watchedOn = WatchHistoryDate::parse($request->input('watched_on'));
        if ($watchedOn === null) {
            return Response::html('Data inválida.', 422);
        }

        $this->history->updateDate($userId, $id, $watchedOn);
        $this->session->flash('success', 'Data atualizada.');

        return Response::redirect('/historico', 303);
    }

    public function remove(Request $request, string $rawId): Response
    {
        $userId = $this->auth->id();
        if ($userId === null) {
            return Response::redirect('/login', 303);
        }

        $id = WatchHistoryId::parse($rawId);
        if ($id === null || $this->history->findForUser($userId, $id) === null) {
            return Response::html('Registro não encontrado.', 404);
        }
        if (!$this->csrf->verify($request->input('_token'))) {
            return Response::html('Ação não autorizada.', 403);
        }

        $this->history->delete($userId, $id);
        $this->session->flash('success', 'Registro removido.');

        return Response::redirect('/historico', 303);
    }

    /** @return array{?string, ?string} */
    private function validatedCreationInput(Request $request): array
    {
        return [
            WatchHistoryDate::parse($request->input('watched_on')),
            WatchHistoryRequestKey::parse($request->input('request_key')),
        ];
    }

    /** @param list<TmdbEpisode> $episodes */
    private function findEpisode(array $episodes, int $episodeNumber): ?TmdbEpisode
    {
        foreach ($episodes as $episode) {
            if ($episode->episodeNumber === $episodeNumber) {
                return $episode;
            }
        }

        return null;
    }

    /** @param list<WatchHistoryEntry> $items @return array<int, string> */
    private function posterUrls(array $items): array
    {
        if (!array_filter($items, static fn (WatchHistoryEntry $item): bool => $item->posterPath !== null)) {
            return [];
        }

        try {
            $configuration = $this->tmdb->configuration();
        } catch (TmdbException) {
            return [];
        }

        $size = (new TmdbImageSizeSelector())->poster($configuration->posterSizes);
        if ($size === null) {
            return [];
        }

        $builder = new TmdbImageUrlBuilder($configuration);
        $urls = [];
        foreach ($items as $item) {
            $url = $builder->posterUrl($item->posterPath, $size);
            if ($url !== null) {
                $urls[$item->id] = $url;
            }
        }

        return $urls;
    }

    private function seasonPath(int $seriesId, int $seasonNumber): string
    {
        return '/series/' . $seriesId . '/temporadas/' . $seasonNumber;
    }
}
