<?php

namespace App\Service;

use Carbon\Carbon;
use App\Facades\APIFacade;
use App\Models\Transaction;
use App\Models\RecurringOrder;
use App\Facades\JazzPaymentFacade;
use App\Logic\Payments\PayMultiple;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

class TransactionService
{
    /** @category Store Transaction  */
    public function storeTransaction($policy_id, $phone, $txn_status, $amount, $payment_mode, $entity_id, $underwriter_id, $txn_state = "new", $txn_id = null, $txn_amount = null, $txn_time = null, $created_at = null, $updated_at = null, $context = null, $last_balance = null)
    {
        // dd($policy_id, $phone, $txn_status, $amount, $payment_mode, $entity_id, $underwriter_id, $txn_state = "new", $txn_id = "", $txn_amount = "", $txn_time = "", $created_at = null, $updated_at = null, $context = null, $last_balance = null);
        // * Insert transaction from query builder
        return Transaction::create([
            'policy_id'      => $policy_id,
            'phone'          => $phone,
            'txn_status'     => $txn_status,
            'txn_state'      => $txn_state,
            'amount'         => $amount,
            'payment_mode'   => $payment_mode,
            'entity_id'      => $entity_id,
            'underwriter_id' => $underwriter_id,
            'txn_id'         => @$txn_id,
            'txn_amount'     => @$txn_amount,
            'txn_time'       => @$txn_time,
            'last_balance'   => @$last_balance,
            'context'        => $context ?? @Cache::get('transaction_context_' . $entity_id),
            'created_at'     => $created_at ?? Carbon::now(),
            'updated_at'     => $updated_at ?? Carbon::now(),
        ]);
    }

    /**
     * @category Update Transaction
     * @param  Object  $transaction_obj
     */
    public function updateTransaction($transaction_obj)
    {
        $transaction_obj->update();
    }

