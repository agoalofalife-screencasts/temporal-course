<?php

namespace App\Modules\Order\Enums;
enum OrderStatus: string
{
    case Created = 'Created';
    case Processing = 'Processing';
    case Completed = 'Completed';
    case Canceled = 'Canceled';
}
