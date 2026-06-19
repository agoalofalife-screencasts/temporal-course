<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Modules\Order\Dto\OrderDto;
use App\Modules\Order\Enums\OrderStatus;
use App\Temporal\Workflows\OrderWorkflow;
use Ramsey\Uuid\Uuid;
use Temporal\Client\ClientOptions;
use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\WorkflowClient;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Testing\ActivityMocker;
use Temporal\Testing\TestService;
use Tests\TestCase;
use Throwable;

class OrderWorkflowTest extends TestCase
{
    protected WorkflowClientInterface $workflowClient;
    private ActivityMocker $activityMocks;

    protected function setUp(): void
    {
        parent::setUp();

        $this->workflowClient = new WorkflowClient(
            serviceClient: ServiceClient::create(config('temporal.address')),
            options: (new ClientOptions())->withNamespace(config('temporal.namespace')),
            converter: $this->app->make(DataConverterInterface::class),
        );

        $this->activityMocks = new ActivityMocker();

        if ($this->usesTimeSkippingServer()) {
            $testService = TestService::create(config('temporal.address'));
            $testService->lockTimeSkipping();
        }
    }

    public function test_restaurant_rejected_order(): void
    {
        $orderId = Uuid::uuid7();

        $order = new Order([
            'id' => $orderId->toString(),
            'workflow_id' => "order-{$orderId->toString()}",
            'customer_name' => 'John Doe',
            'customer_phone' => '+10000000000',
            'delivery_address' => '123 Test Street',
        ]);

        $orderDto = new OrderDto(
            order: $order,
            status: OrderStatus::Created,
        );

        // is a stub, so we can't make assert of anything
        // also we don't have a possibility to return diff result with diff call, for example
        // if we call some activity twice, it will return the same result
        $this->activityMocks->expectCompletion('NotifyRestaurant.notify', '');

        /** @var OrderWorkflow $workflow */
        $workflow = $this->workflowClient->newWorkflowStub(
            OrderWorkflow::class,
            WorkflowOptions::new()
                ->withWorkflowId("order-{$orderId->toString()}")
                ->withTaskQueue(config('temporal.queue')),
        );

        $run = $this->workflowClient->start($workflow, $orderDto);

        $this->waitForStatus($workflow, OrderStatus::RestaurantProcessing);


        $workflow->restaurantConfirmation(false);
        $run->getResult(timeout: 5); // set expectations to wait for the workflow to complete

        // Status is read from the workflow state via the #[QueryMethod].
        $this->assertSame(OrderStatus::RestaurantRejected, $workflow->getStatus());
    }

    /**
     * Poll the workflow query until it reaches the expected status.
     */
    private function waitForStatus(
        object $workflow,
        OrderStatus $expected,
        int $timeoutSeconds = 10
    ): void {
        $deadline = microtime(true) + $timeoutSeconds;
        $last = null;

        while (microtime(true) < $deadline) {
            usleep(100_000); // 100ms

            try {
                $last = $workflow->getStatus();
            } catch (Throwable $e) {
                var_dump($e);
                // status not initialized yet on the very first task — keep polling
                continue;
            }
            if ($last === $expected) {
                return;
            }
        }

        $this->fail(sprintf(
            'Workflow did not reach status "%s" within %.1fs (last seen: "%s").',
            $expected->value,
            $timeoutSeconds,
            $last?->value ?? 'null',
        ));
    }
}
