<?php

namespace Tests\Feature;

use Carbon\Carbon;
use Tests\TestCase;
use App\Facades\APIFacade;
use App\Models\Transaction;
use Illuminate\Support\Str;
use App\Models\RecurringOrder;
use App\Facades\JazzPaymentFacade;
use App\Facades\TransactionFacade;
use Illuminate\Support\Facades\Storage;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Foundation\Testing\RefreshDatabase;

class TransactionTest extends TestCase
{
    /**
     * A basic feature test example.
     */
    public function test_example(): void
    {
        $string = "initiate_transaction";
        $first_word = Str::before($string, '_');
        dd($first_word);
        // try {
        //     // thorw exception
        //     throw new \Exception("Test Exceptio");
        // } catch (\Throwable $th) {
        //     \Log::build([
        //         'driver' => 'single',
        //         'path' => Storage::disk('logs')->path('Jazz-Trnsa-Activate-' . Carbon::now()->format('Y-m-d') . '.log'),
        //     ])->error($th->getMessage());
        // }
        // dd("test");
        // ddd(JazzPaymentFacade::sendMultipleBalanceRequest([
        //     "amount" => 10000,
        // ]));
        // $recurring_order = RecurringOrder::query()
        // ->whereIn("id", [13])
        // ->with(['recurringRequest', 'recurringRequest.policy' , 'recurringRequest.policy.plan'])
        // ->get();
        // $transaction_data = TransactionFacade::doMultipleTransactions($recurring_order);
        // ddd($transaction_data);
    }

    // public function test_sendMultipleBalanceRequest()
    // {
    //     $transaction_obj = Transaction::query()->whereIn("id", [24])
    //     ->with(['policy', 'policy.policyHolder', 'policy.plan'])
    //     ->get();
    //     // dd($transaction_obj);
    //     $transactions_payload = [];
    //     foreach ($transaction_obj as $key => $transaction) {
    //         $transaction_payload_single = [
    //             'policy_id'       => $transaction->policy_id,
    //             'phone'           => $transaction->policy->policyHolder->phone,
    //             'amount'          => $transaction->policy->plan->premiumlevel,
    //             'transaction_obj' => $transaction,
    //             'plan_number'     => $transaction->policy->plan->plan_number,
    //         ];
    //         $transactions_payload[$transaction->id] = $transaction_payload_single;
    //     }
    //     // dd($transactions_payload);
    //     dd(JazzPaymentFacade::sendMultipleBalanceRequest($transactions_payload));
    // }
}
