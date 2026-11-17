<?php

declare(strict_types=1);

namespace App\Social;

interface ViewerAwarePublicProfileReader
{
    public function searchForViewer(string $query, int $page, int $perPage, ?int $viewerUserId): PublicProfilePage;
}
