[![CI](https://github.com/swinn-io/server/actions/workflows/ci.yml/badge.svg)](https://github.com/swinn-io/server/actions/workflows/ci.yml)
[![codecov](https://codecov.io/gh/swinn-io/server/branch/master/graph/badge.svg)](https://codecov.io/gh/swinn-io/server)
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
