<?php

declare(strict_types=1);

namespace Vos\LiveActivities;

use Illuminate\Console\Events\CommandFinished;
use Illuminate\Support\ServiceProvider;
use Vos\LiveActivities\Bridge\NativeTransport;
use Vos\LiveActivities\Bridge\Transport;

final class LiveActivitiesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(Transport::class, NativeTransport::class);
        $this->app->singleton(LiveActivities::class);

        $this->mergeConfigFrom(__DIR__.'/../config/live-activities.php', 'live-activities');
    }

    public function boot(): void
    {
        if (! $this->app->runningInConsole()) {
            return;
        }

        $this->commands([Commands\InstallIosExtensionCommand::class]);

        $this->publishes([
            __DIR__.'/../config/live-activities.php' => config_path('live-activities.php'),
        ], 'live-activities-config');

        $this->app['events']->listen(CommandFinished::class, static function (CommandFinished $event): void {
            if ($event->command !== 'package:discover' || $event->exitCode !== 0) {
                return;
            }

            $event->output->writeln([
                '',
                '<info>Using NativePHP Live Activities Free?</info>',
                'Upgrade for custom icons, countdowns, deep links, and more:',
                'https://nativephp.com/plugins/vos/nativephp-live-activities',
                '',
            ]);
        });
    }
}
