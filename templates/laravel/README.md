# Laravel Starter

Copy the files from `examples/laravel/` into a Laravel app:

- `config-aif.php` to `config/aif.php`
- `AifServiceProvider.php` and `LaravelExecutionContextResolver.php` to an application provider namespace
- `AifController.php` to `app/Http/Controllers`
- `routes.php` into your authenticated API routes

The example keeps all AI calls behind `AifApi` and `AifGateway`.

