# CMS Desa Ogan Ilir - Backend

Backend Laravel untuk mengelola portal website desa di Kabupaten Ogan Ilir. Aplikasi ini menjadi pusat administrasi konten, konfigurasi desa, widget, tema, pengguna, integrasi SIDESI, serta API publik yang dikonsumsi oleh aplikasi `public_web`.

## Fungsi utama

- Dashboard CMS untuk developer, admin desa, dan editor.
- Manajemen desa, pengguna, profil, styling, banner, halaman, artikel, galeri, dan widget.
- Pengaturan modul per desa, termasuk artikel, halaman, galeri, statistik, anggaran, peta sebaran, perangkat desa, dan Desa Cantik.
- Integrasi SIDESI Ogan Ilir untuk statistik penduduk, APBDes, absensi perangkat desa, fasilitas umum, dan bantuan.
- API publik berbasis `village_id` untuk frontend Next.js.
- Statistik pengunjung dan pencatatan view artikel.
- Optimasi gambar upload untuk konten CMS.

## Stack

- PHP `^8.3`
- Laravel `^13.8`
- Livewire `^4.0`
- PostgreSQL
- Redis untuk session, cache, dan queue sesuai `.env.example`
- Vite, Tailwind CSS, Quill, Tom Select, Leaflet, ApexCharts, Swiper, PhotoSwipe

## Persiapan lokal

Salin konfigurasi environment:

```bash
cp .env.example .env
```

Sesuaikan minimal nilai berikut:

```env
APP_NAME="CMS Desa Ogan Ilir"
APP_URL=http://127.0.0.1:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=desa_cms
DB_USERNAME=postgres
DB_PASSWORD=

REDIS_HOST=127.0.0.1
REDIS_PORT=6379

SIDESI_BASE_URL=https://sidesi.oganilirkab.go.id/api/v1
SIDESI_APP_KEY=eofficedesa-OGANILIRBANGKIT
```

Jika Redis belum tersedia di mesin lokal, ubah sementara `SESSION_DRIVER`, `CACHE_STORE`, dan `QUEUE_CONNECTION` ke driver yang tersedia.

## Instalasi

```bash
composer install
npm install
php artisan key:generate
php artisan migrate:fresh --seed
npm run build
```

Seeder awal membuat akun developer:

- Email: `developer@desa.oganilirkab.go.id`
- Username: `developer`
- Password: `password`

Seeder juga membuat data awal `Desa Tanjung Lubuk` sebagai contoh desa dengan `sidesi_village_id` yang siap dipakai untuk uji integrasi.

## Menjalankan aplikasi

Jalankan backend:

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

Untuk pengembangan aset admin:

```bash
npm run dev
```

Atau jalankan server, queue listener, log pail, dan Vite sekaligus:

```bash
composer run dev
```

CMS dapat dibuka di:

```text
http://127.0.0.1:8000/login
```

## Konfigurasi Nginx dan Cloudflare

Livewire 4 menggunakan prefix route yang memiliki hash, misalnya
`/livewire-9bf29492`. Preview temporary upload juga berada di bawah prefix ini
dan URL-nya berakhir dengan ekstensi gambar. Karena itu, location Nginx untuk
file statis seperti `jpg`, `jpeg`, `png`, `js`, dan `css` tidak boleh memotong
request Livewire sebelum request diteruskan ke Laravel.

Tambahkan isi [`deploy/nginx/livewire.conf`](deploy/nginx/livewire.conf) ke
server block aplikasi **sebelum** location regex file statis, lalu validasi dan
reload Nginx:

```bash
sudo nginx -t
sudo systemctl reload nginx
```

Di Cloudflare, buat Cache Rule dengan kondisi URI Path `starts with
/livewire-` dan aksi **Bypass cache**. Setelah perubahan origin diterapkan,
purge cache untuk `https://desa.oganilirkab.go.id/livewire-*`; respons 404 lama
dapat tersimpan di edge walaupun Nginx sudah diperbaiki.

Verifikasi dari production dengan URL route yang sedang aktif:

```bash
php artisan route:list --path=livewire
curl -I https://desa.oganilirkab.go.id/livewire-<hash>/livewire.js
```

Route `livewire.js` harus menghasilkan `200`, sedangkan preview upload yang
masih berlaku harus menghasilkan `200` dengan `Content-Type` gambar dan tidak
boleh memiliki status cache Cloudflare `HIT`.

## Endpoint publik

Frontend publik membaca data dari endpoint berikut:

- `GET /api/villages/{village}/site`
- `GET /api/villages/{village}/posts`
- `GET /api/villages/{village}/posts/{slug}`
- `POST /api/villages/{village}/posts/{slug}/view`
- `GET /api/villages/{village}/widgets`
- `GET /api/villages/{village}/officials/today`
- `GET /api/villages/{village}/officials/photo`
- `GET /api/villages/{village}/budget`
- `GET /api/villages/{village}/statistics`
- `GET /api/villages/{village}/map/categories`
- `GET /api/villages/{village}/map/facilities`
- `GET /api/villages/{village}/map/facilities/{listing}`
- `GET /api/villages/{village}/map/assistance`
- `POST /api/villages/{village}/visitors`

Cache payload publik dikontrol oleh:

```env
PUBLIC_SITE_CACHE_TTL=300
PUBLIC_SITE_CACHE_STALE_TTL=1800
EXTERNAL_DATA_CACHE_FRESH=120
EXTERNAL_DATA_CACHE_STALE=1800
PUBLIC_SITE_ARTICLE_LIMIT=12
```

## Validasi

```bash
php artisan test
./vendor/bin/pint
npm run build
```

Gunakan `php artisan route:list` saat perlu mengecek ulang route admin dan API publik.
