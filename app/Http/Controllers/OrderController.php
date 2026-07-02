<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Modules\Order\Dto\OrderDto;
use App\Modules\Order\Enums\OrderStatus;
use App\Temporal\Workflows\OrderWorkflow;
use Carbon\CarbonInterval;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use OpenTelemetry\API\Trace\SpanKind;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Spiral\RoadRunner\Metrics\Metrics;
use Temporal\OpenTelemetry\Tracer;
use Temporal\Client\Update\LifecycleStage;
use Temporal\Client\Update\UpdateOptions;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\Common\IdReusePolicy;
use Temporal\Common\SearchAttributes\SearchAttributeKey;
use Temporal\Common\TypedSearchAttributes;
use Temporal\Common\WorkflowIdConflictPolicy;
use Temporal\Exception\Client\TimeoutException;
use Temporal\Exception\Client\WorkflowNotFoundException;
use Temporal\Exception\Client\WorkflowUpdateException;
use Temporal\Exception\Failure\ApplicationFailure;

class OrderController extends Controller
{
//    successful example
//    curl -X PUT "http://localhost:8000/orders/address" \
//    -H "Content-Type: application/json" \
//    -H "Accept: application/json" \
//    -d '{
//    "order_id": "019e9158-3366-7306-8b7a-27559e440017",
//    "new_address": "456 Market Street, Floor 2, San Francisco, CA"
//    }'
    public function updateAddress(Request $request, WorkflowClientInterface $client)
    {
        $orderId = Uuid::fromString($request->string('order_id'));
        $newAddress = (string)$request->string('new_address');

        $workflowId = "order-{$orderId->toString()}";

        try {
            $workflow = $client->newRunningWorkflowStub(
                class: OrderWorkflow::class,
                workflowID: $workflowId,
            );

            $updatedOrder = $workflow->updateAddress($newAddress);

            return response()->json([
                'order' => $updatedOrder,
            ]);
        } catch (WorkflowNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Order not found',
            ], 404);
        } catch (WorkflowUpdateException $e) {
            Log::error("workflow update error occurred", [$e]);
            return response()->json([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

//    successful example
//    curl -X PUT "http://localhost:8000/orders/address-async" \
//    -H "Content-Type: application/json" \
//    -H "Accept: application/json" \
//    -d '{
//    "order_id": "019e9c26-dd25-711d-bbf9-74918a4e713c",
//    "new_address": "456 Market Street, Floor 2, San Francisco, CA"
//    }'
    public function asyncUpdateAddress(Request $request, WorkflowClientInterface $client)
    {
        $orderId = Uuid::fromString((string) $request->string('order_id'));
        $newAddress = (string) $request->string('new_address');

        $workflowId = "order-{$orderId->toString()}";

        try {
            $stub = $client->newUntypedRunningWorkflowStub($workflowId);

            $handle = $stub->startUpdate(// Note that the processing Workflow Worker must be available.
                UpdateOptions::new('updateAddress', LifecycleStage::StageAccepted)
                    ->withUpdateId($workflowId)      // ID for idempotency
                    ->withResultType(OrderDto::class),      // name of update method
                $newAddress,
            );
//            $waitTimeoutInSeconds = 1;
//            $handle->getResult(timeout: $waitTimeoutInSeconds); // optional timeout

            // Client back immediately after validation!
            // Handler still executing in the background
            return response()->json([
                'update_id' => $handle->getId(),
                'workflow_id' => $workflowId,
            ], 202);
        } catch (WorkflowNotFoundException $e) {
            return response()->json([
                'success' => false,
                'error' => 'Order not found',
            ], 404);
        } catch (WorkflowUpdateException $e) {
            Log::error("Update failed", [
                'workflow_id' => $workflowId,
                'message'     => $e->getMessage(),
                'previous'    => $e->getPrevious()?->getMessage(),  // includes stacktrace
            ]);

            $cause = $e->getPrevious();

            if ($cause instanceof ApplicationFailure) {
                $type    = $cause->getType();              // e.g. "DomainException"
                $message = $cause->getOriginalMessage();   // your actual message

                // Distinguish by exception type
                $http = match (true) {
                    str_contains($type, 'DomainException')        => 422,  // business rule
                    str_contains($type, 'InvalidArgumentException') => 400,  // input invalid
                    default                                        => 500,
                };

                return response()->json([
                    'status'  => 'failed',
                    'error'   => $message,
                    'type'    => $type,
                ], $http);
            }

            return response()->json([
                'status' => 'failed',
                'error'  => $e->getMessage(),
            ], 500);
        }
    }
//      curl -X GET http://localhost:8000/orders/019e9c26-dd25-711d-bbf9-74918a4e713c/updates/order-019e9c26-dd25-711d-bbf9-74918a4e713c -H "Accept: application/json"
    public function getUpdateResult(string $workflowId, string $updateId, WorkflowClientInterface $client)
    {
        // newUntypedRunningWorkflowStub gives you access to update handles
        $stub = $client->newUntypedRunningWorkflowStub($workflowId);

        try {
            // Re-attach to the existing update handle
            $handle = $stub->getUpdateHandle($updateId, OrderDto::class);

            // Poll with short timeout — long-poll pattern
            $result = $handle->getResult(timeout: 5);  // wait up to 5s for completion

            return response()->json([
                'status' => 'completed',
                'result' => $result,
            ]);
        } catch (TimeoutException $e) {
            // Still running — client should retry
            return response()->json([
                'status' => 'pending',
                'message' => 'Update still running, poll again',
            ], 202);
        } catch (WorkflowUpdateException $e) {
            // Update method threw an exception
            return response()->json([
                'status' => 'failed',
                'error'  => $e->getMessage(),
            ], 422);
        }
    }

//     curl -X POST http://localhost:8000/orders -H "Content-Type: application/json" -H "Accept: application/json" -d '{"customer_name":"John Doe","phone":"+1234567890","address":"123 Main St, Apt4"}'
    public function store(
        Request $request,
        WorkflowClientInterface $client,
        Metrics $metrics,
    )
    {
        $validated = $request->validate([
            'customer_name' => 'required|string',
            'phone' => 'required|string',
            'address' => 'required|string',
        ]);

        $orderId = Uuid::uuid7();
        $workflowId = "order-{$orderId->toString()}";

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
            ->withTypedSearchAttributes(
                TypedSearchAttributes::empty()
                    ->withValue(SearchAttributeKey::forKeyword('OrderId'), $orderId)
                    ->withValue(SearchAttributeKey::forKeyword('OrderStatus'), OrderStatus::Created->value)
                )
        );

        $order = Order::firstOrCreate([
            'workflow_id' => $workflowId,
        ], [
            'id' => $orderId,
            'customer_name' => $validated['customer_name'],
            'customer_phone' => $validated['phone'],
            'delivery_address' => $validated['address'],
        ]);

        $metrics->add('created_orders', 1);

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
