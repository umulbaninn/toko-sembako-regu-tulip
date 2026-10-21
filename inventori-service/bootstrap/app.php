<?php

use App\Exceptions\StokTidakCukup;
use App\Support\Problem;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return Problem::make(422, 'Validasi gagal', 'Data yang dikirim tidak valid.', 'validasi-gagal', ['errors' => $e->errors()]);
            }
        });
        $exceptions->render(function (NotFoundHttpException $e, Request $request) {
            if ($request->is('api/*')) {
                return Problem::make(404, 'Sumber daya tidak ditemukan', 'Data yang diminta tidak ada.', 'tidak-ditemukan');
            }
        });
        $exceptions->render(function (StokTidakCukup $e, Request $request) {
            if ($request->is('api/*')) {
                return Problem::make(409, 'Stok tidak mencukupi', $e->getMessage(), 'stok-tidak-cukup', ['stok_tersedia' => $e->tersedia]);
            }
        });
    })
    ->create(); 