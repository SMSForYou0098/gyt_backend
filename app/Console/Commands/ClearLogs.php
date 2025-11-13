<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ClearLogs extends Command
{
    protected $signature = 'logs:clear';
    protected $description = 'Clear application logs';

    public function handle()
    {
        $logFile = storage_path('logs/laravel.log');

        if (File::exists($logFile)) {
            File::put($logFile, ''); // Clear the log file
            $this->info('Application logs cleared!');
        } else {
            $this->info('Log file does not exist.');
        }
    }
}
