# Stack constraints
- PHP 8.3+ / Laravel 13.
- Vue + Inertia frontend, TypeScript, Vite; npm selected by package-lock.json.
- Composer autoload includes app/Helpers/SchoolSettingHelper.php in addition to App PSR-4.
- Production-sensitive drivers/config are database-backed for cache, queue, session; verify `.env` separately from `.env.example`.
- No Lerd or other wrapper environment; run PHP/Composer/Artisan/npm directly. Local URL comes from `APP_URL` in `.env` or `php artisan serve`.
