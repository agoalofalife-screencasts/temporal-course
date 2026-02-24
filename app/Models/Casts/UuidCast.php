<?php

declare(strict_types=1);

namespace App\Models\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * @implements CastsAttributes<UuidInterface, UuidInterface|string>
 */
final class UuidCast implements CastsAttributes
{
    /** @param string|null $value */
    public function get($model, string $key, $value, array $attributes): ?UuidInterface
    {
        if ($value === null) {
            return null;
        }

        return Uuid::fromString($value);
    }

    /** @param UuidInterface|string|null $value */
    public function set($model, string $key, $value, array $attributes): string
    {
        if ($value instanceof UuidInterface) {
            return $value->toString();
        }

        if (is_string($value) && Uuid::isValid($value)) {
            return $value;
        }
        throw new \InvalidArgumentException('Value must be a string or Uuid object.');
    }
}
