# Development workflow

This is the canonical shared workflow for any AI agent working in this repository (Claude, Gemini, Codex, etc.). Agent-specific files (`CLAUDE.md`, `AI_RULES.md`, `GEMINI.md`) must not repeat what is written here — they should link back to this file and only add what is genuinely specific to that agent.

## Project and environment
- Project: `lms_sekolah`. Use this repository as the Serena project. For application-code changes, map relevant symbols and references; documentation-only edits do not need source-code exploration.
- Local environment is plain PHP/Composer/Node — no Lerd or other wrapper tool. Run `php artisan`, `composer`, and `npm` commands directly from the repository root.
- Local URL: read `APP_URL` from `.env` (or run `php artisan serve` and use the printed URL) instead of assuming a fixed domain.
- Package managers: PHP via Composer (`composer.json`/`composer.lock`); JavaScript via npm, detected from `package-lock.json`. Re-check the manifest/lockfile before installing anything, since this can change.
- Use Context7 when checking current Laravel, Vue, TypeScript, Vite, or third-party API behavior instead of relying on memory.

## Adding or changing dependencies
- Before adding a package (Composer or npm), check whether it is actively maintained and not deprecated/abandoned (check `composer show <pkg>`/Packagist, or `npm view <pkg> deprecated`/npmjs, and last release date).
- Never add a deprecated, abandoned, or archived package without an explicit justification recorded in the commit message or PR description (why no maintained alternative fits, or why migration is out of scope now).
- Prefer a package already used elsewhere in the repo over introducing a second library that solves the same problem.
- Do not upgrade or downgrade unrelated dependencies as a side effect of an unrelated change.

## Testing and coverage
- Backend: run PHPUnit directly, e.g. `composer test` or `vendor/bin/phpunit --filter=<Name>` for a relevant subset first; expand to the full suite for shared-code or authorization/data changes.
- `phpunit.xml` uses SQLite `:memory:`; verify test bootstrap/env overrides before any test that writes data, and never point tests at the development database.
- Coverage requirement: any new or changed backend logic (services, controllers, policies, form requests) must be covered by a Feature or Unit test that exercises the new behavior and its authorization/validation failure paths, not only the happy path. Xdebug is available locally for coverage reports (`vendor/bin/phpunit --coverage-text`) when checking gaps on a change — use it when coverage of the change is in doubt rather than asserting coverage without checking.
- Frontend: `npm run typecheck` and `npm run build` for changes touching `resources/js`; `npm run test:browser` (via Playwright, see `scripts/test-browser.mjs`) for UI-visible workflows only, using isolated data and never invented credentials.
- Do not run `migrate:fresh`, seed, or reset the development database for verification.

## Static analysis
- PHP: no PHPStan/Larastan is currently configured in `composer.json`. Do not assume static analysis runs as part of CI or hooks. If asked to add one, use `larastan/larastan` and wire it into `composer.json` scripts explicitly rather than running an ad hoc, uninstalled tool.
- PHP style (not static analysis): `composer lint` / `composer format` run Pint against the specific file list defined in `composer.json` — not the whole repository. Prefer targeted Pint runs for changed files.
- Frontend: `npm run typecheck` (via `vue-tsc`) is the static type-check gate for TypeScript/Vue and should be treated as required for any `resources/js` change.

## Implementation rules
- Preserve existing role and ownership authorization patterns; do not broaden access as a shortcut for a failing test.
- Scale planning, implementation, tests, review, and verification to task risk. Use relevant ECC skills; do not invoke every skill or stage as a separate ceremony.
- Keep application code changes scoped to the requested task; avoid unrelated refactors.

## Reporting
- Report changes made, commands actually run (with results), and any remaining limitations. Never claim a check ran if it did not.
