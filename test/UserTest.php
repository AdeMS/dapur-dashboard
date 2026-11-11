<?php

declare(strict_types=1);

namespace AdeMS\Dapur\Dashboard\Test;

use AdeMS\Dapur\Dashboard\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class UserTest extends TestCase
{
    /** @return iterable<string, array{string, string, string}> */
    public static function contohInisial(): iterable
    {
        yield 'nama depan dan belakang' => ['Ade M Saragih', 'ade@contoh.com', 'AS'];
        yield 'dua kata'                => ['budi santoso', 'budi@contoh.com', 'BS'];
        yield 'satu kata'               => ['Ade', 'ade@contoh.com', 'A'];
        yield 'spasi berlebih'          => ['  Siti   Nur  ', 'siti@contoh.com', 'SN'];
        yield 'huruf non-ASCII'         => ['élise ñandú', 'elise@contoh.com', 'ÉÑ'];
        yield 'nama kosong'             => ['', 'admin@contoh.com', 'A'];
    }

    #[DataProvider('contohInisial')]
    public function testInisial(string $nama, string $email, string $harapan): void
    {
        self::assertSame($harapan, (new User(1, $email, $nama, '', true))->inisial());
    }
}
