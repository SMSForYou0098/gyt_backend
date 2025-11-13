<?php

namespace App\Providers;

use App\Models\EmailConfig;
use Config;
use Illuminate\Support\ServiceProvider;
use App\Channels\FirebaseChannel;
use Illuminate\Support\Facades\Notification;


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
        Notification::extend('firebase', function ($app) {
            return new FirebaseChannel();
        });

        $emailConfig = EmailConfig::first();

        if ($emailConfig) {
            $config = [
                'driver' => $emailConfig->mail_driver,
                'host' => $emailConfig->mail_host,
                'port' => $emailConfig->mail_port,
                'username' => $emailConfig->mail_username,
                'password' => $emailConfig->mail_password,
                'encryption' => $emailConfig->mail_encryption,
                'from' => [
                    'address' => $emailConfig->mail_from_address,
                    'name' => $emailConfig->mail_from_name,
                ],
            ];
            Config::set('mail', $config);
        }
    }
}
