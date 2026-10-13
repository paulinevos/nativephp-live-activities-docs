<?php

declare(strict_types=1);

namespace Vos\LiveActivities\Bridge;

interface Transport
{
    public function available(): bool;

    public function call(string $method, string $payload): ?string;
}
