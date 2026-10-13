<?php

declare(strict_types=1);

namespace Vos\LiveActivities\Support;

use JsonException;
use Vos\LiveActivities\Exceptions\ActivityException;

final class Payload
{
    /** @param array<string, mixed> $payload
     * @param  list<string>  $fields
     * @param  list<string>  $optional
     */
    public static function fields(array $payload, array $fields, array $optional = []): void
    {
        if (array_diff($fields, array_keys($payload)) !== [] || array_diff(array_keys($payload), [...$fields, ...$optional]) !== []) {
            throw ActivityException::invalid('Payload fields do not match the activity contract.');
        }
    }

    public static function text(string $value, string $field, ?int $maximum = null, bool $allowEmpty = false): string
    {
        if (! mb_check_encoding($value, 'UTF-8')) {
            throw ActivityException::invalid("$field must be valid UTF-8.");
        }
        if (! $allowEmpty && preg_match('/^[\s\p{Z}]*$/u', $value) === 1) {
            throw ActivityException::invalid("$field must not be empty.");
        }
        if ($maximum !== null && mb_strlen($value, 'UTF-8') > $maximum) {
            throw ActivityException::invalid("$field exceeds $maximum Unicode characters.");
        }

        return $value;
    }

    /** @param array<string, mixed> $payload */
    public static function encode(array $payload): string
    {
        try {
            return json_encode((object) $payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } catch (JsonException) {
            throw ActivityException::invalid('Payload must contain valid JSON values.');
        }
    }

    /** @param array<string, mixed> $payload */
    public static function assertSize(array $payload): void
    {
        if (strlen(self::encode($payload)) > 3800) {
            throw ActivityException::tooLarge();
        }
    }
}
