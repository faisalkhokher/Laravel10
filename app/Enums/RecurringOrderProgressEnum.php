<?php

namespace App\Enums;

enum RecurringOrderProgressEnum
{
    case new;
    case inprocess;
    case success;
    case failed;
    case unsuccess;
}
