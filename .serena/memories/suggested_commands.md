# Tool commands
- Backend checks: composer validate; artisan about; artisan migrate:status; artisan route:list.
- Tests: vendor/bin/phpunit or composer test; phpunit.xml isolates tests on SQLite :memory: with array cache/session and sync queue.
- Formatting: vendor/bin/pint --test; composer lint is an explicit subset, not whole-app coverage.
- Frontend: npm run typecheck/build; browser tests require reviewing isolation/seed behavior first.
