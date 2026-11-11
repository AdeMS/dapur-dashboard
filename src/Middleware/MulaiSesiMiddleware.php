<?php

declare(strict_types=1);

namespace AdeMS\Dapur\Dashboard\Middleware;

use AdeMS\Dapur\Dashboard\Sesi;
use AdeMS\Dapur\Dashboard\SesiPhp;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

/** Memulai session dengan pengaturan aman, hanya pada route dashboard. */
final class MulaiSesiMiddleware implements MiddlewareInterface
{
    private const NAMA_COOKIE = 'dapur_sesi';

    public function __construct(
        private readonly string $folderSesi,
    ) {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->siapkanFolder();

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.gc_maxlifetime', '86400');
        ini_set('session.gc_probability', '1');
        ini_set('session.gc_divisor', '100');

        session_save_path($this->folderSesi);
        session_name(self::NAMA_COOKIE);
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => '/',
            'secure'   => $request->getUri()->getScheme() === 'https',
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

        if (! session_start()) {
            throw new RuntimeException('Session dashboard tidak bisa dimulai.');
        }

        try {
            $response = $handler->handle($request->withAttribute(Sesi::class, new SesiPhp()));
        } finally {
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
        }

        return $response
            ->withHeader('X-Frame-Options', 'DENY')
            ->withHeader('X-Content-Type-Options', 'nosniff')
            ->withHeader('Referrer-Policy', 'same-origin');
    }

    private function siapkanFolder(): void
    {
        if (is_dir($this->folderSesi)) {
            return;
        }

        if (! @mkdir($this->folderSesi, 0770, true) && ! is_dir($this->folderSesi)) {
            throw new RuntimeException("Folder session tidak bisa dibuat: {$this->folderSesi}");
        }
    }
}
