<?php

namespace App\Logic\Payments;

use Carbon\Carbon;
use GuzzleHttp\Pool;
use GuzzleHttp\Client;
use GuzzleHttp\Promise;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use App\Traits\EncryptionTrait;
use GuzzleHttp\Handler\CurlHandler;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\Client\ConnectionException;

class JazzPayment
{
    use EncryptionTrait;

    /**
     * # Deduction API
     * @param transaction_payload $amount
     * @return
     * @api http://10.11.221.85:10011/Air
     */
    function sendMultipleXmlRequest($xml_requests)
    {
        $return = [
            'success' => false,
            'message' => "",
            'data' => [],
            'status' => "",
            "transaction_id" => ""
        ];

        // Create a new Guzzle client with a custom HandlerStack
        $handler = new CurlHandler();
        $handlerStack = HandlerStack::create($handler);

        // Define the maximum number of connections you want to allow
        $maxConnections = 1;
        $concurrency = 5;

        // Add a custom middleware to limit the number of connections
        $handlerStack->push(function (callable $handler) use ($maxConnections) {
            return function ($request, array $options) use ($handler, $maxConnections) {
                // Limit the number of total connections
                $options['curl'][CURLOPT_FRESH_CONNECT] = false; // Disable reusing connections
                $options['curl'][CURLOPT_MAXCONNECTS] = $maxConnections;
                $options['verify'] = false; // Disable SSL verification for testing purposes
                return $handler($request, $options);
            };
        });

        $client = new Client(['handler' => $handlerStack, 'timeout' => 305, 'verify' => false]);
        $url = 'http://localhost:8888/Air'; // Local
        // $url = 'http://10.13.35.55:10011/Air'; // DEV
        // $url = 'http://10.13.32.179:10010/Air'; // PROD

        $headers = [
            'Content-Type' => 'text/xml',
            'User-Agent' => 'UGw Server/5.0/2.0',
            'Authorization' => 'Basic ' . (env('JAZZ_DEDUCTION_AUTH')),
            'Cache-Control' => 'no-cache',
            'Pragma' => 'no-cache',
            'Accept' => 'text/html, image/gif, image/jpeg, *; q=.2, */*; q=.2',
            'Connection' => 'keep-alive',
        ];

        try {
            $promises = [];
            $requests = function () use ($client, $url, $headers, $xml_requests) {

                foreach ($xml_requests as $body) {
                    yield new Request('POST', $url, $headers, $body);
                }
            };
        } catch (\Exception $e) {
            Log::channel('jazzLogging')->info("Something Occurred " . $e->getMessage());
            Log::channel('jazzLogging')->info("Something Occurred " . $e->getTraceAsString());
        }


        // Initialize the pool with the generator function and options
        try {
            $json_responses = [];
            $pool = new Pool($client, $requests(), [
                'concurrency' => $concurrency,
                'stream' => true,
                'fulfilled' => function ($response, $index) use (&$json_responses, $xml_requests) {
                    $xmlResponse = simplexml_load_string($response->getBody());
                    $arrayResponse = json_decode(json_encode($xmlResponse), true);
                    //Initializing the response success
                    $arrayResponse['success'] = true;

                    // Retrieve the txn_id for this response
                    $txn_id = array_keys($xml_requests)[$index];
                    // Handle successful responses
                    $json_responses[$txn_id] = $arrayResponse;
                },
                'rejected' => function ($reason, $index) use ($xml_requests) {
                    // Handle failed requests
                    \Log::build([
                        'driver' => 'single',
                        'path' => Storage::disk('logs')->path('Pool-Rejected-Error-' . Carbon::now()->format('Y-m-d') . '.log'),
                    ])->info("Request $index failed: {$reason->getMessage()}\n");
                    $arrayResponse['success'] = false;
                    $txn_id = array_keys($xml_requests)[$index];
                    $json_responses[$txn_id] = $arrayResponse;
                },
            ]);
            $promise = $pool->promise();
            $promise->wait();
        } catch (\Exception $e) {
            \Log::build([
                'driver' => 'single',
                'path' => Storage::disk('logs')->path('pool-api-error-' . Carbon::now()->format('Y-m-d') . '.log'),
            ])->info(json_encode($e->getMessage()));
        }



        // TODO: need to change the re
        return $json_responses;
    }

