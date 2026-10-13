<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;
use Symfony\Component\Process\Process;

final class Simulator
{
    public function boot(?string $requested = null): string
    {
        if (PHP_OS_FAMILY !== 'Darwin') {
            throw new RuntimeException('The iOS simulator requires macOS and Xcode.');
        }
        $listing = json_decode($this->run(['xcrun', 'simctl', 'list', 'devices', 'available', '--json']), true, flags: JSON_THROW_ON_ERROR);
        $phones = array_values(array_filter(array_merge(...array_values($listing['devices'])), fn (array $device): bool => str_starts_with($device['name'], 'iPhone')));
        $id = $requested ?: config('demo.simulator');
        $device = $this->select($phones, $id);
        if ($device['state'] !== 'Booted') {
            $this->run(['xcrun', 'simctl', 'boot', $device['udid']]);
        }
        $this->run(['xcrun', 'simctl', 'bootstatus', $device['udid'], '-b']);

        return $device['udid'];
    }

    private function select(array $phones, ?string $id): array
    {
        if ($id !== null) {
            foreach ($phones as $phone) {
                if ($phone['udid'] === $id) {
                    return $phone;
                }
            }
            throw new RuntimeException('The requested iPhone simulator is unavailable.');
        }
        foreach ($phones as $phone) {
            if ($phone['state'] === 'Booted') {
                return $phone;
            }
        }

        return $phones[0] ?? throw new RuntimeException('Install an iOS simulator runtime in Xcode first.');
    }

    public function run(array $arguments): string
    {
        $process = new Process($arguments, base_path(), timeout: 180);
        $process->mustRun();

        return $process->getOutput();
    }
}
