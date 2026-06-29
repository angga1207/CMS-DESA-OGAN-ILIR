# Rekap Fitur dan Rancangan Database Website Desa Tebing Gerinting Selatan

Dokumen ini merangkum fitur yang tersedia pada website Desa Tebing Gerinting Selatan (`https://tebinggerintingselatan.oganilirkab.go.id/`) dan rancangan struktur database PostgreSQL untuk membangun website desa sejenis.

Catatan pengumpulan data:

- Situs utama dilindungi Cloudflare Challenge sehingga halaman tidak bisa diambil penuh melalui fetch biasa.
- Rekap ini disusun dari halaman yang terindeks mesin pencari, metadata halaman, dan potongan konten publik yang masih dapat dibaca.
- Desain visual diabaikan sesuai permintaan; fokus dokumen ini adalah fitur, modul, dan struktur data.

## Sumber Halaman yang Teridentifikasi

| Modul | URL |
| --- | --- |
| Beranda | `https://tebinggerintingselatan.oganilirkab.go.id/` |
| Berita / Artikel Desa | `https://tebinggerintingselatan.oganilirkab.go.id/artikel` |
| Berita Kabupaten Ogan Ilir | `https://tebinggerintingselatan.oganilirkab.go.id/berita-oi/...` |
| Lapak UMKM | `https://tebinggerintingselatan.oganilirkab.go.id/lapak` |
| Detail UMKM | `https://tebinggerintingselatan.oganilirkab.go.id/lapak/2` |
| Video | `https://tebinggerintingselatan.oganilirkab.go.id/videos` |
| Galeri | `https://tebinggerintingselatan.oganilirkab.go.id/gallery` |
| Download Berkas | `https://tebinggerintingselatan.oganilirkab.go.id/download` |
| Pembangunan | `https://tebinggerintingselatan.oganilirkab.go.id/pembangunan` |
| Peta Sebaran / GIS | `https://tebinggerintingselatan.oganilirkab.go.id/gis/peta-sebaran` |
| Desa Cantik | `https://tebinggerintingselatan.oganilirkab.go.id/desa-cantik` |
| Potensi Desa / Podes | `https://tebinggerintingselatan.oganilirkab.go.id/potensi-desa` |
| Statistik Penduduk | `https://tebinggerintingselatan.oganilirkab.go.id/data-penduduk` |
| Data Pendidikan | `https://tebinggerintingselatan.oganilirkab.go.id/data-pendidikan` |
| Data Pekerjaan | `https://tebinggerintingselatan.oganilirkab.go.id/data-pekerjaan` |
| Data Usia | `https://tebinggerintingselatan.oganilirkab.go.id/data-usia` |

## Rekap Fitur Website

### 1. Beranda

Fitur yang terlihat atau terindikasi:

- Sambutan / profil singkat Pemerintah Desa Tebing Gerinting Selatan.
- Identitas wilayah: Desa Tebing Gerinting Selatan, Kecamatan Indralaya Selatan, Kabupaten Ogan Ilir, Sumatera Selatan.
- Ringkasan statistik penduduk.
- Ringkasan data pendidikan, pekerjaan, dan usia.
- Ringkasan jenis usaha UMKM.
- Daftar perangkat desa dan status kehadiran.
- Transparansi anggaran per tahun, tersedia pilihan tahun 2020 sampai 2026.
- Akses cepat ke berita, media, download, pembangunan, peta sebaran, dan Desa Cantik.

### 2. Profil Desa dan Pemerintahan

Fitur yang perlu disediakan:

- Profil desa.
- Data kepala desa dan perangkat desa.
- Jabatan perangkat desa, misalnya kepala seksi pemerintahan.
- Status kehadiran perangkat desa.
- Struktur organisasi pemerintahan desa.
- Lembaga desa seperti BPD, TP PKK, Karang Taruna, BUMDes, atau lembaga lain jika diperlukan.

### 3. Berita / Artikel Desa

Fitur yang tersedia:

