<?php

namespace App\Http\Controllers\Atencion;

use App\DTOs\AuthenticatedUserData;
use App\Http\Controllers\Controller;
use App\Http\Requests\Atencion\CreateAtencionRequest;
use App\Http\Requests\Atencion\FinalizarAtencionRequest;
use App\Http\Requests\Atencion\IniciarAtencionRequest;
use App\Http\Requests\Atencion\RetornoAtencionRequest;
use App\Http\Requests\Atencion\SeleccionarEntidadRequest;
use App\Http\Requests\Atencion\SeleccionarServicioRequest;
use App\Http\Resources\AtencionResource;
use App\Services\Atencion\AtencionService;

class AtencionController extends Controller
{
    public function __construct(
        private readonly AtencionService $atencionService,
    ) {
    }

    public function store(CreateAtencionRequest $request)
    {
        $advisor = AuthenticatedUserData::fromSession($request->session()->get('authenticated_advisor', []));
        $atencion = $this->atencionService->crear($request->validated(), $advisor);

        return $this->successResponse(new AtencionResource($atencion), 'Atencion creada correctamente.', 201);
    }

    public function iniciar(int $id, IniciarAtencionRequest $request)
    {
        $atencion = $this->atencionService->iniciar($id, $request->validated());

        return $this->successResponse(new AtencionResource($atencion), 'Atencion iniciada correctamente.');
    }

    public function seleccionarEntidad(int $id, SeleccionarEntidadRequest $request)
    {
        $atencion = $this->atencionService->seleccionarEntidad($id, (int) $request->validated('id_entidad'));

        return $this->successResponse(new AtencionResource($atencion), 'Entidad seleccionada correctamente.');
    }

    public function seleccionarServicio(int $id, SeleccionarServicioRequest $request)
    {
        $atencion = $this->atencionService->seleccionarServicio($id, (int) $request->validated('id_servicio'));

        return $this->successResponse(new AtencionResource($atencion), 'Servicio seleccionado correctamente.');
    }

    public function retorno(int $id, RetornoAtencionRequest $request)
    {
        $atencion = $this->atencionService->registrarRetorno($id, $request->validated());

        return $this->successResponse(new AtencionResource($atencion), 'Retorno registrado correctamente.');
    }

    public function finalizar(int $id, FinalizarAtencionRequest $request)
    {
        $atencion = $this->atencionService->finalizar($id, $request->validated());

        return $this->successResponse(new AtencionResource($atencion), 'Atencion finalizada correctamente.');
    }
}
