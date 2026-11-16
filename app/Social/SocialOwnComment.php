<?php

declare(strict_types=1);

namespace App\Social;

final readonly class SocialOwnComment
{
    public function __construct(
        public int $id,
        public int $historyId,
        public string $body,
        public string $createdAt,
        public string $activityTitle,
        public string $entryType,
        public ?int $seasonNumber,
        public ?int $episodeNumber,
        public ?string $episodeTitle,
        public string $actorUsername,
    ) {
    }
}
