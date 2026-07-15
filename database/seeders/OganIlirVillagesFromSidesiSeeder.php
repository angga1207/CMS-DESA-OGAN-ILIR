<?php

namespace Database\Seeders;

use App\Services\SidesiClient;
use App\Services\VillageProvisioner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OganIlirVillagesFromSidesiSeeder extends Seeder
{
    public function run(): void
    {
        $payload = app(SidesiClient::class)->skpd();
        $rows = collect($payload['data'] ?? [])->filter(fn($row): bool => is_array($row));

        // only take Desa Tanjung Lubuk, Desa Tanjung Dayang Utara, Desa Meranjat Ilir
        $rows = $rows->filter(fn(array $row): bool => in_array($this->value($row['nama_skpd'] ?? null), ['DESA TANJUNG LUBUK', 'DESA TANJUNG DAYANG UTARA', 'DESA MERANJAT ILIR'], true));

        $authorId = DB::table('users')->where('role', 'developer')->orderBy('id')->value('id');
        $provisioned = 0;

        $districtsBySkpdId = $rows
            ->filter(fn(array $row): bool => $this->clean($row['jenis_skpd'] ?? null) === 'kecamatan')
            ->mapWithKeys(fn(array $row): array => [
                (string) ($row['id_skpd'] ?? '') => $this->districtName($row['nama_skpd'] ?? null),
            ])
            ->filter()
            ->all();

        $districtsByCode = $rows
            ->filter(fn(array $row): bool => $this->clean($row['jenis_skpd'] ?? null) === 'kecamatan')
            ->mapWithKeys(fn(array $row): array => [
                (string) ($row['id_kecamatan'] ?? '') => $this->districtName($row['nama_skpd'] ?? null),
            ])
            ->filter()
            ->all();

        $villages = $rows
            ->filter(fn(array $row): bool => $this->clean($row['jenis_skpd'] ?? null) === 'desa')
            ->filter(fn(array $row): bool => filled($this->value($row['id_desa'] ?? null)))
            ->sortBy('nama_skpd')
            ->values();

        $villages->each(function (array $row) use ($districtsBySkpdId, $districtsByCode, $authorId, &$provisioned): void {
            $sidesiVillageId = (string) $this->value($row['id_desa'] ?? null);
            $name = $this->villageName($row['nama_skpd'] ?? null);
            $district = $districtsBySkpdId[(string) ($row['id_skpd_induk'] ?? '')]
                ?? $districtsByCode[(string) ($row['id_kecamatan'] ?? '')]
                ?? null;

            if ($name === '' || $sidesiVillageId === '') {
                return;
            }

            $payload = [
                'name' => $name,
                'slug' => $this->slug($name, $district, $sidesiVillageId),
                'district' => $district,
                'regency' => 'Ogan Ilir',
                'province' => 'Sumatera Selatan',
                'address' => $this->address($row['alamat_skpd'] ?? null, $district),
                'phone' => $this->value($row['telepon_skpd'] ?? null),
                'email' => $this->email($row['email_skpd'] ?? null),
                'website_url' => $this->url($row['website'] ?? null),
                'latitude' => $this->decimal($row['latitude'] ?? null),
                'longitude' => $this->decimal($row['longitude'] ?? null),
                'description' => $this->description($name, $district),
                'welcome_message' => "Selamat datang di website resmi {$name}.",
                'updated_at' => now(),
            ];

            $existingId = DB::table('villages')
                ->where('sidesi_village_id', $sidesiVillageId)
                ->value('id');

            if ($existingId) {
                DB::table('villages')
                    ->where('id', $existingId)
                    ->update($payload);

                $this->provisionIfNeeded((int) $existingId, $authorId === null ? null : (int) $authorId, $provisioned);

                return;
            }

            $villageId = DB::table('villages')->insertGetId([
                ...$payload,
                'sidesi_village_id' => $sidesiVillageId,
                'analytics_key' => Str::random(64),
                'created_at' => now(),
            ]);

            $this->provisionIfNeeded((int) $villageId, $authorId === null ? null : (int) $authorId, $provisioned);
        });

        $this->command?->info("Sinkronisasi {$villages->count()} desa Ogan Ilir dari SIDESI selesai. Provision: {$provisioned} desa.");
    }

    private function provisionIfNeeded(int $villageId, ?int $authorId, int &$provisioned): void
    {
        $hasProvisionedContent = DB::table('navigation_menus')
            ->where('village_id', $villageId)
            ->exists();

        if ($hasProvisionedContent) {
            return;
        }

        app(VillageProvisioner::class)->provision($villageId, $authorId);
        $provisioned++;
    }

    private function villageName(mixed $value): string
    {
        $name = $this->value($value);

        if ($name === null) {
            return '';
        }

        return Str::of($name)
            ->lower()
            ->title()
            ->replaceMatches('/^Desa\s+/i', '')
            ->prepend('Desa ')
            ->trim()
            ->toString();
    }

    private function districtName(mixed $value): ?string
    {
        $name = $this->value($value);

        if ($name === null) {
            return null;
        }

        return Str::of($name)
            ->lower()
            ->title()
            ->replaceMatches('/^Kecamatan\s+/i', '')
            ->trim()
            ->toString();
    }

    private function slug(string $name, ?string $district, string $sidesiVillageId): string
    {
        $existing = DB::table('villages')
            ->where('sidesi_village_id', $sidesiVillageId)
            ->value('slug');

        if (is_string($existing) && $existing !== '') {
            return $existing;
        }

        $base = Str::slug($name);
        $candidate = $base;

        if (! DB::table('villages')->where('slug', $candidate)->exists()) {
            return $candidate;
        }

        $candidate = trim($base . '-' . Str::slug((string) $district), '-');

        if ($candidate !== '' && ! DB::table('villages')->where('slug', $candidate)->exists()) {
            return $candidate;
        }

        return "{$base}-{$sidesiVillageId}";
    }

    private function address(mixed $value, ?string $district): ?string
    {
        $address = $this->value($value);

        if ($address !== null) {
            return $address;
        }

        if ($district === null || $district === '') {
            return 'Kabupaten Ogan Ilir, Sumatera Selatan';
        }

        return "Kecamatan {$district}, Kabupaten Ogan Ilir, Sumatera Selatan";
    }

    private function description(string $name, ?string $district): string
    {
        if ($district === null || $district === '') {
            return "Portal informasi publik {$name}, Kabupaten Ogan Ilir.";
        }

        return "Portal informasi publik {$name}, Kecamatan {$district}, Kabupaten Ogan Ilir.";
    }

    private function email(mixed $value): ?string
    {
        $email = $this->value($value);

        return $email !== null && filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null;
    }

    private function url(mixed $value): ?string
    {
        $url = $this->value($value);

        if ($url === null) {
            return null;
        }

        if (! preg_match('/^https?:\/\//i', $url)) {
            $url = "https://{$url}";
        }

        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    private function decimal(mixed $value): ?float
    {
        $value = $this->value($value);

        return $value !== null && is_numeric($value) ? (float) $value : null;
    }

    private function clean(mixed $value): string
    {
        return Str::of((string) $value)->lower()->trim()->toString();
    }

    private function value(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' || $value === '-' ? null : $value;
    }
}
