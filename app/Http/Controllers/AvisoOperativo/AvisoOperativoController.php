<?php

namespace App\Http\Controllers\AvisoOperativo;

use App\DTOs\AuthenticatedUserData;
use App\Http\Controllers\Controller;
use App\Http\Requests\AvisoOperativo\StoreAvisoOperativoRequest;
use App\Http\Requests\AvisoOperativo\UpdateEstadoAvisoOperativoRequest;
use App\Http\Resources\AvisoOperativoResource;
use App\Services\AvisoOperativo\AvisoOperativoService;
use Illuminate\Http\Request;

class AvisoOperativoController extends Controller
{
    public function __construct(
        private readonly AvisoOperativoService $avisoOperativoService,
    ) {
    }

    public function index(Request $request)
    {
        $advisor = AuthenticatedUserData::fromSession($request->session()->get('authenticated_advisor', []));
        $limit = $request->integer('limit');

        return $this->successResponse(
            AvisoOperativoResource::collection($this->avisoOperativoService->listarPorUsuario($advisor, $limit)),
            'Avisos operativos obtenidos correctamente.'
        );
    }

    public function store(StoreAvisoOperativoRequest $request, Request $httpRequest)
    {
        $advisor = AuthenticatedUserData::fromSession($httpRequest->session()->get('authenticated_advisor', []));
        $aviso = $this->avisoOperativoService->registrar($request->validated(), $advisor);

        return $this->successResponse(
            new AvisoOperativoResource($aviso),
            'Aviso operativo registrado correctamente.',
            201
        );
    }

    public function updateEstado(int $id, UpdateEstadoAvisoOperativoRequest $request)
    {
        $aviso = $this->avisoOperativoService->actualizarEstado($id, $request->validated());

        return $this->successResponse(
            new AvisoOperativoResource($aviso),
            'Estado del aviso operativo actualizado correctamente.'
        );
    }
}
