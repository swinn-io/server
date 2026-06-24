# Laravel 8 → 13 / PHP 8.3 Upgrade Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Upgrade swinn-server from Laravel 8.75/PHP ^8.0 to Laravel 13/PHP 8.3, walking each major version's breaking changes in order, then add a modern GitHub Actions CI workflow that runs on every push and PR.

**Architecture:** One task per Laravel major version (9 → 10 → 11 → 12 → 13). Each task bumps `composer.json` constraints for that version, applies the breaking changes confirmed against this codebase, runs the full PHPUnit suite plus an artisan-serve smoke test, and commits before moving to the next version. The frontend (`resources/views`, `resources/js`, `resources/css`, `webpack.mix.js`, `tailwind.config.js`, `routes/web.php`, `FrontEnd\*` controllers, `LoginController`) is never touched, built, or run in this plan.

**Tech Stack:** PHP 8.1 → 8.3 (binaries already installed via Homebrew at `/usr/local/opt/php@8.1/bin/php` and `/usr/local/opt/php@8.3/bin/php`), Composer, MySQL 8.0/8.4 (already running locally, credentials `root`/`root` on `127.0.0.1:3306`), PHPUnit, GitHub Actions.

**Working directory:** `.worktrees/laravel-upgrade` (branch `laravel-upgrade`). All commands below assume you're in that directory unless stated otherwise.

---

## Baseline (already verified, do not repeat)

- `composer install` only succeeds locally with PHP 8.1 (`/usr/local/opt/php@8.1/bin/php`) — the locked `nette/schema`, `nette/utils`, `phpspec/prophecy` versions require PHP `<8.2`. This will resolve itself once Task 1 bumps `laravel/framework` and dev deps.
- Test DB: `mysql -u root -h127.0.0.1 -proot -e "CREATE DATABASE IF NOT EXISTS \`swinn-test\`;"` (already created).
- Baseline run confirmed **32 tests, 94 assertions, all passing** on PHP 8.1 / Laravel 8.75, using:
  ```bash
  DB_CONNECTION=mysql DB_DATABASE=swinn-test DB_HOST=127.0.0.1 DB_PORT=3306 DB_USERNAME=root DB_PASSWORD=root \
    /usr/local/opt/php@8.1/bin/php vendor/bin/phpunit
  ```
- This exact command (swap the PHP binary as you progress through versions) is the **verification command for every task below**. Treat it as ground truth — if it doesn't show "OK" with 32+ tests, the task isn't done.

---

## Task 1: Laravel 8 → 9 (PHP 8.1, still)

**Files:**
- Modify: `composer.json`
- Modify: `app/Http/Middleware/TrustProxies.php`
- Modify: `app/Http/Kernel.php:19`
- Modify: `config/database.php` (pgsql block, `schema` key)
- Reference: https://laravel.com/docs/9.x/upgrade

**Step 1: Update composer.json constraints**

In `composer.json` `require`:
- `"laravel/framework": "^9.0"`
- Remove `"fideloper/proxy": "^4.2"` entirely
- Remove `"fruitcake/laravel-cors": "^2.0"` entirely (CORS is built into Laravel core via `\Illuminate\Http\Middleware\HandleCors`)

In `require-dev`:
- Replace `"facade/ignition": "^2.3.6"` with `"spatie/laravel-ignition": "^1.0"`
- Replace `"fzaninotto/faker": "^1.9.1"` with nothing — Laravel 9's scaffolding uses `fakerphp/faker`, but check first: run `grep -rn "Faker\\\\" tests/ database/` and only add `"fakerphp/faker": "^1.9.1"` if Faker is actually used (the baseline test run did not reference it directly, but `database/factories` might).
- Bump `"nunomaduro/collision": "^6.1"`

**Step 2: Run composer update for just these packages**

```bash
/usr/local/opt/php@8.1/bin/php /usr/local/bin/composer.phar update \
  laravel/framework spatie/laravel-ignition nunomaduro/collision \
  --with-all-dependencies --no-interaction --no-progress
```

If this fails because `laravel/passport`, `laravel/telescope`, `laravel/socialite`, or `cmgmyr/messenger` won't resolve against Laravel 9 yet, run a full `composer update --with-all-dependencies` instead — Composer will pick compatible minor versions of those packages automatically since none of them have been version-pinned away from Laravel 9 support yet.

