<?php

declare(strict_types=1);

namespace App\Support;

use RuntimeException;

final class JavaRuntime
{
    /** @param list<string> $candidates */
    public function select(array $candidates): string
    {
        foreach ($candidates as $directory) {
            if ($this->supported($directory)) {
                return $directory;
            }
        }

        throw new RuntimeException('This NativePHP build needs JDK 17 or 21. Install openjdk@21 or set DEMO_JAVA_HOME to a compatible JDK. Android Studio may bundle an incompatible newer Java version.');
    }

    private function supported(string $directory): bool
    {
        if (! is_file($directory.'/release') || ! is_executable($directory.'/bin/java')) {
            return false;
        }
        preg_match('/^JAVA_VERSION="(\d+)/m', file_get_contents($directory.'/release'), $matches);

        return in_array((int) ($matches[1] ?? 0), [17, 21], true);
    }
}
