<?php

namespace App\Providers;

use App\Models\Solicitud;
use App\Models\Usuario;
use App\Policies\SolicitudPolicy;
use Illuminate\Auth\Access\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Policy de propiedad de datos para las solicitudes del cliente
        Gate::policy(Solicitud::class, SolicitudPolicy::class);

        // Gate global: sirve para cualquier modelo que tenga la columna id_usuario
        Gate::define('manage-own-resource', function (Usuario $user, $recurso): Response {
            return (int) $user->id_usuario === (int) $recurso->id_usuario
                ? Response::allow()
                : Response::deny('No tienes permiso para acceder a este registro.');
        });
    }
}