**Step 3: Fix TrustProxies middleware**

`app/Http/Middleware/TrustProxies.php` — change:
```php
use Fideloper\Proxy\TrustProxies as Middleware;
```
to:
```php
use Illuminate\Http\Middleware\TrustProxies as Middleware;
```
and change:
```php
protected $headers = Request::HEADER_X_FORWARDED_ALL;
```
to:
```php
protected $headers =
    Request::HEADER_X_FORWARDED_FOR |
    Request::HEADER_X_FORWARDED_HOST |
    Request::HEADER_X_FORWARDED_PORT |
    Request::HEADER_X_FORWARDED_PROTO |
    Request::HEADER_X_FORWARDED_AWS_ELB;
```

**Step 4: Fix CORS middleware in Kernel**

`app/Http/Kernel.php:19` — change:
```php
\Fruitcake\Cors\HandleCors::class,
```
to:
```php
\Illuminate\Http\Middleware\HandleCors::class,
```

**Step 5: Fix Postgres schema config key**

`config/database.php` — in the `pgsql` connection block, rename:
```php
'schema' => 'public',
```
to:
```php
'search_path' => 'public',
```

**Step 6: Run the test suite**

```bash
DB_CONNECTION=mysql DB_DATABASE=swinn-test DB_HOST=127.0.0.1 DB_PORT=3306 DB_USERNAME=root DB_PASSWORD=root \
  /usr/local/opt/php@8.1/bin/php vendor/bin/phpunit
```
Expected: OK, same 32 tests passing. If anything fails, check it against the "Symfony Mailer", "$dates removed", or "assertDeleted → assertModelMissing" sections of the L9 upgrade guide linked above — none of those should currently be hit by this app's code, but if a test fails on one of them, fix it there.

**Step 7: Smoke test**

```bash
DB_CONNECTION=mysql DB_DATABASE=swinn-test DB_HOST=127.0.0.1 DB_PORT=3306 DB_USERNAME=root DB_PASSWORD=root \
  /usr/local/opt/php@8.1/bin/php artisan serve --port=8123 &
sleep 2
curl -s -o /dev/null -w "%{http_code}\n" http://127.0.0.1:8123/api/user
kill %1
```
Expected: some HTTP status (401/200), not a 500 crash page. A 500 means a provider/middleware didn't load — investigate before continuing.

**Step 8: Commit**

```bash
git add composer.json composer.lock app/Http/Middleware/TrustProxies.php app/Http/Kernel.php config/database.php
git commit -m "Upgrade to Laravel 9: drop fideloper/proxy and fruitcake/laravel-cors for core equivalents"
```

---

## Task 2: Laravel 9 → 10 (bump to PHP 8.1 minimum, already satisfied)

**Files:**
- Modify: `composer.json`
- Modify: `phpunit.xml`
- Modify: `app/Models/Participant.php`
- Modify: `app/Providers/AuthServiceProvider.php`
- Reference: https://laravel.com/docs/10.x/upgrade

**Step 1: Update composer.json**

In `require`: `"laravel/framework": "^10.0"`, `"laravel/passport": "^11.0"`, `"laravel/sanctum": "^3.2"`.
In `require-dev`: `"spatie/laravel-ignition": "^2.0"`, `"phpunit/phpunit": "^10.0"`, `"nunomaduro/collision": "^7.0"`.
Remove the `"minimum-stability": "dev"` line (default `stable` is correct, leftover from old scaffolding — leaving it as `dev` risks pulling unstable transitive packages during the remaining upgrades).

**Step 2: composer update**

```bash
/usr/local/opt/php@8.1/bin/php /usr/local/bin/composer.phar update --with-all-dependencies --no-interaction --no-progress
```

**Step 3: Remove the PHPUnit 10-incompatible coverage attribute**

`phpunit.xml` — delete the `processUncoveredFiles="true"` attribute from the `<coverage>` tag (PHPUnit 10 removed it):
```xml
<coverage processUncoveredFiles="true">
```
becomes:
```xml
<coverage>
```

**Step 4: Remove deprecated `registerPolicies()` call**

`app/Providers/AuthServiceProvider.php` — Laravel 10 calls `registerPolicies()` automatically. Remove the line `$this->registerPolicies();` from `boot()`.

**Step 5: Migrate Participant model off `$dates`**

