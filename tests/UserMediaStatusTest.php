<?php

declare(strict_types=1);

namespace Tests;

use App\Media\UserMediaStatus;
use PHPUnit\Framework\TestCase;

final class UserMediaStatusTest extends TestCase
{
    public function testCentralizesFiveValidValuesAndPortugueseLabels(): void
    {
        self::assertSame([
            'planned' => 'Quero assistir',
            'watching' => 'Assistindo',
            'paused' => 'Pausado',
            'completed' => 'Concluído',
            'dropped' => 'Abandonado',
        ], UserMediaStatus::options());
        foreach (array_keys(UserMediaStatus::options()) as $status) {
            self::assertTrue(UserMediaStatus::isValid($status));
        }
        self::assertFalse(UserMediaStatus::isValid('favorite'));
        self::assertFalse(UserMediaStatus::isValid(null));
    }

    public function testInvalidLabelIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        UserMediaStatus::label('invalid');
    }
}
