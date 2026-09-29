<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Facades\Storage;

class CmsSetting extends Model
{
    use HasFactory;

    /**
     * Known media keys whose `value` holds a storage-relative file path
     * (public disk). The public API resolves them to absolute URLs.
     */
    public const MEDIA_KEYS = ['brand_logo', 'brand_favicon', 'hero_image'];

    protected $fillable = ['key', 'value', 'is_active', 'updated_by'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function attachments(): MorphMany
    {
        return $this->morphMany(Attachment::class, 'attachable');
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function isMedia(): bool
    {
        return in_array($this->key, self::MEDIA_KEYS, true);
    }

    /**
     * Absolute public URL for media keys; plain text otherwise. Returns null
     * when the referenced file does not exist on the public disk.
     */
    public function publicValue(): ?string
    {
        $value = $this->value;
        if ($value === null || $value === '') {
            return null;
        }

        if (! $this->isMedia()) {
            return $value;
        }

        return Storage::disk('public')->exists($value)
            ? Storage::disk('public')->url($value)
            : null;
    }
}
