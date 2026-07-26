<?php

namespace App\Models;

use App\Support\Enums\AppConfigType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * @property int $id
 * @property string $key
 * @property string|null $value
 * @property AppConfigType $type
 * @property string|null $description
 */
class AppConfig extends Model
{
    use HasFactory, LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected $fillable = ['key', 'value', 'type', 'description'];

    protected function casts(): array
    {
        return [
            'type' => AppConfigType::class,
        ];
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        /** @var array{value: string|null, type: string}|null $config */
        $config = Cache::remember(
            "app_config:{$key}",
            now()->addHour(),
            function () use ($key): ?array {
                $record = static::query()->where('key', $key)->first(['value', 'type']);

                if ($record === null) {
                    return null;
                }

                return [
                    'value' => $record->value,
                    'type' => $record->type->value,
                ];
            }
        );

        if ($config === null) {
            return $default;
        }

        return self::castStoredValue($config['value'], AppConfigType::from($config['type']));
    }

    public static function set(string $key, mixed $value): void
    {
        /** @var AppConfig $config */
        $config = static::query()->firstOrNew(['key' => $key]);
        $config->value = is_array($value) ? json_encode($value) : (string) $value;
        $config->save();

        Cache::forget("app_config:{$key}");
    }

    public static function allPublic(): array
    {
        return Cache::remember('app_config:all', now()->addHour(), function (): array {
            return static::query()->get()
                ->mapWithKeys(fn (AppConfig $c): array => [$c->key => $c->castValue()])
                ->toArray();
        });
    }

    public static function bustCache(?string $key = null): void
    {
        if ($key !== null) {
            Cache::forget("app_config:{$key}");
        }
        Cache::forget('app_config:all');
    }

    private function castValue(): mixed
    {
        return self::castStoredValue($this->value, $this->type);
    }

    private static function castStoredValue(?string $value, AppConfigType $type): mixed
    {
        return match ($type) {
            AppConfigType::Boolean => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            AppConfigType::Integer => (int) $value,
            AppConfigType::Json => json_decode((string) $value, true),
            default => $value,
        };
    }
}