    public function multiplPayment($transaction_payload)
    {
        try {
            $return = [
                'success' => false,
                'message' => "",
                'data' => [],
                'status' => ""
            ];

            $createXMLRequests = [];
            foreach ($transaction_payload as $txn_id => $payload) {
                $phone = $this->decrypt($payload['phone']);
                $amount           = $payload['amount_to_deduct'];
                $transaction_id   = $payload['transaction_obj']['id'];
                $transaction_date = $payload['transaction_obj']['created_at'];
                $plan_number      = $payload['plan_number'];

                preg_match('/^([a-zA-Z]+)daily([0-9]+)$/i', $plan_number, $matches);
                $ucip                 = 'WaadaHandsetInsuranceDaily' . $matches['2'];
                $ext_data             = $ucip . "_VAS";
                $carbonDate           = Carbon::parse($transaction_date);
                $transaction_formated = $carbonDate->format('Ymd\TH:i:sO');
                $amount               = $amount*100;

                // Corrected heredoc syntax without leading spaces
                $xmlPayload = <<<XML
<?xml version="1.0" encoding="iso-8859-1"?>
<methodCall>
    <methodName>UpdateBalanceAndDate</methodName>
    <params>
        <param>
            <value>
                <struct>
                    <member>
                        <name>originNodeType</name>
                        <value><string>EXT</string></value>
                    </member>
                    <member>
                        <name>originHostName</name>
                        <value><string>{$ucip}</string></value>
                    </member>
                    <member>
                        <name>originTransactionID</name>
                        <value><string>{$transaction_id}</string></value>
                    </member>
                    <member>
                        <name>transactionType</name>
                        <value><string>{$ucip}</string></value>
                    </member>
                    <member>
                        <name>transactionCode</name>
                        <value><string>{$ucip}</string></value>
                    </member>
                    <member>
                        <name>externalData1</name>
                        <value><string>{$ext_data}</string></value>
                    </member>
                    <member>
                        <name>externalData2</name>
                        <value><string>{$ext_data}</string></value>
                    </member>
                    <member>
                        <name>originTimeStamp</name>
                        <value><dateTime.iso8601>{$transaction_formated}</dateTime.iso8601></value>
                    </member>
                    <member>
                        <name>transactionCurrency</name>
                        <value><string>PKR</string></value>
                    </member>
                    <member>
                        <name>subscriberNumber</name>
                        <value><string>{$phone}</string></value>
                    </member>
                    <member>
                        <name>adjustmentAmountRelative</name>
                        <value><string>-{$amount}</string></value>
                    </member>
                </struct>
            </value>
        </param>
    </params>
</methodCall>
XML;
                $createXMLRequests[$payload['transaction_obj']['id']] = $xmlPayload;
            }
            $json_responses = $this->sendMultipleXmlRequest($createXMLRequests);
        } catch (\Exception $exception) {
            Log::channel('jazzLogging')->info("Something Occurred " . $exception->getMessage());
        }

        $return = $this->proccessTransactions($json_responses ,$transaction_payload);
        return $return;
    }

