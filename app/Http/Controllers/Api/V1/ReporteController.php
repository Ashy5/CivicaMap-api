<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Reporte;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Throwable;

class ReporteController extends Controller
{
    use ApiResponse;

    // Relaciones que se devuelven con cada reporte (sin datos sensibles del usuario)
    private const RELACIONES = ['usuario:id_usuario,nombre', 'categoria', 'evidencias'];

    // GET /api/v1/reportes
    public function index()
    {
        try {
            $reportes = Reporte::with(self::RELACIONES)->get();

            return $this->success('Reportes consultados correctamente.', $reportes);
        } catch (Throwable $e) {
            return $this->serverError($e, 'reportes.index');
        }
    }

    // GET /api/v1/reportes/{id}
    public function show($id)
    {
        if ($invalido = $this->idInvalido($id)) {
            return $invalido;
        }

        try {
            $reporte = Reporte::with(self::RELACIONES)->find($id);

            if (!$reporte) {
                return $this->notFound('El reporte solicitado no existe.');
            }

            return $this->success('Reporte consultado correctamente.', $reporte);
        } catch (Throwable $e) {
            return $this->serverError($e, 'reportes.show');
        }
    }

    // POST /api/v1/reportes
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_usuario'     => 'required|integer|exists:usuarios,id_usuario',
            'id_categoria'   => 'required|integer|exists:categorias_reporte,id_categoria',
            'descripcion'    => 'required|string|min:5|max:2000',
            'latitud_aprox'  => 'nullable|numeric|between:-90,90',
            'longitud_aprox' => 'nullable|numeric|between:-180,180',
            'fecha_reporte'  => 'required|date',
            'estado'         => 'required|string|max:30',
            'anonimizado'    => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        try {
            $reporte = Reporte::create($validator->validated());

            return $this->success(
                'Reporte registrado correctamente.',
                $reporte->fresh(self::RELACIONES),
                201
            );
        } catch (Throwable $e) {
            return $this->serverError($e, 'reportes.store');
        }
    }

    // PUT (reemplazo) / PATCH (parcial) /api/v1/reportes/{id}
    public function update(Request $request, $id)
    {
        if ($invalido = $this->idInvalido($id)) {
            return $invalido;
        }

        $reporte = Reporte::find($id);

        if (!$reporte) {
            return $this->notFound('El reporte solicitado no existe.');
        }

        $req = $this->reglaPresencia($request);

        $validator = Validator::make($request->all(), [
            'id_usuario'     => "$req|integer|exists:usuarios,id_usuario",
            'id_categoria'   => "$req|integer|exists:categorias_reporte,id_categoria",
            'descripcion'    => "$req|string|min:5|max:2000",
            'latitud_aprox'  => 'sometimes|nullable|numeric|between:-90,90',
            'longitud_aprox' => 'sometimes|nullable|numeric|between:-180,180',
            'fecha_reporte'  => "$req|date",
            'estado'         => "$req|string|max:30",
            'anonimizado'    => "$req|boolean",
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        try {
            $reporte->update($validator->validated());

            return $this->success('Reporte actualizado correctamente.', $reporte->fresh(self::RELACIONES));
        } catch (Throwable $e) {
            return $this->serverError($e, 'reportes.update');
        }
    }

    // DELETE /api/v1/reportes/{id}
    public function destroy($id)
    {
        if ($invalido = $this->idInvalido($id)) {
            return $invalido;
        }

        $reporte = Reporte::find($id);

        if (!$reporte) {
            return $this->notFound('El reporte solicitado no existe.');
        }

        try {
            // La base de datos borra las evidencias en cascada, pero no sus archivos.
            $rutas = $reporte->evidencias()->pluck('ruta_archivo')->all();

            $reporte->delete();

            Storage::disk('public')->delete($rutas);

            return $this->success('Reporte eliminado correctamente.');
        } catch (Throwable $e) {
            return $this->serverError($e, 'reportes.destroy');
        }
    }
}
