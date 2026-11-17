<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Пускает на /api/leads только с заголовком «Authorization: Bearer <LEADS_API_TOKEN>».
 * Сравнение — hash_equals (без утечки по времени). Токен не задан на сервере — 401 всем.
 */
class AuthenticateLeadsToken
{
    public function __construct(
        private readonly Config $config,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $expected = $this->config->get('services.leads.token');
        $given = $request->bearerToken();

        if (! is_string($expected) || $expected === '' || ! is_string($given) || ! hash_equals($expected, $given)) {
            return new JsonResponse(['message' => 'Неверный или отсутствующий токен.'], Response::HTTP_UNAUTHORIZED);
        }

        /** @var Response */
        return $next($request);
    }
}
