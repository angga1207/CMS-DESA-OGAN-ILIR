<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureAdminModuleAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        $role = $request->user()?->role;
        $allowed = in_array($role, ['developer', 'admin_desa', 'editor'], true);

        abort_unless($allowed, 403, 'Anda tidak memiliki akses untuk mengelola modul ini.');

        return $next($request);
    }
}
