web: cp -n .env.example .env; export PHP_CLI_SERVER_WORKERS=4; php artisan key:generate --force; php artisan migrate --force; php artisan storage:link; php artisan serve --host 0.0.0.0 --port=$PORT
