<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SettingKey;
use App\Models\Setting;

/**
 * Чтение и запись настроек из интерфейса. Значения зашифрованы в базе.
 */
class SettingsService
{
    public function get(SettingKey $key): ?string
    {
        return Setting::query()->find($key->value)?->value;
    }

    public function has(SettingKey $key): bool
    {
        return Setting::query()->whereKey($key->value)->exists();
    }

    public function put(SettingKey $key, string $value): void
    {
        Setting::query()->updateOrCreate(['key' => $key->value], ['value' => $value]);
    }

    public function forget(SettingKey $key): void
    {
        Setting::query()->whereKey($key->value)->delete();
    }
}
