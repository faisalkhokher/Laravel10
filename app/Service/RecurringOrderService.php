<?php

namespace App\Service;

use Carbon\Carbon;
use App\Models\RecurringOrder;
use App\Models\RecurringRequest;
use Illuminate\Support\Facades\DB;

class RecurringOrderService
{
    /**
     * This function will insert and update recurring orders
     * @date 2024-03-21
    */
    public function insertAndUpdateOrders()
    {
        DB::beginTransaction();
        try {
            // Select from recurring_requests
            $recurringRequests = RecurringRequest::whereDate('next_execution_on', now()->format('Y-m-d'))
                ->whereIn('progress', ['new', 'ongoing'])
                ->orderByDesc('id')
                ->get();

            foreach ($recurringRequests as $request) {
                $recurringOrder = RecurringOrder::updateOrCreate(
                    [
                        'recurring_request_id' => $request->id,
                        'execution_date'       => $request->next_execution_on,
                    ],
                    [
                        'attempts'  => 0,
                        'progress'  => 'new',
                        'entity_id' => $request->entity_id
                    ]
                );

                // Update recurring requests in table
                $this->updateRecurringRequest($request);
            }

            DB::commit();
        } catch (\Exception $e) {
            dd($e);
            DB::rollBack();
        }
    }

    /**
     * This function will update the recurring request with respect to provided ID
    */
    public function updateRecurringRequest($recurring_request_node)
    {
        $recurring_update_req                    = RecurringRequest::query()->where('id', $recurring_request_node->id)->first();
        $recurring_update_req->execution_count   = (int)$recurring_update_req->execution_count + 1;
        $recurring_update_req->last_execution_on = $recurring_request_node->next_execution_on;
        $recurring_update_req->next_execution_on = $this->calculateNextExecutionTime($recurring_update_req->last_execution_on, $recurring_request_node->premium_frequency);
        $recurring_update_req->progress          = "ongoing";
        return  $recurring_update_req->save();
    }

    /**
     * Calculate next execution count
    */
    public function calculateNextExecutionTime($last_execution_on, $premium_frequency)
    {
        $next_execution_on = null;

        if ($last_execution_on == null) {
            $last_execution_on = Carbon::now();
        }

        switch ($premium_frequency) {
            case 'daily':
                // Need to remove this line
                // $next_execution_on = Carbon::parse($last_execution_on);
                $next_execution_on = Carbon::parse($last_execution_on)->addDay();
                break;
            case 'weekly':
                $next_execution_on = Carbon::parse($last_execution_on)->addWeek();
                break;
            case 'monthly':
                $next_execution_on = Carbon::parse($last_execution_on)->addMonth();
                break;
            case 'year':
                $next_execution_on = Carbon::parse($last_execution_on)->addYear();
                break;
            default:
                $next_execution_on = $last_execution_on;
                break;
        }
        return $next_execution_on;
    }
}
