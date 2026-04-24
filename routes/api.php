<?php

use App\Http\Controllers\Atencion\AtencionController;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Controllers\Auth\SsoController;
use App\Http\Controllers\Auth\SessionController;
use App\Http\Controllers\AvisoOperativo\AvisoOperativoController;
use App\Http\Controllers\Catalogo\EntidadController;
use App\Http\Controllers\Catalogo\TipoDocumentoController;
use App\Http\Controllers\Ciudadano\CiudadanoController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function (): void {
    Route::get('health', static function () {
        return response()->json([
            'ok' => true,
            'message' => 'API mexpres-atencion-backend operativa',
            'timestamp' => now()->toDateTimeString(),
        ]);
    });

    Route::prefix('auth')->group(function (): void {
        Route::prefix('sso')->group(function (): void {
            Route::post('callback', [SsoController::class, 'callback']);
        });
    });

    Route::post('session/logout', [SessionController::class, 'logout']);

    Route::middleware('advisor.session')->group(function (): void {
        Route::get('me', [SessionController::class, 'me']);

        Route::get('tipos-documento', [TipoDocumentoController::class, 'index']);

        Route::prefix('entidades')->group(function (): void {
            Route::get('visibles', [EntidadController::class, 'visibles']);
            Route::get('{id}/servicios', [EntidadController::class, 'servicios'])
                ->whereNumber('id');
        });

        Route::prefix('ciudadanos')->group(function (): void {
            Route::post('buscar', [CiudadanoController::class, 'buscar']);
            Route::post('consultar-pide', [CiudadanoController::class, 'consultarPide']);
            Route::post('manual', [CiudadanoController::class, 'manual']);
            Route::get('{id}', [CiudadanoController::class, 'show'])
                ->whereNumber('id');
        });

        Route::prefix('atenciones')->group(function (): void {
            Route::post('/', [AtencionController::class, 'store']);
            Route::post('{id}/iniciar', [AtencionController::class, 'iniciar'])
                ->whereNumber('id');
            Route::post('{id}/seleccionar-entidad', [AtencionController::class, 'seleccionarEntidad'])
                ->whereNumber('id');
            Route::post('{id}/seleccionar-servicio', [AtencionController::class, 'seleccionarServicio'])
                ->whereNumber('id');
            Route::post('{id}/retorno', [AtencionController::class, 'retorno'])
                ->whereNumber('id');
            Route::post('{id}/finalizar', [AtencionController::class, 'finalizar'])
                ->whereNumber('id');
        });

        Route::prefix('avisos-operativos')->group(function (): void {
            Route::get('/', [AvisoOperativoController::class, 'index']);
            Route::post('/', [AvisoOperativoController::class, 'store']);
            Route::patch('{id}/estado', [AvisoOperativoController::class, 'updateEstado'])
                ->whereNumber('id');
        });

        Route::prefix('admin')->middleware('admin.access')->group(function (): void {
            Route::get('entidades', [AdminController::class, 'entidades']);
            Route::post('entidades', [AdminController::class, 'storeEntidad']);
            Route::patch('entidades/{id}', [AdminController::class, 'updateEntidad'])->whereNumber('id');

            Route::get('servicios', [AdminController::class, 'servicios']);
            Route::post('servicios', [AdminController::class, 'storeServicio']);
            Route::patch('servicios/{id}', [AdminController::class, 'updateServicio'])->whereNumber('id');

            Route::get('regiones/{regionId}/entidades', [AdminController::class, 'regionEntidades'])->whereNumber('regionId');
            Route::put('regiones/{regionId}/entidades', [AdminController::class, 'updateRegionEntidades'])->whereNumber('regionId');

            Route::get('regiones/{regionId}/servicios', [AdminController::class, 'regionServicios'])->whereNumber('regionId');
            Route::put('regiones/{regionId}/servicios', [AdminController::class, 'updateRegionServicios'])->whereNumber('regionId');
        });
    });
});
