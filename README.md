![Laravel](https://github.com/ikidnapmyself/pp-api/workflows/Laravel/badge.svg?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/swinn-io/server/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/swinn-io/server/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/swinn-io/server/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/swinn-io/server/?branch=master)
[![Build Status](https://scrutinizer-ci.com/g/swinn-io/server/badges/build.png?b=master)](https://scrutinizer-ci.com/g/swinn-io/server/build-status/master)
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
