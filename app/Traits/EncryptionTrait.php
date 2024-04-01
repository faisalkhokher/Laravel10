<?php

namespace App\Traits;

use Illuminate\Support\Facades\Http;

/**
 * In this Trait there are 3 possible array combination so far we can add more combination upto 9.
 * thus we have to use % by 8. Encrypt function will take a number input with that it will be in a modulus condition
 * and checking what is the reminder and after getting reminder it will update the mapping Array[] accordingly.
 * after the encryption now it times to decrypt the encrypted number.
 * I have created mapping array with the reminder value like 1,2,4 and every number start with 3.
 * So if the reminder if 2 somehow then the array mapping value of 3 will be 2 and if reminder 1 then array mapping value of 3 will be 1 and so on.
 * Now coming to decrypt function it will take encrypted number and pick the first value if the value is 1 then 1 array mapping will be selected.
 *
 * @author Raheel Saleem, Muhammad Ali Mughal 12-12-2023
 */
trait  EncryptionTrait
{
    public function encrypt($data)
    {
        return $this->makeCurlRequest('encrypt', $data);
    }
    public function decrypt($data)
    {
        return $this->makeCurlRequest('decrypt', $data);
    }

    protected function makeCurlRequest($action, $data)
    {
        $url = env('BLACKBOX_URL');
        try {
            $params = [
                'action' => $action,
                'data' => $data,
            ];

            $urlParams = $url . '?' . http_build_query($params);

            if ($action == 'encrypt') {
                $response = Http::timeout(1)->get($urlParams);
            } else {
                $response = Http::withOptions(['verify' => false])->get($urlParams);
            }

            // Get the response content as a string
            $result = $response->body();

            return $result;
        } catch (\Exception $e) {
            return "BlackBox Error: " . $e->getMessage();
        }
    }
}
