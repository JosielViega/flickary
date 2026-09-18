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
use App\Integrations\Tmdb\TmdbException;
use App\Integrations\Tmdb\TmdbImageSizeSelector;
use App\Integrations\Tmdb\TmdbImageUrlBuilder;
use App\Integrations\Tmdb\TmdbMediaId;
use App\Media\UserMediaStatus;
use App\Media\UserMediaStore;

final class UserMediaController
{
    public function __construct(
        private readonly View $view,
        private readonly Auth $auth,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly UserMediaStore $media,
        private readonly TmdbCatalog $tmdb,
    ) {
    }

    public function index(Request $request): Response
    {
        $userId = $this->auth->id();
        if ($userId === null) {
            return Response::redirect('/login', 303);
        }

        $statusValue = $request->query('status');
        $status = is_string($statusValue) && UserMediaStatus::isValid($statusValue) ? $statusValue : null;
        $typeValue = $request->query('tipo');
        $type = match ($typeValue) {
            'filmes' => 'movie',
            'series' => 'series',
            default => null,
        };
        $pageValue = $request->query('page');
        $page = is_string($pageValue) && preg_match('/^[1-9]\d{0,4}$/D', $pageValue) === 1 ? (int) $pageValue : 1;
        $pageData = $this->media->paginateForUser($userId, $status, $type, $page, 24);
        if ($page > $pageData->lastPage()) {
            $page = $pageData->lastPage();
            $pageData = $this->media->paginateForUser($userId, $status, $type, $page, 24);
        }

        $posterUrls = $this->posterUrls($pageData->items);

        return Response::html($this->view->render('pages/library', [
            'title' => 'Minha Lista — Flickary',
            'currentRoute' => 'library',
            'auth' => $this->auth,
            'csrf' => $this->csrf,
            'searchQuery' => '',
            'pageData' => $pageData,
            'hasAnyItems' => $this->media->countForUser($userId) > 0,
            'statusFilter' => $status,
            'typeFilter' => $typeValue === 'filmes' || $typeValue === 'series' ? $typeValue : null,
            'statusOptions' => UserMediaStatus::options(),
            'posterUrls' => $posterUrls,
            'messages' => $this->session->consumeFlash(),
        ]));
    }

    public function save(Request $request, string $rawId, string $type): Response
    {
        $userId = $this->auth->id();
        if ($userId === null) {
            return Response::redirect('/login', 303);
        }
        $id = TmdbMediaId::parse($rawId);
        if ($id === null) {
            return Response::html('Título não encontrado.', 404);
        }
        if (!$this->csrf->verify($request->input('_token'))) {
            return Response::html('Ação não autorizada.', 403);
        }
        $status = $request->input('status');
        if (!UserMediaStatus::isValid($status)) {
            return Response::html('Status inválido.', 422);
        }

        $existing = $this->media->findForUser($userId, 'tmdb', $type, $id);
        if ($existing !== null) {
            $this->media->updateStatus($userId, 'tmdb', $type, $id, $status);
            $this->session->flash('success', 'Status atualizado.');
            return Response::redirect($this->detailsPath($type, $id), 303);
        }

        try {
            $details = $type === 'movie' ? $this->tmdb->movieDetails($id) : $this->tmdb->seriesDetails($id);
        } catch (TmdbException) {
            $this->session->flash('error', 'Não foi possível adicionar este título agora. Tente novamente mais tarde.');
            return Response::redirect($this->detailsPath($type, $id), 303);
        }
        if ($details->adult === true) {
            return Response::html('Título não encontrado.', 404);
        }

        $created = $this->media->create($userId, $details, $status);
        if (!$created) {
            $this->media->updateStatus($userId, 'tmdb', $type, $id, $status);
        }
        $this->session->flash('success', $created ? 'Adicionado à sua lista.' : 'Status atualizado.');
        return Response::redirect($this->detailsPath($type, $id), 303);
    }

    public function remove(Request $request, string $rawId, string $type): Response
    {
        $userId = $this->auth->id();
        if ($userId === null) {
            return Response::redirect('/login', 303);
        }
        $id = TmdbMediaId::parse($rawId);
        if ($id === null) {
            return Response::html('Título não encontrado.', 404);
        }
        if (!$this->csrf->verify($request->input('_token'))) {
            return Response::html('Ação não autorizada.', 403);
        }

        $this->media->delete($userId, 'tmdb', $type, $id);
        $this->session->flash('success', 'Removido da sua lista.');
        return Response::redirect($this->detailsPath($type, $id), 303);
    }

    /** @param list<\App\Media\UserMediaItem> $items @return array<int,string> */
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

    private function detailsPath(string $type, int $id): string
    {
        return ($type === 'movie' ? '/filmes/' : '/series/') . $id;
    }
}
