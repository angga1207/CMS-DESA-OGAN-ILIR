<?php

namespace Tests\Feature;

use Tests\TestCase;

class ErrorPageDesignTest extends TestCase
{
    public function test_custom_error_pages_render_the_cms_theme(): void
    {
        foreach ([403, 404, 419, 429, 500, 503] as $status) {
            $html = view("errors.{$status}")->render();

            $this->assertStringContainsString((string) $status, $html);
            $this->assertStringContainsString('CMS Desa', $html);
            $this->assertStringContainsString('ogan-ilir-logo.gif', $html);
            $this->assertStringContainsString('Masuk ke CMS', $html);
            $this->assertStringContainsString('Halaman sebelumnya', $html);
        }
    }

    public function test_unknown_cms_url_uses_the_custom_not_found_page(): void
    {
        $this->get('/admin/alamat-yang-tidak-ada')
            ->assertNotFound()
            ->assertSeeText('Halaman tidak tersedia')
            ->assertSeeText('Alamat tidak ditemukan');
    }
}
