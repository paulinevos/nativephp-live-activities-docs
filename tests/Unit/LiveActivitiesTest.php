<?php

declare(strict_types=1);

namespace Vos\LiveActivities\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Vos\LiveActivities\ActivityContent;
use Vos\LiveActivities\Bridge\NativeBridge;
use Vos\LiveActivities\Bridge\NativeTransport;
use Vos\LiveActivities\Bridge\Transport;
use Vos\LiveActivities\Exceptions\ActivityException;
use Vos\LiveActivities\LiveActivities;

final class LiveActivitiesTest extends TestCase
{
    private function manager(?string $response, ?string $method = null, ?array $payload = null): LiveActivities
    {
        $transport = $this->createMock(Transport::class);
        $transport->method('available')->willReturn(true);
        $call = $transport->expects($this->once())->method('call');
        if ($method !== null) {
            $call->with('LiveActivities.'.$method, json_encode((object) $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        }
        $call->willReturn($response);

        return new LiveActivities(new NativeBridge($transport));
    }

    private function activity(): array
    {
        return ['id' => 'opaque-native-id', 'key' => 'delivery-42', 'content' => ['title' => 'Delivery', 'subtitle' => '', 'progress' => 0], 'state' => 'active'];
    }

    public function test_start_returns_native_id_from_direct_success_data(): void
    {
        $payload = $this->activity();
        $manager = $this->manager(json_encode(['activity' => $payload]), 'Start', ['key' => 'delivery-42', 'content' => $payload['content']]);
        $activity = $manager->start('delivery-42', ActivityContent::make('Delivery'));

        $this->assertSame('opaque-native-id', $activity->id);
        $this->assertSame('delivery-42', $activity->key);
        $this->assertSame('active', $activity->state);
        $this->assertSame('Delivery', $activity->content->title);
    }

    public function test_update_replaces_content_using_opaque_native_id(): void
    {
        $payload = $this->activity();
        $payload['content']['progress'] = 0.75;
        $manager = $this->manager(json_encode(['activity' => $payload]), 'Update', ['id' => 'opaque-native-id', 'content' => $payload['content']]);

        $this->assertSame(0.75, $manager->update('opaque-native-id', ActivityContent::make('Delivery')->progress(0.75))->content->progress);
    }

    public function test_start_passes_presentation_to_native_and_list_restores_it(): void
    {
        $content = ActivityContent::make('Delivery')->icon('truck')->color('#06B6D4');
        $payload = array_replace($this->activity(), ['content' => $content->toArray()]);
        $manager = $this->manager(json_encode(['activity' => $payload]), 'Start', ['key' => 'delivery-42', 'content' => $content->toArray()]);

        $started = $manager->start('delivery-42', $content);
        $this->assertSame($content->toArray(), $started->content->toArray());
        $listed = $this->manager(json_encode(['activities' => [$payload]]))->all();
        $this->assertSame($content->toArray(), $listed->toArray()[0]['content']);
    }

    public function test_update_can_change_icon_and_color_together(): void
    {
        $content = ActivityContent::make('Delivered')->progress(1)->icon('circle-check')->color('#22C55E');
        $payload = array_replace($this->activity(), ['content' => $content->toArray()]);
        $manager = $this->manager(json_encode(['activity' => $payload]), 'Update', ['id' => 'opaque-native-id', 'content' => $content->toArray()]);

        $this->assertSame($content->toArray(), $manager->update('opaque-native-id', $content)->content->toArray());
    }

    public function test_end_requires_native_acknowledgement(): void
    {
        $manager = $this->manager('{"id":"opaque-native-id","state":"ended"}', 'End', ['id' => 'opaque-native-id']);
        $manager->end('opaque-native-id');
    }

    public function test_list_returns_iterable_countable_collection_from_native_state(): void
    {
        $manager = $this->manager(json_encode(['activities' => [$this->activity()]]), 'List', []);
        $activities = $manager->all();

        $this->assertCount(1, $activities);
        $this->assertSame('opaque-native-id', iterator_to_array($activities)[0]->id);
        $this->assertSame('Delivery', $activities->toArray()[0]['content']['title']);
    }

    public function test_empty_native_list_returns_empty_collection(): void
    {
        $this->assertCount(0, $this->manager('{"activities":[]}')->all());
    }

    public function test_list_rejects_duplicate_native_ids(): void
    {
        $this->expectException(ActivityException::class);
        $this->manager(json_encode(['activities' => [$this->activity(), $this->activity()]]))->all();
    }

    public function test_capabilities_expose_platform_and_promotion_separately(): void
    {
        $manager = $this->manager('{"platform":"android","supported":true,"enabled":true,"push":false,"promoted":false}', 'Capabilities', []);
        $capabilities = $manager->capabilities();

        $this->assertSame('android', $capabilities->platform);
        $this->assertTrue($capabilities->supported);
        $this->assertTrue($capabilities->enabled);
        $this->assertFalse($capabilities->push);
        $this->assertFalse($capabilities->promoted);
    }

    public function test_missing_runtime_reports_unsupported_and_rejects_mutations(): void
    {
        $transport = $this->createMock(Transport::class);
        $transport->method('available')->willReturn(false);
        $transport->expects($this->never())->method('call');
        $manager = new LiveActivities(new NativeBridge($transport));
        $this->assertSame('unsupported', $manager->capabilities()->platform);
        $this->assertFalse($manager->capabilities()->supported);

        try {
            $manager->start('delivery', ActivityContent::make('Delivery'));
            $this->fail('Unavailable runtime accepted a mutation.');
        } catch (ActivityException $exception) {
            $this->assertSame('unavailable', $exception->errorCode);
        }
    }

    #[DataProvider('invalidCapabilities')]
    public function test_capabilities_reject_malformed_native_values(string $response): void
    {
        $this->expectException(ActivityException::class);
        $this->manager($response)->capabilities();
    }

    public static function invalidCapabilities(): iterable
    {
        yield ['{"platform":"windows","supported":true,"enabled":true,"push":false,"promoted":false}'];
        yield ['{"platform":"ios","supported":1,"enabled":true,"push":false,"promoted":false}'];
        yield ['{"platform":"ios","supported":true,"enabled":true,"push":true,"promoted":false}'];
        yield ['{"platform":"ios","supported":true,"enabled":true,"push":false,"promoted":false,"extra":0}'];
    }

    public function test_end_rejects_acknowledgement_for_another_activity(): void
    {
        $this->expectException(ActivityException::class);
        $this->manager('{"id":"another-id","state":"ended"}')->end('requested-id');
    }

    public function test_nativephp_jump_shim_on_a_web_boot_does_not_enable_native_operations(): void
    {
        require_once dirname(__DIR__, 2).'/vendor/nativephp/mobile/src/jump_bridge_functions.php';

        $this->assertTrue(function_exists('nativephp_call'));
        $this->assertFalse((new NativeTransport)->available());
    }

    public function test_normal_php_runtime_is_not_a_native_runtime(): void
    {
        $this->assertFalse((new NativeTransport)->available());
    }

    #[DataProvider('nativeFailures')]
    public function test_native_failures_preserve_code_and_message(string $code): void
    {
        $manager = $this->manager(json_encode(['status' => 'error', 'code' => $code, 'message' => 'Native operation rejected.', 'data' => []]));
        try {
            $manager->update('unknown-id', ActivityContent::make('Delivery'));
            $this->fail('Native failure was swallowed.');
        } catch (ActivityException $exception) {
            $this->assertSame($code, $exception->errorCode);
            $this->assertSame('Native operation rejected.', $exception->getMessage());
        }
    }

    public static function nativeFailures(): iterable
    {
        foreach (['activity_not_found', 'duplicate_activity', 'permission_denied', 'unavailable', 'invalid_payload', 'payload_too_large', 'native_error'] as $code) {
            yield $code => [$code];
        }
    }

    #[DataProvider('malformedResponses')]
    public function test_malformed_responses_never_produce_success(?string $response): void
    {
        $this->expectException(ActivityException::class);
        $this->manager($response)->start('delivery', ActivityContent::make('Delivery'));
    }

    public static function malformedResponses(): iterable
    {
        yield [null];
        yield ['invalid json'];
        yield ['null'];
        yield ['[]'];
        yield ['{"status":"error"}'];
        yield ['{"status":"success","data":{}}'];
        yield ['{"activity":null}'];
        yield ['{"activity":{"id":"x","key":"y","content":{},"state":"ended"}}'];
    }

    #[DataProvider('invalidKeys')]
    public function test_invalid_key_is_rejected_before_bridge_call(string $key): void
    {
        $transport = $this->createMock(Transport::class);
        $transport->expects($this->never())->method('call');
        $manager = new LiveActivities(new NativeBridge($transport));
        $this->expectException(ActivityException::class);
        $manager->start($key, ActivityContent::make('Delivery'));
    }

    public static function invalidKeys(): iterable
    {
        yield [''];
        yield ['  '];
        yield [str_repeat('a', 81)];
        yield ["\xff"];
    }
}
