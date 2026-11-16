<?php

declare(strict_types=1);

namespace App\Social;

final readonly class SocialActivity
{
    public function __construct(
        public int $historyId,
        public int $actorUserId,
        public string $actorUsername,
        public string $actorDisplayName,
        public ?string $actorAvatarUrl,
        public string $source,
        public string $entryType,
        public int $sourceId,
        public ?int $seasonNumber,
        public ?int $episodeNumber,
        public string $title,
        public ?string $episodeTitle,
        public ?string $contentDate,
        public ?string $posterPath,
        public ?bool $isAnime,
        public string $watchedOn,
        public string $createdAt,
        public int $likeCount,
        public bool $viewerLiked,
        public int $commentCount,
    ) {
    }
}
