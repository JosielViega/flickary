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
use App\Social\FollowStore;
use App\Social\SocialFeedReader;

final class SocialFeedController
{
    private const PER_PAGE = 30;

    public function __construct(
        private readonly View $view,
        private readonly Auth $auth,
        private readonly Csrf $csrf,
        private readonly SocialFeedReader $feed,
        private readonly FollowStore $follows,
        private readonly TmdbCatalog $tmdb,
    ) {
    }

    public function index(Request $request): Response
    {
        $userId = $this->auth->id();
        if ($userId === null) {
            return Response::redirect('/login', 303);
        }

        $page = $this->page($request->query('page', 1));
        $activities = $this->feed->paginateForViewer($userId, $page, self::PER_PAGE);

        return Response::html($this->view->render('pages/social-feed', [
            'title' => 'Social · Flickary',
            'currentRoute' => 'social',
            'auth' => $this->auth,
            'csrf' => $this->csrf,
            'activities' => $activities,
            'followsAnyone' => $this->follows->countFollowing($userId) > 0,
            'posterUrls' => $this->posterUrls($activities->items),
        ]));
    }

    /** @param list<\App\Social\SocialActivity> $activities */
    private function posterUrls(array $activities): array
    {
        if (!array_filter($activities, static fn ($activity): bool => $activity->posterPath !== null)) {
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
        foreach ($activities as $activity) {
            if ($activity->posterPath !== null) {
                $urls[$activity->historyId] = $builder->posterUrl($activity->posterPath, $size);
            }
        }

        return $urls;
    }

    private function page(mixed $value): int
    {
        $page = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 10000]]);

        return $page === false ? 1 : (int) $page;
    }
}
