<?php

namespace App\Providers;

use Illuminate\Foundation\DevCommands;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // php artisan dev のサーバーに php.d/*.ini（アップロード上限など）を読ませ、
        // OCR のストリーミング中も他のリクエストに応答できるよう複数ワーカーで起動する
        DevCommands::register(sprintf(
            'env PHP_INI_SCAN_DIR=%s PHP_CLI_SERVER_WORKERS=4 php artisan serve --no-reload',
            escapeshellarg(':'.base_path('php.d')),
        ), 'server');
    }
}
