<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('villages', function (Blueprint $table): void {
            $table->string('public_hostname')->nullable()->unique()->after('website_url');
        });

        $usedHostnames = [];

        DB::table('villages')
            ->orderBy('id')
            ->get(['id', 'website_url'])
            ->each(function (object $village) use (&$usedHostnames): void {
                $hostname = strtolower((string) parse_url((string) $village->website_url, PHP_URL_HOST));
                $hostname = preg_replace('/^www\./', '', rtrim($hostname, '.'));

                if ($hostname === '' || isset($usedHostnames[$hostname])) {
                    return;
                }

                $usedHostnames[$hostname] = true;
                DB::table('villages')->where('id', $village->id)->update(['public_hostname' => $hostname]);
            });
    }

    public function down(): void
    {
        Schema::table('villages', function (Blueprint $table): void {
            $table->dropUnique(['public_hostname']);
            $table->dropColumn('public_hostname');
        });
    }
};
