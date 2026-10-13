<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Filesystem\Filesystem;
use Native\Mobile\Plugins\Compilers\AndroidPluginCompiler;
use Native\Mobile\Plugins\PluginRegistry;
use Tests\TestCase;

final class AndroidBuildTest extends TestCase
{
    public function test_android_generation_includes_the_demo_renderer_permissions_and_activity_presentation(): void
    {
        $files = new Filesystem;
        $directory = sys_get_temp_dir().'/live-activities-android-'.bin2hex(random_bytes(8));
        $files->copyDirectory(base_path('vendor/nativephp/mobile/resources/androidstudio'), $directory.'/android');
        try {
            $compiler = new AndroidPluginCompiler($files, app(PluginRegistry::class), $directory);
            $compiler->compile();
            $generated = $directory.'/android/app/src/nativephp/kotlin';
            $registration = $files->get($generated.'/com/nativephp/mobile/bridge/plugins/PluginBridgeFunctionRegistration.kt');
            $this->assertStringContainsString('DemoNotifications.Request(activity)', $registration);
            $this->assertStringContainsString('LiveActivitiesFunctions.Start(context)', $registration);
            $this->assertStringContainsString('DemoTextRenderer.Render', $files->get($generated.'/com/nativephp/mobile/bridge/plugins/PluginRendererRegistration.kt'));
            $this->assertFileExists($generated.'/com/vos/demoui/DemoTextRenderer.kt');
            $this->assertFileEquals(base_path('vendor/vos/nativephp-live-activities-free/resources/android/src/main/java/com/vos/liveactivities/ActivityPresentation.kt'), $generated.'/com/vos/liveactivities/ActivityPresentation.kt');
            $this->assertFileExists($generated.'/com/vos/liveactivities/ActivityIcons.kt');
            $manifest = $files->get($directory.'/android/app/src/main/AndroidManifest.xml');
            $this->assertStringContainsString('android.permission.POST_NOTIFICATIONS', $manifest);
            $this->assertStringContainsString('android.permission.POST_PROMOTED_NOTIFICATIONS', $manifest);
            $compiler->compile();
            $this->assertSame($registration, $files->get($generated.'/com/nativephp/mobile/bridge/plugins/PluginBridgeFunctionRegistration.kt'));
        } finally {
            $files->deleteDirectory($directory);
        }
    }
}
