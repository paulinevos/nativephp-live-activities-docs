<?php

declare(strict_types=1);

namespace Vos\LiveActivities\Bridge;

use JsonException;
use Vos\LiveActivities\Exceptions\ActivityException;
use Vos\LiveActivities\Support\Payload;

final readonly class NativeBridge
{
    public function __construct(private Transport $transport) {}

    public function available(): bool
    {
        return $this->transport->available();
    }

    /** @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function call(string $operation, array $payload = []): array
    {
        if (! $this->available()) {
            throw ActivityException::unavailable();
        }
        $response = $this->transport->call('LiveActivities.'.$operation, Payload::encode($payload));
        if ($response === null) {
            throw ActivityException::unavailable();
        }

        return $this->decode($response);
    }

    /** @return array<string, mixed> */
    private function decode(string $response): array
    {
        try {
            $payload = json_decode($response, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw ActivityException::malformedResponse();
        }
        if (! is_array($payload) || array_is_list($payload)) {
            throw ActivityException::malformedResponse();
        }
        if (($payload['status'] ?? null) === 'error') {
            throw $this->failure($payload);
        }

        // NativePHP 4.5 returns success data directly; only errors are enveloped.
        return $payload;
    }

    /** @param array<string, mixed> $payload */
    private function failure(array $payload): ActivityException
    {
        if (! is_string($payload['code'] ?? null) || ! is_string($payload['message'] ?? null)) {
            return ActivityException::malformedResponse();
        }

        return ActivityException::native($payload['code'], $payload['message']);
    }
}
