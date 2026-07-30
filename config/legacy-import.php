<?php

declare(strict_types=1);

return [
    'default_source_url' => env('LEGACY_IMPORT_URL', 'https://meranjatilir.oganilirkab.go.id/api/v1'),
    'timeout' => (int) env('LEGACY_IMPORT_TIMEOUT', 30),
    'max_asset_size' => (int) env('LEGACY_IMPORT_MAX_ASSET_SIZE', 20 * 1024 * 1024),
    'types' => [
        'pages' => 'Halaman',
        'banners' => 'Banner',
        'galleries' => 'Galeri',
        'videos' => 'Video',
        'downloads' => 'Unduhan',
        'umkms' => 'UMKM',
        'bumdes' => 'BUMDes',
        'developments' => 'Pembangunan',
    ],
    'asset_directories' => [
        'pages' => ['storage/images', 'storage/pages'],
        'banners' => ['storage/banners', 'storage/images'],
        'galleries' => ['storage/gallery', 'storage/images'],
        'umkms' => ['storage/umkm', 'storage/images'],
        'bumdes' => ['storage/bumdes', 'storage/images'],
        'developments' => ['storage/developments', 'storage/images'],
    ],
];
