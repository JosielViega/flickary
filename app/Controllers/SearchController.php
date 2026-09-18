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
use App\Integrations\Tmdb\TmdbImageUrlBuilder;
use App\Integrations\Tmdb\TmdbImageSizeSelector;
use App\Integrations\Tmdb\TmdbMedia;

final class SearchController
{
    private const TYPES = ['todos', 'filmes', 'series'];
    private const SUMMARY_LIMIT = 8;
    private const MAX_PAGE = 500;

    public function __construct(
        private readonly View $view,
        private readonly TmdbCatalog $tmdb,
        private readonly ?Auth $auth = null,
        private readonly ?Csrf $csrf = null,
    ) {
    }

    public function index(Request $request): Response
    {
        $rawQuery = $request->query('q', '');
        $query = is_string($rawQuery) ? trim($rawQuery) : '';
        $type = $this->type($request->query('tipo', 'todos'));
        $page = $type === 'todos' ? 1 : $this->page($request->query('page', 1));
        $data = $this->baseViewData($query, $type);

        if ($query === '') {
            return $this->render($data);
        }

        $validationError = $this->validateQuery($query);
        if ($validationError !== null) {
            return $this->render([...$data, 'validationError' => $validationError], 422);
        }

        $data['searched'] = true;
        if ($type === 'todos') {
            $data['sections'] = [
                $this->searchSection('Filmes', 'filmes', $query, 1, true),
                $this->searchSection('Séries', 'series', $query, 1, true),
            ];
        } else {
            $section = $this->searchSection(
                $type === 'filmes' ? 'Filmes' : 'Séries',
                $type,
                $query,
                $page,
                false,
            );
            $data['sections'] = [$section];
            $data['pagination'] = $this->pagination($query, $type, $page, $section);
        }

        $data['sections'] = $this->attachPosterUrls($data['sections']);

        return $this->render($data);
    }

    private function baseViewData(string $query, string $type): array
    {
        return [
            'title' => 'Buscar — Flickary',
            'currentRoute' => 'search',
            'auth' => $this->auth,
            'csrf' => $this->csrf,
            'searchQuery' => $query,
            'query' => $query,
            'type' => $type,
            'searched' => false,
            'validationError' => null,
            'sections' => [],
            'pagination' => null,
        ];
    }

    private function render(array $data, int $status = 200): Response
    {
        return Response::html($this->view->render('pages/search', $data), $status);
    }

    private function validateQuery(string $query): ?string
    {
        if (!mb_check_encoding($query, 'UTF-8') || preg_match('/[\x00-\x1F\x7F]/u', $query) === 1) {
            return 'A busca contém caracteres que não podem ser utilizados.';
        }

        $length = mb_strlen($query);
        if ($length < 2) {
            return 'Digite pelo menos 2 caracteres para pesquisar.';
        }
        if ($length > 120) {
            return 'A busca deve ter no máximo 120 caracteres.';
        }

        return null;
    }

    private function type(mixed $value): string
    {
        return is_string($value) && in_array($value, self::TYPES, true) ? $value : 'todos';
    }

    private function page(mixed $value): int
    {
        $page = filter_var($value, FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => self::MAX_PAGE],
        ]);
        return $page === false ? 1 : (int) $page;
    }

    private function searchSection(string $title, string $type, string $query, int $page, bool $summary): array
    {
        try {
            $result = $type === 'filmes'
                ? $this->tmdb->searchMovies($query, $page)
                : $this->tmdb->searchSeries($query, $page);
        } catch (TmdbException $exception) {
            return [
                'title' => $title,
                'type' => $type,
                'items' => [],
                'page' => $page,
                'total_pages' => 0,
                'total_results' => 0,
                'error' => $this->publicError($exception),
                'view_all_url' => null,
            ];
        }

        $items = $result['results'];
        $viewAllUrl = null;
        if ($summary && ($result['total_results'] > self::SUMMARY_LIMIT || count($items) > self::SUMMARY_LIMIT)) {
            $items = array_slice($items, 0, self::SUMMARY_LIMIT);
            $viewAllUrl = $this->url($query, $type, 1, false);
        }

        return [
            'title' => $title,
            'type' => $type,
            'items' => array_map(static fn (TmdbMedia $media): array => [
                'media' => $media,
                'poster_url' => null,
            ], $items),
            'page' => $result['page'],
            'total_pages' => $result['total_pages'],
            'total_results' => $result['total_results'],
            'error' => null,
            'view_all_url' => $viewAllUrl,
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

    private function pagination(string $query, string $type, int $requestedPage, array $section): ?array
    {
        if ($section['error'] !== null) {
            return null;
        }

        $lastPage = min(self::MAX_PAGE, max(1, $section['total_pages']));
        if ($requestedPage > $lastPage) {
            return [
                'current' => $requestedPage,
                'total' => $lastPage,
                'invalid' => true,
                'previous_url' => null,
                'next_url' => null,
            ];
        }

        if ($section['total_pages'] <= 1) {
            return null;
        }

        return [
            'current' => $requestedPage,
            'total' => $lastPage,
            'invalid' => false,
            'previous_url' => $requestedPage > 1
                ? $this->url($query, $type, $requestedPage - 1)
                : null,
            'next_url' => $requestedPage < $lastPage
                ? $this->url($query, $type, $requestedPage + 1)
                : null,
        ];
    }

    private function url(string $query, string $type, int $page, bool $includePage = true): string
    {
        $parameters = ['q' => $query, 'tipo' => $type];
        if ($includePage && $page > 1) {
            $parameters['page'] = $page;
        }
        return '/buscar?' . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
    }

    private function publicError(TmdbException $exception): string
    {
        return match ($exception->category) {
            'not_configured' => 'A busca não está disponível neste ambiente.',
            'rate_limited' => 'Muitas buscas foram realizadas em pouco tempo. Tente novamente em instantes.',
            default => 'Não foi possível consultar o catálogo agora. Tente novamente mais tarde.',
        };
    }
}
