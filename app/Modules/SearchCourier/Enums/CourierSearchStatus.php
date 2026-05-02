<?php

namespace App\Modules\SearchCourier\Enums;

enum CourierSearchStatus: string
{
    case Searching = 'Searching';
    case WaitingAcceptance = 'WaitingAcceptance';
    case Found = 'Found';
    case NotFound = 'NotFound';
    case Cancelled = 'Cancelled';
}