    /**  @category
     * Check if policy active
     * resolve => $policy_data
     * insert the transaction
     * do the payment
     * close transaction
     */
    public function doMultipleTransactions($recurring_orders)
    {
        try {
            $return = [
                'success' => false,
                'message' => "",
                'data' => [],
                'partial_deduction' => false
            ];

            $transactions_payload = [];
            foreach ($recurring_orders as $key => $recurring_order) {
                $recurring_order_id = $recurring_order->id;
                $policy             = $recurring_order->recurringRequest->policy;
                $policy_id          = $policy->id;
                $plan               = $policy->plan;

                // If policy not exists in db then through error
                if (!$policy) {
                    $return[$policy_id]['message'] = "Policy not exists";
                    continue;
                }

                // Initialization
                $phone          = $policy->policyHolder->phone;
                $txn_status     = config('constants.transaction_status.open');
                $amount         = $plan->premiumlevel;
                $payment_mode   = $policy->payment_mode;
                $entity_id      = $plan->entity_id;
                $underwriter_id = $plan->underwriter_id;
                $plan_number    = $plan->plan_number;

                // Store transaction in db
                $transaction_obj = $this->storeTransaction($policy->id, $phone, $txn_status, $amount, $payment_mode, $entity_id, $underwriter_id);


                if (isset($recurring_order->id)) {
                    $transaction_obj->recurring_order_id = $recurring_order->id;
                    $this->updateTransaction($transaction_obj);
                }

                // Checking if policy is active
                if ($policy->status_id != Config::get('constants.policy_status.active') && $policy->status_id != Config::get('constants.policy_status.DispatchAssigned') && $policy->status_id != Config::get('constants.policy_status.ActiveWaiting')) {
                    $transaction_obj->txn_state    = "failed";
                    $transaction_obj->txn_status   = Config::get('constants.transaction_status.invalid_policy');
                    $this->updateTransaction($transaction_obj);
                    $return[$policy_id]['message'] = "Policy is invalid state";
                    continue;
                }

                // Return data from transaction object for response to user
                $transaction_payload_single = [
                    'policy_id'       => $policy->id,
                    'phone'           => $phone,
                    'amount'          => $amount,
                    'transaction_obj' => $transaction_obj,
                    'plan_number'     => $plan_number,
                ];
                $transactions_payload[$transaction_obj->id] = $transaction_payload_single;
            } // * End Foreach Loop

            // Check Balance of users from transactions
            $balance_responses = JazzPaymentFacade::sendMultipleBalanceRequest($transactions_payload);

            // Setting for transactions for unsuccessful transactions
            $ro_to_be_deducted = [];
            $ro_to_be_failed = [];
            $mergedCollection = null;
            foreach ($balance_responses as $key => $balance_response) {
                $transaction_obj = $transactions_payload[$key]['transaction_obj'];
                // \Log::build([
                //     'driver' => 'single',
                //     'path' => Storage::channel('logs')->path('abc-' . Carbon::now()->format('Y-m-d') . '.log'),
                // ])->error($balance_response['amount_to_deduct']??"NO DATA");
                if ($balance_response['success'] == false) {
                    $transaction_obj->txn_state    = "failed";
                    $transaction_obj->txn_status   = $balance_response['status'];
                    $transaction_obj->last_balance = $balance_response['balance'];
                    $transaction_obj->txn_amount   = $balance_response['amount_to_deduct'] ?? 0;
                    $this->updateTransaction($transaction_obj);
                    // ! Yaha RO ko fail mark karna hay
                    $ro_to_be_failed[$transaction_obj->recurring_order_id]['success'] = false;
                    $ro_to_be_failed[$transaction_obj->recurring_order_id]['message'] = $balance_response['status'];
                    continue;
                } else {
                    $data['amount_to_deduct']      = $balance_response['amount_to_deduct'];
                    $data['process']               = $balance_response['process'];
                    $collecting_data               = collect($data);
                    $mergedCollection              = $collecting_data->merge($transactions_payload[$key]);
                    $ro_to_be_deducted[$key]       = $mergedCollection->all();
                    $transaction_obj->last_balance = $balance_response['balance'];
                    $transaction_obj->txn_amount   = $balance_response['amount_to_deduct'] ?? 0;
                    $this->updateTransaction($transaction_obj);

                    // * Checking for rollover
                    $recurring_request_id = $transaction_obj->recurringOrder->recurring_request_id;
                    $yesterday_progress = RecurringOrder::where('execution_date', Carbon::yesterday()->format('Y-m-d'))
                        ->where('recurring_request_id', $recurring_request_id)
                        ->select('progress', 'id')
                        ->first();
                    // If $yesterday_progress is equal to fail and $balance_response['balance'] is greater than $balance_response['amount_to_deduct'] and $balance_response['process'] is eqal to full_deduction then create a new transaction
                    try {
                        Log::channel('transaction')->info($yesterday_progress);

                        if ($yesterday_progress != null && ($yesterday_progress->progress == "failed" || $yesterday_progress->progress == "inprocess") && $balance_response['balance'] > $balance_response['amount_to_deduct'] && $balance_response['process'] == "full_deduction") {
                            // Initialization
                            $new_transaction_object                     = $transaction_obj->replicate();
                            $new_transaction_object->context            = "roll_over";
                            $new_transaction_object->txn_time           = Carbon::yesterday();
                            $new_transaction_object->recurring_order_id = $yesterday_progress->id;
                            $new_transaction_object->last_balance       = $balance_response['balance'];
                            $new_transaction_object->txn_amount         = $balance_response['amount_to_deduct'] ?? 0;
                            $new_transaction_object->save();
                            // Setting data for Ro to be deducted
                            $data['policy_id']                              = $new_transaction_object->policy_id;
                            $data['phone']                                  = $new_transaction_object->phone;
                            $data['transaction_obj']                        = $new_transaction_object;
                            $data['plan_number']                            = $new_transaction_object->policy->plan->plan_number;
                            $data['amount_to_deduct']                       = $balance_response['amount_to_deduct'];
                            $data['process']                                = $balance_response['process'];
                            $collecting_data                                = collect($data);
                            $ro_to_be_deducted[$new_transaction_object->id] = $collecting_data->all();
                        }
                    } catch (\Throwable $th) {
                        Log::channel('transaction')->error("System error " . $th->getMessage());
                        Log::channel('transaction')->error("System error " . $th->getTraceAsString());
                    }
                }
            }
        } catch (\Throwable $th) {
            Log::channel('transaction')->error("System error " . $th->getTraceAsString());
        }

        // * Deduct Amount
        $return = [];
        if (!empty($ro_to_be_deducted)) {
            $pay_obj = new PayMultiple($ro_to_be_deducted);
            try {
                $pay_obj_debit =  [
                    'success' => false,
                    'message' => "",
                    'data' => [],
                    'status' => Config::get('constants.transaction_status.time_out')
                ];
                $pay_obj_debits = $pay_obj->multipleDebitAmount();

                foreach ($pay_obj_debits as $key => $pay_obj_debit) {
                    $transaction_obj = $pay_obj_debit['transaction_object'];
                    $policy = $transaction_obj->recurringOrder->recurringRequest->policy;

                    if ($transaction_obj) {
                        // If not success transaction then skip
                        $return[$transaction_obj->recurring_order_id]['success'] = false;
                        // $return[$transaction_obj->recurring_order_id]['partial_success'] = false;
                        if ($pay_obj_debit['status'] != Config::get('constants.transaction_status.completed') && $pay_obj_debit['status'] != Config::get('constants.transaction_status.partial_completed')) {
                            $transaction_obj->txn_state = "failed";
                            $transaction_obj->txn_status = $pay_obj_debit['status'];
                            $this->updateTransaction($transaction_obj);
                            $return[$transaction_obj->recurring_order_id]['message'] = $pay_obj_debit['message'];
                            continue;
                        }

                        // If partial transaction
                        if ($pay_obj_debit['status'] == Config::get('constants.transaction_status.partial_completed')) {
                            $transaction_obj->txn_state = "partial_success";
                            $transaction_obj->txn_status = $pay_obj_debit['status'];
                            $this->updateTransaction($transaction_obj);
                            $return[$transaction_obj->recurring_order_id]['partial_deduction'] = true;
                            $return[$transaction_obj->recurring_order_id]['success'] = true;
                            $return[$transaction_obj->recurring_order_id]['message'] = $pay_obj_debit['message'];

                            // Activate policy if DispatchAssigned
                            if ($policy->status_id == Config::get('constants.policy_status.DispatchAssigned')) {
                               try {
                                APIFacade::activatePolicy($policy->id);
                               } catch (\Throwable $th) {
                                \Log::build([
                                    'driver' => 'single',
                                    'path' => Storage::disk('logs')->path('Jazz-Trnsansaction-Activate-' . Carbon::now()->format('Y-m-d') . '.log'),
                                ])->error(["Policy ID ".$policy->id." Exception is ".$th->getTraceAsString()]);
                               }
                            }
                            continue;
                        }

                        // Transaction completed successfully
                        $transaction_obj->txn_state  = "success";
                        $transaction_obj->txn_status = $pay_obj_debit['status'];
                        $transaction_obj->amount     = $amount;
                        // $transaction_obj->txn_amount = @$pay_obj_debit['data']['amount'];
                        $transaction_obj->txn_amount = $pay_obj_debit['amount_to_deduct'] ?? 0;
                        $this->updateTransaction($transaction_obj);
                        $return[$transaction_obj->recurring_order_id]['success'] = true;
                        $return[$transaction_obj->recurring_order_id]['message'] = $pay_obj_debit['message'];
                        //  Activate policy if DispatchAssigned
                        logger(__LINE__);
                        logger($policy->id);
                        if ($policy->status_id == Config::get('constants.policy_status.DispatchAssigned')) {
                            try {
                            $res =  APIFacade::activatePolicy($policy->id);
                            logger(__LINE__);
                            logger([$res]);
                            } catch (\Exception $th) {
                                \Log::build([
                                    'driver' => 'single',
                                    'path' => Storage::disk('logs')->path('Jazz-Trnsansaction-Activate-' . Carbon::now()->format('Y-m-d') . '.log'),
                                ])->error(["Policy ID ".$policy->id." Exception is ".$th->getTraceAsString()]);
                            }
                        }
                        continue;
                    } else {
                        Log::channel('transaction')->error("==== Transaction Not Found ========" . $key);
                    }
                }
                $return = $return + $ro_to_be_failed;
                return $return;
            } catch (\Throwable $th) {
                Log::channel('transaction')->error("System error " . $th->getMessage());
                Log::channel('transaction')->error("System error " . $th->getTraceAsString());
                // $transaction_obj->txn_state = "failed";
                // $transaction_obj->txn_status = Config::get('constants.transaction_status.system_error');
                // $this->updateTransaction($transaction_obj);
                // $return['message'] = "System error";
                // return $return;
            }
        } else {
            $return = $return + $ro_to_be_failed;
            return $return;
        }
    }
}
