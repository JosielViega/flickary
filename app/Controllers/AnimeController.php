<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Integrations\Tmdb\TmdbAnimeClassifier;
use App\Integrations\Tmdb\TmdbCatalog;
use App\Integrations\Tmdb\TmdbException;
use App\Integrations\Tmdb\TmdbImageSizeSelector;
use App\Integrations\Tmdb\TmdbImageUrlBuilder;
use App\Integrations\Tmdb\TmdbMedia;

final class AnimeController
{
    private const TYPES = ['todos', 'series', 'filmes'];
    private const SUMMARY_LIMIT = 8;
    private const MAX_PAGE = 500;
    private readonly TmdbAnimeClassifier $classifier;

    public function __construct(
        private readonly View $view,
        private readonly TmdbCatalog $tmdb,
        private readonly ?Auth $auth = null,
        private readonly ?Csrf $csrf = null,
        ?TmdbAnimeClassifier $classifier = null,
    ) {
        $this->classifier = $classifier ?? new TmdbAnimeClassifier();
    }

    public function index(Request $request): Response
    {
        $type = $this->type($request->query('tipo', 'todos'));
        $page = $type === 'todos' ? 1 : $this->page($request->query('page', 1));

        if ($type === 'todos') {
            $sections = [
                $this->section('Séries Anime', 'series', 1, true),
                $this->section('Filmes Anime', 'filmes', 1, true),
            ];
            $pagination = null;
        } else {
            $section = $this->section($type === 'series' ? 'Séries Anime' : 'Filmes Anime', $type, $page, false);
            $sections = [$section];
            $pagination = $this->pagination($type, $page, $section);
        }

        return Response::html($this->view->render('pages/anime', [
            'title' => 'Anime — Flickary',
            'currentRoute' => 'search',
            'auth' => $this->auth,
            'csrf' => $this->csrf,
            'searchQuery' => '',
            'type' => $type,
            'sections' => $this->attachPosterUrls($sections),
            'pagination' => $pagination,
        ]));
    }

    private function type(mixed $value): string
    {
        return is_string($value) && in_array($value, self::TYPES, true) ? $value : 'todos';
    }

    private function page(mixed $value): int
    {
        $page = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => self::MAX_PAGE]]);
        return $page === false ? 1 : (int) $page;
    }

    private function section(string $title, string $type, int $page, bool $summary): array
    {
        try {
            $result = $type === 'filmes'
                ? $this->tmdb->discoverAnimeMovies($page)
                : $this->tmdb->discoverAnimeSeries($page);
        } catch (TmdbException $exception) {
            return ['title'=>$title,'type'=>$type,'items'=>[],'page'=>$page,'total_pages'=>0,'total_results'=>0,'error'=>$this->publicError($exception),'view_all_url'=>null];
        }

        $media = array_values(array_filter(
            $result['results'],
            fn (TmdbMedia $item): bool => $this->classifier->media($item),
        ));
        if ($summary) {
            $media = array_slice($media, 0, self::SUMMARY_LIMIT);
        }

        return [
            'title' => $title,
            'type' => $type,
            'items' => array_map(static fn (TmdbMedia $item): array => ['media'=>$item,'poster_url'=>null,'is_anime'=>true], $media),
            'page' => $result['page'],
            'total_pages' => $result['total_pages'],
            'total_results' => $result['total_results'],
            'error' => null,
            'view_all_url' => $summary ? $this->url($type, 1, false) : null,
        ];
    }

    private function attachPosterUrls(array $sections): array
    {
        $hasPoster = false;
        foreach ($sections as $section) {
            foreach ($section['items'] as $item) {
                if ($item['media']->posterPath !== null) {
                    $hasPoster = true;
                    break 2;
                }
            }
        }
        if (!$hasPoster) {
            return $sections;
        }

        try {
            $configuration = $this->tmdb->configuration();
        } catch (TmdbException) {
            return $sections;
        }
        $size = (new TmdbImageSizeSelector())->poster($configuration->posterSizes);
        if ($size === null) {
            return $sections;
        }
        $builder = new TmdbImageUrlBuilder($configuration);
        foreach ($sections as &$section) {
            foreach ($section['items'] as &$item) {
                $item['poster_url'] = $builder->posterUrl($item['media']->posterPath, $size);
            }
            unset($item);
        }
        unset($section);
        return $sections;
    }

    private function pagination(string $type, int $requestedPage, array $section): ?array
    {
        if ($section['error'] !== null) {
            return null;
        }
        $lastPage = min(self::MAX_PAGE, max(1, $section['total_pages']));
        if ($requestedPage > $lastPage) {
            return ['current'=>$requestedPage,'total'=>$lastPage,'invalid'=>true,'previous_url'=>null,'next_url'=>null];
        }
        if ($section['total_pages'] <= 1) {
            return null;
        }
        return [
            'current'=>$requestedPage,'total'=>$lastPage,'invalid'=>false,
            'previous_url'=>$requestedPage > 1 ? $this->url($type, $requestedPage - 1) : null,
            'next_url'=>$requestedPage < $lastPage ? $this->url($type, $requestedPage + 1) : null,
        ];
    }

    private function url(string $type, int $page, bool $includePage = true): string
    {
        $parameters = ['tipo' => $type];
        if ($includePage && $page > 1) {
            $parameters['page'] = $page;
        }
        return '/anime?' . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
    }

    private function publicError(TmdbException $exception): string
    {
        return match ($exception->category) {
            'not_configured' => 'A descoberta de Anime não está disponível neste ambiente.',
            'rate_limited' => 'Muitas consultas foram realizadas em pouco tempo. Tente novamente em instantes.',
            default => 'Não foi possível consultar o catálogo Anime agora. Tente novamente mais tarde.',
        };
    }
}
