<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Integrations\Tmdb\TmdbAnimeClassifier;
use App\Integrations\Tmdb\TmdbCatalog;
use App\Integrations\Tmdb\TmdbException;
use App\Integrations\Tmdb\TmdbImageSizeSelector;
use App\Integrations\Tmdb\TmdbImageUrlBuilder;
use App\Integrations\Tmdb\TmdbMediaId;
use App\Preferences\PersonalRating;
use App\Preferences\UserMediaPreferenceDraft;
use App\Preferences\UserMediaPreferenceStore;

final class UserMediaPreferenceController
{
    private readonly TmdbAnimeClassifier $animeClassifier;

    public function __construct(
        private readonly View $view,
        private readonly Auth $auth,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly UserMediaPreferenceStore $preferences,
        private readonly TmdbCatalog $tmdb,
        ?TmdbAnimeClassifier $animeClassifier = null,
    ) {
        $this->animeClassifier = $animeClassifier ?? new TmdbAnimeClassifier();
    }

    public function save(Request $request, string $rawId, string $mediaType): Response
    {
        $userId = $this->auth->id();
        if ($userId === null) {
            return Response::redirect('/login', 303);
        }
        $id = TmdbMediaId::parse($rawId);
        if ($id === null || !in_array($mediaType, ['movie', 'series'], true)) {
            return Response::html('Título não encontrado.', 404);
        }
        if (!$this->csrf->verify($request->input('_token'))) {
            return Response::html('Ação não autorizada.', 403);
        }

        try {
            $rating = PersonalRating::parse($request->input('rating'));
        } catch (\InvalidArgumentException) {
            return Response::html('Sua nota deve ser um número inteiro entre 1 e 10.', 422);
        }
        $favorite = $request->input('favorite') === '1';
        $existing = $this->preferences->findForUser($userId, 'tmdb', $mediaType, $id);

        if (!$favorite && $rating === null) {
            if ($existing !== null) {
                $this->preferences->delete($userId, 'tmdb', $mediaType, $id);
            }
            $this->session->flash('success', 'Preferências salvas.');
            return Response::redirect($this->detailsPath($mediaType, $id), 303);
        }

        if ($existing !== null) {
            $this->preferences->update($userId, 'tmdb', $mediaType, $id, $favorite, $rating);
            $this->session->flash('success', 'Preferências salvas.');
            return Response::redirect($this->detailsPath($mediaType, $id), 303);
        }

        try {
            $details = $mediaType === 'movie' ? $this->tmdb->movieDetails($id) : $this->tmdb->seriesDetails($id);
        } catch (TmdbException) {
            $this->session->flash('error', 'Não foi possível salvar suas preferências agora. Tente novamente mais tarde.');
            return Response::redirect($this->detailsPath($mediaType, $id), 303);
        }
        if ($details->adult === true) {
            return Response::html('Título não encontrado.', 404);
        }

        $draft = UserMediaPreferenceDraft::fromDetails(
            $details,
            $this->animeClassifier->details($details),
            $favorite,
            $rating,
        );
        if (!$this->preferences->create($userId, $draft)) {
            $this->preferences->update($userId, 'tmdb', $mediaType, $id, $favorite, $rating);
        }
        $this->session->flash('success', 'Preferências salvas.');

        return Response::redirect($this->detailsPath($mediaType, $id), 303);
    }

    public function favorites(Request $request): Response
    {
        return $this->index($request, 'favorites');
    }

    public function ratings(Request $request): Response
    {
        return $this->index($request, 'ratings');
    }

    private function index(Request $request, string $mode): Response
    {
        $userId = $this->auth->id();
        if ($userId === null) {
            return Response::redirect('/login', 303);
        }

        $rawType = $request->query('tipo');
        $mediaType = match ($rawType) {
            'filmes' => 'movie',
            'series' => 'series',
            default => null,
        };
        $animeOnly = $rawType === 'anime';
        $typeFilter = in_array($rawType, ['filmes', 'series', 'anime'], true) ? $rawType : 'todos';
        $rawPage = $request->query('page');
        $page = is_string($rawPage) && preg_match('/^[1-9]\d{0,4}$/D', $rawPage) === 1 ? (int) $rawPage : 1;
        $pageData = $mode === 'favorites'
            ? $this->preferences->paginateFavorites($userId, $mediaType, $animeOnly, $page, 24)
            : $this->preferences->paginateRated($userId, $mediaType, $animeOnly, $page, 24);
        if ($page > $pageData->lastPage()) {
            $page = $pageData->lastPage();
            $pageData = $mode === 'favorites'
                ? $this->preferences->paginateFavorites($userId, $mediaType, $animeOnly, $page, 24)
                : $this->preferences->paginateRated($userId, $mediaType, $animeOnly, $page, 24);
        }

        return Response::html($this->view->render('pages/preferences', [
            'title' => ($mode === 'favorites' ? 'Favoritos' : 'Avaliações') . ' — Flickary',
            'currentRoute' => 'library',
            'auth' => $this->auth,
            'csrf' => $this->csrf,
            'searchQuery' => '',
            'mode' => $mode,
            'pageData' => $pageData,
            'hasAnyItems' => $mode === 'favorites'
                ? $this->preferences->countFavorites($userId) > 0
                : $this->preferences->countRated($userId) > 0,
            'typeFilter' => $typeFilter,
            'posterUrls' => $this->posterUrls($pageData->items),
            'messages' => $this->session->consumeFlash(),
        ]));
    }

    /** @param list<\App\Preferences\UserMediaPreference> $items @return array<int,string> */
    private function posterUrls(array $items): array
    {
        if (!array_filter($items, static fn ($item): bool => $item->posterPath !== null)) {
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

    private function detailsPath(string $mediaType, int $id): string
    {
        return ($mediaType === 'movie' ? '/filmes/' : '/series/') . $id;
    }
}
