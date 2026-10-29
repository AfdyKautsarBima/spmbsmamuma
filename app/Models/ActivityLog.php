<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Arr;
use LogicException;

/**
 * Log aktivitas: hanya bisa ditambah. Tidak boleh diubah atau dihapus.
 */
class ActivityLog extends Model
{
    const UPDATED_AT = null;

    protected $guarded = [];

    protected function casts(): array
    {
        return ['old_values' => 'array', 'new_values' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Log aktivitas tidak boleh diubah.'));
        static::deleting(fn () => throw new LogicException('Log aktivitas tidak boleh dihapus.'));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public static function record(
        string $event,
        string $description,
        ?Model $subject = null,
        ?array $old = null,
        ?array $new = null,
        ?string $reason = null,
    ): self {
        return static::create([
            'user_id'      => auth()->id(),
            'event'        => $event,
            'description'  => $description,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id'   => $subject?->getKey(),
            'old_values'   => $old ?: null,
            'new_values'   => $new ?: null,
            'reason'       => $reason,
            'ip_address'   => request()->ip(),
        ]);
    }

    /**
     * Ambil nilai lama dan baru dari atribut yang berubah.
     * Panggil SETELAH fill() dan SEBELUM save().
     *
     * @return array{0: array, 1: array}
     */
    public static function changes(Model $model, array $except = ['password', 'remember_token', 'updated_at']): array
    {
        $new = Arr::except($model->getDirty(), $except);
        $old = Arr::only($model->getRawOriginal(), array_keys($new));

        return [$old, $new];
    }
}
