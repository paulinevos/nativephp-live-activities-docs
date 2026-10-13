<?php

declare(strict_types=1);

namespace Vos\LiveActivities\Tests\Feature;

use Vos\LiveActivities\Bridge\Transport;
use Vos\LiveActivities\Facades\LiveActivities;
use Vos\LiveActivities\LiveActivities as Manager;
use Vos\LiveActivities\Tests\TestCase;

final class FacadeTest extends TestCase
{
    public function test_facade_resolves_manager_and_uses_injected_transport(): void
    {
        $transport = $this->createMock(Transport::class);
        $transport->method('available')->willReturn(true);
        $transport->expects($this->once())->method('call')->with('LiveActivities.List', '{}')->willReturn('{"activities":[]}');
        $this->app->instance(Transport::class, $transport);

        $this->assertInstanceOf(Manager::class, LiveActivities::getFacadeRoot());
        $this->assertCount(0, LiveActivities::all());
    }

    public function test_facade_reports_unsupported_in_web_preview(): void
    {
        $this->assertSame('unsupported', LiveActivities::capabilities()->platform);
    }
}
