<?php

declare(strict_types=1);

namespace Tests;

use App\Preferences\PersonalRating;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PersonalRatingTest extends TestCase
{
    #[DataProvider('validRatings')]
    public function testParsesEmptyAndIntegerScale(mixed $input, ?int $expected): void
    {
        self::assertSame($expected, PersonalRating::parse($input));
    }

    public static function validRatings(): array
    {
        return [[null,null],['',null],['1',1],['10',10],[1,1],[10,10]];
    }

    #[DataProvider('invalidRatings')]
    public function testRejectsAnythingOutsideExactIntegerScale(mixed $input): void
    {
        $this->expectException(\InvalidArgumentException::class);
        PersonalRating::parse($input);
    }

    public static function invalidRatings(): array
    {
        return [['0'],['11'],['-1'],['7.5'],['abc'],['1<script>'],[0],[11],[7.5],['01']];
    }
}