- Daftar artikel atau berita desa.
- Filter jenis artikel, minimal `berita` dan `pengumuman`.
- Detail artikel dengan slug.
- Tanggal publikasi.
- Kategori/topik seperti pemerintahan, BUMDes, pembangunan, TP PKK, BLT, Desa Cantik, dan kegiatan masyarakat.
- Share artikel.
- Arsip berita.

Contoh artikel yang teridentifikasi:

- Penghargaan Desa Cinta Statistik.
- Lomba TP PKK tingkat Kabupaten Ogan Ilir.
- Sertifikasi pembangunan fisik dan ketahanan pangan.
- Penyaluran BLT-DD.
- Pembinaan data Desa Cantik oleh BPS/Diskominfo.
- Layanan BUMDes Usaha Bersama.

### 4. Berita Kabupaten Ogan Ilir

Fitur yang tersedia:

- Kanal berita kabupaten dengan path `berita-oi`.
- Isi berita berasal dari Pemerintah Kabupaten Ogan Ilir.
- Detail berita menggunakan slug.
- Berita ini terpisah dari artikel desa, tetapi tetap tampil dalam website desa.

### 5. Statistik Penduduk

Fitur yang tersedia:

- Statistik penduduk umum.
- Data populasi berdasarkan jenis kelamin.
- Data pendidikan.
- Data pekerjaan.
- Data usia.
- Tabel jumlah dan persentase.
- Pemisahan data laki-laki dan perempuan.

Halaman yang teridentifikasi:

- `data-penduduk`
- `data-pendidikan`
- `data-pekerjaan`
- `data-usia`

### 6. Transparansi Anggaran

Fitur yang tersedia:

- Tampilan APBDes per tahun.
- Pilihan tahun 2020 sampai 2026.
- Data anggaran desa kemungkinan dikelompokkan menjadi pendapatan, belanja, pembiayaan, dan bidang kegiatan.

Struktur database sebaiknya dibuat per tahun anggaran dan per pos anggaran agar dapat menampung APBDes, realisasi, dan grafik transparansi.

### 7. Lapak UMKM

Fitur yang tersedia:

- Daftar UMKM Desa Tebing Gerinting Selatan.
- Filter semua jenis usaha.
- Jenis usaha yang teridentifikasi:
  - Kerupuk Kemplang Mentah.
  - Kerupuk Kemplang Panggang.
- Detail UMKM berisi:
  - Nama usaha.
  - Pemilik.
  - WhatsApp.
  - Alamat.
  - Jenis usaha.

Contoh detail terindeks:

- `Kerupuk Cik Imah`
- Pemilik: `Cik Imah`
- Alamat: `Tebing Gerinting Selatan`
- Jenis usaha: `Kerupuk Kemplang Panggang`

### 8. Galeri

Fitur yang tersedia:

- Daftar album galeri.
- Detail album galeri.
- Contoh album:
  - Tim Penggerak PKK (TP PKK).
  - Perangkat Desa.
  - Bulan Kemerdekaan (2024).
- Tanggal album.
- Kumpulan foto per album.

### 9. Video

Fitur yang tersedia:

- Daftar video.
- Detail video.
- Contoh video: `Fasilitasi RKPD 2023`.
- Kemungkinan menyimpan embed YouTube atau file video.

### 10. Download Berkas

Fitur yang tersedia:

- Daftar berkas unduhan.
- Kolom yang terindikasi: nomor, judul berkas, tanggal.
- Cocok untuk dokumen PDF, surat, regulasi desa, laporan, atau formulir layanan.

### 11. Pembangunan

Fitur yang tersedia:

- Daftar kegiatan pembangunan desa.
- Detail pembangunan kemungkinan berisi nama kegiatan, lokasi, sumber dana, anggaran, volume, tahun, status, dan dokumentasi.
- Artikel terkait pembangunan fisik dan ketahanan pangan terindeks di kanal artikel.

### 12. Peta Sebaran / GIS

Fitur yang tersedia:

