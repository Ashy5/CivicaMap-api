<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Traits\ApiResponse;
use App\Models\CategoriaReporte;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Throwable;

class CategoriaReporteController extends Controller
{
    use ApiResponse;

    // GET /api/v1/categorias
    public function index()
    {
        try {
            return $this->success('Categorías consultadas correctamente.', CategoriaReporte::all());
        } catch (Throwable $e) {
            return $this->serverError($e, 'categorias.index');
        }
    }

    // GET /api/v1/categorias/{id}
    public function show($id)
    {
        if ($invalido = $this->idInvalido($id)) {
            return $invalido;
        }

        try {
            $categoria = CategoriaReporte::find($id);

            if (!$categoria) {
                return $this->notFound('La categoría solicitada no existe.');
            }

            return $this->success('Categoría consultada correctamente.', $categoria);
        } catch (Throwable $e) {
            return $this->serverError($e, 'categorias.show');
        }
    }

    // POST /api/v1/categorias
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nombre'      => 'required|string|max:80',
            'descripcion' => 'nullable|string|max:255',
            'estado'      => 'required|boolean',
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        try {
            $categoria = CategoriaReporte::create($validator->validated());

            return $this->success('Categoría registrada correctamente.', $categoria->fresh(), 201);
        } catch (Throwable $e) {
            return $this->serverError($e, 'categorias.store');
        }
    }

    // PUT (reemplazo) / PATCH (parcial) /api/v1/categorias/{id}
    public function update(Request $request, $id)
    {
        if ($invalido = $this->idInvalido($id)) {
            return $invalido;
        }

        $categoria = CategoriaReporte::find($id);

        if (!$categoria) {
            return $this->notFound('La categoría solicitada no existe.');
        }

        $req = $this->reglaPresencia($request);

        $validator = Validator::make($request->all(), [
            'nombre'      => "$req|string|max:80",
            'descripcion' => 'sometimes|nullable|string|max:255',
            'estado'      => "$req|boolean",
        ]);

        if ($validator->fails()) {
            return $this->validationError($validator);
        }

        try {
            $categoria->update($validator->validated());

            return $this->success('Categoría actualizada correctamente.', $categoria->fresh());
        } catch (Throwable $e) {
            return $this->serverError($e, 'categorias.update');
        }
    }

    // DELETE /api/v1/categorias/{id}
    public function destroy($id)
    {
        if ($invalido = $this->idInvalido($id)) {
            return $invalido;
        }

        $categoria = CategoriaReporte::find($id);

        if (!$categoria) {
            return $this->notFound('La categoría solicitada no existe.');
        }

        // La llave foránea es "restrict": no se puede borrar una categoría con reportes.
        if ($categoria->reportes()->exists()) {
            return $this->error(
                'No se puede eliminar la categoría porque tiene reportes asociados.',
                400
            );
        }

        try {
            $categoria->delete();

            return $this->success('Categoría eliminada correctamente.');
        } catch (Throwable $e) {
            return $this->serverError($e, 'categorias.destroy');
        }
    }
}
