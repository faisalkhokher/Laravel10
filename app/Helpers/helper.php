<?php

namespace App\Helpers;

if (!function_exists('test')) {
    /**
     * Encode a string to base64
     */
    function test($sting)
    {
        return base64_encode($sting);
    }
}
