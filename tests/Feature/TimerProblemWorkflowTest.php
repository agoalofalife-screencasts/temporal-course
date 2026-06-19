<?php

namespace Feature;

use App\Temporal\Workflows\TimerProblemWorkflow;
use Temporal\Client\ClientOptions;
use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\WorkflowClient;
use Temporal\Client\WorkflowClientInterface;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Testing\TestService;
use Tests\TestCase;

class TimerProblemWorkflowTest extends TestCase
{
    protected WorkflowClientInterface $workflowClient;

    protected function setUp(): void
    {
        parent::setUp();

        if (! $this->usesTimeSkippingServer()) {
            $this->markTestSkipped('Needs the time-skipping server — TEMPORAL_TEST_MODE=local.');
        }

        $this->workflowClient = new WorkflowClient(
            serviceClient: ServiceClient::create(config('temporal.address')),
            options: (new ClientOptions())->withNamespace(config('temporal.namespace')),
            converter: $this->app->make(DataConverterInterface::class),
        );
    }

    public function test_a(): void
    {
        $workflow = $this->workflowClient->newWorkflowStub(TimerProblemWorkflow::class);
        $run = $this->workflowClient->start($workflow);

        $this->assertTrue($run->getResult());
    }

    //  TEMPORAL_TEST_MODE=local php artisan test
    public function test_b(): void
    {
        $workflow = $this->workflowClient->newWorkflowStub(TimerProblemWorkflow::class);
        $run = $this->workflowClient->start($workflow);

        $this->assertTrue($run->getResult());
    }
}
