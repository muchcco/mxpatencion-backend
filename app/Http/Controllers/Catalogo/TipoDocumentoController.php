<?php

namespace App\Http\Controllers\Catalogo;

use App\Http\Controllers\Controller;
use App\Http\Resources\CatalogItemResource;
use App\Services\Catalogo\CatalogoService;

class TipoDocumentoController extends Controller
{
    public function __construct(
        private readonly CatalogoService $catalogoService,
    ) {
    }

    public function index()
    {
        return $this->successResponse(
            CatalogItemResource::collection($this->catalogoService->listarTiposDocumento()),
            'Tipos de documento obtenidos correctamente.'
        );
    }
}
