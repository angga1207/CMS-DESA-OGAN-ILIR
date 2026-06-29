<?php

namespace App\Support;

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

final class CurrentVillage
{
    public static function id(): int
    {
        if (Auth::user()?->role === 'developer') {
            $sessionVillageId = session('active_village_id');

            if ($sessionVillageId && DB::table('villages')->where('id', $sessionVillageId)->exists()) {
                return (int) $sessionVillageId;
            }
        }

        $userVillageId = Auth::user()?->village_id;

        if ($userVillageId) {
            return (int) $userVillageId;
        }

        return (int) (DB::table('villages')->orderBy('id')->value('id') ?: 1);
    }
}
