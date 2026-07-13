<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

final class SidesiClient
{
    public function skpd(): array
    {
        return $this->get('noc/get_skpd');
    }

    public function facilityCategories(): array
    {
        return $this->get('listing/get_kategori_listing');
    }

    public function assistanceCategories(): array
    {
        return $this->get('rtangga_miskin/get_bantuan');
    }

    public function facilities(string $villageId, int $categoryId): array
    {
        return $this->get('listing/get_listing', [
            'desa' => $villageId,
            'id_kategori_listing' => $categoryId,
        ]);
    }

    public function facilityDetail(int $listingId): array
    {
        return $this->get('listing/get_detail_listing', [
            'id_listing' => $listingId,
        ]);
    }

    public function assistanceRecipients(string $villageId, int $assistanceId): array
    {
        return $this->get('rtangga_miskin/bantuan_keluarga', [
            'id_desa' => $villageId,
            'bantuan' => $assistanceId,
        ]);
    }

    public function todayAttendance(string $villageId): array
    {
        return $this->get('website/absensi/hari_ini', [
            'id_desa' => $villageId,
        ]);
    }

    public function villageBudget(string $villageId, int $year): array
    {
        return $this->get('website/transparansi_anggaran/apbdesa', [
            'id_desa' => $villageId,
            'tahun' => $year,
        ]);
    }

    public function populationStatistics(string $villageId): array
    {
        return $this->get('website/penduduk/statistik_penduduk', ['id_desa' => $villageId]);
    }

    public function occupationStatistics(string $villageId): array
    {
        return $this->get('website/penduduk/jumlah_persentase_menurut_jenis_pekerjaan', ['id_desa' => $villageId]);
    }

    public function educationStatistics(string $villageId): array
    {
        return $this->get('website/penduduk/jumlah_persentase_menurut_pendidikan', ['id_desa' => $villageId]);
    }

    public function ageStatistics(string $villageId): array
    {
        return $this->get('website/penduduk/jumlah_persentase_menurut_usia', ['id_desa' => $villageId]);
    }

    public function employeePhoto(string $url): Response
    {
        $expectedHost = parse_url((string) config('services.sidesi.base_url'), PHP_URL_HOST);
        $host = parse_url($url, PHP_URL_HOST);
        $path = parse_url($url, PHP_URL_PATH);

        if ($host !== $expectedHost || ! is_string($path) || ! str_starts_with($path, '/data/foto/pegawai/')) {
            throw new InvalidArgumentException('URL foto pegawai SIDESI tidak valid.');
        }

        try {
            $response = $this->request(acceptJson: false)->get($url);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('Foto pegawai SIDESI tidak dapat dihubungi.', previous: $exception);
        }

        $contentType = strtolower((string) $response->header('Content-Type'));

        if (! $response->successful() || ! str_starts_with($contentType, 'image/')) {
            throw new RuntimeException('Foto pegawai SIDESI tidak tersedia.');
        }

        return $response;
    }

    private function get(string $path, array $query = []): array
    {
        try {
            $response = $this->request()->get($path, $query);
        } catch (ConnectionException $exception) {
            throw new RuntimeException('SIDESI tidak dapat dihubungi.', previous: $exception);
        }

        return $this->decode($response);
    }

    private function request(bool $acceptJson = true): PendingRequest
    {
        $appKey = (string) config('services.sidesi.app_key');

        if ($appKey === '') {
            throw new RuntimeException('SIDESI_APP_KEY belum dikonfigurasi.');
        }

        $request = Http::baseUrl(rtrim((string) config('services.sidesi.base_url'), '/'))
            ->withHeaders([
                'App-Key' => $appKey,
                'User-Agent' => (string) config('services.sidesi.user_agent', 'PostmanRuntime/7.51.1'),
            ])
            ->timeout((int) config('services.sidesi.timeout', 15))
            ->retry(2, 250, throw: false);

        return $acceptJson ? $request->acceptJson() : $request->accept('image/*');
    }

    private function decode(Response $response): array
    {
        if (! $response->successful()) {
            throw new RuntimeException("SIDESI mengembalikan HTTP {$response->status()}.");
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            throw new RuntimeException('Respons SIDESI bukan JSON yang valid.');
        }

        return $payload;
    }
}
