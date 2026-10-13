<?php

declare(strict_types=1);

namespace Vos\LiveActivities\Tests\Build;

use Illuminate\Filesystem\Filesystem;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class IosExtensionTest extends TestCase
{
    private string $projectPath;

    protected function setUp(): void
    {
        if (PHP_OS_FAMILY !== 'Darwin') {
            $this->markTestSkipped('Xcode project integration requires macOS and Xcode.');
        }

        $this->projectPath = sys_get_temp_dir().'/live-activities-'.bin2hex(random_bytes(8));
        mkdir($this->projectPath.'/NativePHP.xcodeproj', 0755, true);
        mkdir($this->projectPath.'/NativePHP', 0755, true);
        $template = dirname(__DIR__, 2).'/vendor/nativephp/mobile/resources/xcode';
        copy($template.'/NativePHP.xcodeproj/project.pbxproj', $this->projectPath.'/NativePHP.xcodeproj/project.pbxproj');
        copy($template.'/NativePHP/Info.plist', $this->projectPath.'/NativePHP/Info.plist');
        copy($template.'/NativePHP-simulator-Info.plist', $this->projectPath.'/NativePHP-simulator-Info.plist');
    }

    protected function tearDown(): void
    {
        if (isset($this->projectPath)) {
            (new Filesystem)->deleteDirectory($this->projectPath);
        }
    }

    public function test_installation_is_idempotent_and_embeds_extensions_for_both_host_targets(): void
    {
        $this->install('com.example.activities');
        $before = file_get_contents($this->projectPath.'/NativePHP.xcodeproj/project.pbxproj');
        $this->install('com.example.activities');
        $this->assertSame($before, file_get_contents($this->projectPath.'/NativePHP.xcodeproj/project.pbxproj'));
        $project = $this->plist($this->projectPath.'/NativePHP.xcodeproj/project.pbxproj');
        $extensions = array_filter($project['objects'], fn (array $object): bool => ($object['productType'] ?? '') === 'com.apple.product-type.app-extension');
        $this->assertCount(2, $extensions);
        $hosts = array_filter($project['objects'], fn (array $object): bool => ($object['productType'] ?? '') === 'com.apple.product-type.application');
        foreach ($hosts as $host) {
            $this->assertHostEmbedsExtension($host, $project['objects']);
        }
        $this->assertTrue($this->plist($this->projectPath.'/NativePHP/Info.plist')['NSSupportsLiveActivities']);
        $this->assertTrue($this->plist($this->projectPath.'/NativePHP-simulator-Info.plist')['NSSupportsLiveActivities']);
        $this->assertFileEquals(dirname(__DIR__, 2).'/resources/ios/Sources/LiveActivityAttributes.swift', $this->projectPath.'/LiveActivitiesExtension/LiveActivityAttributes.swift');
        $this->assertFileEquals(dirname(__DIR__, 2).'/resources/ios/Sources/LiveActivityIcons.swift', $this->projectPath.'/LiveActivitiesExtension/LiveActivityIcons.swift');
        foreach ($extensions as $extension) {
            $phase = $project['objects'][$extension['buildPhases'][0]];
            $sourcePaths = array_map(fn (string $id): string => $project['objects'][$project['objects'][$id]['fileRef']]['path'], $phase['files']);
            $this->assertContains('LiveActivityIcons.swift', $sourcePaths);
        }
        $this->install('com.example.changed');
        $updated = file_get_contents($this->projectPath.'/NativePHP.xcodeproj/project.pbxproj');
        $this->assertStringContainsString('com.example.changed.liveactivities', $updated);
        $this->assertStringNotContainsString('com.example.activities.liveactivities', $updated);
    }

    public function test_invalid_bundle_id_fails_without_changing_the_project(): void
    {
        $before = file_get_contents($this->projectPath.'/NativePHP.xcodeproj/project.pbxproj');
        $process = $this->installer('invalid bundle identifier');
        $process->run();
        $this->assertFalse($process->isSuccessful());
        $this->assertSame($before, file_get_contents($this->projectPath.'/NativePHP.xcodeproj/project.pbxproj'));
    }

    /** @param array<string, mixed> $host @param array<string, array<string, mixed>> $objects */
    private function assertHostEmbedsExtension(array $host, array $objects): void
    {
        $phases = array_map(fn (string $id): array => $objects[$id], $host['buildPhases']);
        $embeds = array_filter($phases, fn (array $phase): bool => ($phase['name'] ?? '') === 'Embed Live Activities');
        $this->assertCount(1, $embeds);
        $phase = array_values($embeds)[0];
        $this->assertSame('13', $phase['dstSubfolderSpec']);
        $this->assertCount(1, $phase['files']);
        $dependencies = array_map(fn (string $id): array => $objects[$id], $host['dependencies']);
        $extensionDependencies = array_filter($dependencies, fn (array $dependency): bool => ($objects[$dependency['target']]['productType'] ?? '') === 'com.apple.product-type.app-extension');
        $this->assertCount(1, $extensionDependencies);
    }

    private function install(string $appId): void
    {
        $process = $this->installer($appId);
        $process->run();
        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput());
    }

    private function installer(string $appId): Process
    {
        $root = dirname(__DIR__, 2);

        return new Process(['xcrun', 'swift', $root.'/resources/templates/ios/install-extension.swift', $this->projectPath, $root, $appId], timeout: 120);
    }

    /** @return array<string, mixed> */
    private function plist(string $path): array
    {
        $process = new Process(['plutil', '-convert', 'json', '-o', '-', $path]);
        $process->mustRun();

        return json_decode($process->getOutput(), true, flags: JSON_THROW_ON_ERROR);
    }
}
