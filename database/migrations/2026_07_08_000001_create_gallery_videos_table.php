<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('gallery_videos');
    }
};
