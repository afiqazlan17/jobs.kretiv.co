# Kretiv OS — pelan (draf untuk semakan)

Status: **draf, belum dibina.** Semak, ubah, kemudian beri lampu hijau.

## Idea

Kretiv OS ialah pintu masuk kerja. Semua staff login di sini dahulu, dan
dari sini masuk ke modul yang mereka dibenarkan: **Jobs**, **Finance**, **HR**.
Staff dalaman sahaja (kini 3 orang: Afiq, Amirul, Ila). BOD urus akses.

## Seni bina: satu app, banyak modul

- **Satu kod Laravel, satu database, satu akaun per staff.** Jobs, Finance
  dan HR ialah modul dalam kod yang sama (`app/Modules/...` atau kumpulan
  route/controller/view mengikut modul), bukan app berasingan.
- **Empat subdomain, semuanya menunjuk ke app yang sama.** App tahu modul
  mana daripada host:

  | Domain | Fungsi |
  |---|---|
  | `os.kretiv.co` | login, launcher, Users & Access, (kelak) dashboard gabungan |
  | `jobs.kretiv.co` | modul Jobs (URL sedia ada, tak berubah) |
  | `finance.kretiv.co` | modul Finance |
  | `hr.kretiv.co` | modul HR |

- **Kenapa bukan app berasingan:** cPanel tanpa SSH dan composer di server.
  Setiap app baru = repo, `vendor.zip`, cron migrate dan `.env` tambahan
  serta SSO yang perlu dibina. Modular monolith memberi pengasingan akses
  yang sama, dan Finance baca ledger terus (tiada API/sync). Modul boleh
  dicabut keluar kemudian jika perlu.

## Login dan sesi

- Login **hanya di `os.kretiv.co`**. Modul lain, jika belum login, hantar ke
  OS dan kembali ke halaman asal selepas login.
- Sesi dikongsi antara subdomain: `SESSION_DOMAIN=.kretiv.co`,
  `SESSION_SECURE_COOKIE=true`, `SESSION_DRIVER=database`, `APP_KEY` sama
  (memang sama kerana satu app). Cookie disulitkan dengan `APP_KEY`, jadi
  tapak lain di `*.kretiv.co` (cth. `chefammar.kretiv.co`) tak boleh baca.
- Logout di mana-mana = logout di semua (sesi yang sama).
- Sekali login pertama selepas deploy, semua staff akan dilog keluar sekali.

## Akses

- Lajur baru `users.modules` (JSON, boleh kosong) dengan modul: `jobs`,
  `finance`, `hr`. Kosong = guna lalai role; BOD simpan senarai eksplisit
  untuk menggantikan. **BOD sentiasa ada semua modul.**
- Role `finance` ditambah (di sebelah bod / dept_head / staff / intern).
- Lalai bila akaun dibuat (BOD boleh tukar): staff dan intern = Jobs + HR;
  dept_head = Jobs + Finance + HR; finance = Finance + HR.
- Middleware `module:<nama>` pada setiap kumpulan route. Tanpa akses =
  403. Menu dan launcher hanya tunjuk modul yang dibenarkan.
- **Users & Access** (di OS, BOD sahaja): senarai staff, tanda modul, role,
  aktif/tidak aktif. Menggantikan bahagian pengurusan pengguna dalam
  Settings Jobs.
- Nombor duit (revenue, pipeline, ledger, kos vendor) di modul Jobs hanya
  nampak untuk BOD, Dept Head dan Finance. Harga dalam dokumen dan line item
  kekal untuk staff Jobs (itu kerja mereka).

## Fasa

### Fasa 1 — Asas OS
1. Config domain (`OS_HOST`, `JOBS_HOST`, `FINANCE_HOST`, `HR_HOST` dalam
   `.env`), route dibahagi mengikut host, middleware modul.
2. Login dipindah ke OS; redirect-balik selepas login.
3. Launcher di `os.kretiv.co` (kad modul mengikut akses).
4. Users & Access; migrasi `user_modules` + role `finance`; isi lalai untuk
   pengguna sedia ada.
5. Ujian: akses, redirect, sekatan host.

### Fasa 2 — Finance
1. Pindah menu dan laporan Finance dari Jobs ke `finance.kretiv.co`.
2. Invoice/Receipt di Jobs terus posting ke ledger (kod dan DB sama).
3. Sorok nombor duit di Jobs ikut role.
4. Page Pembayaran Vendor (kos vendor belum bayar merentasi job + tanda paid
   yang posting ke ledger).

### Fasa 3 — HR (urutan)
1. Announcement dan memo (tunjuk di launcher).
2. Apply cuti: staff mohon, **BOD lulus** (kelak Head of Department), baki
   cuti dikira.
3. Slip gaji: **muat naik PDF per staff**, staff nampak slip sendiri
   sahaja, disimpan di storan peribadi (bukan public). Tiada kira gaji.
4. Onboarding dan training (senarai semak, bahan).

## Kerja di cPanel/Cloudflare (kau buat, aku bagi langkah tepat)

Untuk setiap `os`, `finance`, `hr`:
1. cPanel → Domains → tambah subdomain, document root sama dengan jobs
   (`repositories/jobs.kretiv.co/public`).
2. Cloudflare DNS: rekod `os` / `finance` / `hr` (sama seperti `jobs`).
3. Pastikan SSL aktif (AutoSSL) sebelum guna.
4. Kemas kini `.env` (host dan sesi) dan jalankan cron migrate sekali.

## Soalan terbuka

- Login OS dianggap **kehadiran** (clock-in masuk kerja)? Jika ya, ia masuk
  fasa HR (kehadiran) dan kita rekod masa login pertama setiap hari.
- Apa yang mahu ditunjuk di launcher selain kad modul (announcement terkini,
  jobs aktif, duit masuk, cuti hari ini)?
