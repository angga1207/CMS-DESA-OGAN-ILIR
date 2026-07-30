<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('legacy_import_runs')) {
            Schema::create('legacy_import_runs', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('village_id')->constrained()->cascadeOnDelete();
                $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
                $table->string('source_url', 2048);
                $table->string('status', 24)->default('running');
                $table->json('selected_types');
                $table->json('summary')->nullable();
                $table->text('error_message')->nullable();
                $table->timestamp('started_at');
                $table->timestamp('finished_at')->nullable();
                $table->timestamps();
                $table->index(['village_id', 'created_at']);
            });
        }

        if (! Schema::hasTable('legacy_import_items')) {
            Schema::create('legacy_import_items', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('run_id')->nullable()->constrained('legacy_import_runs')->nullOnDelete();
                $table->foreignId('village_id')->constrained()->cascadeOnDelete();
                $table->string('source_url', 2048);
                $table->string('source_type', 48);
                $table->string('source_id', 120);
                $table->string('target_table', 80);
                $table->unsignedBigInteger('target_id')->nullable();
                $table->char('payload_hash', 64);
                $table->string('status', 24);
                $table->text('message')->nullable();
                $table->timestamps();
                $table->unique(['village_id', 'source_url', 'source_type', 'source_id'], 'legacy_import_source_unique');
                $table->index(['run_id', 'status']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_import_items');
        Schema::dropIfExists('legacy_import_runs');
    }
};
