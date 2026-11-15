<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Cache\RateLimiter;
use Illuminate\Contracts\Auth\Factory as AuthFactory;
use Illuminate\Contracts\Auth\StatefulGuard;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class LoginController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public function __construct(
        private readonly AuthFactory $auth,
        private readonly RateLimiter $limiter,
    ) {}

    public function show(): View
    {
        return view('auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $key = $request->throttleKey();

        if ($this->limiter->tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            throw ValidationException::withMessages([
                'email' => 'Слишком много попыток. Повторите через '.$this->limiter->availableIn($key).' с.',
            ]);
        }

        if (! $this->guard()->attempt($request->credentials(), $request->boolean('remember'))) {
            $this->limiter->hit($key, self::DECAY_SECONDS);

            throw ValidationException::withMessages([
                'email' => 'Неверная почта или пароль.',
            ]);
        }

        $this->limiter->clear($key);
        $request->session()->regenerate();

        return redirect()->intended(route('home'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->guard()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function guard(): StatefulGuard
    {
        $guard = $this->auth->guard('web');
        assert($guard instanceof StatefulGuard);

        return $guard;
    }
}
