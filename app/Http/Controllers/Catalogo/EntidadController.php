<?php

namespace App\Http\Controllers\Catalogo;

use App\DTOs\AuthenticatedUserData;
use App\Http\Controllers\Controller;
use App\Http\Resources\CatalogItemResource;
use App\Services\Catalogo\CatalogoService;
use Illuminate\Http\Request;

class EntidadController extends Controller
{
    public function __construct(
        private readonly CatalogoService $catalogoService,
    ) {
    }

    public function visibles(Request $request)
    {
        $advisor = AuthenticatedUserData::fromSession($request->session()->get('authenticated_advisor', []));

        return $this->successResponse(
            CatalogItemResource::collection($this->catalogoService->listarEntidadesVisibles($advisor)),
            'Entidades visibles obtenidas correctamente.'
        );
    }

    public function servicios(int $id)
    {
        return $this->successResponse(
            CatalogItemResource::collection($this->catalogoService->listarServiciosPorEntidad($id)),
            'Servicios obtenidos correctamente.'
        );
    }
}
