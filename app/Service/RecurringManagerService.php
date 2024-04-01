<?php

namespace App\Service;

use Carbon\Carbon;
use Illuminate\Support\Str;
use App\Models\RecurringOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Jobs\ProcessMultipleTransactionsJob;

class RecurringManagerService
{
    /**
     * @category Initiate Transaction
     * Fetch insertIncrementAttempts and Initiate the transaction.
     * @param int $entity_id = 11
     * @throws \Exception
     */
    public function initiateTransaction($entity_id = 11, $context)
    {
        // DB::beginTransaction();
        $cron = Str::before($context, '_');

        // This code is for retrying the failed attempts in the recurring orders table where progress = "failed"
        if ($cron == "retry") {
            //1) Find the attempt number where the count of failed was max
            $max_failed_attempts =  DB::table('recurring_orders')
                ->select(DB::raw('COUNT(recurring_orders.id) as ro_counts'), 'recurring_orders.attempts', 'recurring_orders.progress')
                ->where([
                    'execution_date' => Carbon::today(),
                    'progress' => 'failed',
                    'entity_id' => $entity_id
                ])
                ->groupBy('recurring_orders.attempts')
                ->groupBy('recurring_orders.progress')
                ->orderBy('ro_counts', 'desc')
                ->first();

            //2) Fetching the max attempts from the recurring orders
            if (isset($max_failed_attempts->attempts)) {
                $attempts = $max_failed_attempts->attempts;
            } else {
                \Log::info("No failed attempts found");
                return false;
            }

            //3) Marking all the recurring orders as failed who is in process at the time of initiating the retry
            // also updated the attempt to the highest attempt of failed RO just to automatically inclued in next cycle of Deduction
            RecurringOrder::where([
                'execution_date' => Carbon::today(),
                'progress' => 'inprocess',
                'entity_id' => $entity_id
            ])->update([
                'progress' => 'failed',
                'attempts' => $max_failed_attempts->attempts
            ]);
        } // code => Retry Cron Ended

        try {
            $flag = 1;
            while ($flag > 0) {

                // This code is for initiating the recurring orders
                if ($cron == "initiate") {
                    $recurring_orders =  RecurringOrder::query();

                    // Select from recurring_orders
                    $recurring_orders->select('id');
                    $recurring_orders->where(
                        'execution_date',
                        today()
                    );
                    $recurring_orders->where('progress', "new");
                    $recurring_orders->where('entity_id', $entity_id);

                    // Fetch recurring orders
                    $flag = $this->fetchTodayRecurringOrders($entity_id);
                }

                // This code is for retrying the failed attempts in the recurring orders table where progress = "failed"
                if ($cron == "retry") {
                    $flag = $this->fetchTodayFailedRecurringOrders($attempts, $entity_id, 'count');
                    $recurring_orders = $this->fetchTodayFailedRecurringOrders($attempts, $entity_id, 'id');
                }

                Log::driver('recurringManager')->info("FLAG 1: " . $flag);

                // Update recurring orders
                $recurring_orders->chunk(500, function ($orders) use ($entity_id) {
                    try {
                        RecurringOrder::whereIn('id', $orders)->where('progress', '<>', 'inprocess')->update(['progress' => 'inprocess']);
                    } catch (\Exception $e) {
                        dd($e);
                        Log::driver('recurringManager')->error("Unable to update recurring orders: " . $e->getMessage());
                    }
                    try {
                        // $queue_name = 'internal_transactions';
                        if ($entity_id == config('constants.entity.Jazz')) {
                            try {
                                // Using Chunks
                                $setsOf25 = collect($orders)->chunk(17);
                                $setsOf25->each(function ($chunk) {
                                    Log::driver('recurringManager')->info("Chunk Size: " . $chunk->count());
                                    $queue_name = 'jazz_transactions';
                                    ProcessMultipleTransactionsJob::dispatch($chunk->pluck('id')->toArray())->onQueue($queue_name);
                                    Log::driver('recurringManager')->info("Queue Dispatched: " . $queue_name);
                                });
                            } catch (\Throwable $th) {
                                Log::driver('recurringManager')->error("Unable to dispatch queue: " . $th->getMessage());
                                // DB::rollBack();
                            }
                        } else {
                            // Todo : Hold this code for now. (Other than Jazz)
                        }
                    } catch (\Exception $e) {
                        dd($e);
                        Log::driver('recurringManager')->error("Unable to initiate transaction: " . $th->getMessage());
                        // DB::rollBack();
                    }
                });
                if ($cron == "initiate") {
                    $flag = $this->fetchTodayRecurringOrders($entity_id);
                }
                if ($cron == "retry") {
                    $flag = $this->fetchTodayFailedRecurringOrders($attempts, $entity_id, 'count');
                }
                Log::driver('recurringManager')->info("FLAG 2: " . $flag);
                // DB::commit();
            }
        } catch (\Exception $e) {
            dd($e);
            Log::driver('recurringManager')->error("Unable to initiate transaction: " . $e->getMessage());
            // DB::rollBack();
        }
    }

    /**
     * @category Initiate Transaction
     * Fetch insertIncrementAttempts and Initiate the transaction.
     * Run once a day and fetch all the rows from recurring orders with the status "new" and next execution date = "today"
     */
    public function fetchTodayRecurringOrders($entity_id)
    {
        $recurring_results =  RecurringOrder::query();
        $recurring_results->select('id');
        $recurring_results->where(
            'execution_date',
            Carbon::today()
        );
        $recurring_results->where('progress', "new");
        $recurring_results->where('entity_id', $entity_id);
        $data = $recurring_results->count();
        return $data;
    }

    /**
     * @category fetchTodayFailedRecurringOrders
     * Fetch all the rows from recurring orders with the status "failed" and next execution date = "today"
     * @param $attempts
     * @param $entity_id
     * @param $select
     * @return
     * @throws
     */
    public function fetchTodayFailedRecurringOrders($attempts, $entity_id, $select)
    {

        $recurring_results =  RecurringOrder::query();
        /* $recurring_results->with(['recurring_request' => function ($query) {
            $query->select('id','entity_id');
        }]);*/
        $recurring_results->select('id');
        $recurring_results->where(
            [
                'execution_date' => Carbon::today(),
                'attempts' => $attempts,
                'progress' => "failed"
            ]
        );
        $recurring_results->where('entity_id', $entity_id);

        if ($attempts % 2 == 0) {
            $recurring_results->orderBy('id', 'asc');
        } else {
            $recurring_results->orderBy('id', 'desc');
        }

        if ($select == 'id') {
            $data = $recurring_results;
        } else {
            $data = $recurring_results->count();
        }

        return $data;
    }

    // Batch Job
    function batchJob()
    {
        // ! Using Batch
        // $setsOfchunk = collect($recurring_orders)->chunk(17);
        // //
        // $chunksToProcess = [];
        // //
        // foreach ($setsOfchunk as $chunk) {
        //     $chunksToProcess[] = new ProcessMultipleTransactionsJob($chunk);
        // }
        // //
        // $batch = Bus::batch($chunksToProcess)->dispatch();
        // //
        // $batch->then(function (Batch $batch) {
        //     Log::driver('recurringManager')->info("Completed");
        // })->catch(function (Batch $batch, Throwable $e) {
        //     Log::driver('recurringManager')->info($e->getMessage());
        // });
    }
}
