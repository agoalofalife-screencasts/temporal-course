<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Modules\Order\Dto\WeeklyOrderInput;
use App\Temporal\Workflows\WeeklySubscriptionWorkflow;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterval;
use Illuminate\Console\Command;
use Ramsey\Uuid\Uuid;
use Temporal\Client\ClientOptions;
use Temporal\Client\GRPC\ServiceClientInterface;
use Temporal\Client\Schedule\Action\StartWorkflowAction;
use Temporal\Client\Schedule\BackfillPeriod;
use Temporal\Client\Schedule\Policy\ScheduleOverlapPolicy;
use Temporal\Client\Schedule\Policy\SchedulePolicies;
use Temporal\Client\Schedule\Schedule;
use Temporal\Client\Schedule\Spec\CalendarSpec;
use Temporal\Client\Schedule\Spec\Range;
use Temporal\Client\Schedule\Spec\ScheduleSpec;
use Temporal\Client\Schedule\Spec\StructuredCalendarSpec;
use Temporal\Client\ScheduleClient;
use Temporal\Common\RetryOptions;
use Temporal\DataConverter\DataConverterInterface;

class MakeWeeklyOrderWorkflowBySchedule extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:weekly-order-workflow-by-schedule';

    /**
     * Execute the console command.
     * ServiceClientInterface @see LaravelTemporalServiceProvider::packageRegistered() for calling temporal by grps
     * DataConverterInterface @see LaravelTemporalServiceProvider::packageRegistered()
     */
    public function handle(ServiceClientInterface $serviceClient, DataConverterInterface $dataConverter)
    {
        $scheduleClient = ScheduleClient::create(
            serviceClient: $serviceClient,
            options: (new ClientOptions)->withNamespace('default'),
            converter: $dataConverter, // serialize and unserialize data to and from temporal
        );

        $handle = $scheduleClient->getHandle('f9560746-9115-41f9-8a4a-bb83d372644e');
//        dd($handle->);
//        $handle->backfill([
//            BackfillPeriod::new(
//                startTime: CarbonImmutable::parse('2026-05-01 00:00:00'),
//                endTime: CarbonImmutable::parse('2026-05-30 23:59:59'),
//            )->withOverlapPolicy(ScheduleOverlapPolicy::BufferAll),
//        ]);
        exit(0);

        $orderId = Uuid::uuid7();
        $scheduleWorkflowType = class_basename(WeeklySubscriptionWorkflow::class);

        // find some how appropriate users for that
        $userPhone = fake()->numerify('##########');

        $workflowId = "weekly-meal-{$userPhone}";

        $order = Order::firstOrCreate([
            'workflow_id' => $workflowId,
        ], [
            'id' => $orderId,
            'customer_name' => fake()->name(),
            'customer_phone' => $userPhone,
            'delivery_address' => fake()->address(),
        ]);

        $this->info("Order created: $order->id");
        $this->info("Workflow ID: $workflowId");

        $scheduleHandle = $scheduleClient->createSchedule(
            Schedule::new()->withAction(
                StartWorkflowAction::new($scheduleWorkflowType)
                    ->withWorkflowId($workflowId)
                    ->withWorkflowRunTimeout(CarbonInterval::minutes(10))
                    ->withRetryPolicy(RetryOptions::new()->withMaximumAttempts(2))
                    ->withInput([new WeeklyOrderInput(menuId: Uuid::uuid7()->toString(), order: $order)])
                    ->withTaskQueue('default')
            )->withSpec(
                ScheduleSpec::new()
//                   timezone mostly matters for calendar/cron-based schedules like "run at 9:00 every Monday"
//                    ->withTimezoneName('Asia/Tokyo') // IANA standard
//                    ->withTimezoneData() // TZif format, timezone raw file, for example in unix system store
//                  /usr/share/zoneinfo/
//                    ->withCalendarList() // alternative

//                    withAddedCalendar adds one calendar rule
//                    ->withAddedCalendar( // run every monday at 9:00
//                        CalendarSpec::new() // not eloquent enough the api of this class, use withAddedStructuredCalendar
//                            ->withSecond(0)
//                            ->withMinute(0)
//                            ->withHour(9)
//                            ->withDayOfWeek(1) // monday
//                    )
                    ->withAddedInterval(CarbonInterval::days(7)) // start from creation
//                    ->withAddedCronString('0 0 9 * * 1')
//                    ->withJitter(CarbonInterval::minutes(10))
//                 for evenly distribute loading if for example have a lot of subscriptions in 9 am
// withAddedStructuredCalendar() is like withAddedCalendar(), but more programmatic and strongly structured.
//                   ->withAddedStructuredCalendar(
//                       StructuredCalendarSpec::new() // every Monday at 09:00
//                           ->withAddedSecond(Range::new(0, 0))
//                           ->withAddedMinute(Range::new(0, 0))
//                           ->withAddedHour(Range::new(9, 9))
//                           ->withAddedDayOfMonth(Range::new(1, 31))
//                           ->withAddedMonth(Range::new(1, 12))
//                           ->withAddedDayOfWeek(Range::new(1, 1))
//                        // 0 = Sunday, 1 = Monday, 2 = Tuesday, 3 = Wednesday, 4 = Thursday, 5 = Friday, 6 = Saturday
//                    )
            )->withPolicies(
                SchedulePolicies::new()
//                    ->withCatchupWindow()
//                    ->withPauseOnFailure(true)
//  If a scheduled action fails, automatically pause the schedule so it does not keep starting new failed runs.
                    ->withOverlapPolicy(ScheduleOverlapPolicy::Skip)
                // ScheduleOverlapPolicy::Skip - SKIP — if prev still working

                // ScheduleOverlapPolicy::BufferOne — add in queue (max 1)
                // Good for: reports where it's important not to miss

                // ScheduleOverlapPolicy::BufferAll — add all in queue

                // ScheduleOverlapPolicy::CancelOther — cancel previous, start new

                // ScheduleOverlapPolicy::TerminateOther — terminate previous, start new
                // Good for: data updates where only the latest version is important

                // ScheduleOverlapPolicy::AllowAll — run in parallel, could be conflicts
            ),
        );

        $this->info("Schedule created: {$scheduleHandle->getID()}");

        // Start the schedule actions that should have happened in the past time range.
        // recover missed schedule runs
        // replay jobs that should have happened while schedule was paused
        // migrate from cron/manual jobs to Temporal schedules
        // generate missed weekly/monthly reports
        // create missed orders/invoices
        // re-run scheduled processing after fixing a bug

//        $scheduleHandle->backfill([
//            BackfillPeriod::new(
//                startTime: CarbonImmutable::parse('2026-05-01 00:00:00'),
//                endTime: CarbonImmutable::parse('2026-05-21 23:59:59'),
//            )->withOverlapPolicy(ScheduleOverlapPolicy::BufferAll),
//        ]);


//        $scheduleHandle->trigger();

        // to manage schedule from code
//        $handle = $scheduleClient->getHandle('schedule-id');
//        $handle->pause('because..'); // the reason I could not find in UI
//        $handle->unpause();
//        $handle->delete();

//        $description = $handle->describe();
//        $description->info->createdAt;
//        $handle->update();
    }
}
