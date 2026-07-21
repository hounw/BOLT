<?php

namespace App\Models;

use App\Traits\RecordsAudit;
use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    use RecordsAudit;

    public const WEBHOOK_DELIVERY_HISTORY_LIMIT = 'webhook_delivery_history_limit';

    public const QUEUE_WORKER_COUNT = 'queue_worker_count';

    public const MAIN_CURRENCY = 'main_currency';

    public const ASSET_TAGS = 'asset_tags';

    public const KNOWLEDGE_CATEGORIES = 'knowledge_categories';

    public const KNOWLEDGE_TAGS = 'knowledge_tags';

    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    public static function integer(string $key, int $default): int
    {
        $setting = self::query()->where('key', $key)->first();

        return (int) ($setting?->value['value'] ?? $default);
    }

    public static function putInteger(string $key, int $value): self
    {
        return self::query()->updateOrCreate(
            ['key' => $key],
            ['value' => ['value' => $value]],
        );
    }

    public static function string(string $key, string $default): string
    {
        $setting = self::query()->where('key', $key)->first();

        return (string) ($setting?->value['value'] ?? $default);
    }

    public static function putString(string $key, string $value): self
    {
        return self::query()->updateOrCreate(
            ['key' => $key],
            ['value' => ['value' => $value]],
        );
    }

    public static function mainCurrency(): string
    {
        return strtoupper(self::string(self::MAIN_CURRENCY, 'USD'));
    }

    public static function mainCurrencySymbol(): string
    {
        return match (self::mainCurrency()) {
            'USD', 'CAD', 'AUD', 'NZD', 'SGD', 'HKD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'JPY', 'CNY' => '¥',
            'GTQ' => 'Q',
            'MXN' => '$',
            default => self::mainCurrency(),
        };
    }

    public static function array(string $key, array $default = []): array
    {
        $setting = self::query()->where('key', $key)->first();
        $value = $setting?->value['value'] ?? $default;

        return is_array($value) ? $value : $default;
    }

    public static function putArray(string $key, array $value): self
    {
        return self::query()->updateOrCreate(
            ['key' => $key],
            ['value' => ['value' => array_values($value)]],
        );
    }
}
