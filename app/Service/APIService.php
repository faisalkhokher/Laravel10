<?php

namespace App\Service;

use Illuminate\Support\Facades\Http;

class APIService
{
    /**
     * @category API
     * @param string $policyId
     * @return void
     */
    function activatePolicy($policyId)
    {
        $return = [
            'success'     => false,
            'message'     => "",
            'http_status' => ""
        ];

        $URL = config('app.env') == 'prod' ? env('ACTIVATE_API_PROD') : env('ACTIVATE_API_DEV');

        $response = Http::withHeaders([
            'Authorization' => 'Basic ZGVkdWN0aW9uX3YyOjRLSn1FODBUbWMyOkIvcE5sUVRTQEB4SDxWdDc=',
            'Content-Type'  => 'application/json',
        ])->post($URL, [
            'policy_id' => $policyId,
        ]);

        // If response is empty
        if (empty($response)) {
            $return['message'] = "Api connection error";
            return $return;
        }

        // If response is not empty
        $responseBody          = $response->body();
        $response_body         = json_decode($responseBody, 1);

        // Response
        $return['success']     = true;
        $return['http_status'] = $response->status();
        $return['message']     = $response_body['message'] ?? "Activated successfully";
        return $return;
    }
}
