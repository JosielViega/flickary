<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Response;
use App\Core\Session;
use App\Core\View;
use App\Integrations\Tmdb\TmdbCatalog;
use App\Integrations\Tmdb\TmdbException;
use App\Integrations\Tmdb\TmdbImageSizeSelector;
use App\Integrations\Tmdb\TmdbImageUrlBuilder;
use App\Integrations\Tmdb\TmdbMediaDetails;
use App\Integrations\Tmdb\TmdbMediaId;
use App\Media\UserMediaStatus;
use App\Media\UserMediaStore;
use App\Media\UserSeriesProgressStore;
use App\History\WatchHistoryRequestKey;

final class MediaDetailsController
{
    public function __construct(
        private readonly View $view,
        private readonly TmdbCatalog $tmdb,
        private readonly ?Auth $auth = null,
        private readonly ?Csrf $csrf = null,
        private readonly ?UserMediaStore $userMedia = null,
        private readonly ?Session $session = null,
        private readonly ?UserSeriesProgressStore $seriesProgress = null,
    ) {
    }

    public function movie(string $id): Response
    {
        return $this->show($id, 'movie');
    }

    public function series(string $id): Response
    {
        return $this->show($id, 'series');
    }

    private function show(string $rawId, string $type): Response
    {
        $id = TmdbMediaId::parse($rawId);
        if ($id === null) {
            return $this->error(404, 'Título não encontrado', 'Essa história ainda não está aqui.');
        }

        try {
            $details = $type === 'movie'
                ? $this->tmdb->movieDetails($id)
                : $this->tmdb->seriesDetails($id);
        } catch (TmdbException $exception) {
            return $this->tmdbError($exception);
        }

        if ($details->adult === true) {
            return $this->error(404, 'Título não encontrado', 'Essa história ainda não está aqui.');
        }

        [$posterUrl, $backdropUrl] = $this->imageUrls($details);
        $userId = $this->auth?->id();
        $savedMedia = $userId === null || $this->userMedia === null
            ? null
            : $this->userMedia->findForUser($userId, 'tmdb', $type, $id);
        $progressCounts = $userId !== null && $type === 'series' && $this->seriesProgress !== null
            ? $this->seriesProgress->countsBySeason($userId, 'tmdb', $id)
            : [];

        return $this->render([
            'title' => $details->title . ' — Flickary',
            'details' => $details,
            'posterUrl' => $posterUrl,
            'backdropUrl' => $backdropUrl,
            'errorTitle' => null,
            'errorMessage' => null,
            'userMedia' => $savedMedia,
            'statusOptions' => UserMediaStatus::options(),
            'messages' => $this->session?->consumeFlash() ?? [],
            'progressCounts' => $progressCounts,
            'historyRequestKey' => $userId !== null && $type === 'movie'
                ? WatchHistoryRequestKey::generate()
                : null,
            'today' => date('Y-m-d'),
        ]);
    }

    /** @return array{?string,?string} */
    private function imageUrls(TmdbMediaDetails $details): array
    {
        if ($details->posterPath === null && $details->backdropPath === null) {
            return [null, null];
        }

        try {
            $configuration = $this->tmdb->configuration();
        } catch (TmdbException) {
            return [null, null];
        }

        $selector = new TmdbImageSizeSelector();
        $builder = new TmdbImageUrlBuilder($configuration);
        $posterSize = $selector->poster($configuration->posterSizes);
        $backdropSize = $selector->backdrop($configuration->backdropSizes);

        return [
            $posterSize === null ? null : $builder->posterUrl($details->posterPath, $posterSize),
            $backdropSize === null ? null : $builder->backdropUrl($details->backdropPath, $backdropSize),
        ];
    }

    private function tmdbError(TmdbException $exception): Response
    {
        return match ($exception->category) {
            'not_found' => $this->error(404, 'Título não encontrado', 'Essa história ainda não está aqui.'),
            'not_configured' => $this->error(503, 'Detalhes indisponíveis', 'O catálogo não está disponível neste ambiente.'),
            'rate_limited' => $this->error(503, 'Detalhes temporariamente indisponíveis', 'Muitas consultas foram realizadas. Tente novamente em instantes.'),
            default => $this->error(503, 'Detalhes temporariamente indisponíveis', 'Não foi possível consultar o catálogo agora. Tente novamente mais tarde.'),
        };
    }

    private function error(int $status, string $title, string $message): Response
    {
        return $this->render([
            'title' => $title . ' — Flickary',
            'details' => null,
            'posterUrl' => null,
            'backdropUrl' => null,
            'errorTitle' => $title,
            'errorMessage' => $message,
            'userMedia' => null,
            'statusOptions' => UserMediaStatus::options(),
            'messages' => $this->session?->consumeFlash() ?? [],
            'progressCounts' => [],
            'historyRequestKey' => null,
            'today' => date('Y-m-d'),
        ], $status);
    }

    private function render(array $data, int $status = 200): Response
    {
        return Response::html($this->view->render('pages/media-detail', [
            'currentRoute' => 'search',
            'auth' => $this->auth,
            'csrf' => $this->csrf,
            'searchQuery' => '',
            ...$data,
        ]), $status);
    }
}
