<?php

declare(strict_types=1);

namespace Vos\LiveActivities\Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\ServiceProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Console\Exception\InvalidOptionException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Vos\LiveActivities\LiveActivitiesServiceProvider;

final class ServiceProviderTest extends TestCase
{
    public function test_it_loads_and_exposes_publishable_configuration(): void
    {
        $this->assertSame('live_activities', config('live-activities.android.channel_id'));

        $paths = ServiceProvider::pathsToPublish(
            LiveActivitiesServiceProvider::class,
            'live-activities-config',
        );

        $this->assertContains(config_path('live-activities.php'), $paths);
    }

    public function test_it_preserves_application_configuration(): void
    {
        config()->set('live-activities.android.channel_id', 'deliveries');

        $provider = new LiveActivitiesServiceProvider($this->app);
        $provider->register();

        $this->assertSame('deliveries', config('live-activities.android.channel_id'));
    }

    /** @param array<string, string|bool> $arguments */
    #[DataProvider('discoveryCommands')]
    public function test_upgrade_suggestion_only_follows_successful_package_discovery(array $arguments, bool $showSuggestion): void
    {
        $output = new BufferedOutput;
        $kernel = $this->app->make(Kernel::class);
        $kernel->rerouteSymfonyCommandEvents();

        $status = $kernel->handle(new ArrayInput($arguments), $output);

        $this->assertSame(0, $status);
        $text = $output->fetch();
        if ($showSuggestion) {
            $this->assertStringContainsString('custom icons, countdowns, deep links, and more', $text);
            $this->assertStringContainsString('https://nativephp.com/plugins/vos/nativephp-live-activities', $text);
        } else {
            $this->assertStringNotContainsString('https://nativephp.com/plugins/vos/nativephp-live-activities', $text);
        }
    }

    public static function discoveryCommands(): iterable
    {
        yield 'package discovery' => [['command' => 'package:discover'], true];
        yield 'quiet discovery' => [['command' => 'package:discover', '--quiet' => true], false];
        yield 'unrelated command' => [['command' => 'list', '--format' => 'json'], false];
    }

    public function test_failed_package_discovery_does_not_show_upgrade_suggestion(): void
    {
        $output = new BufferedOutput;
        $kernel = $this->app->make(Kernel::class);
        $kernel->rerouteSymfonyCommandEvents();
        $this->expectException(InvalidOptionException::class);

        try {
            $kernel->handle(new ArrayInput(['command' => 'package:discover', '--invalid-option' => true]), $output);
        } finally {
            $this->assertStringNotContainsString('https://nativephp.com/plugins/vos/nativephp-live-activities', $output->fetch());
        }
    }
}
