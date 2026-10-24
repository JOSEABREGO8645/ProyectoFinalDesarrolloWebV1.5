<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $rechazo = fn (Request $request, array $headers) => response()->json(['message' => 'Demasiados intentos. Espere un minuto y vuelva a intentar.'], 429, $headers);
        RateLimiter::for('auth-login', fn (Request $request) => [
            Limit::perMinute(5)->by('login:'.hash('sha256', mb_strtolower(is_string($request->input('usuario')) ? $request->input('usuario') : '').'|'.$request->ip()))->response($rechazo),
            Limit::perMinute(30)->by('login-ip:'.$request->ip())->response($rechazo),
        ]);
        RateLimiter::for('auth-refresh', fn (Request $request) => Limit::perMinute(60)->by($request->path().'|'.$request->ip())->response($rechazo));
        RateLimiter::for('codigo', fn (Request $request) => Limit::perMinute(5)->by('codigo:'.$request->user()?->getKey().'|'.$request->ip())->response($rechazo));
    }
}
