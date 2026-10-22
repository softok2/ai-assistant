# Stack upgrade baseline (branch upgrade/laravel-13-php-8.5, from stage)

- Tooling: PHP 8.3.13 (php@8.3, because the current composer.lock does not install on 8.5), Composer 2.9.7, Node v22.22.2, npm 10.9.7.
- `composer install` with the current lock: OK.
- `artisan test`: 313 passed (1199 assertions), 0 failures, 16.33s. No pre-existing failures.
- `npm ci` OK (npm audit reports vulnerabilities in current lock). `npm run build`: OK, "built in 4.13s".
- Build warning: chunk size (>500 kB) for Show-*.js (1,119 kB); no other warnings.
