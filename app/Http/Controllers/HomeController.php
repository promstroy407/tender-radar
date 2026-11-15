<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

/**
 * Временная главная страница после входа. Экраны Mini App придут на её место.
 */
class HomeController extends Controller
{
    public function __invoke(): View
    {
        return view('home');
    }
}
