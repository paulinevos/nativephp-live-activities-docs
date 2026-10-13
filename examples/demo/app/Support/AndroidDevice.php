<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

final class AndroidDevice
{
    public function select(?string $serial = null): string
    {
        $this->configureEnvironment();
        $listing = $this->run(['devices']);
        preg_match_all('/^([^\s]+)\s+device$/m', $listing, $matches);
        $devices = $matches[1];
        if ($serial !== null && in_array($serial, $devices, true)) {
            return $serial;
        }
        if ($serial !== null) {
            throw new RuntimeException('Requested Android device is not connected and authorized: '.$serial);
        }
        if (count($devices) !== 1) {
            throw new RuntimeException('Start one Android emulator, or choose a connected device with --serial=...');
        }

        return $devices[0];
    }

    private function configureEnvironment(): void
    {
        $sdk = config('nativephp.android.android_sdk_path') ?: getenv('ANDROID_HOME') ?: getenv('ANDROID_SDK_ROOT') ?: getenv('HOME').'/Library/Android/sdk';
        if (is_dir($sdk)) {
            config()->set('nativephp.android.android_sdk_path', $sdk);
            putenv('ANDROID_HOME='.$sdk);
            putenv('PATH='.$sdk.'/platform-tools:'.$sdk.'/emulator:'.getenv('PATH'));
        }
        $java = (new JavaRuntime)->select(array_values(array_filter([
            getenv('DEMO_JAVA_HOME'),
            getenv('JAVA_HOME'),
            '/opt/homebrew/opt/openjdk@21/libexec/openjdk.jdk/Contents/Home',
            '/usr/local/opt/openjdk@21/libexec/openjdk.jdk/Contents/Home',
            ...glob(getenv('HOME').'/Library/Java/JavaVirtualMachines/*/Contents/Home'),
            ...glob('/Library/Java/JavaVirtualMachines/*/Contents/Home'),
            '/Applications/Android Studio.app/Contents/jbr/Contents/Home',
        ])));
        putenv('JAVA_HOME='.$java);
        putenv('PATH='.$java.'/bin:'.getenv('PATH'));
        if ((new ExecutableFinder)->find('adb') === null) {
            throw new RuntimeException('Android platform-tools are missing. Install them in Android Studio, then rerun this command.');
        }
    }

    public function connectMock(string $serial): void
    {
        $url = parse_url(config('demo.mock_url'));
        if (! in_array($url['host'] ?? '', ['127.0.0.1', 'localhost'], true)) {
            return;
        }
        $port = $url['port'] ?? 80;
        $this->run(['-s', $serial, 'reverse', 'tcp:'.$port, 'tcp:'.$port]);
    }

    public function launch(string $serial): void
    {
        $package = config('nativephp.app_id');
        $installed = $this->run(['-s', $serial, 'shell', 'pm', 'path', $package]);
        if (! str_contains($installed, 'package:')) {
            throw new RuntimeException('Demo is not installed. Run php bin/demo android first.');
        }
        $this->run(['-s', $serial, 'shell', 'am', 'start', '-W', '-n', $package.'/com.nativephp.mobile.ui.MainActivity']);
    }

    public function run(array $arguments): string
    {
        $process = new Process(['adb', ...$arguments], base_path(), timeout: 120);
        $process->mustRun();

        return $process->getOutput();
    }
}
