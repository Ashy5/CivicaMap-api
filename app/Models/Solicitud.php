<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Solicitud extends Model
{
    public const ESTADO_PENDIENTE = 'pendiente';
    public const ESTADO_CANCELADA = 'cancelada';

    protected $table = 'solicitudes';

    protected $primaryKey = 'id_solicitud';

    public $timestamps = false;

    protected $fillable = [
        'id_usuario',
        'fecha_solicitud',
        'estado',
        'observaciones',
    ];

    protected $casts = [
        'fecha_solicitud' => 'datetime',
    ];

    public function usuario()
    {
        return $this->belongsTo(
            Usuario::class,
            'id_usuario',
            'id_usuario'
        );
    }

    public function detalles()
    {
        return $this->hasMany(
            SolicitudDetalle::class,
            'id_solicitud',
            'id_solicitud'
        );
    }

    /**
     * Scope de propiedad: filtra desde la base de datos solo los registros del usuario.
     */
    public function scopeDelUsuario($query, $idUsuario)
    {
        return $query->where('id_usuario', $idUsuario);
    }
}
