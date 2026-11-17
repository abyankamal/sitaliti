<?php

namespace App\Models\Concerns;

use Illuminate\Database\Eloquent\ModelNotFoundException;

trait ResolvesByIdOrNumber
{
    abstract protected static function numberColumn(): string;

    /**
     * Mencari model berdasarkan ID numerik (diprioritaskan) atau kolom nomor unik
     * secara aman di PostgreSQL maupun SQLite tanpa risiko SQLSTATE[22P02].
     *
     * @param  array<int, string>  $with
     */
    public static function resolveByIdOrNumber(string|int $identifier, array $with = [], bool $lockForUpdate = false): ?static
    {
        $key = (string) $identifier;

        if (preg_match('/^[1-9][0-9]*$/', $key) === 1 && filter_var($key, FILTER_VALIDATE_INT) !== false) {
            $byIdQuery = static::query()->with($with)->where('id', (int) $key);
            if ($lockForUpdate) {
                $byIdQuery->lockForUpdate();
            }

            $byId = $byIdQuery->first();
            if ($byId) {
                return $byId;
            }
        }

        $byNumberQuery = static::query()->with($with)->where(static::numberColumn(), $key);
        if ($lockForUpdate) {
            $byNumberQuery->lockForUpdate();
        }

        return $byNumberQuery->first();
    }

    /**
     * @param  array<int, string>  $with
     */
    public static function resolveOrFailByIdOrNumber(string|int $identifier, array $with = [], bool $lockForUpdate = false): static
    {
        $record = static::resolveByIdOrNumber($identifier, $with, $lockForUpdate);
        if (! $record) {
            throw (new ModelNotFoundException)->setModel(static::class, [$identifier]);
        }

        return $record;
    }
}
