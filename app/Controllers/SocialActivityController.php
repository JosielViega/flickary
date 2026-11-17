<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Integrations\Tmdb\TmdbCatalog;
use App\Integrations\Tmdb\TmdbException;
use App\Integrations\Tmdb\TmdbImageSizeSelector;
use App\Integrations\Tmdb\TmdbImageUrlBuilder;
use App\Social\SocialActivityCommentCreateResult;
use App\Social\SocialActivityCommentReader;
use App\Social\SocialActivityCommentStore;
use App\Social\SocialActivityDetail;
use App\Social\SocialCommentBody;
use App\Social\ViewerAwareSocialActivityCommentReader;

final class SocialActivityController
{
    private const PER_PAGE = 30;

    public function __construct(
        private readonly View $view,
        private readonly Auth $auth,
        private readonly Csrf $csrf,
        private readonly SocialActivityCommentReader $comments,
        private readonly SocialActivityCommentStore $commentStore,
        private readonly TmdbCatalog $tmdb,
    ) {
    }

    public function show(Request $request, string $id): Response
    {
        $userId = $this->auth->id();
        if ($userId === null) {
            return Response::redirect('/login', 303);
        }
        $historyId = $this->positiveId($id);
        if ($historyId === null) {
            return $this->notFound('/social/atividades/' . $id);
        }
        $detail = $this->comments->detailForViewer($userId, $historyId);
        if ($detail === null) {
            return $this->notFound('/social/atividades/' . $id);
        }

        return $this->page($detail, $this->pageNumber($request->query('page', 1)));
    }

    public function createComment(Request $request, string $id): Response
    {
        $userId = $this->auth->id();
        if ($userId === null) {
            return Response::redirect('/login', 303);
        }
        if (!$this->csrf->verify($request->input('_token'))) {
            return Response::html('A sessão de formulário expirou.', 403);
        }
        $historyId = $this->positiveId($id);
        if ($historyId === null) {
            return $this->notFound('/social/atividades/' . $id);
        }
        $detail = $this->comments->detailForViewer($userId, $historyId);
        if ($detail === null || !$detail->canComment) {
            return $this->notFound('/social/atividades/' . $id);
        }

        $submitted = $request->input('body');
        [$body, $error] = SocialCommentBody::validate($submitted);
        if (!$body instanceof SocialCommentBody) {
            return $this->page(
                $detail,
                1,
                is_string($submitted) ? str_replace(["\r\n", "\r"], "\n", $submitted) : '',
                $error ?? 'Escreva um comentário válido.',
                422,
            );
        }

        if ($this->commentStore->createAuthorized($userId, $historyId, $body)
            === SocialActivityCommentCreateResult::Hidden) {
            return $this->notFound('/social/atividades/' . $id);
        }

        return Response::redirect('/social/atividades/' . $historyId, 303);
    }

    public function deleteComment(Request $request, string $id): Response
    {
        $userId = $this->auth->id();
        if ($userId === null) {
            return Response::redirect('/login', 303);
        }
        if (!$this->csrf->verify($request->input('_token'))) {
            return Response::html('A sessão de formulário expirou.', 403);
        }
        $commentId = $this->positiveId($id);
        if ($commentId === null) {
            return $this->notFound('/social/comentarios/' . $id);
        }
        $historyId = $this->commentStore->deleteAuthorized($userId, $commentId);
        if ($historyId === null) {
            return $this->notFound('/social/comentarios/' . $id);
        }

        return Response::redirect(
            $request->input('context') === 'profile'
                ? '/perfil/comentarios'
                : '/social/atividades/' . $historyId,
            303,
        );
    }

    private function page(
        SocialActivityDetail $detail,
        int $page,
        string $commentBody = '',
        ?string $commentError = null,
        int $status = 200,
    ): Response {
        return Response::html($this->view->render('pages/social-activity', [
            'title' => $detail->activity->title . ' · Social · Flickary',
            'currentRoute' => 'social',
            'auth' => $this->auth,
            'csrf' => $this->csrf,
            'detail' => $detail,
            'comments'=>$this->comments instanceof ViewerAwareSocialActivityCommentReader?$this->comments->paginateVisibleForActivity($detail->activity->historyId,$page,self::PER_PAGE,(int)$this->auth->id(),$detail->activity->actorUserId):$this->comments->paginateForActivity($detail->activity->historyId,$page,self::PER_PAGE),
            'posterUrl' => $this->posterUrl($detail),
            'commentBody' => $commentBody,
            'commentError' => $commentError,
        ]), $status);
    }

    private function posterUrl(SocialActivityDetail $detail): ?string
    {
        $path = $detail->activity->posterPath;
        if ($path === null) {
            return null;
        }
        try {
            $configuration = $this->tmdb->configuration();
        } catch (TmdbException) {
            return null;
        }
        $size = (new TmdbImageSizeSelector())->poster($configuration->posterSizes);
        return $size === null ? null : (new TmdbImageUrlBuilder($configuration))->posterUrl($path, $size);
    }

    private function positiveId(string $value): ?int
    {
        if ($value === '' || preg_match('/^[1-9][0-9]*$/D', $value) !== 1) {
            return null;
        }
        $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        return $id === false ? null : (int) $id;
    }

    private function pageNumber(mixed $value): int
    {
        $page = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10000]]);
        return $page === false ? 1 : (int) $page;
    }

    private function notFound(string $path): Response
    {
        return Response::html($this->view->render('pages/404', [
            'title' => 'Conteúdo social não encontrado', 'path' => $path,
            'currentRoute' => 'social', 'auth' => $this->auth, 'csrf' => $this->csrf,
        ]), 404);
    }
}
