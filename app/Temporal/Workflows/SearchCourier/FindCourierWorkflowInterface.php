<?php

declare(strict_types=1);

namespace App\Temporal\Workflows\SearchCourier;

use App\Modules\SearchCourier\Dto\CourierSearchStatusInfo;
use App\Modules\SearchCourier\Dto\DeliveryLocation;
use App\Modules\SearchCourier\Dto\SearchCourierResult;
use Temporal\Workflow\QueryMethod;
use Temporal\Workflow\ReturnType;
use Temporal\Workflow\SignalMethod;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
interface FindCourierWorkflowInterface
{
    #[WorkflowMethod(name: 'FindCourier')]
    #[ReturnType(SearchCourierResult::class)]
    public function find(DeliveryLocation $pickup, DeliveryLocation $dropOff);

    #[SignalMethod]
    public function courierAccepted(string $courierId): void;

    #[SignalMethod]
    public function courierDeclined(string $courierId): void;

    #[QueryMethod]
    public function getSearchStatus(): CourierSearchStatusInfo;
}