    /**
     *  Following function will process the transactions after getting responses from the API
     *
    */
    public function proccessTransactions($json_responses,$transaction_payload)
    {
        // 2nd array
        try {
            $transaction_response = [];
            foreach ($json_responses as $txn_id => $response) {
                $process = $transaction_payload[$txn_id]['process'];
                // Initializing the Response array with false values.
                $return['success']            = false;
                $return['data']               = "";
                $return['message']            = "UnSuccessful";
                $return['status']             = Config::get('constants.transaction_status.system_error');
                $return['transaction_id']     = $txn_id;
                $return['partial_deduction']  = false;
                $return['transaction_object'] = $transaction_payload[$txn_id]['transaction_obj'];
                $return['amount_to_deduct']   = $transaction_payload[$txn_id]['amount_to_deduct'];

                if (isset($response["fault"])) {
                    // \Log::build([
                    //     'driver' => 'single',
                    //     'path' => Storage::disk('logs')->path('Jazz-Res-fault-' . Carbon::now()->format('Y-m-d') . '.log'),
                    // ])->info('fault : ' . json_encode($response));
                    $return['message'] = "Fault Response";
                    $transaction_response[$txn_id] = $return;
                    continue;
                }

                $member = $response["params"]["param"]["value"]["struct"]["member"] ?? $response["param"]["value"]["struct"]["member"]; //get the response from this node
                $responseCode = array_filter($member, function ($node) {
                    if (isset($node['name']) && $node['name'] === 'responseCode') {
                        return $node;
                    }
                    return false;
                });
                $originTransactionID = array_filter($member, function ($node) {
                    if (isset($node['name']) && $node['name'] === 'originTransactionID') {
                        return $node;
                    }
                    return false;
                });

                $responseCode = array_values($responseCode);
                $originTransaction_id = array_values($originTransactionID);

                if (isset($responseCode)) {
                    $i4_code = $responseCode[0]["value"]['i4'];
                    $res_transaction_id = $originTransaction_id[0]["value"]['string'];
                    if (isset($i4_code)) {
                        if ($i4_code == "0") {
                            $return['success'] = true;
                            $return['data'] = $responseCode;
                            $return['message'] = "Successful";
                            if ($process == "full_deduction") {
                                $return['status'] = Config::get('constants.transaction_status.completed');
                            }else{
                                $return['partial_deduction'] = true;
                                $return['status'] = Config::get('constants.transaction_status.partial_completed');
                            }
                            $return['transaction_id'] = $res_transaction_id;
                            $transaction_response[$res_transaction_id] = $return;
                        } else {
                            $return['data'] = $responseCode;
                            $return['status'] = $this->mapResponseCode($i4_code);
                            $return['message'] = "Unsuccessful";
                            $return['transaction_id'] = $res_transaction_id;
                            $transaction_response[$res_transaction_id] = $return;
                        }
                    } else {
                        $return['message'] = "Response not found";
                        $transaction_response[$txn_id] = $return;
                    }
                }
            }
            return $transaction_response;
        } catch (\Exception $e) {
            Log::channel('jazzLogging')->info("Something Occurred " . $e->getMessage());
            Log::channel('jazzLogging')->info("Something Occurred " . $e->getTraceAsString());
        }
    }

