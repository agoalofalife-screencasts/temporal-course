<?php

declare(strict_types=1);

namespace App\Modules\SearchCourier\Dto;
use App\Modules\SearchCourier\Entity\Courier;

class SearchCourierResult
{
    public ?Courier $courier;
    public CourierSearchStatusInfo $statusInfo;

    /**
     * @param Courier|null $courier might be NullObject if no courier was found
     */
    public function __construct(?Courier $courier, CourierSearchStatusInfo $statusInfo)
    {
        $this->courier = $courier;
        $this->statusInfo = $statusInfo;
    }
    public static function courierFound(Courier $courier, CourierSearchStatusInfo $statusInfo): self
    {
        return new self($courier, $statusInfo);
    }

    public static function courierNotFound(CourierSearchStatusInfo $statusInfo): self
    {
        return new self(null, $statusInfo);
    }

    public static function courierSearchCancelled(CourierSearchStatusInfo $statusInfo): self
    {
        return new self(null, $statusInfo);
    }

    public function courierWasFound(): bool
    {
        return $this->courier !== null;
    }

}
