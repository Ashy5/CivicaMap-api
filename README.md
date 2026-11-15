# CivicaMap – API REST (Laravel 12)

API RESTful de CivicaMap. Este proyecto es **solo backend**: no contiene vistas, rutas web ni assets de front-end.
El front-end vive en un proyecto separado y consume esta API mediante HTTP/JSON.

Recursos implementados: la API administrativa (categorias, reportes, evidencias) vive bajo `/api/v1/admin` y desde la **Actividad 5.2 está protegida con `auth:sanctum`**; el área del cliente (`/client`) también usa Sanctum.

| Recurso    | Descripción                                        |
|------------|----------------------------------------------------|
| categorias | Categorías de reportes                             |
| reportes   | Reportes ciudadanos (con usuario, categoría y evidencias) |
| evidencias | Imágenes asociadas a un reporte (carga y consulta) |

## Instalación

```bash
composer install
cp .env.example .env          # en Windows: copy .env.example .env
php artisan key:generate
# Crear la base de datos "civicamap" en MySQL y ajustar DB_* en .env
php artisan migrate
php artisan db:seed           # datos de ejemplo (usuario, categorías, un reporte)
php artisan storage:link      # IMPORTANTE: habilita las URLs públicas de las imágenes
php artisan serve             # http://localhost:8000
```

Para permitir que el frontend Vite consuma la API desde otro origen, configura
`FRONTEND_URL` en el `.env` del backend (por defecto permite
`http://localhost:5173` y `http://127.0.0.1:5173`). Puede ser una lista de
orígenes separados por comas. En producción, indica el origen público del
frontend. La autenticación usa tokens Bearer, por lo que CORS no requiere
cookies ni credenciales compartidas.

Verificación: `GET http://localhost:8000/api/v1/prueba`

## Configuración de imágenes

- Las imágenes se guardan en `storage/app/public/evidencias` (disco `public`) con un nombre aleatorio.
- En la base de datos se guarda la **ruta** (`ruta_archivo`, ej. `evidencias/abc123.jpg`).
- La API devuelve además la **URL pública** (`url`, ej. `http://localhost:8000/storage/evidencias/abc123.jpg`), que solo funciona si ejecutó `php artisan storage:link`.
- Formatos permitidos: jpg, jpeg, png, webp. Tamaño máximo: 2 MB.
- Verifique que `upload_max_filesize` y `post_max_size` de `php.ini` sean ≥ 2 MB. Si una solicitud excede `post_max_size`, la API responde 413 en JSON.
- La carga se hace con `multipart/form-data`.

## Área administrativa (Sanctum) – `/api/v1/admin`  *(Actividad 5.2)*

Todas las rutas, salvo el login, están dentro del grupo `auth:sanctum` + `abilities:admin` (ver `routes/api.php`).
Sin token válido responden **401**; con un token válido que no es de administrador (p. ej. uno de cliente) responden **403**.

| Método | Endpoint                         | Acceso | Descripción |
|--------|----------------------------------|--------|-------------|
| POST   | /admin/login                     | Público (throttle 10/min) | Credenciales `correo` + `password`; devuelve `token`, `token_type` y `usuario` (con `roles`). Solo cuentas activas con rol *Administrador* |
| POST   | /admin/logout                    | Token admin | Revoca (borra de `personal_access_tokens`) el token usado en la petición |
| GET    | /admin/perfil                    | Token admin | Perfil del administrador autenticado |
| GET/POST | /admin/categorias, /admin/reportes, /admin/evidencias | Token admin | Consultar colección / crear (evidencias: multipart con `archivo`) |
| GET/PUT/PATCH/DELETE | /admin/{recurso}/{id} | Token admin | Consultar / reemplazar / actualizar parcialmente / eliminar |
| POST   | /admin/evidencias/{id}/imagen    | Token admin | Reemplazar la imagen (multipart, campo `archivo`) |

Encabezado requerido en las rutas protegidas: `Authorization: Bearer <token>` (más `Accept: application/json`).
Administrador de prueba del seeder: `admin@civicamap.test` / `password123`.

> **PUT/PATCH con archivos:** PHP no procesa `multipart/form-data` en PUT/PATCH. Para cambiar la imagen use
> `POST /admin/evidencias/{id}/imagen`.

### Rutas históricas (sin prefijo `admin`) que se conservan para el sitio público

