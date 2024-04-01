<?php

namespace App\Console\Commands;

use Carbon\Carbon;
use Illuminate\Console\Command;
use App\Service\RecurringManager;
use Illuminate\Support\Facades\Cache;
use App\Service\RecurringManagerService;

class InitiateTransactionCron extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:initiate-transaction-cron {transaction_context} {entity_id}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Initiate Transaction Cron Command For Jazz and Telenor and Zong Entities ';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        ini_set('max_execution_time', 0);
        \Log::info("Start Time " . Carbon::now());
        ini_set("memory_limit", "1024M");

        // getting the entity_id from cron params
        $entity_id = $this->argument('entity_id');

        // clear jobs in queues
        if ($entity_id == config('constants.entity.Zong')) {
            \Artisan::call('queue:clear --queue=internal_transactions');
        }

        if ($entity_id == config('constants.entity.Telenor')) {
            \Artisan::call('queue:clear --queue=telenor_transactions');
        }

        // Jazz Entity
        if ($entity_id == config('constants.entity.Jazz')) {
            \Artisan::call('queue:clear --queue=jazz_transactions');
        }

        // Append data in cache.
        Cache::forget('transaction_context_' . $entity_id);
        $context = $this->argument('transaction_context');
        Cache::put('transaction_context_' . $entity_id, $context);

        $recurringManagerObj = new RecurringManagerService();
          $recurringOrderObj = $recurringManagerObj->initiateTransaction($entity_id, $context);

        \Log::info("End Time " . Carbon::now());
        return true;
    }
}
