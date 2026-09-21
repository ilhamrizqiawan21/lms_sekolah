# Tool commands
- Discover: Lerd site(action=list), then use path=/home/ilhamzp/Projects/lms_sekolah.
- Backend checks: Lerd exec vendor_bins; composer validate; artisan about; artisan migrate:status; artisan route:list.
- Tests: Lerd exec vendor_run bin=phpunit; phpunit.xml isolates tests on SQLite :memory: with array cache/session and sync queue.
- Formatting: Lerd exec vendor_run bin=pint args=["--test"]; composer lint is an explicit subset, not whole-app coverage.
- Frontend: npm run typecheck/build; browser tests require reviewing isolation/seed behavior first.