| Método | Endpoint                        | Acceso  | Motivo |
|--------|---------------------------------|---------|--------|
| GET    | /categorias, /reportes, /{recurso}/{id} | Público | Mapa/listado ciudadano (**solo lectura**) |
| POST   | /reportes, /evidencias          | Token (cualquier usuario autenticado) | Un ciudadano registra su reporte y evidencia |

Cualquier otra operación de escritura en esas rutas (PUT/PATCH/DELETE) ya no existe: responde 405.

### Ejemplos de campos

- **categorias:** `nombre` (obligatorio, máx. 80), `descripcion` (opcional, máx. 255), `estado` (boolean).
- **reportes:** `id_usuario`, `id_categoria`, `descripcion`, `latitud_aprox` y `longitud_aprox` (opcionales), `fecha_reporte`, `estado`, `anonimizado`.
- **evidencias:** `id_reporte`, `archivo` (imagen).

### Pruebas rápidas con curl

```bash
# 1) Sin token -> 401
curl -i -H "Accept: application/json" http://localhost:8000/api/v1/admin/reportes

# 2) Login -> token
curl -s -X POST http://localhost:8000/api/v1/admin/login -H "Accept: application/json" \
     -H "Content-Type: application/json" -d '{"correo":"admin@civicamap.test","password":"password123"}'

# 3) Con token -> 200
curl -s -H "Accept: application/json" -H "Authorization: Bearer <TOKEN>" http://localhost:8000/api/v1/admin/reportes

# 4) Logout (revoca el token) y reintento -> 401
curl -s -X POST -H "Accept: application/json" -H "Authorization: Bearer <TOKEN>" http://localhost:8000/api/v1/admin/logout
```

## Área del cliente (Sanctum) – `/api/v1/client`

Autenticación por Bearer token (Laravel Sanctum). Cada cliente solo accede a sus propios datos (Policy/Gate + Eloquent Scope).
Catálogo = `servicios`; "mis-registros" = `solicitudes` del cliente.

| Método    | Endpoint                          | Acceso   | Descripción |
|-----------|-----------------------------------|----------|-------------|
| POST      | /client/register                  | Público  | Registra un cliente (rol Cliente automático) |
| POST      | /client/login                     | Público  | Devuelve el token Bearer |
| POST      | /client/logout                    | Token    | Revoca el token actual |
| GET       | /client/catalogo, /catalogo/{id}  | Público  | Catálogo de servicios |
| GET/PUT/PATCH | /client/perfil                | Token    | Perfil del cliente autenticado |
| GET/POST  | /client/mis-registros             | Token    | Listar propios / crear (el dueño sale del token) |
| GET/PUT/PATCH/DELETE | /client/mis-registros/{id} | Token | Ver / modificar / cancelar solo si es propio (403 si no) |

Ejemplo de creación: `POST /client/mis-registros` con `{"observaciones":"...","servicios":[{"id_servicio":1,"cantidad":2}]}`.
Clientes de prueba del seeder: `clienteA@civicamap.test` y `clienteB@civicamap.test` (contraseña `password123`).
La colección de Postman está en `postman/CivicaMap_ClientAPI.postman_collection.json`.
Descripción del mecanismo y cuestionario: `docs/MECANISMO_Y_CUESTIONARIO.md`.

## Formato de respuesta

Todas las respuestas (éxito y error) usan la misma estructura:

```json
{ "success": true, "message": "…", "data": {}, "errors": null }
```

| Código | Uso |
|--------|-----|
| 200 / 201 | Éxito / creado |
| 400 | Identificador inválido o solicitud incorrecta |
| 401 | Sin token, token inválido, revocado o expirado, o credenciales incorrectas |
| 403 | Registro de otro usuario (Policy/Gate), cuenta sin rol/desactivada o token sin la habilidad requerida (`admin`) |
| 404 | Registro o ruta inexistente |
| 405 | Método HTTP no permitido |
| 413 | Archivo o solicitud demasiado grande |
| 415 | Tipo de contenido no admitido |
| 422 | Errores de validación (`errors` contiene el detalle por campo) |
| 500 | Error interno (detalle solo en `storage/logs/laravel.log`) |

Los errores que ocurren antes de llegar al controlador (ruta inexistente, método no permitido, solicitud demasiado grande) también se devuelven en JSON, configurado en `bootstrap/app.php`.

## Pruebas

```bash
php artisan test
```

Para Postman: envíe siempre el encabezado `Accept: application/json`. Las rutas `/admin/*` (salvo login) y `/client/*` privadas requieren `Authorization: Bearer <token>`.
