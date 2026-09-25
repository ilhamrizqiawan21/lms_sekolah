# LMS Sekolah project guide

This guide contains repository-specific instructions. Personal working preferences belong in the user's global agent instructions; do not duplicate them here. Read `AGENTS.md` for the shared development workflow.

## Stack and code map
- Laravel 13 / PHP, with Inertia 3, Vue 3, TypeScript, and Vite. Check manifests and lockfiles for exact versions.
- Routes: `routes/`; backend: `app/Http/`, `app/Services/`, and `app/Policies/`; frontend: `resources/js/`.
- Tests: `tests/Unit/` and `tests/Feature/`. Inspect only the areas affected by the task.
- Preserve existing role and ownership authorization patterns; do not broaden access as a shortcut for a failing test.

## Local environment
- Use Lerd for PHP, Composer, Artisan, database operations, and logs. Discover sites first and pass this repository's absolute path explicitly from a projectless session.
- The registered domain was `https://lms_sekolah.test`; confirm it through Lerd before browser checks.
- `package-lock.json` currently selects npm. Recheck `packageManager` and lockfiles before dependency installation.
- Prefer the existing Lerd Vite worker. Do not start a second server or the multi-process Composer development command when equivalent services already run.

## Verification
- Discover PHP tools through Lerd `exec` / `vendor_bins`; run `phpunit` via `vendor_run`, with a relevant test path or `--filter` first.
- `phpunit.xml` currently declares SQLite `:memory:`. Check test bootstrap, environment overrides, and effective isolation before tests that modify data; never reset or seed the application database for verification.
- Frontend checks from `package.json`: `npm run typecheck`, `npm run build`, and `npm run test:browser`. Select checks relevant to the change and run heavy checks sequentially.
- Before `test:browser`, inspect `scripts/test-browser.mjs` for environment, authentication, and data effects. Use isolated data and do not invent credentials.
- Composer `lint` and `format` scripts currently target a specific file list; they are not whole-repository checks. Prefer targeted Pint checks for changed PHP files through Lerd.
- For UI behavior changes, verify the affected workflow using Playwright against the confirmed Lerd domain. Documentation-only changes need diff/reference review, not application tests.
