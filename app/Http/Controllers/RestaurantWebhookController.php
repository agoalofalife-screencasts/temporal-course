<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Order;
use App\Temporal\Workflows\OrderWorkflow;
use Illuminate\Http\Request;
use Temporal\Client\WorkflowClientInterface;

class RestaurantWebhookController extends Controller
{
    public function __construct(private readonly WorkflowClientInterface $workflowClient)
    {
    }


//    curl -X POST http://localhost:8000/orders/1/states -H "Content-Type: application/json" -H "Accept: application/json" -d '{"is_confirmed": true}'
    public function restaurantConfirmation(Request $request, Order $order)
    {
        $isConfirmed = $request->input('is_confirmed');

        $workflowId = "order-{$order->id}"; // workflow id has to be unique

        $workflow = $this->workflowClient->newRunningWorkflowStub(
            OrderWorkflow::class,
            $workflowId,
        );

        $workflow->restaurantConfirmation($isConfirmed);

        return response()->json(['message' => 'Restaurant confirmation received']);
    }
}
