<?php

namespace App\Jobs;

use Log;
use Carbon\Carbon;
use Illuminate\Bus\Queueable;
use App\Models\RecurringOrder;
use App\Facades\TransactionFacade;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class ProcessMultipleTransactionsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $recurring_orders;
    private $allowedROProgress = ['new', 'failed', 'partial_success', 'inprocess'];
    /**
     * Create a new job instance.
     */
    public function __construct($recurring_orders)
    {
        $this->recurring_orders = $recurring_orders;
    }

    /**
     * Execute the job.
     */
    public function handle()
    {
        try {
            $recurring_orders = $this->recurring_orders;
            // * Add incremanet of all recurring_orders ids
            RecurringOrder::whereIn('id', $recurring_orders)->increment('attempts');

            $recurring_order = RecurringOrder::query()
                ->whereIn("id", $recurring_orders)
                ->with(['recurringRequest', 'recurringRequest.policy' , 'recurringRequest.policy.plan'])
                ->get();

            // * Initiate transactions
            try {
                $transaction_data = TransactionFacade::doMultipleTransactions($recurring_order);
            } catch (\Throwable $th) {
                // Add log into processMultiple logging
                Log::channel('processMultiple')->info("Something Occurred " . $th->getMessage());
                return false;
            }

            // * If do transaction return success
            foreach ($transaction_data as $recurring_order_id => $transaction_response) {
                $recurring_order = RecurringOrder::query()
                    ->with(['recurringRequest'])
                    ->where("id", $recurring_order_id)
                    ->first();
                if ($transaction_response["success"] == true) {

                    if (isset($transaction_response["partial_deduction"]) && $transaction_response["partial_deduction"] == true) {
                        $recurring_order->progress = "partial_success";
                    } else if ($recurring_order->progress == "partial_success") {
                        $recurring_order->progress = "success";
                    } else {
                        $recurring_order->progress = "success";
                    }

                    /* Check RR frequecy, if its weekly/monthly than add 7/30 days to current date and its next exec date will be updated*/
                    if ($recurring_order->recurringRequest->premium_frequency != 'daily') {

                        if ($recurring_order->recurringRequest->premium_frequency == 'weekly') {
                            $next_exec = Carbon::now()->addWeek();
                            $recurring_order->recurringRequest->update([
                                'next_execution_on' => $next_exec
                            ]);
                        } elseif ($recurring_order->recurringRequest->premium_frequency == 'monthly') {
                            $next_exec = Carbon::now()->addMonth();
                            $recurring_order->recurringRequest->update([
                                'next_execution_on' => $next_exec
                            ]);
                        }
                    }

                    // Update Last Collection Date
                    try {
                        $recurring_order->recurringRequest->update([
                            "last_collection_date" => now()
                        ]);
                    } catch (\Throwable $th) {
                       logger($th->getMessage());
                    }
                } else {
                    if ($recurring_order->progress == "partial_success") {
                        // do nothing
                    }
                    // if the attempts is max of the configured value then it will be marked as unseccessfull
                    elseif ($recurring_order->attempts >= 15) {
                        $recurring_order->progress = "unsuccess";
                    } else {
                        $recurring_order->progress = "failed";
                    }
                }
                // Save response
                $recurring_order->save();
            }
        } catch (\Exception $th) {
            Log::channel('processMultiple')->info("Something Occurred " . $th->getTraceAsString());
        }
    }
}
