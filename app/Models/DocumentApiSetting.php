<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Contracts\Encryption\DecryptException;

class DocumentApiSetting extends Model
{
    protected $fillable = ['url', 'token', 'is_active', 'updated_by'];

    protected function casts(): array
    {
        return ['token' => 'encrypted', 'is_active' => 'boolean'];
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function hasToken(): bool
    {
        return filled($this->tokenValue());
    }

    public function tokenValue(): ?string
    {
        try {
            return $this->token;
        } catch (DecryptException) {
            return null;
        }
    }

    public function hasUnreadableToken(): bool
    {
        if (blank($this->getRawOriginal('token'))) return false;

        try {
            $this->getAttribute('token');
            return false;
        } catch (DecryptException) {
            return true;
        }
    }

    public static function current(): self
    {
        return self::firstOrCreate(['id' => 1], [
            'url' => 'https://api.ejemplo.com/consulta/{document}',
            'is_active' => true,
        ]);
    }
}