- Peta sebaran berbasis kategori.
- Informasi desa.
- Sumber data: Sidesi Ogan Ilir.
- Kategori yang teridentifikasi:
  - Bantuan.
  - Fasilitas Umum.
- Data sebaiknya menyimpan latitude, longitude, kategori titik, alamat/lokasi, dan metadata.

### 13. Desa Cantik

Fitur yang tersedia:

- Halaman khusus Desa Cantik.
- Mendukung publikasi dan penyajian data statistik desa.
- Berkaitan dengan pembinaan BPS/Diskominfo dan tata kelola data desa.
- Bisa diimplementasikan sebagai modul halaman statis dinamis plus dataset/indikator.

### 14. Dashboard Potensi Desa / Podes

Fitur yang tersedia:

- Dashboard Potensi Desa.
- Data Podes berbentuk kode, isian, dan jawaban.
- Contoh data:
  - Provinsi: Sumatera Selatan.
  - Kabupaten: Ogan Ilir.
  - Status Pemerintahan: Desa.
  - Badan Permusyawaratan Desa/Lembaga Musyawarah.
- Cocok disimpan sebagai dataset fleksibel per tahun/periode.

### 15. Integrasi Sidesi / SDGs Desa

Fitur yang terindikasi:

- Link atau data dari Sidesi Ogan Ilir.
- Dashboard SDGs Desa memuat sosial ekonomi keluarga, perumahan, aset, dan program.
- Data dapat disimpan lokal sebagai cache/snapshot jika website baru ingin menampilkan ulang data eksternal.

## Modul Backend yang Disarankan

1. Autentikasi dan manajemen admin.
2. Profil desa.
3. Pemerintahan desa dan lembaga desa.
4. Artikel, berita, pengumuman, dan berita kabupaten.
5. Statistik penduduk.
6. Transparansi anggaran.
7. UMKM/lapak.
8. Galeri foto.
9. Video.
10. Download berkas.
11. Pembangunan.
12. GIS/peta sebaran.
13. Desa Cantik dan dataset statistik.
14. Potensi Desa/Podes.
15. Pengaturan website.

## Rancangan Struktur Database PostgreSQL

Rancangan berikut bersifat modular. Nama tabel menggunakan bahasa Inggris agar konsisten dengan praktik umum backend, sementara label UI tetap bisa berbahasa Indonesia.

### Ekstensi PostgreSQL

```sql
CREATE EXTENSION IF NOT EXISTS pgcrypto;
CREATE EXTENSION IF NOT EXISTS postgis;
```

`postgis` hanya diperlukan jika peta sebaran akan memakai tipe data geospasial. Jika ingin lebih sederhana, kolom `latitude` dan `longitude` sudah cukup.

### 1. Users dan Role

```sql
CREATE TABLE roles (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE users (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    role_id UUID REFERENCES roles(id) ON DELETE SET NULL,
    name VARCHAR(150) NOT NULL,
    email VARCHAR(150) NOT NULL UNIQUE,
    password_hash TEXT NOT NULL,
    phone VARCHAR(30),
    avatar_path TEXT,
    is_active BOOLEAN NOT NULL DEFAULT true,
    last_login_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
```

### 2. Profil Desa

```sql
CREATE TABLE villages (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(180) NOT NULL UNIQUE,
    district VARCHAR(150),
    regency VARCHAR(150),
    province VARCHAR(150),
    postal_code VARCHAR(20),
    address TEXT,
    phone VARCHAR(30),
    email VARCHAR(150),
    website_url TEXT,
    latitude NUMERIC(10, 7),
    longitude NUMERIC(10, 7),
    logo_path TEXT,
    description TEXT,
    welcome_message TEXT,
    vision TEXT,
    mission TEXT,
    history TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE village_social_links (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    village_id UUID NOT NULL REFERENCES villages(id) ON DELETE CASCADE,
    platform VARCHAR(50) NOT NULL,
    url TEXT NOT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
```

### 3. Pemerintahan Desa dan Lembaga

