# Deploy ke Exabytes cPanel (AI Pro)

Sistem ni Laravel + MySQL, dibina untuk jalan kat shared hosting cPanel
(akaun sama yang host `kretiv.co`), guna subdomain `jobs.kretiv.co`. Proses
dan gotcha di bawah adalah sama macam yang confirmed berfungsi untuk sistem
`terra_lestari` (Sajian Baginda) atas akaun cPanel yang sama — rujuk
`DEPLOYMENT.md` repo tu untuk konteks lanjut.

## 1. Cipta database MySQL

Dalam cPanel → **MySQL Database Wizard**:
1. Cipta database (contoh `cpaneluser_jobskretiv`)
2. Cipta user database + password kuat
3. Assign user tu ke database dengan **All Privileges**

Simpan nama database, username, password — akan diperlukan dalam `.env`.

## 2. Upload kod

Pilihan A — **Git Version Control** (disyorkan, dalam cPanel):
1. cPanel → **Git Version Control** → **Create**
2. Repository URL: `https://github.com/afiqazlan17/jobs.kretiv.co.git`
3. Branch: `main` (atau branch yang dah di-merge)
4. Deploy ke folder di luar `public_html` root (contoh
   `/home/cpaneluser/jobs-kretiv`)

Pilihan B — Upload ZIP:
1. Zip seluruh repo (kecuali `node_modules`, `.git`, `vendor`)
2. Upload & extract guna **File Manager**

## 3. Set Document Root ke folder `public/`

**PENTING**: Laravel punya entry point ialah folder `public/`, bukan root
projek. Jangan point domain terus ke root repo — nanti semua fail source
boleh diakses terus dari browser (risiko keselamatan).

Dalam **Domains**/**Subdomains**, set "Document Root" terus ke
`/home/cpaneluser/jobs-kretiv/public`.

## 4. Setup `.env`

Copy `.env.example` ke `.env`, isi:
```
APP_NAME="Kretivco Jobs"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://jobs.kretiv.co

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cpaneluser_jobskretiv
DB_USERNAME=cpaneluser_dbuser
DB_PASSWORD=<password database>

QUEUE_CONNECTION=sync
```

Generate app key locally dan copy nilai `APP_KEY` ke `.env` server (tiada
Terminal untuk run `php artisan key:generate` di server — lihat bahagian
"Realiti deployment" di bawah).

## 5. Migrate & seed database

Run sekali via Cron Jobs (lihat bahagian bawah untuk cara).

## 6. Storage link (untuk attachment job)

```bash
php artisan storage:link
```

Kalau symlink tak jalan sebab batasan hosting, boleh guna cPanel File
Manager untuk buat symlink manual, atau serve fail melalui route Laravel
yang ada auth check (lihat pelan migrasi — attachment routes dilindungi
oleh auth middleware, bukan bergantung sepenuhnya kepada symlink public).

## 7. Permission fail

```bash
chmod -R 775 storage bootstrap/cache
```

## Nota keselamatan

- Padam semua fail `deploy*.log` / `diag.log` di root repo lepas setiap
  sesi update — fail ni boleh dedah struktur server.
- Jangan biarkan cron job "one-off" kekal aktif lepas siap digunakan —
  selalu padam di **Cron Jobs** lepas confirm hasil.

---

## Realiti deployment di Exabytes (cPanel AI Pro, tiada Terminal/SSH)

Plan hosting ni **takde Terminal/SSH access** (confirmed sama akaun yang
host `terra_lestari`). Semua command CLI (composer, artisan) kena jalan
melalui **Cron Jobs** (run sekali, dengan jadual masa akan datang, lepas tu
padam job tu).

### Setup yang confirmed berfungsi (dari pengalaman `terra_lestari`)

- **PHP binary untuk cron**: `/usr/local/bin/ea-php84` (bukan `php`
  biasa — cron punya `PATH` default terhad)
- **Composer binary**: wujud di `/usr/local/bin/composer`, tapi **tak
  boleh dipanggil dari cron** — network outbound dari proses cron nampak
  disekat (composer install hang tanpa habis, tanpa error). Jangan cuba
  run `composer install` via cron.
- **DNS**: domain `kretiv.co` guna Cloudflare (bukan DNS Exabytes) —
  rekod DNS baru (`jobs.kretiv.co`) kena ditambah di **Cloudflare
  dashboard** (proxy status: **DNS only**), BUKAN cPanel Zone Editor.
- **`config:cache`**: jangan guna. OPcache PHP-FPM server ni nampak
  simpan bytecode lama fail `bootstrap/cache/config.php` walaupun fail tu
  berubah (`opcache.validate_timestamps` kemungkinan `0`). Biarkan config
  uncached (`.env` dibaca terus setiap request).

### Cara update kod (bila ada perubahan)

1. **Aku (Claude) push kod baru ke branch `main`** di GitHub.
2. Buka cPanel → **Git Version Control** → **Manage** repo "Kretivco Jobs"
   → tab **Pull or Deploy** → **Update from Remote** (git pull).
3. **Kalau composer.json berubah** (dependency PHP baru) — `vendor/` kena
   dibina semula secara **local** (bukan di server) sebab composer tak
   boleh run di server ni:
   - Aku run `composer install --no-dev --optimize-autoloader` di local,
     zip folder `vendor/`, hantar fail zip.
   - Upload ke `jobs-kretiv/` (root, bukan dalam `public/`) via File
     Manager, extract, replace folder `vendor/` lama.
4. **Kalau ada migration database baru** — run sekali via Cron Jobs:
   ```
   cd /home/cpaneluser/jobs-kretiv && /usr/local/bin/ea-php84 artisan migrate --force > /home/cpaneluser/jobs-kretiv/deploy-update.log 2>&1
   ```
   (Set jadual 2-3 minit akan datang, check log, **padam cron job lepas
   siap** — jangan biar cron ni kekal berulang.)
5. **Kalau CSS/JS (Tailwind/Blade) berubah** — fail compiled
   (`public/build/`) kena dibina semula secara local (`npm run build`)
   dan upload/extract macam vendor/ di atas.
6. **Blade view (.blade.php) yang berubah tanpa kelas Tailwind baru** —
   biasanya auto-refresh sendiri, tak perlu apa-apa command tambahan.
