<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Sanctum\HasApiTokens;

class Usuario extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'usuarios';

    protected $primaryKey = 'id_usuario';

    public $timestamps = false;

    protected $fillable = [
        'nombre',
        'correo',
        'password',
        'fecha_registro',
        'estado',
    ];

    // Nunca se devuelve la contraseña en las respuestas JSON
    protected $hidden = [
        'password',
    ];

    protected $casts = [
        'fecha_registro' => 'datetime',
        'estado' => 'boolean',
        'password' => 'hashed',
    ];

    public function esCliente(): bool
    {
        return $this->roles()->where('nombre', 'Cliente')->exists();
    }

    public function roles()
    {
        return $this->belongsToMany(
            Rol::class,
            'usuario_rol',
            'id_usuario',
            'id_rol'
        );
    }

    public function autenticaciones()
    {
        return $this->hasMany(
            Autenticacion::class,
            'id_usuario',
            'id_usuario'
        );
    }

    public function reportes()
    {
        return $this->hasMany(
            Reporte::class,
            'id_usuario',
            'id_usuario'
        );
    }

    public function listaDeseos()
    {
        return $this->hasMany(
            ListaDeseo::class,
            'id_usuario',
            'id_usuario'
        );
    }

    public function carritos()
    {
        return $this->hasMany(
            Carrito::class,
            'id_usuario',
            'id_usuario'
        );
    }

    public function solicitudes()
    {
        return $this->hasMany(
            Solicitud::class,
            'id_usuario',
            'id_usuario'
        );
    }

    public function logs()
    {
        return $this->hasMany(
            Log::class,
            'id_usuario',
            'id_usuario'
        );
    }
}