<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Service\RecurringOrderService;

class InsertRRIntoROCron extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:insert-rr-into-ro-cron';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Insert recurring requests into recurring orders table';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        try {
            \Log::driver('recurringorders')->info("Start Time " . now());
            $update_recurring_req = (new RecurringOrderService())->insertAndUpdateOrders();
            \Log::driver('recurringorders')->info("Total recurring orders created");
            \Log::driver('recurringorders')->info("End Time " . now());
        } catch (\Throwable $th) {
            dd($th);
        }
        return true;
    }
}
