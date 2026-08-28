# Kretivco Jobs

Job/CRM/finance dashboard for Kretivco Mediaworks — Laravel + MySQL,
self-hosted on Exabytes cPanel (`jobs.kretiv.co`).

Replaces the Next.js/Vercel/Supabase version at
[`kretivco-jobs-dashboard`](https://github.com/afiqazlan17/kretivco-jobs-dashboard).
Stack and hosting pattern mirror
[`terra_lestari`](https://github.com/afiqazlan17/terra_lestari) (Sajian
Baginda), which already runs successfully on the same cPanel account.

## Stack

- Laravel 13, Breeze (Blade + Alpine.js), MySQL, Tailwind CSS, Vite
- No SSH/Terminal on the target hosting plan — see `DEPLOYMENT.md`

## Local development

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm run build   # or `npm run dev` for hot-reload
php artisan serve
```

## Deployment

See `DEPLOYMENT.md` for the full Exabytes cPanel process (no SSH — builds
happen locally and are uploaded, one-off Artisan commands run via a
temporary Cron Job).
