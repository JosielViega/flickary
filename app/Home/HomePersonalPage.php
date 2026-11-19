<?php

declare(strict_types=1);

namespace App\Home;

final readonly class HomePersonalPage
{
    public function __construct(
        public array $watching,
        public array $agenda,
        public array $history,
        public array $watchingPosterUrls,
        public array $watchingBackdropUrls,
        public array $agendaPosterUrls,
        public array $historyPosterUrls,
    ) {
    }
}
