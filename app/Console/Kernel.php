<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // $schedule->command('inspire')->hourly();
        // $schedule->command('getScheduleData')->dailyAt('00:00');
        // $schedule->command('update-expireds-users')->everyMinute();
        // $schedule->command('campaigns:process')->everyMinute();
        // $schedule->command('balance-alerts')->dailyAt('10:00');

        $schedule->command('bookings:reset-status')->dailyAt('00:00');
         $schedule->command('bookings:reset-status')->dailyAt('11:15');
        $schedule->command('bookings:reset-status')->dailyAt('15:45');
        // $schedule->command('bookings:reset-status')->dailyAt('14:07');
        $schedule->command('logs:clear')->daily();
        $schedule->command('app:close-expired-sales')->dailyAt('00:00');
        $schedule->command('tickets:check-soldout')->everyFiveMinutes();
      $schedule->command('events:close-expired')->dailyAt('00:00');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__ . '/Commands');

        require base_path('routes/console.php');
    }
}
