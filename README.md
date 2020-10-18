![Laravel](https://github.com/ikidnapmyself/pp-api/workflows/Laravel/badge.svg?branch=master)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/ikidnapmyself/pp-api/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/ikidnapmyself/pp-api/?branch=master)
[![Code Coverage](https://scrutinizer-ci.com/g/ikidnapmyself/pp-api/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/ikidnapmyself/pp-api/?branch=master)
[![Build Status](https://scrutinizer-ci.com/g/ikidnapmyself/pp-api/badges/build.png?b=master)](https://scrutinizer-ci.com/g/ikidnapmyself/pp-api/build-status/master)
[![StyleCI](https://github.styleci.io/repos/276172517/shield?branch=master)](https://github.styleci.io/repos/276172517?branch=master)
 [![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
## About PP API

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
php artisan passport:install --uuids
```
