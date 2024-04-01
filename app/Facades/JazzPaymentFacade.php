<?php

namespace App\Facades;

use Illuminate\Support\Facades\Facade;

class JazzPaymentFacade extends Facade
{
    protected static function getFacadeAccessor()
    {
        return 'jazzpayment';
    }
}
