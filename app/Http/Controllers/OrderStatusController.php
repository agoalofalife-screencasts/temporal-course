<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Temporal\Workflows\OrderWorkflow;
use Illuminate\Http\Request;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Exception\Client\WorkflowNotFoundException;

class OrderStatusController extends Controller
{
    public function __construct(private readonly WorkflowClientInterface $workflowClient)
    {
    }

    // curl -X GET http://localhost:8000/orders/{ORDER_UUID}/statuses -H "Accept: application/json"
    public function index(Request $request, Order $order)
    {
        $workflowId = "order-{$order->id->toString()}";

        try {
            $workflow = $this->workflowClient->newRunningWorkflowStub(
                class: OrderWorkflow::class,
                workflowID: $workflowId,
            );
            return response()->json([
                'status' => $workflow->getStatus()->getHumanReadableStatus()
            ]);
        } catch (WorkflowNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Order not found',
            ], 404);
        }
    }
}
