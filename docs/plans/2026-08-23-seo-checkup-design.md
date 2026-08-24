# SEO Checkup — design

Add `ikidnapmyself/seo-checkup@v1` to the existing `ci.yml`; no new workflow.

- `tests` job, after PHPUnit, on `pull_request` only: serve the already-built, already-migrated app with `php artisan serve` and audit `/`.
- `seo-production` job on push to `master` (deploy): audit `vars.PRODUCTION_URL` (repo variable, must be set).
- `fail-on: recommended` for both.
- Shared PHP/Composer/Node/build steps extracted into `.github/actions/setup`.