    /**
     * Jazz Balance API
     * @param $data
     * @api http://172.31.13.20:8444/Air
     * @apiVersion 1.0.0
     * @apiName GetBalance
     * @apiGroup Payments
     * @apiParam {string} phone
     * @return array
     */
    public function sendMultipleBalanceRequest($transactions_payload)
    {
        try {
            $xmlPayloads = [];
            foreach ($transactions_payload as $key => $object) {

                // * Initialization data from RO
                $transaction_payload  = $object['transaction_obj'];
                $transaction_id       = $transaction_payload->id;
                $policy               = $transaction_payload->policy;
                $plan_number          = $object['plan_number'];
                $phone                = $this->decrypt($policy->policyHolder->phone);

                // * Initialization Body
                $carbonDate           = Carbon::now();
                $origin_time_stamp    = $carbonDate->format('Ymd\TH:i:sO');

                // * Initialization XML
                $xmlPayload = <<<XML
                <?xml version="1.0" encoding="iso-8859-1"?>
                <methodCall>
                    <methodName>GetBalanceAndDate</methodName>
                    <params>
                        <param>
                            <value>
                                <struct>
                                    <member>
                                        <name>originTransactionID</name>
                                        <value>
                                            <string>{$transaction_id}</string>
                                        </value>
                                    </member>
                                    <member>
                                        <name>originNodeType</name>
                                        <value>
                                            <string>EXT</string>
                                        </value>
                                    </member>
                                    <member>
                                        <name>originHostName</name>
                                        <value>
                                            <string>{$plan_number}</string>
                                        </value>
                                    </member>
                                    <member>
                                        <name>originTimeStamp</name>
                                        <value>
                                            <dateTime.iso8601>{$origin_time_stamp}</dateTime.iso8601>
                                        </value>
                                    </member>
                                    <member>
                                        <name>subscriberNumber</name>
                                        <value>
                                            <string>{$phone}</string>
                                        </value>
                                    </member>
                                </struct>
                            </value>
                        </param>
                    </params>
                </methodCall>
                XML;
                $xmlPayloads[$transaction_payload->id] = $xmlPayload;
            } // End Foreach

            // dd($xmlPayloads);
            // Calling API
            $client = $this->getBalanceHttpClient();

            // * URL
            $url = 'http://localhost:8888/Air'; // Local
            // $url = 'http://10.13.35.55:10011/Air'; // DEV
            // $url = 'http://10.13.32.179:10010/Air'; // Prod

            $headers = [
                'Content-Type'  => 'text/xml',
                'User-Agent'    => 'UGw Server/5.0/2.0',
                'Authorization' => 'Basic ' . (env('JAZZ_DEDUCTION_AUTH')),
                'Cache-Control' => 'no-cache',
                'Pragma'        => 'no-cache',
            ];

            // Generator of Requests
            $requests = function () use ($client, $url, $headers, $xmlPayloads) {
                foreach ($xmlPayloads as $body) {
                    yield new Request('POST', $url, $headers, $body);
                }
            };

            $json_responses = [];
            $pool = new Pool($client, $requests(), [
                'concurrency' => 5,
                'stream' => true,
                'fulfilled' => function ($response, $index) use (&$json_responses, $xmlPayloads) {
                    $xmlResponse = simplexml_load_string($response->getBody());
                    $arrayResponse = json_decode(json_encode($xmlResponse), true);
                    // Retrieve the recurring_order_id for this response
                    $transaction_id = array_keys($xmlPayloads)[$index];
                    return $json_responses[$transaction_id] = $arrayResponse;
                },
                'rejected' => function ($reason, $index) {
                },
            ]);
            $promise = $pool->promise();
            $promise->wait();

            // dd($json_responses);
            // Send Reponse to Process Balance Function
            $process_balance_array = $this->processBalance($json_responses, $transactions_payload);
            return $process_balance_array;
        } catch (\Exception $e) {
            // dd($e->getMessage());
            \Log::channel('jazzLogging')->info($e->getTraceAsString());
        }
    }

    /**
     * @param $timeout
     */
    function getBalanceHttpClient($timeout = 10)
    {
        // Define the maximum number of connections you want to allow
        $maxConnections = 1;
        $concurrency = 5;
        // Create a new Guzzle client with a custom HandlerStack
        $handler = new CurlHandler();
        $handlerStack = HandlerStack::create($handler);
        // Add a custom middleware to limit the number of connections
        $handlerStack->push(function (callable $handler) use ($maxConnections) {
            return function ($request, array $options) use ($handler, $maxConnections) {
                // Limit the number of total connections
                $options['curl'][CURLOPT_FRESH_CONNECT] = false; // Disable reusing connections
                $options['curl'][CURLOPT_MAXCONNECTS] = $maxConnections;
                $options['verify'] = false; // Disable SSL verification for testing purposes
                return $handler($request, $options);
            };
        });

        $client = new Client(['handler' => $handlerStack, 'timeout' => $timeout, 'verify' => false]);
        return $client;
    }

