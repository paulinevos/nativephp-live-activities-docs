<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Support\JavaRuntime;
use Illuminate\Filesystem\Filesystem;
use RuntimeException;
use Tests\TestCase;

final class JavaRuntimeTest extends TestCase
{
    public function test_it_skips_incompatible_android_studio_java_and_selects_a_supported_jdk(): void
    {
        $directory = sys_get_temp_dir().'/demo-jdks-'.bin2hex(random_bytes(8));
        $files = new Filesystem;
        try {
            foreach ([25, 21, 17] as $version) {
                $files->ensureDirectoryExists($directory.'/'.$version.'/bin');
                $files->put($directory.'/'.$version.'/release', 'JAVA_VERSION="'.$version.'.0.3"');
                $files->put($directory.'/'.$version.'/bin/java', '#!/bin/sh');
                chmod($directory.'/'.$version.'/bin/java', 0755);
            }
            $runtime = new JavaRuntime;
            $this->assertSame($directory.'/21', $runtime->select([$directory.'/25', $directory.'/21']));
            $this->assertSame($directory.'/17', $runtime->select([$directory.'/17', $directory.'/21']));
            $this->expectException(RuntimeException::class);
            $this->expectExceptionMessage('JDK 17 or 21');
            $runtime->select([$directory.'/25', $directory.'/missing']);
        } finally {
            $files->deleteDirectory($directory);
        }
    }
}