```sql
CREATE TABLE village_officials (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    village_id UUID NOT NULL REFERENCES villages(id) ON DELETE CASCADE,
    name VARCHAR(150) NOT NULL,
    position VARCHAR(150) NOT NULL,
    nip VARCHAR(80),
    phone VARCHAR(30),
    email VARCHAR(150),
    photo_path TEXT,
    bio TEXT,
    attendance_status VARCHAR(30) DEFAULT 'unknown',
    sort_order INTEGER NOT NULL DEFAULT 0,
    is_active BOOLEAN NOT NULL DEFAULT true,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE village_institutions (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    village_id UUID NOT NULL REFERENCES villages(id) ON DELETE CASCADE,
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(180) NOT NULL,
    type VARCHAR(80),
    description TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (village_id, slug)
);

CREATE TABLE institution_members (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    institution_id UUID NOT NULL REFERENCES village_institutions(id) ON DELETE CASCADE,
    name VARCHAR(150) NOT NULL,
    position VARCHAR(150),
    phone VARCHAR(30),
    photo_path TEXT,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
```

### 4. Konten Artikel, Berita, dan Pengumuman

```sql
CREATE TABLE content_categories (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(120) NOT NULL,
    slug VARCHAR(150) NOT NULL UNIQUE,
    type VARCHAR(50) NOT NULL DEFAULT 'article',
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE posts (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    author_id UUID REFERENCES users(id) ON DELETE SET NULL,
    category_id UUID REFERENCES content_categories(id) ON DELETE SET NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(280) NOT NULL UNIQUE,
    excerpt TEXT,
    body TEXT,
    featured_image_path TEXT,
    source_type VARCHAR(50) NOT NULL DEFAULT 'village',
    source_name VARCHAR(150),
    external_url TEXT,
    status VARCHAR(30) NOT NULL DEFAULT 'draft',
    published_at TIMESTAMPTZ,
    view_count INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE post_tags (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(100) NOT NULL UNIQUE,
    slug VARCHAR(130) NOT NULL UNIQUE
);

CREATE TABLE post_tag_pivots (
    post_id UUID NOT NULL REFERENCES posts(id) ON DELETE CASCADE,
    tag_id UUID NOT NULL REFERENCES post_tags(id) ON DELETE CASCADE,
    PRIMARY KEY (post_id, tag_id)
);
```

Nilai `source_type` yang disarankan:

- `village` untuk artikel desa.
- `regency` untuk berita Ogan Ilir.
- `announcement` untuk pengumuman.

### 5. Statistik Penduduk

```sql
CREATE TABLE population_snapshots (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    village_id UUID NOT NULL REFERENCES villages(id) ON DELETE CASCADE,
    year INTEGER NOT NULL,
    period_label VARCHAR(100),
    total_population INTEGER NOT NULL DEFAULT 0,
    male_population INTEGER NOT NULL DEFAULT 0,
    female_population INTEGER NOT NULL DEFAULT 0,
    total_families INTEGER,
    source_name VARCHAR(150),
    source_url TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (village_id, year, period_label)
);

CREATE TABLE demographic_categories (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    code VARCHAR(80) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    type VARCHAR(50) NOT NULL
);

CREATE TABLE demographic_values (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    snapshot_id UUID NOT NULL REFERENCES population_snapshots(id) ON DELETE CASCADE,
    category_id UUID NOT NULL REFERENCES demographic_categories(id) ON DELETE CASCADE,
    total_count INTEGER NOT NULL DEFAULT 0,
    male_count INTEGER NOT NULL DEFAULT 0,
    female_count INTEGER NOT NULL DEFAULT 0,
    percentage NUMERIC(6, 2),
    male_percentage NUMERIC(6, 2),
    female_percentage NUMERIC(6, 2),
    sort_order INTEGER NOT NULL DEFAULT 0,
    UNIQUE (snapshot_id, category_id)
);
```

Nilai `demographic_categories.type` yang disarankan:

- `age_group`
- `education`
- `occupation`
- `gender`
- `marital_status`
- `religion`

