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
        // * Jazz Cron Job
        // $schedule->command('app:initiate-transaction-cron initiate_transaction 182')->dailyAt('00:01')->withoutOverlapping();
        // $schedule->command('app:initiate-transaction-cron retry_1 182')->dailyAt('03:00')->withoutOverlapping();
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
