<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEntidadRequest;
use App\Http\Requests\Admin\StoreServicioRequest;
use App\Http\Requests\Admin\UpdateEntidadRequest;
use App\Http\Requests\Admin\UpdateRegionEntidadesRequest;
use App\Http\Requests\Admin\UpdateRegionServiciosRequest;
use App\Http\Requests\Admin\UpdateServicioRequest;
use App\Http\Resources\CatalogItemResource;
use App\Services\Admin\AdminService;

class AdminController extends Controller
{
    public function __construct(
        private readonly AdminService $adminService,
    ) {
    }

    public function entidades()
    {
        return $this->successResponse(
            CatalogItemResource::collection($this->adminService->listarEntidades()),
            'Entidades obtenidas correctamente.'
        );
    }

    public function storeEntidad(StoreEntidadRequest $request)
    {
        return $this->successResponse(
            new CatalogItemResource($this->adminService->crearEntidad($request->validated())),
            'Entidad creada correctamente.',
            201
        );
    }

    public function updateEntidad(int $id, UpdateEntidadRequest $request)
    {
        return $this->successResponse(
            new CatalogItemResource($this->adminService->actualizarEntidad($id, $request->validated())),
            'Entidad actualizada correctamente.'
        );
    }

    public function servicios()
    {
        return $this->successResponse(
            CatalogItemResource::collection($this->adminService->listarServicios()),
            'Servicios obtenidos correctamente.'
        );
    }

    public function storeServicio(StoreServicioRequest $request)
    {
        return $this->successResponse(
            new CatalogItemResource($this->adminService->crearServicio($request->validated())),
            'Servicio creado correctamente.',
            201
        );
    }

    public function updateServicio(int $id, UpdateServicioRequest $request)
    {
        return $this->successResponse(
            new CatalogItemResource($this->adminService->actualizarServicio($id, $request->validated())),
            'Servicio actualizado correctamente.'
        );
    }

    public function regionEntidades(int $regionId)
    {
        return $this->successResponse(
            CatalogItemResource::collection($this->adminService->listarEntidadesPorRegion($regionId)),
            'Cobertura de entidades obtenida correctamente.'
        );
    }

    public function updateRegionEntidades(int $regionId, UpdateRegionEntidadesRequest $request)
    {
        return $this->successResponse(
            CatalogItemResource::collection(
                $this->adminService->actualizarEntidadesPorRegion($regionId, $request->validated('entidad_ids'))
            ),
            'Cobertura regional de entidades actualizada correctamente.'
        );
    }

    public function regionServicios(int $regionId)
    {
        return $this->successResponse(
            CatalogItemResource::collection($this->adminService->listarServiciosPorRegion($regionId)),
            'Cobertura de servicios obtenida correctamente.'
        );
    }

    public function updateRegionServicios(int $regionId, UpdateRegionServiciosRequest $request)
    {
        return $this->successResponse(
            CatalogItemResource::collection(
                $this->adminService->actualizarServiciosPorRegion($regionId, $request->validated('servicio_ids'))
            ),
            'Cobertura regional de servicios actualizada correctamente.'
        );
    }
}
