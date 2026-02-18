<?php

namespace App\Temporal\Workflows;

use App\Temporal\Activities\GreetingActivity;
use Carbon\CarbonInterval;
use Temporal\Activity\ActivityOptions;
use Temporal\Workflow;
use Temporal\Workflow\WorkflowInterface;
use Temporal\Workflow\WorkflowMethod;

#[WorkflowInterface]
class HelloWorldWorkflow
{
    private $greetingActivity;

    public function __construct()
    {
        $this->greetingActivity = Workflow::newActivityStub(
            GreetingActivity::class,
            ActivityOptions::new()->withScheduleToCloseTimeout(CarbonInterval::seconds(30))
        );
    }

    #[WorkflowMethod(name: "HelloWorld")]
    public function execute(string $name): \Generator
    {
        Workflow::getLogger()->info('Hello world workflow started', ['name' => $name]);

        $greeting = yield $this->greetingActivity->greet($name);

        Workflow::getLogger()->info('Hello world workflow completed', ['greeting' => $greeting]);

        return $greeting;
    }
}
