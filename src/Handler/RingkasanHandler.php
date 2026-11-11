<?php

declare(strict_types=1);

namespace AdeMS\Dapur\Dashboard\Handler;

use AdeMS\Dapur\Dashboard\Tampilan;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class RingkasanHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly Tampilan $tampilan,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return $this->tampilan->halaman('dashboard::ringkasan', $request);
    }
}
