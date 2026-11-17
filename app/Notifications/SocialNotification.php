<?php

declare(strict_types=1);

namespace App\Notifications;

final readonly class SocialNotification
{
    public function __construct(
        public int $id,
        public SocialNotificationType $type,
        public int $actorUserId,
        public string $actorUsername,
        public string $actorDisplayName,
        public ?string $actorAvatarUrl,
        public ?int $historyId,
        public ?int $commentId,
        public ?string $readAt,
        public string $createdAt,
    ) {
    }

    public function destination(): string
    {
        return match ($this->type) {
            SocialNotificationType::FollowRequest => '/perfil/solicitacoes',
            SocialNotificationType::NewFollower,
            SocialNotificationType::FollowAccepted => '/u/' . rawurlencode($this->actorUsername),
            SocialNotificationType::ActivityLike,
            SocialNotificationType::ActivityComment => '/social/atividades/' . $this->historyId,
        };
    }
}
