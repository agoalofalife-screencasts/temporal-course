<?php

namespace App\Http\Controllers;

use App\Temporal\Workflows\HelloWorldWorkflow;
use Illuminate\Http\Request;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Client\WorkflowOptions;

class HelloWorldController extends Controller
{
    public function __invoke(
        Request $request,
        WorkflowClientInterface $client,
    )
    {
        $name = $request->input('name');
        $workflow = $client->newWorkflowStub(
            HelloWorldWorkflow::class,
            WorkflowOptions::new()->withTaskQueue(config('temporal.queue'))
                ->withWorkflowId('hello-world-workflow-' . $name . '-'. time())
        );

        $result = $workflow->execute($name);

        return response()->json([
            'greeting' => $result,
        ]);
    }
}
