# Development workflow

- Project: `lms_sekolah`. Use this repository as the Serena project. For application-code changes, map relevant symbols and references; documentation-only edits do not need source-code exploration.
- Scale planning, implementation, tests, review, and verification to task risk. Use relevant ECC skills; do not invoke every skill or stage as a separate ceremony. Use Context7 when checking current Laravel, Vue, TypeScript, Vite, or third-party APIs.
- Discover the site with Lerd, then use the explicit repository path for PHP, Composer, Artisan, tests, database operations, and logs. The currently registered HTTPS domain is `https://lms_sekolah.test`; verify it from Lerd before browser testing.
- The current JavaScript package manager is npm, detected from `package-lock.json`; re-check the manifest and lockfile before installing dependencies. Prefer Lerd's declared Vite worker for development.
- Discover installed test tools using Lerd `exec` action `vendor_bins`, then run PHPUnit with `vendor_run`. Check `phpunit.xml` and test bootstrap before database-changing tests; tests must use isolated data.
- For browser-visible changes, use Playwright to verify the affected workflow through the Lerd domain. Documentation-only changes require diff/reference review instead of application or browser tests. Do not seed/reset the application database or invent login credentials for browser checks.
- Report changes, tests actually run, and any remaining limitations. Keep application code changes scoped to the requested task.