### 6. Transparansi Anggaran / APBDes

```sql
CREATE TABLE budget_years (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    village_id UUID NOT NULL REFERENCES villages(id) ON DELETE CASCADE,
    year INTEGER NOT NULL,
    title VARCHAR(180) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'published',
    notes TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (village_id, year)
);

CREATE TABLE budget_categories (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(180) NOT NULL,
    code VARCHAR(50),
    parent_id UUID REFERENCES budget_categories(id) ON DELETE SET NULL,
    category_type VARCHAR(50) NOT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE budget_items (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    budget_year_id UUID NOT NULL REFERENCES budget_years(id) ON DELETE CASCADE,
    category_id UUID REFERENCES budget_categories(id) ON DELETE SET NULL,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(80),
    planned_amount NUMERIC(18, 2) NOT NULL DEFAULT 0,
    realized_amount NUMERIC(18, 2) NOT NULL DEFAULT 0,
    unit VARCHAR(80),
    volume NUMERIC(14, 2),
    description TEXT,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
```

Nilai `budget_categories.category_type`:

- `income`
- `expense`
- `financing`
- `field`
- `activity`

### 7. UMKM / Lapak Desa

```sql
CREATE TABLE business_categories (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(180) NOT NULL UNIQUE,
    description TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE businesses (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    village_id UUID NOT NULL REFERENCES villages(id) ON DELETE CASCADE,
    category_id UUID REFERENCES business_categories(id) ON DELETE SET NULL,
    name VARCHAR(180) NOT NULL,
    slug VARCHAR(220) NOT NULL UNIQUE,
    owner_name VARCHAR(150),
    phone VARCHAR(30),
    whatsapp VARCHAR(30),
    address TEXT,
    description TEXT,
    featured_image_path TEXT,
    latitude NUMERIC(10, 7),
    longitude NUMERIC(10, 7),
    worker_count INTEGER,
    hamlet VARCHAR(120),
    is_featured BOOLEAN NOT NULL DEFAULT false,
    is_active BOOLEAN NOT NULL DEFAULT true,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE business_products (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    business_id UUID NOT NULL REFERENCES businesses(id) ON DELETE CASCADE,
    name VARCHAR(180) NOT NULL,
    description TEXT,
    price NUMERIC(14, 2),
    unit VARCHAR(50),
    image_path TEXT,
    is_active BOOLEAN NOT NULL DEFAULT true,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
```

### 8. Galeri Foto

```sql
CREATE TABLE gallery_albums (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title VARCHAR(180) NOT NULL,
    slug VARCHAR(220) NOT NULL UNIQUE,
    description TEXT,
    cover_image_path TEXT,
    album_date DATE,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE gallery_photos (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    album_id UUID NOT NULL REFERENCES gallery_albums(id) ON DELETE CASCADE,
    title VARCHAR(180),
    caption TEXT,
    image_path TEXT NOT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
```

### 9. Video

```sql
CREATE TABLE videos (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title VARCHAR(180) NOT NULL,
    slug VARCHAR(220) NOT NULL UNIQUE,
    description TEXT,
    video_url TEXT,
    embed_url TEXT,
    thumbnail_path TEXT,
    published_at TIMESTAMPTZ,
    is_published BOOLEAN NOT NULL DEFAULT true,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
```

### 10. Download Berkas

```sql
CREATE TABLE file_categories (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(180) NOT NULL UNIQUE
);

CREATE TABLE downloadable_files (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    category_id UUID REFERENCES file_categories(id) ON DELETE SET NULL,
    title VARCHAR(220) NOT NULL,
    slug VARCHAR(260) NOT NULL UNIQUE,
    description TEXT,
    file_path TEXT NOT NULL,
    file_type VARCHAR(50),
    file_size_bytes BIGINT,
    published_at DATE,
    download_count INTEGER NOT NULL DEFAULT 0,
    is_published BOOLEAN NOT NULL DEFAULT true,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
```

### 11. Pembangunan Desa

