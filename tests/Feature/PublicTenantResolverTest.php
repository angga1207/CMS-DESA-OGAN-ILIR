<?php

namespace Tests\Feature;

use App\Services\TenantResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PublicTenantResolverTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_resolves_the_public_tenant_from_a_normalized_hostname(): void
    {
        $villageId = $this->createVillage('Desa Satu', 'desa-satu', 'desa-satu.example.test');
        $this->createVillage('Desa Dua', 'desa-dua', 'desa-dua.example.test');

        $this->getJson('/api/sites/resolve?hostname=WWW.DESA-SATU.EXAMPLE.TEST%3A443')
            ->assertOk()
            ->assertJsonPath('data.id', $villageId)
            ->assertJsonPath('data.slug', 'desa-satu')
            ->assertJsonPath('data.hostname', 'desa-satu.example.test');
    }

    public function test_it_does_not_fall_back_to_another_village_for_an_unknown_hostname(): void
    {
        $this->createVillage('Desa Satu', 'desa-satu', 'desa-satu.example.test');

        $this->getJson('/api/sites/resolve?hostname=unknown.example.test')
            ->assertNotFound();
    }

    public function test_resolver_cache_can_be_invalidated_after_a_hostname_change(): void
    {
        $villageId = $this->createVillage('Desa Satu', 'desa-satu', 'lama.example.test');
        $resolver = app(TenantResolver::class);

        $this->assertSame($villageId, (int) $resolver->resolve('lama.example.test')->id);

        DB::table('villages')->where('id', $villageId)->update([
            'public_hostname' => 'baru.example.test',
        ]);
        $resolver->forget('lama.example.test');
        $resolver->forget('baru.example.test');

        $this->assertNull($resolver->resolve('lama.example.test'));
        $this->assertSame($villageId, (int) $resolver->resolve('baru.example.test')->id);
    }

    private function createVillage(string $name, string $slug, string $hostname): int
    {
        return (int) DB::table('villages')->insertGetId([
            'name' => $name,
            'slug' => $slug,
            'public_hostname' => $hostname,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
