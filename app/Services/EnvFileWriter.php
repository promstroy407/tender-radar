<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Contracts\Foundation\Application;
use RuntimeException;

/**
 * Записывает секреты в .env на сервере — для команд, которые создают их сами,
 * чтобы значение не набирали руками и не пересылали в чат.
 */
class EnvFileWriter
{
    public function __construct(
        private readonly Application $app,
    ) {}

    public function path(): string
    {
        return $this->app->environmentFilePath();
    }

    /** Задана ли переменная непустым значением. */
    public function has(string $key): bool
    {
        return preg_match($this->pattern($key), $this->read(), $match) === 1 && trim($match[1]) !== '';
    }

    /** Заменяет строку KEY=… или дописывает её в конец файла. */
    public function put(string $key, string $value): void
    {
        $contents = $this->read();
        $line = $key.'='.$value;

        $contents = preg_match($this->pattern($key), $contents) === 1
            ? (string) preg_replace($this->pattern($key), $line, $contents, 1)
            : rtrim($contents, "\n")."\n".$line."\n";

        if (! is_writable($this->path()) || file_put_contents($this->path(), $contents) === false) {
            throw new RuntimeException('Файл .env недоступен для записи: '.$this->path());
        }
    }

    /** Новый случайный секрет: 64 шестнадцатеричных символа (256 бит). */
    public function randomSecret(): string
    {
        return bin2hex(random_bytes(32));
    }

    private function read(): string
    {
        $path = $this->path();

        if (! is_file($path)) {
            throw new RuntimeException('Файл .env не найден: '.$path);
        }

        return (string) file_get_contents($path);
    }

    private function pattern(string $key): string
    {
        return '/^'.preg_quote($key, '/').'=(.*)$/m';
    }
}
