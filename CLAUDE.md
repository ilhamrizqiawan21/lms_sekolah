# LMS Sekolah project guide

This guide contains repository-specific instructions. Personal working preferences belong in the user's global agent instructions; do not duplicate them here. Read `AGENTS.md` first — it is the canonical shared workflow (environment, dependency policy, testing/coverage, static analysis, reporting). This file only adds what is specific to working here as Claude.

## Stack and code map
- Laravel 13 / PHP, with Inertia 3, Vue 3, TypeScript, and Vite. Check manifests and lockfiles for exact versions.
- Routes: `routes/`; backend: `app/Http/`, `app/Services/`, and `app/Policies/`; frontend: `resources/js/`.
- Tests: `tests/Unit/` and `tests/Feature/`. Inspect only the areas affected by the task.

## Claude-specific notes
- Do not start a second dev server (`php artisan serve`, `npm run dev`) if one is already running for this project in the session; reuse it.
- Before `npm run test:browser`, inspect `scripts/test-browser.mjs` for environment, authentication, and data effects — see `AGENTS.md` for the coverage/isolation requirement.
- For UI behavior changes, verify the affected workflow with Playwright against the URL confirmed from `.env`/`artisan serve` (see `AGENTS.md`). Documentation-only changes need diff/reference review, not application tests.
