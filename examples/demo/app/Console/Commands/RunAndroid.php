<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\AndroidDevice;
use Illuminate\Console\Command;

final class RunAndroid extends Command
{
    protected $signature = 'demo:android {--serial= : Connected emulator or device serial} {--install-only : Install the last built APK without recompiling}';

    protected $description = 'Bundle and run the demo on a connected Android emulator';

    public function handle(AndroidDevice $device): int
    {
        $serial = $device->select($this->option('serial'));
        $device->connectMock($serial);
        if ($this->option('install-only')) {
            $apk = base_path('nativephp/android/app/build/outputs/apk/debug/app-debug.apk');
            if (! is_file($apk)) {
                $this->error('No built APK found. Run php bin/demo android first.');

                return self::FAILURE;
            }
            $device->run(['-s', $serial, 'install', '-r', $apk]);
            $device->launch($serial);
            $this->info('Installed the last built APK on '.$serial.'.');

            return self::SUCCESS;
        }
        if (! is_dir(base_path('nativephp/android'))) {
            $status = $this->call('native:install', ['platform' => 'android', '--no-interaction' => true]);
            if ($status !== self::SUCCESS) {
                return $status;
            }
        }
        $status = $this->call('native:run', ['os' => 'android', 'udid' => $serial, '--no-tty' => true, '--no-interaction' => true, '--start-url' => '/']);
        if ($status !== self::SUCCESS) {
            return $status;
        }
        // NativePHP may return zero after a failed Android build. Require its
        // success marker before reporting that this revision was installed.
        $log = base_path('nativephp/android-build.log');
        $output = is_file($log) ? file_get_contents($log) : '';
        if (! str_contains($output, '=== NativePHP Android Build Completed ===')) {
            $this->error(str_contains($output, 'Gradle build completed successfully')
                ? 'APK built, but installation failed. Start the emulator and run php bin/demo android --install-only.'
                : 'Android build did not complete. Check nativephp/android-build.log.');

            return self::FAILURE;
        }
        $device->launch($serial);
        $this->info('Demo installed on '.$serial.'. Tap Allow notifications, then Preview Live Update.');

        return self::SUCCESS;
    }
}
