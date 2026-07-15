<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('content_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('village_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type', 40)->default('article');
            $table->timestamps();
            $table->index(['village_id', 'type', 'name']);
        });

        Schema::create('posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('village_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('content_categories')->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->string('featured_image_url')->nullable();
            $table->string('status', 40)->default('published');
            $table->timestamp('published_at')->nullable();
            $table->unsignedInteger('view_count')->default(0);
            $table->timestamps();
            $table->index(['village_id', 'status', 'published_at']);
            $table->index(['village_id', 'updated_at']);
            $table->index('category_id');
            $table->index('author_id');
        });

        Schema::create('post_revisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->foreignId('village_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('content_categories')->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('revision_author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('slug');
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->string('featured_image_url')->nullable();
            $table->string('status', 40)->default('published');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['post_id', 'created_at']);
            $table->index(['village_id', 'post_id']);
        });

        Schema::create('post_view_identities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('village_id')->constrained()->cascadeOnDelete();
            $table->foreignId('post_id')->constrained()->cascadeOnDelete();
            $table->date('view_date');
            $table->char('visitor_hash', 64);
            $table->timestamps();
            $table->unique(['village_id', 'post_id', 'view_date', 'visitor_hash'], 'post_view_identity_unique');
            $table->index(['village_id', 'view_date']);
        });

        Schema::create('business_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('village_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
            $table->index(['village_id', 'name']);
        });

        Schema::create('businesses', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('village_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('business_categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('owner_name')->nullable();
            $table->string('whatsapp', 40)->nullable();
            $table->string('instagram_url', 2048)->nullable();
            $table->string('facebook_url', 2048)->nullable();
            $table->string('tiktok_url', 2048)->nullable();
            $table->text('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('description')->nullable();
            $table->string('featured_image_url')->nullable();
            $table->unsignedInteger('worker_count')->default(0);
            $table->string('hamlet', 80)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['village_id', 'is_active', 'name']);
            $table->index(['village_id', 'updated_at']);
            $table->index('category_id');
        });

        Schema::create('business_products', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('village_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->decimal('price', 14, 2)->nullable();
            $table->string('unit', 40)->nullable();
            $table->string('image_url')->nullable();
            $table->timestamps();
            $table->index(['village_id', 'business_id']);
            $table->index('business_id');
        });

        Schema::create('business_photos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('village_id')->constrained()->cascadeOnDelete();
            $table->foreignId('business_id')->constrained()->cascadeOnDelete();
            $table->string('image_url');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['village_id', 'business_id', 'sort_order']);
            $table->index('business_id');
        });

        Schema::create('bumdes_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('village_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
            $table->index(['village_id', 'name']);
        });

        Schema::create('bumdes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('village_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->nullable()->constrained('bumdes_categories')->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('manager_name')->nullable();
            $table->string('whatsapp', 40)->nullable();
            $table->string('instagram_url', 2048)->nullable();
            $table->string('facebook_url', 2048)->nullable();
            $table->string('tiktok_url', 2048)->nullable();
            $table->text('address')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->text('description')->nullable();
            $table->string('featured_image_url')->nullable();
            $table->unsignedInteger('worker_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['village_id', 'is_active', 'name']);
            $table->index(['village_id', 'updated_at']);
            $table->index('category_id');
        });

        Schema::create('bumdes_photos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('village_id')->constrained()->cascadeOnDelete();
            $table->foreignId('bumdes_id')->constrained('bumdes')->cascadeOnDelete();
            $table->string('image_url');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['village_id', 'bumdes_id', 'sort_order']);
            $table->index('bumdes_id');
        });

        Schema::create('gallery_albums', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('village_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('cover_image_url')->nullable();
            $table->date('album_date')->nullable();
            $table->timestamps();
            $table->index(['village_id', 'album_date']);
        });

        Schema::create('gallery_photos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('village_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('album_id')->constrained('gallery_albums')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->string('image_url');
            $table->text('caption')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['village_id', 'album_id', 'sort_order']);
            $table->index('album_id');
        });

        Schema::create('gallery_videos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('village_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('album_id')->constrained('gallery_albums')->cascadeOnDelete();
            $table->string('title')->nullable();
            $table->string('video_url', 2048);
            $table->string('youtube_video_id', 32);
            $table->string('embed_url', 2048);
            $table->string('thumbnail_url', 2048)->nullable();
            $table->text('caption')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
            $table->index(['village_id', 'album_id', 'sort_order']);
            $table->index('album_id');
        });

        Schema::create('videos', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('village_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('video_url')->nullable();
            $table->string('thumbnail_url')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();
            $table->index(['village_id', 'is_published', 'published_at']);
        });

        Schema::create('downloadable_files', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('village_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('file_url');
            $table->date('published_at')->nullable();
            $table->unsignedInteger('download_count')->default(0);
            $table->boolean('is_published')->default(true);
            $table->timestamps();
            $table->index(['village_id', 'is_published', 'published_at']);
        });

        Schema::create('desa_cantik_categories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('village_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('type', 40);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['village_id', 'type', 'is_active', 'sort_order']);
        });

        Schema::create('desa_cantik_posts', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('village_id')->constrained()->cascadeOnDelete();
            $table->foreignId('category_id')->constrained('desa_cantik_categories')->restrictOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('content_type', 40);
            $table->string('image_url')->nullable();
            $table->string('file_url')->nullable();
            $table->string('external_url', 2048)->nullable();
            $table->timestamp('published_at')->nullable();
            $table->boolean('is_published')->default(true);
            $table->timestamps();
            $table->index(['village_id', 'category_id', 'is_published', 'published_at'], 'desa_cantik_posts_publish_index');
            $table->index(['village_id', 'updated_at']);
        });

        Schema::create('development_projects', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('village_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->unsignedSmallInteger('year');
            $table->text('location')->nullable();
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->string('source_fund')->nullable();
            $table->decimal('budget_amount', 18, 2)->default(0);
            $table->string('volume')->nullable();
            $table->decimal('progress_percentage', 5, 2)->default(0);
            $table->string('status', 40)->default('planned');
            $table->text('description')->nullable();
            $table->string('image_url')->nullable();
            $table->timestamps();
            $table->index(['village_id', 'year', 'updated_at']);
        });

        Schema::create('pages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('village_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('excerpt')->nullable();
            $table->longText('body')->nullable();
            $table->string('featured_image_url')->nullable();
            $table->string('status', 40)->default('draft');
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->index(['village_id', 'status', 'title']);
            $table->index(['village_id', 'updated_at']);
            $table->index('author_id');
        });

        Schema::create('navigation_menus', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('village_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('location', 80)->default('public');
            $table->timestamps();
            $table->index(['village_id', 'location']);
        });

        Schema::create('navigation_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('village_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('menu_id')->constrained('navigation_menus')->cascadeOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('navigation_items')->cascadeOnDelete();
            $table->foreignId('page_id')->nullable()->constrained('pages')->nullOnDelete();
            $table->string('label');
            $table->string('type', 40)->default('url');
            $table->string('url')->nullable();
            $table->string('target', 20)->default('_self');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_system')->default(false);
            $table->timestamps();
            $table->index(['village_id', 'is_active', 'sort_order']);
            $table->index('menu_id');
            $table->index('parent_id');
            $table->index('page_id');
        });

        Schema::create('site_settings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('village_id')->nullable()->constrained()->nullOnDelete();
            $table->string('key');
            $table->longText('value')->nullable();
            $table->string('type', 40)->default('text');
            $table->timestamps();
            $table->unique(['village_id', 'key']);
        });

        Schema::create('hero_banners', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('village_id')->nullable()->constrained()->nullOnDelete();
            $table->string('title');
            $table->text('subtitle')->nullable();
            $table->string('image_url');
            $table->string('portrait_image_url')->nullable();
            $table->string('button_label')->nullable();
            $table->string('button_url')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['village_id', 'is_active', 'sort_order']);
        });

        Schema::create('village_features', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('village_id')->constrained()->cascadeOnDelete();
            $table->string('feature_key', 80);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
            $table->unique(['village_id', 'feature_key']);
        });

        Schema::create('village_visitor_daily_stats', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('village_id')->constrained()->cascadeOnDelete();
            $table->date('visit_date');
            $table->unsignedBigInteger('unique_visitors')->default(0);
            $table->unsignedBigInteger('total_visits')->default(0);
            $table->timestamps();
            $table->unique(['village_id', 'visit_date']);
            $table->index('visit_date');
        });

        Schema::create('village_visitor_identities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('village_id')->constrained()->cascadeOnDelete();
            $table->date('visit_date');
            $table->char('visitor_hash', 64);
            $table->timestamps();
            $table->unique(['village_id', 'visit_date', 'visitor_hash']);
        });

        Schema::create('village_widgets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('village_id')->constrained()->cascadeOnDelete();
            $table->string('widget_type', 80);
            $table->string('title');
            $table->string('placement', 40)->default('sidebar');
            $table->json('config')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->index(['village_id', 'placement', 'is_active']);
        });
    }

    public function down(): void
    {
        collect([
            'village_widgets', 'village_visitor_identities', 'village_visitor_daily_stats', 'village_features',
            'hero_banners', 'site_settings',
            'navigation_items', 'navigation_menus', 'pages',
            'development_projects',
            'desa_cantik_posts', 'desa_cantik_categories',
            'downloadable_files', 'videos', 'gallery_videos', 'gallery_photos', 'gallery_albums',
            'bumdes_photos', 'bumdes', 'bumdes_categories',
            'business_photos', 'business_products', 'businesses', 'business_categories',
            'post_view_identities', 'post_revisions', 'posts',
            'content_categories',
        ])->each(fn (string $table): bool => Schema::dropIfExists($table));
    }
};
