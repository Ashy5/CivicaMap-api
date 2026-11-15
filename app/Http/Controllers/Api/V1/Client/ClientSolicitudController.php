<?php

namespace App\Http\Controllers\Api\V1\Client;

use App\Http\Controllers\Controller;
use App\Http\Requests\ClientResourceRequest;
use App\Http\Resources\SolicitudResource;
use App\Http\Traits\ApiResponse;
use App\Models\Servicio;
use App\Models\Solicitud;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Throwable;

/**
 * "Mis registros": solicitudes del cliente autenticado.
 *
 * Protección en dos capas:
 *  1) Scope delUsuario(): las listas se filtran desde la base de datos.
 *  2) Policy / Gate: cada acción sobre un registro individual compara el id del
 *     usuario del token con el propietario del registro (403 si no coincide).
 *
 * Importante: Gate::authorize() lanza AuthorizationException; por eso se ejecuta
 * fuera de los try/catch (el manejador global de bootstrap/app.php la convierte en 403 JSON).
 */
class ClientSolicitudController extends Controller
{
    use ApiResponse;

    private const RELACIONES = ['detalles.servicio'];

    // GET /api/v1/client/mis-registros
    public function index()
    {
        try {
            $solicitudes = Solicitud::delUsuario(auth()->id())
                ->with(self::RELACIONES)
                ->orderByDesc('fecha_solicitud')
                ->get();

            return $this->success(
                'Registros consultados correctamente.',
                SolicitudResource::collection($solicitudes)->resolve()
            );
        } catch (Throwable $e) {
            return $this->serverError($e, 'mis-registros.index');
        }
    }

    // POST /api/v1/client/mis-registros
    public function store(ClientResourceRequest $request)
    {
        try {
            $data = $request->validated();

            $solicitud = DB::transaction(function () use ($data) {
                $solicitud = Solicitud::create([
                    'id_usuario'      => auth()->id(), // siempre desde el token, nunca del body
                    'fecha_solicitud' => now(),
                    'estado'          => Solicitud::ESTADO_PENDIENTE,
                    'observaciones'   => $data['observaciones'] ?? null,
                ]);

                foreach ($data['servicios'] as $item) {
                    $servicio = Servicio::find($item['id_servicio']);

                    $solicitud->detalles()->create([
                        'id_servicio' => $servicio->id_servicio,
                        'cantidad'    => $item['cantidad'],
                        'precio'      => $servicio->costo, // el precio lo fija el servidor
                    ]);
                }

                return $solicitud;
            });

            return $this->success(
                'Registro creado correctamente.',
                (new SolicitudResource($solicitud->load(self::RELACIONES)))->resolve(),
                201
            );
        } catch (Throwable $e) {
            return $this->serverError($e, 'mis-registros.store');
        }
    }

    // GET /api/v1/client/mis-registros/{id}
    public function show($id)
    {
        if ($invalido = $this->idInvalido($id)) {
            return $invalido;
        }

        $solicitud = Solicitud::with(self::RELACIONES)->find($id);

        if (!$solicitud) {
            return $this->notFound('El registro solicitado no existe.');
        }

        // Gate global de propiedad de datos
        Gate::authorize('manage-own-resource', $solicitud);

        return $this->success(
            'Registro consultado correctamente.',
            (new SolicitudResource($solicitud))->resolve()
        );
    }

    // PUT / PATCH /api/v1/client/mis-registros/{id}
    public function update(ClientResourceRequest $request, $id)
    {
        if ($invalido = $this->idInvalido($id)) {
            return $invalido;
        }

        $solicitud = Solicitud::find($id);

        if (!$solicitud) {
            return $this->notFound('El registro solicitado no existe.');
        }

        // SolicitudPolicy@update: solo el propietario
        Gate::authorize('update', $solicitud);

        if ($solicitud->estado !== Solicitud::ESTADO_PENDIENTE) {
            return $this->error('Solo se pueden modificar registros pendientes.', 422);
        }

        try {
            $solicitud->update($request->validated());

            return $this->success(
                'Registro actualizado correctamente.',
                (new SolicitudResource($solicitud->fresh(self::RELACIONES)))->resolve()
            );
        } catch (Throwable $e) {
            return $this->serverError($e, 'mis-registros.update');
        }
    }

    // DELETE /api/v1/client/mis-registros/{id}  (cancela el registro)
    public function destroy($id)
    {
        if ($invalido = $this->idInvalido($id)) {
            return $invalido;
        }

        $solicitud = Solicitud::find($id);

        if (!$solicitud) {
            return $this->notFound('El registro solicitado no existe.');
        }

        // SolicitudPolicy@delete: propietario Y estado cancelable
        Gate::authorize('delete', $solicitud);

        try {
            $solicitud->update(['estado' => Solicitud::ESTADO_CANCELADA]);

            return $this->success(
                'Registro cancelado correctamente.',
                (new SolicitudResource($solicitud->fresh(self::RELACIONES)))->resolve()
            );
        } catch (Throwable $e) {
            return $this->serverError($e, 'mis-registros.destroy');
        }
    }
}