    /**
     * Process Balance Function
     * @param $json_responses
     * @return $process_balance_array
     * @date 1-March-2024
     * @author
     * @description
     */
    public function processBalance($json_responses , $transactions_payload)
    {
        $return  = [];
        foreach ($json_responses as $key => $response) {
            $process_balance_array = [
                "success"            => false,
                "status"             => config('constants.transaction_status.system_error'),
                "transaction_id"     => $key,
                "balance"            => null,
                "amount_to_deduct"   => null,
                "process"            => null // partial_deduction / full_deduction
            ];


            // Handle fault error
            if (isset($response["fault"])) {
                $process_balance_array["status"] = config('constants.transaction_status.invalid_response');
                $return[$key] = $process_balance_array;
                continue;
            }

            // Getting Balance and originTransactionID from response.
            $member = $response["params"]["param"]["value"]["struct"]["member"] ?? $response["param"]["value"]["struct"]["member"]; //get the response from this node
            //
            $responseCode = array_filter($member, function ($node) {
                if (isset($node['name']) && $node['name'] === 'responseCode') {
                    return $node;
                }
                return false;
            });
            $accountValue1 = array_filter($member, function ($node) {
                if (isset($node['name']) && $node['name'] === 'accountValue1') {
                    return $node;
                }
                return false;
            });
            $originTransactionID = array_filter($member, function ($node) {
                if (isset($node['name']) && $node['name'] === 'originTransactionID') {
                    return $node;
                }
                return false;
            });

            // Error code Description:
            /**
             * 0: successful
            *  102: subscriber not found
            *  103: account barred from refill
            *  104: Temporary blocked
            */
            $responseCode = array_values($responseCode);
            if (isset($responseCode)) {
                $i4_code = Arr::get($responseCode, '0.value.i4');
                if (isset($i4_code)) {
                    if ($i4_code != "0") {
                        $process_balance_array['status'] = config('constants.transaction_status.invalid_customer');
                        $return[$key] = $process_balance_array;
                        continue;
                    }

                } else {
                    $process_balance_array['status'] = config('constants.transaction_status.invalid_response');
                    $return[$key] = $process_balance_array;
                    continue;
                }
            }

            // Getting the balance in integer
            $accountValue1        = array_values($accountValue1);
            $balance = Arr::get($accountValue1, '0.value.string');
            $current_balance = (int) $balance / 100;
            $process_balance_array['balance'] = $current_balance ?? null;
            $transaction_obj = $transactions_payload[$key]['transaction_obj'];

            // Mark full_deduction/partial_deduction based on balance and transaction amount
            if (isset($accountValue1,$current_balance) && $current_balance > 2) { // beacuse the min balance required is 2
                $transaction_amount = $transaction_obj->amount;
                if ($current_balance >= $transaction_amount) {
                    $process_balance_array['success']            = true;
                    $process_balance_array['amount_to_deduct']   = $transaction_amount;
                    $process_balance_array['status']             = null;
                    $process_balance_array["process"]            = "full_deduction";
                } elseif ($current_balance < $transaction_amount && now()->format('H:i:s') > '18:00:00') { // Partial deduction only after 6PM
                    $plans = collect(config('constants.Jazz_plan_values'));
                    $matchingPlan = $plans->first(function ($value) use ($current_balance) {
                        return $current_balance >= $value;
                    });
                    if (filled($matchingPlan)) {
                        $process_balance_array['success']            = true;
                        $process_balance_array['amount_to_deduct']   = $matchingPlan;
                        $process_balance_array['status']             = null;
                        $process_balance_array["process"]            = "partial_deduction";
                    }
                }
            } else {
                $process_balance_array['status']   = config('constants.transaction_status.insufficient_balance');
                $return[$key] = $process_balance_array;
                continue;
            }
            // dd($process_balance_array);
            $return[$key] = $process_balance_array;
        }

        return $return;
    }

        /**
     * @category Map Response Code
     * @param  String  $response_code
     * @return String
    */
    public function mapResponseCode($response_code)
    {
        $array = [
            '100' => Config::get('constants.transaction_status.system_error'),
            '101' => Config::get('constants.transaction_status.invalid_customer'),
            '102' => Config::get('constants.transaction_status.invalid_customer'),
            '103' => Config::get('constants.transaction_status.insufficient_balance'),
            '104' => Config::get('constants.transaction_status.system_error'),
            '124' => Config::get('constants.transaction_status.insufficient_balance'),
            '126' => Config::get('constants.transaction_status.invalid_customer'),
        ];
        return (isset($array[$response_code]) ? $array[$response_code] : Config::get('constants.transaction_code.system_error'));
    }
}

