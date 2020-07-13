![Laravel](https://github.com/ikidnapmyself/pp-api/workflows/Laravel/badge.svg)

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

Migrate:
```
php artisan migrate
```

Refresh Migrations with Passport Installer (say yes to refresh migrations):
```
php artisan passport:install --uuids
```
