<?php

declare(strict_types=1);

namespace AdeMS\Dapur\Dashboard\Test;

use AdeMS\Dapur\Dashboard\DaftarUser;
use AdeMS\Dapur\Dashboard\Middleware\WajibMasukMiddleware;
use AdeMS\Dapur\Dashboard\Pengaturan;
use AdeMS\Dapur\Dashboard\Sesi;
use AdeMS\Dapur\Dashboard\User;
use Laminas\Db\Adapter\Adapter;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\ServerRequest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

require_once __DIR__ . '/BantuanUji.php';

final class WajibMasukTest extends TestCase
{
    private Adapter $db;
    private DaftarUser $daftar;
    private SesiLarik $sesi;
    private int $id;
    private ?ServerRequestInterface $diteruskan = null;

    protected function setUp(): void
    {
        $this->db     = DatabaseUji::baru();
        $this->daftar = new DaftarUser($this->db);
        $this->sesi   = new SesiLarik();
        $this->id     = $this->daftar->buat('admin@contoh.com', 'Admin', 'kata-sandi-yang-panjang');
        $this->daftar->tambahAkses($this->id, 'dashboard');
    }

    private function proses(string $lingkup = 'dashboard', string $alamat = '/', string $metode = 'GET'): ResponseInterface
    {
        $uji        = $this;
        $middleware = new WajibMasukMiddleware(
            $this->daftar,
            new Pengaturan(['alamat_dasar' => '/', 'batas_diam' => 3600]),
            $lingkup,
        );

        return $middleware->process(
            (new ServerRequest([], [], $alamat, $metode))->withAttribute(Sesi::class, $this->sesi),
            new class ($uji) implements RequestHandlerInterface {
                public function __construct(private readonly WajibMasukTest $uji)
                {
                }

                public function handle(ServerRequestInterface $request): ResponseInterface
                {
                    $this->uji->catat($request);

                    return new EmptyResponse(200);
                }
            },
        );
    }

    public function catat(ServerRequestInterface $request): void
    {
        $this->diteruskan = $request;
    }

    private function login(int $terakhir = 0): void
    {
        $this->sesi->simpan(WajibMasukMiddleware::SESI_USER, $this->id);
        $this->sesi->simpan(WajibMasukMiddleware::SESI_TERAKHIR, $terakhir ?: time());
    }

    public function testBelumLoginDiarahkanKeMasuk(): void
    {
        $response = $this->proses();

        self::assertSame(302, $response->getStatusCode());
        self::assertSame('/masuk', $response->getHeaderLine('Location'));
        self::assertNull($this->diteruskan);
    }

    public function testSudahLoginDiteruskanDenganUser(): void
    {
        $this->login();

        self::assertSame(200, $this->proses()->getStatusCode());
        self::assertInstanceOf(User::class, $this->diteruskan?->getAttribute(User::class));
    }

    public function testTerlaluLamaDiamDikeluarkan(): void
    {
        $this->login(time() - 3601);

        self::assertSame(302, $this->proses()->getStatusCode());
        self::assertNull($this->sesi->ambil(WajibMasukMiddleware::SESI_USER));
        self::assertSame(1, $this->sesi->gantiId);
    }

    public function testUserDinonaktifkanLangsungDikeluarkan(): void
    {
        $this->login();
        $this->db->query('UPDATE adems_user SET aktif = 0 WHERE id = ?', [$this->id]);

        self::assertSame(302, $this->proses()->getStatusCode());
        self::assertNull($this->sesi->ambil(WajibMasukMiddleware::SESI_USER));
        self::assertNull($this->sesi->ambil(WajibMasukMiddleware::SESI_TERAKHIR));
        self::assertSame(1, $this->sesi->gantiId);
    }

    public function testAksesDicabutLangsungDikeluarkan(): void
    {
        $this->login();
        $this->db->query('DELETE FROM adems_user_akses WHERE user_id = ?', [$this->id]);

        self::assertSame(302, $this->proses()->getStatusCode());
    }

    public function testSesiLingkupLainTidakBerlaku(): void
    {
        $this->login();

        self::assertSame(302, $this->proses('blog.contoh.com')->getStatusCode());
    }

    public function testHalamanYangDimintaDisimpanUntukSetelahLogin(): void
    {
        $this->proses('dashboard', 'https://contoh.com/website?urut=nama');

        self::assertSame('/website?urut=nama', $this->sesi->ambil(WajibMasukMiddleware::SESI_TUJUAN));
    }

    public function testTujuanTetapTersimpanSaatSesiKedaluwarsa(): void
    {
        $this->login(time() - 3601);

        $this->proses('dashboard', '/website');

        self::assertSame('/website', $this->sesi->ambil(WajibMasukMiddleware::SESI_TUJUAN));
    }

    public function testPermintaanSelainGetTidakDisimpan(): void
    {
        $this->proses('dashboard', '/website', 'POST');

        self::assertNull($this->sesi->ambil(WajibMasukMiddleware::SESI_TUJUAN));
    }

    /** @return iterable<string, array{string, string, bool}> */
    public static function contohTujuan(): iterable
    {
        yield 'halaman dashboard utama'     => ['/', '/website', true];
        yield 'dengan query'                => ['/', '/website?urut=nama', true];
        yield 'host lain'                   => ['/', '//jahat.contoh', false];
        yield 'host lain lewat backslash'   => ['/', '/\\jahat.contoh', false];
        yield 'alamat lengkap'              => ['/', 'https://jahat.contoh/', false];
        yield 'karakter kendali'            => ['/', "/website\r\nX: y", false];
        yield 'halaman masuk'               => ['/', '/masuk', false];
        yield 'halaman keluar'              => ['/', '/keluar', false];
        yield 'dashboard website'           => ['/dashboard', '/dashboard/website', true];
        yield 'beranda dashboard website'   => ['/dashboard', '/dashboard', true];
        yield 'di luar dashboard website'   => ['/dashboard', '/halaman-publik', false];
        yield 'awalan mirip'                => ['/dashboard', '/dashboard-palsu', false];
        yield 'masuk dashboard website'     => ['/dashboard', '/dashboard/masuk', false];
    }

    #[DataProvider('contohTujuan')]
    public function testTujuanSah(string $dasar, string $tujuan, bool $harapan): void
    {
        self::assertSame($harapan, WajibMasukMiddleware::tujuanSah($tujuan, new Pengaturan(['alamat_dasar' => $dasar])));
    }
}