`app/Models/Participant.php` — the `$dates` property was removed in Laravel 10. Change:
```php
protected $dates = ['last_read', 'created_at', 'updated_at', 'deleted_at'];
```
to:
```php
protected $casts = [
    'last_read' => 'datetime',
    'created_at' => 'datetime',
    'updated_at' => 'datetime',
    'deleted_at' => 'datetime',
];
```
Check whether the parent `Cmgmyr\Messenger\Models\Participant` already casts `created_at`/`updated_at`/`deleted_at` (Eloquent does this by default) — if so, you only strictly need `'last_read' => 'datetime'`, but casting all four explicitly is harmless and matches prior behavior exactly.

**Step 6: Run tests, smoke test, commit**

Same commands as Task 1 Steps 6–7 (still using the PHP 8.1 binary — Laravel 10 only requires PHP 8.1+).

```bash
git add composer.json composer.lock phpunit.xml app/Providers/AuthServiceProvider.php app/Models/Participant.php
git commit -m "Upgrade to Laravel 10: PHPUnit 10, drop deprecated \$dates and registerPolicies() call"
```

---

## Task 3: Laravel 10 → 11 (PHP 8.2 required — switch to PHP 8.2 binary)

**Files:**
- Modify: `composer.json`
- Run: `php artisan vendor:publish` for passport/sanctum/telescope migrations
- Modify: `config/sanctum.php` (will be created by publish if it doesn't exist — confirm, since this app doesn't have one yet)
- Modify: `app/Providers/AppServiceProvider.php` (enable Passport password grant)
- Reference: https://laravel.com/docs/11.x/upgrade

**Step 1: Switch PHP binary for all subsequent commands**

