<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
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
    }

    public function down(): void
    {
        Schema::dropIfExists('business_photos');
    }
};
