<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Native\ActivityDemo;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;
use Vos\LiveActivities\Bridge\Transport;

final class ActivityDemoTest extends TestCase
{
    public function test_invalid_deep_links_never_start_a_scenario(): void
    {
        Http::fake();
        $demo = new ActivityDemo;
        $demo->mount('../bad-run');
        $this->assertFalse($demo->playing);
        Http::assertNothingSent();
    }

    public function test_unknown_runs_are_rejected_before_native_operations(): void
    {
        Http::fake(['*/runs/*' => Http::response([], 404)]);
        $demo = new ActivityDemo;
        $demo->mount(str_repeat('a', 32));
        $this->assertFalse($demo->playing);
    }

    public function test_a_second_tap_does_not_abandon_a_running_activity(): void
    {
        Http::fake();
        $demo = new ActivityDemo;
        $demo->playing = true;
        $demo->runId = str_repeat('a', 32);
        $demo->activityId = 'existing-native-id';
        $demo->begin();
        $this->assertSame('existing-native-id', $demo->activityId);
        $this->assertSame(str_repeat('a', 32), $demo->runId);
        Http::assertNothingSent();
    }

    public function test_preview_holds_each_state_until_explicitly_advanced_or_ended(): void
    {
        $run = str_repeat('a', 32);
        $content = ['title' => 'Preparing', 'subtitle' => 'Kitchen', 'progress' => 0];
        Http::fake([
            '*/runs' => Http::response(['id' => $run]),
            '*/scenario' => Http::response(['steps' => [
                ['operation' => 'start', 'content' => $content],
                ['operation' => 'update', 'content' => ['title' => 'On the way', 'subtitle' => 'Courier', 'progress' => 0.5]],
                ['operation' => 'update', 'content' => ['title' => 'Delivered', 'subtitle' => 'Enjoy', 'progress' => 1]],
                ['operation' => 'end'],
            ]]),
            '*/events' => Http::response(['accepted' => true]),
        ]);
        $transport = new class implements Transport
        {
            public array $calls = [];

            public array $contents = [];

            public function available(): bool
            {
                return true;
            }

            public function call(string $method, string $payload): ?string
            {
                $this->calls[] = $method;
                $parameters = json_decode($payload, true);
                if (isset($parameters['content'])) {
                    $this->contents[] = $parameters['content'];
                }

                return json_encode(match ($method) {
                    'LiveActivities.Start', 'LiveActivities.Update' => ['activity' => ['id' => 'native-id', 'key' => 'mock-'.str_repeat('a', 32), 'content' => $parameters['content'], 'state' => 'active']],
                    'LiveActivities.List' => ['activities' => []],
                    'LiveActivities.End' => ['id' => 'native-id', 'state' => 'ended'],
                }, JSON_THROW_ON_ERROR);
            }
        };
        $this->app->instance(Transport::class, $transport);

        $demo = new ActivityDemo;
        $demo->preview();
        $this->assertTrue($demo->previewing);
        $this->assertFalse($demo->playing);
        $this->assertSame(1, $demo->step);
        $demo->advance();
        $demo->begin();
        $demo->preview();
        $this->assertSame(1, $demo->step);
        $demo->nextState();
        $this->assertSame(2, $demo->step);
        $demo->nextState();
        $demo->advance();
        $demo->nextState();
        $this->assertSame(3, $demo->step);
        $this->assertTrue($demo->previewing);
        $demo->endPreview();
        $this->assertFalse($demo->previewing);
        $this->assertFalse($demo->playing);
        $this->assertSame('', $demo->activityId);
        $demo->endPreview();
        $this->assertSame([
            'LiveActivities.Start', 'LiveActivities.List',
            'LiveActivities.Update', 'LiveActivities.List',
            'LiveActivities.Update', 'LiveActivities.List',
            'LiveActivities.End', 'LiveActivities.List',
        ], $transport->calls);
        $this->assertSame(['utensils', 'truck', 'circle-check'], array_column($transport->contents, 'icon'));
        $this->assertSame(['#F97316', '#06B6D4', '#22C55E'], array_column($transport->contents, 'color'));
    }

    public function test_reporting_failure_ends_the_owned_activity(): void
    {
        $transport = new class implements Transport
        {
            public array $calls = [];

            public function available(): bool
            {
                return true;
            }

            public function call(string $method, string $payload): ?string
            {
                $this->calls[] = $method;

                return json_encode(match ($method) {
                    'LiveActivities.Start' => ['activity' => ['id' => 'native-id', 'key' => 'mock-'.str_repeat('a', 32), 'content' => ['title' => 'Delivery', 'subtitle' => '', 'progress' => 0], 'state' => 'active']],
                    'LiveActivities.List' => ['activities' => []],
                    'LiveActivities.End' => ['id' => 'native-id', 'state' => 'ended'],
                }, JSON_THROW_ON_ERROR);
            }
        };
        $this->app->instance(Transport::class, $transport);
        Http::fake([
            '*/scenario' => Http::response(['steps' => [['operation' => 'start', 'content' => ['title' => 'Delivery', 'subtitle' => '', 'progress' => 0]]]]),
            '*/events' => Http::response([], 503),
        ]);
        $demo = new ActivityDemo;
        $demo->runId = str_repeat('a', 32);
        $demo->playing = true;
        $demo->advance();
        $this->assertSame(['LiveActivities.Start', 'LiveActivities.List', 'LiveActivities.End'], $transport->calls);
        $this->assertFalse($demo->playing);
        $this->assertSame('', $demo->activityId);
    }
}
