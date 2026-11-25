<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Throwable;

class PengaturanKepatuhan extends Model
{
    use HasFactory;

    protected $table = 'pengaturan_kepatuhan';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'value',
        'description',
        'updated_by',
    ];

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Membaca flag boolean pengaturan kepatuhan dengan fallback aman jika tabel/baris belum ada.
     */
    public static function isEnabled(string $key, bool $default = false): bool
    {
        try {
            if (! Schema::hasTable('pengaturan_kepatuhan')) {
                return $default;
            }

            $record = self::where('key', $key)->first();
            if (! $record) {
                return $default;
            }

            return filter_var($record->value, FILTER_VALIDATE_BOOLEAN);
        } catch (Throwable) {
            return $default;
        }
    }

    public static function setValue(string $key, bool|string $value, ?int $userId = null, ?string $description = null): self
    {
        $normalized = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;

        return self::updateOrCreate(
            ['key' => $key],
            array_filter([
                'value' => $normalized,
                'updated_by' => $userId,
                'description' => $description,
            ], fn ($v) => $v !== null)
        );
    }
}
