<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\TenantResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PublicTenantController extends Controller
{
    public function __construct(
        private readonly TenantResolver $resolver,
    ) {}

    public function resolve(Request $request): JsonResponse
    {
        $data = $request->validate([
            'hostname' => ['required', 'string', 'max:300'],
        ]);
        $village = $this->resolver->resolve($data['hostname']);

        abort_unless($village, 404, 'Domain website desa tidak terdaftar.');

        return response()->json([
            'data' => [
                'id' => (int) $village->id,
                'slug' => $village->slug,
                'name' => $village->name,
                'hostname' => $village->public_hostname,
            ],
        ]);
    }
}