```sql
CREATE TABLE development_projects (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    village_id UUID NOT NULL REFERENCES villages(id) ON DELETE CASCADE,
    title VARCHAR(220) NOT NULL,
    slug VARCHAR(260) NOT NULL UNIQUE,
    year INTEGER NOT NULL,
    location TEXT,
    source_fund VARCHAR(150),
    budget_amount NUMERIC(18, 2),
    volume VARCHAR(120),
    executor VARCHAR(180),
    start_date DATE,
    end_date DATE,
    progress_percentage NUMERIC(5, 2) NOT NULL DEFAULT 0,
    status VARCHAR(50) NOT NULL DEFAULT 'planned',
    description TEXT,
    latitude NUMERIC(10, 7),
    longitude NUMERIC(10, 7),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE development_project_photos (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    project_id UUID NOT NULL REFERENCES development_projects(id) ON DELETE CASCADE,
    image_path TEXT NOT NULL,
    caption TEXT,
    photo_stage VARCHAR(50),
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
```

Nilai `development_projects.status`:

- `planned`
- `in_progress`
- `completed`
- `cancelled`

Nilai `development_project_photos.photo_stage`:

- `before`
- `progress`
- `after`

### 12. GIS / Peta Sebaran

```sql
CREATE TABLE map_categories (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(150) NOT NULL,
    slug VARCHAR(180) NOT NULL UNIQUE,
    icon VARCHAR(80),
    color VARCHAR(30),
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE map_points (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    village_id UUID NOT NULL REFERENCES villages(id) ON DELETE CASCADE,
    category_id UUID REFERENCES map_categories(id) ON DELETE SET NULL,
    name VARCHAR(180) NOT NULL,
    description TEXT,
    address TEXT,
    latitude NUMERIC(10, 7) NOT NULL,
    longitude NUMERIC(10, 7) NOT NULL,
    geom GEOGRAPHY(POINT, 4326),
    source_name VARCHAR(150),
    source_reference TEXT,
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
    is_active BOOLEAN NOT NULL DEFAULT true,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE INDEX map_points_geom_idx ON map_points USING GIST (geom);
CREATE INDEX map_points_metadata_idx ON map_points USING GIN (metadata);
```

Jika tidak memakai PostGIS, hapus kolom `geom` dan index `map_points_geom_idx`.

### 13. Desa Cantik dan Dataset Statistik

```sql
CREATE TABLE statistic_datasets (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    village_id UUID NOT NULL REFERENCES villages(id) ON DELETE CASCADE,
    title VARCHAR(220) NOT NULL,
    slug VARCHAR(260) NOT NULL UNIQUE,
    topic VARCHAR(120),
    year INTEGER,
    source_name VARCHAR(150),
    source_url TEXT,
    description TEXT,
    published_at DATE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE statistic_indicators (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    dataset_id UUID NOT NULL REFERENCES statistic_datasets(id) ON DELETE CASCADE,
    code VARCHAR(80),
    name VARCHAR(180) NOT NULL,
    unit VARCHAR(80),
    sort_order INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE statistic_values (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    indicator_id UUID NOT NULL REFERENCES statistic_indicators(id) ON DELETE CASCADE,
    label VARCHAR(180),
    value_numeric NUMERIC(18, 4),
    value_text TEXT,
    male_value NUMERIC(18, 4),
    female_value NUMERIC(18, 4),
    metadata JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
```

### 14. Potensi Desa / Podes

```sql
CREATE TABLE podes_snapshots (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    village_id UUID NOT NULL REFERENCES villages(id) ON DELETE CASCADE,
    year INTEGER NOT NULL,
    title VARCHAR(180) NOT NULL,
    source_name VARCHAR(150),
    source_url TEXT,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (village_id, year)
);

CREATE TABLE podes_sections (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    snapshot_id UUID NOT NULL REFERENCES podes_snapshots(id) ON DELETE CASCADE,
    code VARCHAR(50),
    title VARCHAR(180) NOT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0
);

CREATE TABLE podes_items (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    section_id UUID NOT NULL REFERENCES podes_sections(id) ON DELETE CASCADE,
    code VARCHAR(50),
    question VARCHAR(255) NOT NULL,
    answer TEXT,
    answer_numeric NUMERIC(18, 4),
    unit VARCHAR(80),
    sort_order INTEGER NOT NULL DEFAULT 0
);
```

