<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Modules\Order\Dto\OrderDto;
use App\Modules\Order\Enums\OrderStatus;
use App\Temporal\Workflows\OrderWorkflow;
use Carbon\CarbonInterval;
use Illuminate\Http\Request;
use Ramsey\Uuid\Uuid;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Common\IdReusePolicy;
use Temporal\Common\WorkflowIdConflictPolicy;

class OrderController extends Controller
{
//     curl -X POST http://localhost:8000/orders -H "Content-Type: application/json" -H "Accept: application/json" -d '{"customer_name":"John Doe","phone":"+1234567890","address":"123 Main St, Apt4"}'
    public function __invoke(
        Request $request,
        WorkflowClientInterface $client,
    )
    {
        $validated = $request->validate([
            'customer_name' => 'required|string',
            'phone' => 'required|string',
            'address' => 'required|string',
        ]);

        $workflowId = 'order-'. Uuid::uuid7();
//        $workflowId = 'order-1';

        $workflow = $client->newWorkflowStub(
            OrderWorkflow::class,
            WorkflowOptions::new()
            ->withStaticSummary('Order workflow')
//            ->withPriority()
//            ->withEagerStart()
            // Normal flow (without eager start):
            // Client → Temporal Server → Task Queue → Worker picks up → Executes first task
            // The worker polls the task queue periodically, so there's a delay before it picks up the new workflow task.
            //
            // With eager start:
            // Client → Temporal Server → directly to local Worker → Executes first task
            // The server skips the task queue and feeds the first workflow task directly to the worker
            // that is connected from the same process. This eliminates the polling delay.
            //
            // When to use it:
            // - Latency-sensitive applications — when you need the workflow to start processing
            //   as fast as possible (e.g., user-facing API where the customer is waiting)
            //
            // Requirements:
            // - The Temporal server must support it
            // - A local worker must be available (running in the same process/connection as the client)
            ->withStaticDetails(
                "Customer: John Doe\n" .
                "Items: 2x Pizza, 1x Cola\n"
            )
            ->withTaskQueue(config('temporal.queue'))
            ->withWorkflowId($workflowId)
            // structured and not indexed for search
            ->withMemo([
                'phone' => $validated['phone'],
                'address' => $validated['address'],
            ])
            // sets the maximum total lifetime of a workflow execution.
            // If the workflow doesn't complete within this time, Temporal automatically terminates it.
            // this timeout covers everything — including retries and "continue-as-new" chains. It's the absolute hard limit.
//            ->withWorkflowExecutionTimeout()
            // sets the maximum time for a single run of a workflow. It resets on each continue-as-new.
//            ->withWorkflowRunTimeout()
            // sets the maximum time a single workflow task is allowed to execute on the worker.
            // Default is 10 seconds, max is 60 seconds.
            // A workflow task is not the whole workflow. It's one decision step —
            // the piece of workflow code that runs between two yield points.
//            ->withWorkflowTaskTimeout()
            // Scheduled delivery: customer orders now, but wants delivery at 7 PM
//            ->withWorkflowStartDelay(CarbonInterval::minutes(5))
//            ->withRetryOptions()
//            ->withWorkflowIdConflictPolicy() demonstrate in video how works
//                ->withWorkflowIdConflictPolicy(WorkflowIdConflictPolicy::Fail)

//                ->withWorkflowIdReusePolicy(IdReusePolicy::AllowDuplicate)
//            ->withWorkflowIdReusePolicy() demonstrate in video how works
        );

        $order = Order::firstOrCreate([
            'workflow_id' => $workflowId,
        ], [
            'id' => Uuid::uuid7(),
            'customer_name' => $validated['customer_name'],
            'customer_phone' => $validated['phone'],
            'delivery_address' => $validated['address'],
        ]);

        /**
         * Start the workflow ASYNCHRONOUSLY.
         *
         * start() returns immediately, without waiting for completion.
         * This allows the API to respond quickly.
         */
        $client->start(
            $workflow,
            new OrderDto(
            order: $order,
            status: OrderStatus::Created,
        ));

        return response()->json($order, 201);
    }
}
