# Design: Resurrect swinn-server — Laravel/PHP upgrade + CI

## Goal

This app has sat on Laravel 8.75 / PHP ^8.0 for ~5 years. Bring it up to
Laravel 13 / PHP 8.3, modernize dependencies, and add GitHub Actions CI that
runs on every push and PR. No frontend work in this pass.

## Out of scope

- `resources/views`, `resources/js`, `resources/css`, `webpack.mix.js`,
  `tailwind.config.js`, `routes/web.php`, and the `FrontEnd\*` /
  `LoginController` controllers are left completely untouched. Do not edit,
  delete, or attempt to build/run them. They will be reworked in a separate
  pass later.
- No adoption of the Laravel 11+ slim app skeleton (no `Kernel.php` removal,
  no `bootstrap/app.php` rewrite). In-place upgrades keep the existing
  Kernel/Provider structure — that restructure is optional and would add
  risk for no behavioral benefit here.

## Approach

Walk the Laravel upgrade guides in order — 8→9→10→11→12→13 — applying each
version's breaking changes, landing on a single PR with Laravel 13 / PHP 8.3.
Going version-by-version (rather than guessing a single diff) is what makes a
5-major-version jump tractable and verifiable at each step.

Known package moves along the way:
- `fideloper/proxy`, `fruitcake/laravel-cors` — drop (merged into Laravel core)
- `facade/ignition` → `spatie/laravel-ignition`
- `fzaninotto/faker` → `fakerphp/faker`
- `laravel/passport` — must bump in lockstep with Laravel; latest passport
  requires Laravel ^11.35+, so it can't jump straight to latest until the
  app reaches Laravel 11
- `laravel/sanctum`, `laravel/socialite`, `laravel/telescope`,
  `cmgmyr/messenger` — all have releases supporting Laravel 13; bump each at
  the matching Laravel major
- `simplesoftwareio/simple-qrcode` compatibility with Laravel 13/PHP 8.3 is
  unconfirmed — verify during execution; if abandoned/incompatible, surface
  it rather than silently swapping it out

## Verification per step

After each major-version bump: `composer install`, `php artisan migrate`,
run PHPUnit suite, boot `php artisan serve` and hit a couple of API routes
smoke-test style. Do not proceed to the next major until the current one is
green.

## CI

Replace `.github/workflows/laravel.yml` with a current-day workflow
(`actions/checkout@v4`, `shivammathur/setup-php` pinned to 8.3, current MySQL
service image) that runs on every push and every PR targeting `master`:
install deps → copy `.env` → `key:generate` → `migrate --force` →
`passport:install` → PHPUnit.