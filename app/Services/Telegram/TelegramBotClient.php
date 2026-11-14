<?php

declare(strict_types=1);

namespace App\Services\Telegram;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Throwable;

/**
 * Единственная точка обращения к Telegram Bot API.
 * Адрес: {TELEGRAM_API_URL}/bot{TOKEN}/{метод}. Токен и адрес пересыльщика
 * никогда не попадают в тексты ошибок и журналы.
 */
class TelegramBotClient
{
    private const TIMEOUT_SECONDS = 15;

    public function __construct(
        private readonly HttpFactory $http,
        private readonly TelegramConfig $config,
    ) {}

    /**
     * @param  array<string, mixed>  $params
     * @return mixed поле result из ответа Telegram
     */
    public function call(string $method, array $params = [], ?int $timeoutSeconds = null): mixed
    {
        if (preg_match('/^[A-Za-z]+$/', $method) !== 1) {
            throw new TelegramApiException('Недопустимое имя метода Telegram: '.$method);
        }

        $url = $this->methodUrl($method);

        try {
            $response = $this->http
                ->timeout($timeoutSeconds ?? self::TIMEOUT_SECONDS)
                ->acceptJson()
                ->asJson()
                ->post($url, $params);
        } catch (ConnectionException $e) {
            throw new TelegramApiException(
                'Telegram недоступен ('.$method.'): '.$this->mask($e->getMessage()),
            );
        } catch (Throwable $e) {
            throw new TelegramApiException(
                'Ошибка запроса к Telegram ('.$method.'): '.$this->mask($e->getMessage()),
            );
        }

        $body = $response->json();

        if (! is_array($body) || ($body['ok'] ?? false) !== true) {
            $description = is_array($body) && is_string($body['description'] ?? null)
                ? $body['description']
                : 'ответ не в формате Telegram';

            throw new TelegramApiException(sprintf(
                'Telegram ответил ошибкой на %s (HTTP %d): %s',
                $method,
                $response->status(),
                $this->mask($description),
            ));
        }

        return $body['result'] ?? null;
    }

    public function methodUrl(string $method): string
    {
        return $this->config->apiUrl.'/bot'.$this->config->requireToken().'/'.$method;
    }

    /**
     * Заменяет на *** токен и секретную часть адреса пересыльщика.
     */
    public function mask(string $text): string
    {
        $secrets = array_filter([
            $this->config->botToken,
            $this->config->apiUrl === TelegramConfig::DEFAULT_API_URL ? null : $this->config->apiUrl,
            $this->secretPath(),
        ], fn (?string $secret): bool => $secret !== null && $secret !== '' && $secret !== '/');

        // Сначала длинные значения, чтобы не оставлять «хвостов».
        usort($secrets, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        return str_replace($secrets, '***', $text);
    }

    private function secretPath(): ?string
    {
        $path = parse_url($this->config->apiUrl, PHP_URL_PATH);

        return is_string($path) && trim($path, '/') !== '' ? trim($path, '/') : null;
    }
}
