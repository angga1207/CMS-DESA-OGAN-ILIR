<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('bumdes_photos');
        Schema::dropIfExists('bumdes');
        Schema::dropIfExists('bumdes_categories');
    }
};
