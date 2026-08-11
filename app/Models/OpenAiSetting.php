<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OpenAiSetting extends Model
{
    protected $table = 'openai_settings';

    protected $fillable = ['api_key', 'model', 'is_active', 'updated_by'];

    protected function casts(): array
    {
        return [
            'api_key' => 'encrypted',
            'is_active' => 'boolean',
        ];
    }

    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function hasApiKey(): bool
    {
        return filled($this->api_key);
    }

    public static function current(): self
    {
        return self::firstOrCreate(
            ['id' => 1],
            ['model' => 'gpt-5.6-sol', 'is_active' => true]
        );
    }
}
