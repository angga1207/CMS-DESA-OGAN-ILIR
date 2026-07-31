<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('feedback_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('village_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('whatsapp', 30);
            $table->string('email');
            $table->unsignedTinyInteger('rating');
            $table->text('message_original');
            $table->text('message_censored');
            $table->string('moderation_status', 20)->default('pending');
            $table->timestamp('published_at')->nullable();
            $table->foreignId('moderated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('moderated_at')->nullable();
            $table->string('submitter_hash', 64)->nullable();
            $table->timestamps();

            $table->index(['village_id', 'moderation_status', 'created_at']);
            $table->index(['village_id', 'rating']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('feedback_entries');
    }
};