### 15. Halaman Statis Dinamis

```sql
CREATE TABLE pages (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    title VARCHAR(220) NOT NULL,
    slug VARCHAR(260) NOT NULL UNIQUE,
    body TEXT,
    template VARCHAR(80),
    featured_image_path TEXT,
    status VARCHAR(30) NOT NULL DEFAULT 'draft',
    published_at TIMESTAMPTZ,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);
```

Contoh penggunaan:

- Halaman profil.
- Desa Cantik.
- Sejarah desa.
- Visi misi.
- Layanan publik.

### 16. Pengaturan Website

```sql
CREATE TABLE site_settings (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    key VARCHAR(120) NOT NULL UNIQUE,
    value TEXT,
    value_json JSONB,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE navigation_menus (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name VARCHAR(120) NOT NULL,
    location VARCHAR(80) NOT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT now()
);

CREATE TABLE navigation_items (
    id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    menu_id UUID NOT NULL REFERENCES navigation_menus(id) ON DELETE CASCADE,
    parent_id UUID REFERENCES navigation_items(id) ON DELETE CASCADE,
    label VARCHAR(150) NOT NULL,
    url TEXT,
    route_name VARCHAR(120),
    target VARCHAR(30) NOT NULL DEFAULT '_self',
    sort_order INTEGER NOT NULL DEFAULT 0,
    is_active BOOLEAN NOT NULL DEFAULT true
);
```

## Index yang Disarankan

```sql
CREATE INDEX posts_status_published_at_idx ON posts (status, published_at DESC);
CREATE INDEX posts_source_type_idx ON posts (source_type);
CREATE INDEX businesses_category_idx ON businesses (category_id);
CREATE INDEX businesses_active_idx ON businesses (is_active);
CREATE INDEX demographic_values_snapshot_idx ON demographic_values (snapshot_id);
CREATE INDEX budget_items_budget_year_idx ON budget_items (budget_year_id);
CREATE INDEX development_projects_year_status_idx ON development_projects (year, status);
CREATE INDEX downloadable_files_published_idx ON downloadable_files (is_published, published_at DESC);
CREATE INDEX videos_published_idx ON videos (is_published, published_at DESC);
```

## Urutan Implementasi yang Disarankan

1. Buat autentikasi admin, role, dan dashboard.
2. Buat profil desa, perangkat desa, dan menu website.
3. Buat modul artikel/pengumuman/berita kabupaten.
4. Buat modul statistik penduduk.
5. Buat transparansi anggaran.
6. Buat lapak UMKM.
7. Buat media: galeri, video, download.
8. Buat pembangunan desa.
9. Buat GIS/peta sebaran.
10. Buat Desa Cantik dan Potensi Desa/Podes.

## Catatan Implementasi

- Untuk data statistik yang berubah per tahun, selalu simpan dalam bentuk snapshot agar data lama tidak tertimpa.
- Untuk artikel desa dan berita kabupaten, gunakan satu tabel `posts` dengan pembeda `source_type`.
- Untuk Podes dan Desa Cantik, gunakan struktur fleksibel berbasis dataset/indikator karena daftar pertanyaan dan indikator dapat berubah.
- Untuk peta sebaran, mulai dari `latitude` dan `longitude`. Tambahkan PostGIS jika perlu query radius, polygon wilayah, atau analisis spasial.
- Untuk file dan gambar, database cukup menyimpan path/URL, sedangkan file fisik disimpan di object storage atau folder publik server.
- Untuk APBDes, pisahkan `budget_years`, `budget_categories`, dan `budget_items` agar mudah dibuat grafik transparansi per tahun.

