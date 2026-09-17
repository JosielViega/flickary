<?php

declare(strict_types=1);

namespace App\Authentication;

interface ExternalIdentityLinker
{
    public const LINKED = 'linked';
    public const ALREADY_LINKED = 'already_linked';
    public const CONFLICT = 'conflict';

    public function link(int $userId, ExternalIdentity $identity): string;
}
