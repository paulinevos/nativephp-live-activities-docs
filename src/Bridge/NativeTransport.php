<?php

declare(strict_types=1);

namespace Vos\LiveActivities\Bridge;

use ReflectionFunction;

final class NativeTransport implements Transport
{
    public function available(): bool
    {
        if (! function_exists('nativephp_call')) {
            return false;
        }

        // NativePHP also registers a PHP Jump shim on ordinary web/CLI boots.
        $function = new ReflectionFunction('nativephp_call');

        return $function->isInternal() || getenv('JUMP_BRIDGE_PORT') !== false;
    }

    public function call(string $method, string $payload): ?string
    {
        if (function_exists('nativephp_can') && ! nativephp_can($method)) {
            return null;
        }

        return nativephp_call($method, $payload);
    }
}
