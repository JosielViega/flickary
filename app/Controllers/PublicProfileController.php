<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Authentication\UsernamePolicy;
use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\PageMeta;
use App\Core\PublicUrl;
use App\Core\Session;
use App\Core\View;
use App\Integrations\Tmdb\TmdbCatalog;
use App\Integrations\Tmdb\TmdbException;
use App\Integrations\Tmdb\TmdbImageSizeSelector;
use App\Integrations\Tmdb\TmdbImageUrlBuilder;
use App\Social\FollowStore;
use App\Social\FollowRequestStore;
use App\Social\FollowRelationshipState;
use App\Social\PrivateProfileAccessPolicy;
use App\Social\PublicProfile;
use App\Social\PublicProfileReader;

final class PublicProfileController
{
    private const CONNECTIONS_PER_PAGE = 30;

    public function __construct(
        private readonly View $view,
        private readonly Auth $auth,
        private readonly Csrf $csrf,
        private readonly Session $session,
        private readonly UsernamePolicy $usernamePolicy,
        private readonly PublicProfileReader $profiles,
        private readonly FollowStore $follows,
        private readonly TmdbCatalog $tmdb,
        private readonly PublicUrl $publicUrl,
        private readonly FollowRequestStore $followRequests,
        private readonly PrivateProfileAccessPolicy $accessPolicy,
    ) {
    }

    public function show(string $username): Response
    {
        $profile = $this->resolve($username);
        if ($profile === null) {
            return $this->notFound('/u/' . $username);
        }

        $canonicalUrl = $this->publicUrl->forPath('/u/' . rawurlencode($profile->username));
        $defaultImage = $this->publicUrl->forPath('/assets/images/flickary-share.png');
        $pageMeta = new PageMeta(
            $profile->displayName . ' (@' . $profile->username . ') · Flickary',
            $profile->isPrivate
                ? 'Perfil privado de @' . $profile->username . ' no Flickary.'
                : ($profile->bio ?: 'Conheça a jornada de @' . $profile->username . ' no Flickary.'),
            $canonicalUrl,
            $profile->coverUrl ?? $profile->avatarUrl ?? $defaultImage,
            ($profile->coverUrl ?? $profile->avatarUrl) === null
                ? 'Flickary — Mais que assistir. Viver histórias.'
                : 'Perfil de ' . $profile->displayName . ' no Flickary',
        );

        $viewerUserId = $this->auth->id();
        $isOwner = $viewerUserId === $profile->userId;
        $isFollowing = $viewerUserId !== null && !$isOwner
            ? $this->follows->isFollowing($viewerUserId, $profile->userId) : false;
        $isPending = $viewerUserId !== null && !$isOwner && !$isFollowing
            ? $this->followRequests->isPending($viewerUserId, $profile->userId) : false;
        $relationshipState = $isFollowing
            ? FollowRelationshipState::Following
            : ($isPending ? FollowRelationshipState::Pending : FollowRelationshipState::None);
        $canViewContent = $this->accessPolicy->canView($profile, $viewerUserId, $isFollowing);
        $favorites = $canViewContent ? $this->profiles->recentFavorites($profile->userId, 6) : [];
        $posterUrls = $this->posterUrls($favorites);

        return Response::html($this->view->render('pages/public-profile', [
            'title' => $profile->displayName . ' (@' . $profile->username . ') · Flickary',
            'currentRoute' => null,
            'auth' => $this->auth,
            'csrf' => $this->csrf,
            'profile' => $profile,
            'isOwner' => $isOwner,
            'relationshipState' => $relationshipState,
            'canViewContent' => $canViewContent,
            'followersCount' => $this->follows->countFollowers($profile->userId),
            'followingCount' => $this->follows->countFollowing($profile->userId),
            'statistics' => $canViewContent ? $this->profiles->statisticsForUser($profile->userId) : null,
            'favorites' => $favorites,
            'posterUrls' => $posterUrls,
            'messages' => $this->session->consumeFlash(),
            'pageMeta' => $pageMeta,
            'shareData' => [
                'title' => $pageMeta->title,
                'text' => $pageMeta->description,
                'url' => $pageMeta->canonicalUrl,
            ],
            'hasShareActions' => true,
        ]));
    }

    public function followers(Request $request, string $username): Response
    {
        return $this->connections($request, $username, true);
    }

    public function following(Request $request, string $username): Response
    {
        return $this->connections($request, $username, false);
    }

    private function connections(Request $request, string $username, bool $followers): Response
    {
        $profile = $this->resolve($username);
        if ($profile === null) {
            return $this->notFound('/u/' . $username . ($followers ? '/seguidores' : '/seguindo'));
        }
        $page = $this->page($request->query('page', 1));
        $profiles = $followers
            ? $this->follows->paginateFollowers($profile->userId, $page, self::CONNECTIONS_PER_PAGE)
            : $this->follows->paginateFollowing($profile->userId, $page, self::CONNECTIONS_PER_PAGE);

        return Response::html($this->view->render('pages/user-connections', [
            'title' => ($followers ? 'Seguidores de ' : 'Seguindo por ') . '@' . $profile->username . ' · Flickary',
            'currentRoute' => null,
            'auth' => $this->auth,
            'csrf' => $this->csrf,
            'profile' => $profile,
            'profiles' => $profiles,
            'connectionType' => $followers ? 'followers' : 'following',
        ]));
    }

    private function resolve(string $username): ?PublicProfile
    {
        $normalized = $this->usernamePolicy->normalize($username);
        if ($normalized === null || $normalized !== $username || $this->usernamePolicy->error($normalized) !== null) {
            return null;
        }
        return $this->profiles->findByUsername($normalized);
    }

    private function posterUrls(array $favorites): array
    {
        if (!array_filter($favorites, static fn ($favorite): bool => $favorite->posterPath !== null)) {
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
        foreach ($favorites as $favorite) {
            $urls[$favorite->mediaType . ':' . $favorite->sourceId] = $builder->posterUrl($favorite->posterPath, $size);
        }
        return $urls;
    }

    private function page(mixed $value): int
    {
        $page = filter_var($value, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1,'max_range'=>10000]]);
        return $page === false ? 1 : (int) $page;
    }

    private function notFound(string $path): Response
    {
        return Response::html($this->view->render('pages/404', [
            'title'=>'Perfil não encontrado','path'=>$path,'currentRoute'=>null,'auth'=>$this->auth,'csrf'=>$this->csrf,
        ]), 404);
    }
}
