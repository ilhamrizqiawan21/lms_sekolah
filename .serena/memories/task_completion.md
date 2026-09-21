# Completion checks
- Read-only backend audit: site_doctor, env check, vendor_bins, composer validate, artisan about/migrate:status/route:list as applicable.
- Code changes: run affected PHPUnit via Lerd, Pint on changed PHP paths, then broader tests proportional to risk.
- Do not reset/seed the development DB for verification; phpunit test config uses isolated in-memory SQLite.
- Report actual commands/results and distinguish environment health from application coverage.
