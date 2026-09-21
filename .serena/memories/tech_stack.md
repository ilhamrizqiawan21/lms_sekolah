# Stack constraints
- PHP 8.3+ / Laravel 13; current Lerd runtime for this repo is PHP 8.5.
- Vue + Inertia frontend, TypeScript, Vite; npm selected by package-lock.json.
- Composer autoload includes app/Helpers/SchoolSettingHelper.php in addition to App PSR-4.
- Production-sensitive drivers/config are database-backed for cache, queue, session; verify `.env` separately from `.env.example`.
- Lerd site domain is discovered, currently lms_sekolah.test with TLS enabled; use Lerd rather than host PHP/Composer/Artisan.
