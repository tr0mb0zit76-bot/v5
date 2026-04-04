<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class KpiSetting extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
        'description',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'string',
        ];
    }

    public static function getValue(string $key, mixed $default = null): mixed
    {
        if (! Schema::hasTable('kpi_settings')) {
            return $default;
        }

        $setting = self::query()->where('key', $key)->first();

        if ($setting === null) {
            return $default;
        }

        return match ($setting->type) {
            'integer' => (int) $setting->value,
            'float' => (float) $setting->value,
            'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode((string) $setting->value, true),
            default => $setting->value,
        };
    }

    public static function setValue(
        string $key,
        mixed $value,
        string $type = 'string',
        string $group = 'general',
        ?string $description = null
    ): self {
        $serializedValue = match ($type) {
            'json' => json_encode($value, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'boolean' => $value ? '1' : '0',
            default => (string) $value,
        };

        /** @var self $setting */
        $setting = self::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => $serializedValue,
                'type' => $type,
                'group' => $group,
                'description' => $description,
            ],
        );

        return $setting;
    }
}
