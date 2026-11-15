<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\SettingKey;
use App\Models\Setting;
use Illuminate\Contracts\Config\Repository as Config;

/**
 * Чтение и запись настроек из интерфейса. Значения зашифрованы в базе.
 */
class SettingsService
{
    public function __construct(
        private readonly Config $config,
    ) {}

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

    /**
     * Ввод из формы с секретом: новое значение — сохранить, пусто — оставить,
     * флажок «удалить» — стереть.
     */
    public function applyInput(SettingKey $key, ?string $value, bool $clear): void
    {
        if ($value !== null) {
            $this->put($key, $value);
        } elseif ($clear) {
            $this->forget($key);
        }
    }

    /** Откуда берётся значение: «settings», «env» или null (не задано). */
    public function source(SettingKey $key, string $configKey): ?string
    {
        if ($this->has($key)) {
            return 'settings';
        }

        return filled($this->config->get($configKey)) ? 'env' : null;
    }
}
