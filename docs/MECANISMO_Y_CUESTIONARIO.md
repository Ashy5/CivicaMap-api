# Actividad 4.4 – Client API (Laravel + Sanctum)

## Descripción del mecanismo de autorización de datos propios

La API protege los datos del cliente en **dos capas**:

**1. Filtro de consultas (Eloquent Scope).** Las listas se filtran desde la base de datos, así que nunca se cargan registros ajenos:

```php
// app/Models/Solicitud.php
public function scopeDelUsuario($query, $idUsuario)
{
    return $query->where('id_usuario', $idUsuario);
}

// ClientSolicitudController@index
Solicitud::delUsuario(auth()->id())->with('detalles.servicio')->get();
```

**2. Policy y Gate (acciones sobre un registro individual).** Se compara el usuario del token de Sanctum con el propietario del registro:

```php
// app/Policies/SolicitudPolicy.php
private function esPropietario(Usuario $user, Solicitud $solicitud): bool
{
    // $user->id_usuario viene del token; $solicitud->id_usuario es el dueño del registro
    return (int) $user->id_usuario === (int) $solicitud->id_usuario;
}

public function delete(Usuario $user, Solicitud $solicitud): Response
{
    if (!$this->esPropietario($user, $solicitud)) {
        return Response::deny('No tienes permiso para cancelar este registro.');
    }
    return $solicitud->estado === Solicitud::ESTADO_PENDIENTE   // estado cancelable
        ? Response::allow()
        : Response::deny("El registro no se puede cancelar porque su estado es '{$solicitud->estado}'.");
}
```

Además se definió un Gate global en `AppServiceProvider`:

```php
Gate::define('manage-own-resource', fn (Usuario $user, $recurso) =>
    (int) $user->id_usuario === (int) $recurso->id_usuario
        ? Response::allow() : Response::deny('No tienes permiso para acceder a este registro.'));
```

Uso en el controlador (después de `find()`, que devuelve 404 si no existe):

- `show` → `Gate::authorize('manage-own-resource', $solicitud)`
- `update` → `Gate::authorize('update', $solicitud)` (SolicitudPolicy)
- `destroy` → `Gate::authorize('delete', $solicitud)` (SolicitudPolicy)

Si la comprobación falla se lanza `AuthorizationException` y `bootstrap/app.php` responde **403 JSON**.

**El propietario nunca viene del cliente:** al crear un registro se usa `'id_usuario' => auth()->id()`, y los FormRequest no incluyen `id_usuario` en sus reglas, por lo que cualquier valor enviado en el JSON se ignora.

> Nota: en este proyecto la llave primaria de usuarios es `id_usuario` (no `id`) y la del propietario en solicitudes es `id_usuario` (equivalente a `user_id` del enunciado).

## Cuestionario de evaluación técnica (respuestas sugeridas)

**1. ¿En qué consiste el principio de autorización de datos propios (Self-Data Ownership) y qué riesgos previene?**
Consiste en que un usuario autenticado solo puede consultar, modificar o eliminar los recursos que le pertenecen, comparando su identidad (obtenida del token) con el propietario del recurso. Autenticar solo dice *quién eres*; la propiedad de datos controla *qué puedes tocar*. Previene el acceso indebido a información de otros usuarios y ataques como IDOR (Insecure Direct Object Reference), donde un atacante cambia el id en la URL (`/mis-registros/5` → `/mis-registros/6`) para leer o alterar datos ajenos, así como la escalada horizontal de privilegios y la filtración de datos personales.

**2. ¿Por qué es mala práctica permitir que el cliente envíe el `user_id` en el JSON?**
Porque el cliente controla el contenido de la petición y puede modificarlo. Si el servidor confía en ese `user_id`, un usuario podría crear registros a nombre de otro o suplantar su identidad (mass assignment / falsificación de propietario). El token de Sanctum, en cambio, está firmado y asociado a un usuario en la base de datos, así que `auth()->id()` es una identidad verificada por el servidor. La regla es: nunca confiar en datos de identidad enviados por el cliente.

**3. ¿Cómo se evalúa la propiedad de un recurso dentro de una Policy con `$user->id === $recurso->user_id`?**
Laravel inyecta como primer parámetro al usuario autenticado y como segundo el modelo a autorizar. El método de la Policy (`view`, `update`, `delete`) compara el identificador del usuario con la columna que guarda al propietario del modelo y devuelve `true`/`Response::allow()` si coinciden o `false`/`Response::deny()` si no. Al llamar `Gate::authorize('update', $recurso)` (o `$this->authorize()`), Laravel localiza la Policy del modelo, ejecuta el método y, si no se autoriza, lanza una excepción que se convierte en una respuesta 403. En este proyecto la comparación es `$user->id_usuario === $solicitud->id_usuario`.

**4. ¿Qué diferencia hay entre `Recurso::where('user_id', auth()->id())` y `$this->authorize('update', $recurso)`?**
El `where` es un **filtro de datos** a nivel de consulta: solo recupera los registros del usuario, ideal para listados; lo ajeno simplemente no aparece (el registro "no existe" para ese usuario). `authorize()` es una **verificación de permiso** sobre un registro ya identificado y para una acción concreta (ver, actualizar, borrar), y puede incluir reglas adicionales (como el estado cancelable); si no se cumple, responde 403. Se complementan: el filtro evita cargar datos ajenos y la Policy protege cada acción individual.

**5. ¿Cómo ayuda `JsonResource` a prevenir la filtración accidental de datos sensibles?**
Un `JsonResource` define explícitamente (lista blanca) qué campos se exponen. Si se devolviera el modelo directamente, se serializarían todos sus atributos, incluyendo `password` u otros campos internos agregados en el futuro. Con el Resource solo salen los campos declarados en `toArray()` (por ejemplo `id`, `nombre`, `correo`), por lo que agregar una columna nueva a la tabla no la expone automáticamente. Además centraliza el formato de salida y permite ocultar identificadores internos como `id_usuario`.
