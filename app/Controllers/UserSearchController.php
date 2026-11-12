<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Request;
use App\Core\Response;
use App\Core\View;
use App\Social\PublicProfileReader;

final class UserSearchController
{
    public function __construct(
        private readonly View $view,
        private readonly Auth $auth,
        private readonly Csrf $csrf,
        private readonly PublicProfileReader $profiles,
    ) {
    }

    public function index(Request $request): Response
    {
        $raw = $request->query('q', '');
        $query = is_string($raw) ? trim($raw) : '';
        $page = $this->page($request->query('page', 1));
        $error = $query === '' ? null : $this->validateQuery($query);
        $results = $query !== '' && $error === null ? $this->profiles->search($query, $page, 20) : null;
        return Response::html($this->view->render('pages/user-search', [
            'title'=>'Encontrar pessoas · Flickary','currentRoute'=>'search','auth'=>$this->auth,'csrf'=>$this->csrf,
            'query'=>$query,'validationError'=>$error,'results'=>$results,
        ]), $error === null ? 200 : 422);
    }

    private function validateQuery(string $query): ?string
    {
        if (!mb_check_encoding($query, 'UTF-8') || preg_match('/[\x00-\x1F\x7F]/u', $query) === 1) {
            return 'A busca contém caracteres que não podem ser utilizados.';
        }
        $length = mb_strlen($query);
        if ($length < 2) { return 'Digite pelo menos 2 caracteres para pesquisar.'; }
        if ($length > 100) { return 'A busca deve ter no máximo 100 caracteres.'; }
        return null;
    }

    private function page(mixed $value): int
    {
        $page=filter_var($value,FILTER_VALIDATE_INT,['options'=>['min_range'=>1,'max_range'=>10000]]);
        return $page === false ? 1 : (int) $page;
    }
}
