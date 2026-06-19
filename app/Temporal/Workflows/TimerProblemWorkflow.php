<?php

namespace App\Temporal\Workflows;

use Carbon\CarbonInterval;
use Illuminate\Support\Facades\Log;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
class TimerProblemWorkflow
{
    #[WorkflowMethod]
    public function handle(): \Generator
    {
        Workflow::getLogger()->info('Time checkpoint', [
            'now' => Workflow::now(),
        ]);

        yield Workflow::timer(CarbonInterval::month(1));

        return true;
    }
}
