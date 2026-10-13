<?php

declare(strict_types=1);

namespace Vos\LiveActivities\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Vos\LiveActivities\ActivityColor;
use Vos\LiveActivities\ActivityContent;
use Vos\LiveActivities\ActivityIcon;
use Vos\LiveActivities\Exceptions\ActivityException;
use Vos\LiveActivities\Support\Payload;

final class ActivityContentTest extends TestCase
{
    public function test_fluent_content_is_immutable_and_serializes_complete_content(): void
    {
        $original = ActivityContent::make('Delivery');
        $changed = $original->subtitle('Almost there')->progress(0.5);

        $this->assertSame(['title' => 'Delivery', 'subtitle' => '', 'progress' => 0.0], $original->toArray());
        $this->assertSame(['title' => 'Delivery', 'subtitle' => 'Almost there', 'progress' => 0.5], $changed->toArray());
    }

    #[DataProvider('invalidContent')]
    public function test_content_rejects_invalid_wire_values(array $content): void
    {
        $this->expectException(ActivityException::class);
        ActivityContent::fromArray($content);
    }

    public static function invalidContent(): iterable
    {
        $valid = ['title' => 'Delivery', 'subtitle' => '', 'progress' => 0];
        yield 'missing field' => [['title' => 'Delivery']];
        yield 'extra field' => [$valid + ['eta' => 10]];
        yield 'empty title' => [array_replace($valid, ['title' => ' '])];
        yield 'unicode whitespace title' => [array_replace($valid, ['title' => "\u{00a0}\u{2003}"])];
        yield 'long title' => [array_replace($valid, ['title' => str_repeat('a', 121)])];
        yield 'long subtitle' => [array_replace($valid, ['subtitle' => str_repeat('a', 241)])];
        yield 'invalid utf8' => [array_replace($valid, ['title' => "\xff"])];
        yield 'boolean progress' => [array_replace($valid, ['progress' => true])];
        yield 'string progress' => [array_replace($valid, ['progress' => '0.5'])];
        yield 'negative progress' => [array_replace($valid, ['progress' => -0.1])];
        yield 'excessive progress' => [array_replace($valid, ['progress' => 1.1])];
        yield 'infinite progress' => [array_replace($valid, ['progress' => INF])];
        yield 'nan progress' => [array_replace($valid, ['progress' => NAN])];
        yield 'unknown icon' => [$valid + ['icon' => 'not-an-icon']];
        yield 'icon path' => [$valid + ['icon' => '../truck']];
        yield 'null icon' => [$valid + ['icon' => null]];
        yield 'non-string icon' => [$valid + ['icon' => ['truck']]];
        yield 'short color' => [$valid + ['color' => '#fff']];
        yield 'alpha color' => [$valid + ['color' => '#FFFFFFFF']];
        yield 'named color' => [$valid + ['color' => 'red']];
        yield 'invalid hex' => [$valid + ['color' => '#GGGGGG']];
        yield 'trailing newline' => [$valid + ['color' => "#123456\n"]];
        yield 'null color' => [$valid + ['color' => null]];
        yield 'non-string color' => [$valid + ['color' => 123456]];
    }

    public function test_icon_and_color_survive_fluent_changes_and_round_trip_without_mutating_original(): void
    {
        $original = ActivityContent::make('Delivery');
        $changed = $original->icon(ActivityIcon::Truck)->color('#06b6d4')
            ->subtitle('Nearby')->progress(0.5);

        $this->assertNull($original->icon);
        $this->assertNull($original->color);
        $this->assertSame([
            'title' => 'Delivery', 'subtitle' => 'Nearby', 'progress' => 0.5,
            'icon' => 'truck', 'color' => '#06B6D4',
        ], $changed->toArray());
        $restored = ActivityContent::fromArray($changed->toArray());
        $this->assertSame(ActivityIcon::Truck, $restored->icon);
        $this->assertSame('#06B6D4', $restored->color->hex);
        $this->assertSame($changed->toArray(), $restored->toArray());
    }

    public function test_icon_names_are_accepted_and_presentation_can_be_supplied_independently(): void
    {
        $icon = ActivityContent::make('Preparing')->icon('utensils');
        $color = ActivityContent::make('Delivered')->color(ActivityColor::hex('#22c55e'));

        $this->assertSame(ActivityIcon::Utensils, $icon->icon);
        $this->assertArrayNotHasKey('color', $icon->toArray());
        $this->assertSame('#22C55E', $color->toArray()['color']);
        $this->assertArrayNotHasKey('icon', $color->toArray());
        $this->assertSame($icon->toArray(), ActivityContent::fromArray($icon->toArray())->toArray());
        $this->assertSame($color->toArray(), ActivityContent::fromArray($color->toArray())->toArray());
    }

    public function test_character_limits_count_unicode_and_json_size_counts_utf8_bytes(): void
    {
        $title = str_repeat('🚲', 120);
        $this->assertSame($title, ActivityContent::make($title)->title);
        $this->assertSame('{"title":"🚲/"}', Payload::encode(['title' => '🚲/']));
        $atLimit = ['value' => str_repeat('🚲', 947)];
        $this->assertSame(3800, strlen(Payload::encode($atLimit)));
        Payload::assertSize($atLimit);
        $this->expectException(ActivityException::class);
        $this->expectExceptionMessage('3800 UTF-8 bytes');
        Payload::assertSize(['value' => $atLimit['value'].'a']);
    }
}
