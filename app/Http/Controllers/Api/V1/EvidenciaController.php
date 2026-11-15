<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\Evidencia;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Throwable;

class EvidenciaController extends Controller
{
    use ApiResponse;

    // Regla de imagen: formato permitido y tamaño máximo de 2 MB (2048 KB)
    private const REGLA_IMAGEN = 'file|image|mimes:jpg,jpeg,png,webp|max:2048';

    // GET /api/v1/evidencias
    public function index()
    {
        try {
            return $this->success(
                'Evidencias consultadas correctamente.',
                Evidencia::with('reporte')->get()
            );
        } catch (Throwable $e) {
            return $this->serverError($e, 'evidencias.index');
        }
    }

    // GET /api/v1/evidencias/{id}
    public function show($id)
    {
        if ($invalido = $this->idInvalido($id)) {
            return $invalido;
        }

        try {
            $evidencia = Evidencia::with('reporte')->find($id);

            if (!$evidencia) {
                return $this->notFound('La evidencia solicitada no existe.');
            }

            return $this->success('Evidencia consultada correctamente.', $evidencia);
        } catch (Throwable $e) {
            return $this->serverError($e, 'evidencias.show');
        }
    }

    // POST /api/v1/evidencias  (multipart/form-data: id_reporte, archivo)
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'id_reporte' => 'required|integer|exists:reportes,id_reporte',
            'archivo'    => 'required|' . self::REGLA_IMAGEN,
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        $ruta = null;

        try {
            $archivo = $request->file('archivo');
            $ruta = $archivo->store('evidencias', 'public'); // nombre aleatorio seguro

            $evidencia = Evidencia::create([
                'id_reporte'   => $request->input('id_reporte'),
                'tipo_archivo' => $archivo->getMimeType(),
                'ruta_archivo' => $ruta,
                'fecha_subida' => now(),
            ]);

            return $this->success('Evidencia subida correctamente.', $evidencia->fresh(), 201);
        } catch (Throwable $e) {
            if ($ruta) {
                Storage::disk('public')->delete($ruta);
            }

            return $this->serverError($e, 'evidencias.store');
        }
    }

    // PUT / PATCH /api/v1/evidencias/{id}
    // Cambia el reporte asociado y, opcionalmente, la imagen.
    // Nota: PHP no procesa multipart/form-data en PUT/PATCH. Para enviar archivo usar
    // POST /evidencias/{id}/imagen (o POST con el campo _method=PATCH).
    public function update(Request $request, $id)
    {
        if ($invalido = $this->idInvalido($id)) {
            return $invalido;
        }

        $evidencia = Evidencia::find($id);

        if (!$evidencia) {
            return $this->notFound('La evidencia solicitada no existe.');
        }

        $validator = Validator::make($request->all(), [
            'id_reporte' => 'sometimes|required|integer|exists:reportes,id_reporte',
            'archivo'    => 'sometimes|required|' . self::REGLA_IMAGEN,
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        return $this->aplicarCambios($request, $evidencia, 'evidencias.update');
    }

    // POST /api/v1/evidencias/{id}/imagen  (multipart/form-data: archivo)
    public function reemplazarImagen(Request $request, $id)
    {
        if ($invalido = $this->idInvalido($id)) {
            return $invalido;
        }

        $evidencia = Evidencia::find($id);

        if (!$evidencia) {
            return $this->notFound('La evidencia solicitada no existe.');
        }

        $validator = Validator::make($request->all(), [
            'archivo' => 'required|' . self::REGLA_IMAGEN,
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        return $this->aplicarCambios($request, $evidencia, 'evidencias.imagen');
    }

    // DELETE /api/v1/evidencias/{id}
    public function destroy($id)
    {
        if ($invalido = $this->idInvalido($id)) {
            return $invalido;
        }

        $evidencia = Evidencia::find($id);

        if (!$evidencia) {
            return $this->notFound('La evidencia solicitada no existe.');
        }

        try {
            $ruta = $evidencia->ruta_archivo;

            $evidencia->delete();
            Storage::disk('public')->delete($ruta);

            return $this->success('Evidencia eliminada correctamente.');
        } catch (Throwable $e) {
            return $this->serverError($e, 'evidencias.destroy');
        }
    }

    /**
     * Guarda la imagen nueva (si llegó), actualiza el registro y borra la imagen anterior
     * solo cuando la base de datos ya quedó actualizada.
     */
    private function aplicarCambios(Request $request, Evidencia $evidencia, string $contexto)
    {
        $nuevaRuta = null;

        try {
            $rutaAnterior = $evidencia->ruta_archivo;

            if ($request->hasFile('archivo')) {
                $archivo = $request->file('archivo');
                $nuevaRuta = $archivo->store('evidencias', 'public');

                $evidencia->ruta_archivo = $nuevaRuta;
                $evidencia->tipo_archivo = $archivo->getMimeType();
                $evidencia->fecha_subida = now();
            }

            if ($request->has('id_reporte')) {
                $evidencia->id_reporte = $request->input('id_reporte');
            }

            $evidencia->save();

            if ($nuevaRuta) {
                // Ninguna otra evidencia usa este archivo: cada carga genera un nombre único.
                Storage::disk('public')->delete($rutaAnterior);
            }

            return $this->success('Evidencia actualizada correctamente.', $evidencia->fresh());
        } catch (Throwable $e) {
            if ($nuevaRuta) {
                Storage::disk('public')->delete($nuevaRuta);
            }

            return $this->serverError($e, $contexto);
        }
    }
}
