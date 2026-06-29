# PHP Linting & Formatting — Design

**Status:** Approved 2026-06-29
**Scope:** Replace the hosted StyleCI formatter with a modern, locally-runnable toolchain — Laravel Pint (formatting) + Larastan/PHPStan (static analysis) — and gate them in CI.
**Delivery:** single branch `chore/php-linting-formatting`, one PR off `master`.

---

## 1. Goal

Give the project industry-standard PHP code quality tooling that every contributor can run locally and that CI enforces on every push/PR:

- **Formatting:** Laravel Pint (official, opinionated PHP-CS-Fixer wrapper) with the `laravel` preset — the same style family the repo already targets via StyleCI, but runnable on a developer's machine.
- **Static analysis:** Larastan (PHPStan + Laravel-aware extensions) at the strictest level (`max`), with a baseline so adoption is green on day one and violations are ratcheted down over time.

StyleCI (the hosted SaaS configured via `.styleci.yml`) is retired — Pint supersedes it.

## 2. Decisions

- **Pint + Larastan**, not Pint-only (no real linting) and not +Rector (refactoring is a separate concern, out of scope here).
- **CI fails the build** in check mode (`pint --test`, `phpstan analyse`). Contributors fix locally; no bot fix-up commits.
- **Separate `quality` CI job** parallel to `tests` — it needs no MySQL/Node/Passport, so it is fast and gives an independent signal. The existing `tests` job is unchanged.
- **Larastan `level: max` with a generated baseline.** New code is held to the strictest level immediately; pre-existing violations are captured in `phpstan-baseline.neon` and burned down incrementally rather than blocking this PR.
- **PHP 8.3** only (matches CI and `composer.json`).

## 3. Components

| Component | Path | Responsibility |
|-----------|------|----------------|
| Pint | `laravel/pint` (require-dev) | Formatter. |
| Pint config | `pint.json` | `laravel` preset; skip `*.blade.php`; exclude `bootstrap`, `storage`. |
| Larastan | `larastan/larastan` ^3 (require-dev) | PHPStan + Laravel rules. |
| PHPStan config | `phpstan.neon` | Includes Larastan; `level: max`; analyses `app`, `config`, `database`, `routes`, `tests`; references the baseline. |
| Baseline | `phpstan-baseline.neon` | Grandfathers current violations. |
| Composer scripts | `composer.json` | `format`, `lint`, `analyse`, `check`. |
| CI | `.github/workflows/ci.yml` | New `quality` job; `tests` job untouched. |
| StyleCI | `.styleci.yml` | **Deleted.** |

## 4. Config Detail

### `pint.json`
```json
{
    "preset": "laravel",
    "exclude": ["bootstrap", "storage"],
    "notName": ["*.blade.php"]
}
```

### `phpstan.neon`
```neon
includes:
    - ./vendor/larastan/larastan/extension.neon
    - ./phpstan-baseline.neon

parameters:
    level: max
    paths:
        - app
        - config
        - database
        - routes
        - tests
    tmpDir: storage/phpstan
```

### `composer.json` scripts
```json
"format": "pint",
"lint": "pint --test",
"analyse": "phpstan analyse",
"check": ["@lint", "@analyse"]
```

## 5. CI Workflow

Add a job; leave `tests` as-is:

```yaml
quality:
  runs-on: ubuntu-latest
  steps:
    - uses: actions/checkout@v4
    - name: Setup PHP 8.3
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.3'
        extensions: mbstring, pdo, pdo_mysql, xml, curl
        coverage: none
    - name: Install Dependencies
      run: composer install -q --no-ansi --no-interaction --no-scripts --no-progress --prefer-dist
    - name: Check formatting (Pint)
      run: vendor/bin/pint --test
    - name: Static analysis (Larastan)
      run: vendor/bin/phpstan analyse --no-progress
```

## 6. Rollout

1. Add tooling + config; generate the baseline so `phpstan analyse` is green.
2. Run `pint` once over the codebase — a single "apply Pint formatting" commit, kept separate from the tooling-add commit for a reviewable diff.
3. Wire the CI `quality` job; delete `.styleci.yml`.
4. Verify locally: `composer lint`, `composer analyse`, and the full PHPUnit suite all green under PHP 8.3.

**Note (operator action, outside the codebase):** removing `.styleci.yml` leaves the StyleCI GitHub app (if still installed) inert. Fully uninstalling it from the repo's GitHub settings is a dashboard action the maintainer should do separately.

## 7. Out of Scope

- Rector / automated refactoring.
- JS/CSS/Vue formatting (Prettier/ESLint) — `.styleci.yml` already had those disabled.
- Lowering the baseline (the incremental burn-down happens in later PRs).