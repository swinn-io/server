[![CI](https://github.com/swinn-io/server/actions/workflows/ci.yml/badge.svg)](https://github.com/swinn-io/server/actions/workflows/ci.yml)
[![Coverage Status](https://coveralls.io/repos/github/swinn-io/server/badge.svg?branch=master)](https://coveralls.io/github/swinn-io/server?branch=master)
[![StyleCI](https://github.styleci.io/repos/276172517/shield?branch=master)](https://github.styleci.io/repos/276172517?branch=master)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
## About Swinn

@todo

## Installation for development

Install dependencies:
```
composer install
```

Crete environment:
```
php -r "file_exists('.env') || copy('.env.example', '.env');"
```

Install Telescope:
```
php artisan telescope:install
```

Migrate:
```
php artisan migrate
```

Refresh Migrations with Passport Installer (say yes to refresh migrations):
```
php artisan passport:install
```

Seed database:
```
php artisan db:seed
```

## Running the app (with real-time messaging)

Reverb requires a persistent process, and message broadcasts are queued — both must be running alongside the usual dev server:

(make sure `REVERB_APP_ID`/`REVERB_APP_KEY`/`REVERB_APP_SECRET` are filled in in your `.env` — `artisan reverb:install` generates these automatically if you haven't run it yet)

```
php artisan reverb:start
php artisan queue:work
npm run dev
```
