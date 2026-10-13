<?php

declare(strict_types=1);

namespace Vos\LiveActivities\Commands;

use Native\Mobile\Plugins\Commands\NativePluginHookCommand;
use Symfony\Component\Process\Process;

final class InstallIosExtensionCommand extends NativePluginHookCommand
{
    protected $signature = 'live-activities:install-ios';

    protected $description = 'Install the Live Activities widget extension into the generated iOS project';

    public function handle(): int
    {
        if (! $this->isIos()) {
            return self::SUCCESS;
        }

        $process = new Process([
            'xcrun', 'swift', $this->pluginPath().'/resources/templates/ios/install-extension.swift',
            $this->buildPath(), $this->pluginPath(), $this->appId(),
        ]);
        $process->setTimeout(120);
        $process->run(fn (string $type, string $buffer) => $this->output->write($buffer));

        return $process->isSuccessful() ? self::SUCCESS : self::FAILURE;
    }
}
