<?php

namespace App\Console\Commands;

use App\Temporal\Workflows\RestaurantPromotionWorkflow;
use Illuminate\Console\Command;
use Temporal\Api\Enums\V1\WorkflowIdReusePolicy;
use Temporal\Client\WorkflowClient;
use Temporal\Client\WorkflowOptions;

class CreateRestaurantPromotionWorkflow extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'create:promotion-workflow';

    /**
     * Execute the console command.
     */
    public function handle(WorkflowClient $client)
    {
        $workflow = $client->newWorkflowStub(
            RestaurantPromotionWorkflow::class,
            WorkflowOptions::new()->withWorkflowId('restaurant-promotion-workflow')
            ->withTaskQueue('default')
            ->withWorkflowIdReusePolicy(
                WorkflowIdReusePolicy::WORKFLOW_ID_REUSE_POLICY_ALLOW_DUPLICATE_FAILED_ONLY,
            )
        );
        $client->start($workflow);

        $this->info('Promotion workflow started');
    }
}
