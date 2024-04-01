<?php

namespace App\Logic\Payments;

use App\Logic\Payments\JazzPayment;

class PayMultiple
{
    private $transaction_payload;
    private $transaction_obj;

    public function __construct($transaction_payload)
    {
        $this->transaction_payload = $transaction_payload;
    }

    public function multipleDebitAmount()
    {
        $return = [
            'success' => false,
            'message' => "",
            'data' => [],
            'status' => ""
        ];

        $jazz_obj = new JazzPayment();
        $charge_amount_response = $jazz_obj->multiplPayment($this->transaction_payload);
        return $charge_amount_response;
    }
}
