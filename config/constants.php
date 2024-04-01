<?php

return [

    /**
     * @category Entity Short codes
     * ! do'not modify that IDS , These IDs updated from Prod DB.
     */
    'entity' => [
        'jcash'     => 1,
        'bluex'     => 2,
        'Retail'    => 3,
        'eocean'    => 4,
        'Tcs'       => 5,
        'PayFast'   => 6,
        'forrun'    => 8,
        'LCS'       => 9,
        'postex'    => 10,
        'Zong'      => 11,
        'EasyPaisa' => 12,
        'Web'       => 13,
        'abhi'      => 14,
        'Telenor'   => config('app.env') == 'prod' ? 107 : 116,
        'Ufone'     => config('app.env') == 'prod' ? 104 : 111,
        'Jazz'      => config('app.env') == 'prod' ? 108 : 182,
        'Kashf'     => config('app.env') == 'prod' ? 106 : 118,
        'Advance'   => config('app.env') == 'prod' ? 109 : 121,
        'ptcl'      => config('app.env') == 'prod' ? 109 : 185,
    ],

    /** @category Policy Status */
    'policy_status' => [
        'qa_assigned'           => '1',
        'active'                => '2',
        'Cancelled'             => '3',
        'Unsubscribed'          => '4',
        'ActiveWaiting'         => '5',
        'DispatchAssigned'      => '6',
        'Expired'               => '7',
        'Claimed'               => '8',
        'ReadyforReconcile'     => '9',
        'qa_rejected'           => '10',
        'ready_for_dispatch'    => '11',
        'call_again'            => '12',
        'RequestedCancellation' => '13',
        'RequestedClaim'        => '14',
        'NonCommenced'          => '15',
        'Commenced'             => '16',
        'Closed'                => '17',
        'IncompletePolicy'      => '18',
        'Rejected'              => '19',
        'Purged'                => '20',
        'Review'                => '21',
    ],

    /** @category Transaction code */
    "transaction_status" => [
        'open'                 => 1,
        'completed'            => 1000,
        'partial_completed'    => 1001,
        'invalid_customer'     => 1010,
        'insufficient_balance' => 1020,
        'time_out'             => 1030,
        'limit_failed'         => 1040,
        'system_error'         => 1050,
        'invalid_policy'       => 1060,
        'invalid_response'     => 1070,
    ],
];
