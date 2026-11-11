<?php

declare(strict_types=1);

namespace AdeMS\Dapur\Dashboard;

/** Token CSRF per session. Nama field pada form: TokenCsrf::FIELD. */
final class TokenCsrf
{
    public const FIELD = '_csrf';

    private const KUNCI = 'dashboard.csrf';

    public function __construct(
        private readonly Sesi $sesi,
    ) {
    }

    public function token(): string
    {
        $token = $this->sesi->ambil(self::KUNCI);
        if (! is_string($token) || $token === '') {
            $token = bin2hex(random_bytes(32));
            $this->sesi->simpan(self::KUNCI, $token);
        }

        return $token;
    }

    public function sah(mixed $kiriman): bool
    {
        $token = $this->sesi->ambil(self::KUNCI);

        return is_string($token) && $token !== '' && is_string($kiriman) && hash_equals($token, $kiriman);
    }

    /** Membuang token lama, dipanggil saat status login berubah. */
    public function baru(): void
    {
        $this->sesi->hapus(self::KUNCI);
    }
}