From this task onward use `/usr/local/opt/php@8.2/bin/php` (verify it exists: `ls /usr/local/opt/php@8.2/bin/php`; if missing, use 8.3 directly since 8.3 satisfies `>=8.2` too — there's no need to stop at 8.2 specifically, the requirement is just a floor).

**Step 2: Update composer.json**

`require`: `"laravel/framework": "^11.0"`, `"laravel/passport": "^12.0"`, `"laravel/sanctum": "^4.0"`, `"laravel/telescope": "^5.0"`.
`require-dev`: `"nunomaduro/collision": "^8.1"`.
Remove `doctrine/dbal` if present anywhere (it isn't in this app's composer.json, confirm with `grep doctrine composer.json` — skip if absent).

**Step 3: composer update**

```bash
/usr/local/opt/php@8.2/bin/php /usr/local/bin/composer.phar update --with-all-dependencies --no-interaction --no-progress
```

**Step 4: Publish migrations (no longer auto-loaded from vendor)**

```bash
/usr/local/opt/php@8.2/bin/php artisan vendor:publish --tag=passport-migrations
/usr/local/opt/php@8.2/bin/php artisan vendor:publish --tag=sanctum-migrations
/usr/local/opt/php@8.2/bin/php artisan vendor:publish --tag=telescope-migrations
```
These will copy migration files into `database/migrations/`. Check `git status` — if any published migration file is identical (by table/columns) to one already in this app's `database/migrations/` (the app already has its own oauth_* and telescope_entries migrations from 2016/2018), **delete the newly-published duplicate** rather than keeping both; this app wrote its own copies of these migrations long before vendor:publish existed for them. Compare table names with `grep -l "create_oauth\|create_telescope\|create_personal_access_tokens" database/migrations/*.php`.

**Step 5: Enable Passport password grant**

Laravel 11's Passport disables the password grant by default; this app uses password-grant clients (confirmed by `passport:install` baseline output creating a "Password grant client"). Add to `app/Providers/AppServiceProvider.php` `boot()`:
```php
use Laravel\Passport\Passport;

public function boot()
{
    Passport::enablePasswordGrant();
}
```
(Add the `use` import at the top and merge into the existing `boot()` method body if one already exists — check the file first.)

**Step 6: Update Sanctum config middleware references**

If `config/sanctum.php` now exists after publishing (it may not, since this app never published it — check `ls config/sanctum.php`), update its `middleware` array to:
```php
'middleware' => [
    'authenticate_session' => Laravel\Sanctum\Http\Middleware\AuthenticateSession::class,
    'encrypt_cookies' => Illuminate\Cookie\Middleware\EncryptCookies::class,
    'validate_csrf_token' => Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class,
],
```
If `config/sanctum.php` doesn't exist, skip this step — Sanctum is using framework defaults and isn't actually wired into any model (`User` uses `Laravel\Passport\HasApiTokens`, not Sanctum's), so there's nothing to update.

**Step 7: Run tests, smoke test, commit**

```bash
DB_CONNECTION=mysql DB_DATABASE=swinn-test DB_HOST=127.0.0.1 DB_PORT=3306 DB_USERNAME=root DB_PASSWORD=root \
  /usr/local/opt/php@8.2/bin/php artisan migrate --force
DB_CONNECTION=mysql DB_DATABASE=swinn-test DB_HOST=127.0.0.1 DB_PORT=3306 DB_USERNAME=root DB_PASSWORD=root \
  /usr/local/opt/php@8.2/bin/php vendor/bin/phpunit
```
Run the same `artisan serve` smoke test as Task 1 Step 7 with the 8.2 binary.

```bash
git add -A
git commit -m "Upgrade to Laravel 11: publish passport/sanctum/telescope migrations, enable password grant"
```

---

## Task 4: Laravel 11 → 12 (PHP 8.2 still sufficient)

**Files:**
- Modify: `composer.json`
- Modify: any model using `HasUuids` (check `app/Traits/HasUUID.php`)
- Reference: https://laravel.com/docs/12.x/upgrade

**Step 1: Update composer.json**

`require`: `"laravel/framework": "^12.0"`.
`require-dev`: `"phpunit/phpunit": "^11.0"`.

**Step 2: composer update**

```bash
/usr/local/opt/php@8.2/bin/php /usr/local/bin/composer.phar update --with-all-dependencies --no-interaction --no-progress
```

**Step 3: Check the app's `HasUUID` trait**

Read `app/Traits/HasUUID.php`. If it uses Laravel's `Illuminate\Database\Eloquent\Concerns\HasUuids` trait, Laravel 12 changes its UUID generation to v7 (ordered) by default. Since this app generates its own UUIDs via a custom trait (not Laravel's `HasUuids`), this almost certainly doesn't apply — confirm by reading the file, and only act if it actually imports `HasUuids`.

**Step 4: Run tests, smoke test, commit**

Same verification pattern as prior tasks (PHP 8.2 binary, full PHPUnit run + serve smoke test).

```bash
git add composer.json composer.lock
git commit -m "Upgrade to Laravel 12"
```
(Add any `app/Traits/HasUUID.php` change to the commit if Step 3 required one.)

---

## Task 5: Laravel 12 → 13 (switch to PHP 8.3)

**Files:**
- Modify: `composer.json`
- Reference: https://laravel.com/docs/13.x/upgrade

**Step 1: Switch to the PHP 8.3 binary for all commands from here on**: `/usr/local/opt/php@8.3/bin/php`.

**Step 2: Update composer.json**

`require`: `"php": "^8.3"`, `"laravel/framework": "^13.0"`, `"laravel/tinker": "^3.0"`.
`require-dev`: `"phpunit/phpunit": "^12.0"`.

**Step 3: composer update**

```bash
/usr/local/opt/php@8.3/bin/php /usr/local/bin/composer.phar update --with-all-dependencies --no-interaction --no-progress
```

If `simplesoftwareio/simple-qrcode` (last released as `4.2.0`, no explicit Laravel version constraint) fails to resolve or its `bacon/bacon-qr-code` dependency conflicts with the rest of the stack, that's the one open risk flagged in the design doc. If it breaks: check `composer why-not simplesoftwareio/simple-qrcode <version>` for the conflict, and if it's genuinely incompatible, stop and report back rather than silently removing the QR code feature — it may be used by a controller (`grep -rn "QrCode\|simple-qrcode" app/`).

**Step 4: Apply the only behavior-relevant Laravel 13 change for this app**

The CSRF middleware rename (`VerifyCsrfToken` → `PreventRequestForgery`) only matters if the app references `VerifyCsrfToken::class` directly somewhere other than `app/Http/Middleware/VerifyCsrfToken.php` (the app's own subclass, which still works fine as a deprecated alias target). Run:
```bash
grep -rn "VerifyCsrfToken" app/ routes/ tests/
```
If the only hits are the app's own `App\Http\Middleware\VerifyCsrfToken` class definition and its registration in `Kernel.php`, no change is needed — that's a local subclass name, not a reference to the framework's renamed class, and Laravel keeps `VerifyCsrfToken` as a working deprecated alias.

**Step 5: Run tests, smoke test, commit**

```bash
DB_CONNECTION=mysql DB_DATABASE=swinn-test DB_HOST=127.0.0.1 DB_PORT=3306 DB_USERNAME=root DB_PASSWORD=root \
  /usr/local/opt/php@8.3/bin/php vendor/bin/phpunit
```
Plus the `artisan serve` smoke test with the 8.3 binary.

```bash
git add composer.json composer.lock
git commit -m "Upgrade to Laravel 13 / PHP 8.3"
```

---

## Task 6: Modernize CI workflow

**Files:**
- Modify: `.github/workflows/laravel.yml` (rename to `.github/workflows/ci.yml`)

**Step 1: Replace the workflow file**

Delete `.github/workflows/laravel.yml` and create `.github/workflows/ci.yml`:

```yaml
name: CI

on:
  push:
  pull_request:

jobs:
  tests:
    runs-on: ubuntu-latest

    services:
      mysql:
        image: mysql:8.0
        env:
          MYSQL_ROOT_PASSWORD: root
          MYSQL_DATABASE: swinn-test
        ports:
          - 3306:3306
        options: >-
          --health-cmd="mysqladmin ping"
          --health-interval=10s
          --health-timeout=5s
          --health-retries=5

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          extensions: mbstring, dom, fileinfo, mysql

      - name: Copy .env
        run: php -r "file_exists('.env') || copy('.env.example', '.env');"

      - name: Install dependencies
        run: composer install -q --no-ansi --no-interaction --no-scripts --no-progress --prefer-dist

      - name: Generate key
        run: php artisan key:generate

      - name: Directory permissions
        run: chmod -R 777 storage bootstrap/cache

      - name: Wait for MySQL
        run: |
          for i in $(seq 1 30); do
            mysqladmin ping -h127.0.0.1 -uroot -proot --silent && break
            sleep 1
          done

      - name: Run migrations
        env:
          DB_CONNECTION: mysql
          DB_HOST: 127.0.0.1
          DB_PORT: 3306
          DB_DATABASE: swinn-test
          DB_USERNAME: root
          DB_PASSWORD: root
        run: php artisan migrate --force

      - name: Install Passport
        env:
          DB_CONNECTION: mysql
          DB_HOST: 127.0.0.1
          DB_PORT: 3306
          DB_DATABASE: swinn-test
          DB_USERNAME: root
          DB_PASSWORD: root
        run: php artisan passport:install --force

      - name: Run test suite
        env:
          DB_CONNECTION: mysql
          DB_HOST: 127.0.0.1
          DB_PORT: 3306
          DB_DATABASE: swinn-test
          DB_USERNAME: root
          DB_PASSWORD: root
        run: vendor/bin/phpunit
```

Note: `on: push` / `on: pull_request` with no `branches:` filter runs on every push to any branch and every PR, per your "PRs only, run on every commit" requirement — broader than the old workflow's `master`-only trigger.

**Step 2: Commit**

```bash
git rm .github/workflows/laravel.yml
git add .github/workflows/ci.yml
git commit -m "Modernize CI workflow: PHP 8.3, run on every push and PR"
```

---

## Task 7: Open the PR

**Step 1:** Push the branch and open a PR (do not push to `master` directly — per standing instruction, all changes from here on go through PRs).

```bash
git push -u origin laravel-upgrade
gh pr create --title "Upgrade to Laravel 13 / PHP 8.3, modernize CI" --body "$(cat <<'EOF'
## Summary
- Walks the Laravel upgrade guides 8→9→10→11→12→13, landing on Laravel 13 / PHP 8.3
- Drops fideloper/proxy and fruitcake/laravel-cors in favor of Laravel core equivalents
- Publishes passport/sanctum/telescope migrations now that they're no longer auto-loaded
- Replaces the CI workflow with one that runs on every push and PR (not just master)
- Frontend (Blade views, Vue/webpack assets, routes/web.php) intentionally untouched

## Test plan
- [ ] Full PHPUnit suite passes locally on PHP 8.3
- [ ] CI workflow passes on the PR
- [ ] `artisan serve` smoke test confirms API routes respond (not 500s)
EOF
)"
```