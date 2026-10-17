# Minimarket Hito 3 — PRO-314

Sistema de productos con **frontend PHP → HTTP/JSON → backend Quarkus → JDBC → MySQL en Docker**. Corresponde a la gestión de productos de la práctica: consulta, alta, edición y **eliminación lógica**.

## Requisitos

- Windows 11 + Docker Desktop con motor Linux activo.
- JDK 21+ instalado, preferentemente **JDK 21**. El `pom.xml` compila con Java 21 (`maven.compiler.release=21`); Java 25 puede requerir pruebas adicionales con Quarkus 3.27.
- PHP 8.2+ con extensión **curl** habilitada. Habilitar `extension=curl` en `php.ini` y verificar `php -m`.
- PowerShell e Internet para la primera descarga de Maven y dependencias Maven. **No se requiere Maven instalado**: `backend/mvnw.cmd` descarga Maven 3.9.11 en `backend/.mvn/` si es necesario.

## Orden de arranque: MySQL → Backend → Frontend

Abrir PowerShell en la carpeta `minimarket_hito3`.

```powershell
# 1. Crear .env
Copy-Item .env.example .env
```

**IMPORTANTE: editar `.env` para pruebas iniciales**. El archivo `.env.example` contiene valores `CAMBIAR_CLAVE_...` por seguridad, y **NO** son las contraseñas `minimarket_local` que usa el backend por defecto. Para usar los comandos tal cual, cambiar el contenido de `.env` a:

```dotenv
MYSQL_ROOT_PASSWORD=root_local_cambiar
MYSQL_USER=minimarket
MYSQL_PASSWORD=minimarket_local
```

Estas son solo credenciales de ejemplo de **desarrollo local**. Nunca subir `.env` al repositorio. Si se mantiene una contraseña diferente, configurar la misma en el backend, por ejemplo, antes de iniciarlo: `$env:DB_PASSWORD='MI_CLAVE_ELEGIDA'`.

```powershell
# 2. Levantar MySQL (desde la raíz del proyecto)
docker compose up -d
# Opcional: esperar hasta que el estado sea healthy
docker compose ps

# 3. Backend (otra terminal PowerShell, desde la raíz)
cd backend
.\mvnw.cmd quarkus:dev

# 4. Frontend (otra terminal PowerShell, desde la raíz)
cd frontend
php -S localhost:8000
```

> Cada terminal 3/4 parte de la raíz del proyecto. **No ejecutes `cd frontend` desde `backend`**. La primera ejecución de `.\mvnw.cmd` requiere conexión a Internet. Los tres procesos deben mantenerse iniciados.

## Direcciones y puertos

| Componente | Dirección | Descripción |
|---|---|---|
| MySQL | `localhost:3307` | Docker publica `3307:3306` |
| Quarkus | `http://localhost:8080/api/productos` | API JSON |
| PHP | `http://localhost:8000` | Interfaz web |

La API lee las variables `DB_USER`, `DB_PASSWORD`, `DB_URL` (valores por defecto en `application.properties`). PHP lee `API_URL` (por defecto `http://localhost:8080/api/productos`), solo hace peticiones HTTP cURL y **no** tiene conexión SQL.

## Endpoints

| Método | Ruta | Función | Respuesta |
|---|---|---|---|
| GET | `/api/productos` | Lista activos (`eliminado=0`) | 200 JSON array |
| GET | `/api/productos/{id}` | Busca activo | 200 o 404 |
| POST | `/api/productos` | Crea producto | 201 o 400 |
| PUT | `/api/productos/{id}` | Edita activo | 200, 400 o 404 |
| DELETE | `/api/productos/{id}` | Eliminación lógica: `UPDATE Producto SET eliminado=1` | 204 o 404 |

Ejemplo de cuerpo POST/PUT:

```json
{"nombre":"Azúcar 1 kg","proveedor":"Proveedor Sur","fecha_vencimiento":"2027-10-20","precio":9.50,"stock":25}
```

Prueba rápida en PowerShell:

```powershell
Invoke-RestMethod http://localhost:8080/api/productos
Invoke-RestMethod -Method Post -Uri http://localhost:8080/api/productos -ContentType 'application/json' -Body '{"nombre":"Azúcar 1 kg","proveedor":"Proveedor Sur","fecha_vencimiento":"2027-10-20","precio":9.50,"stock":25}'
```

Para editar/borrar, sustituir `{id}` por un ID real encontrado en la lista o utilizar la interfaz PHP.

## Verificar la eliminación lógica en MySQL

Tras pulsar Eliminar en `http://localhost:8000`, el registro desaparece del listado activo, **pero sigue en la tabla**:

```powershell
docker exec minimarket_mysql mysql -uminimarket -pminimarket_local db_minimarket_pro413 -e "SELECT id,nombre,eliminado FROM Producto;"
```

Ese comando funciona si en `.env` se eligió `MYSQL_PASSWORD=minimarket_local`; con otra contraseña, sustituirla también en la línea anterior. Si el contenedor ya tenía un volumen MySQL anterior, las variables y `01-schema.sql` **no** se vuelven a aplicar automáticamente (los scripts se ejecutan solo al inicializar un volumen vacío). Para resetear datos de prueba, y **solo si no necesitas preservar los datos existentes**, usar `docker compose down -v` seguido de `docker compose up -d`.

## Estructura del proyecto

```text
minimarket_hito3/
├── backend/
│   ├── pom.xml
│   ├── mvnw
│   ├── mvnw.cmd
│   ├── .mvn/wrapper/maven-wrapper.properties
│   ├── .mvn/wrapper/bootstrap-maven.ps1  (descarga Maven sin instalación global)
│   └── src/main/
│       ├── java/bo/edu/minimarket/{Producto.java,ProductoResource.java}
│       └── resources/application.properties
├── frontend/index.php
├── sql/01-schema.sql
├── docker-compose.yml
├── .env.example
├── .gitignore
└── README.md
```

**Responsabilidades:** `Producto.java` representa los datos JSON; `ProductoResource.java` valida y realiza consultas JDBC preparadas; `application.properties` configura datasource y puerto; `01-schema.sql` prepara la tabla y tres productos; `docker-compose.yml` ejecuta MySQL con volumen persistente; `frontend/index.php` renderiza formulario/tabla y usa cURL para comunicar con la API.

## Diagrama de arquitectura

```text
NAVEGADOR ──HTTP :8000──> PHP (index.php)
                              │
                              │ HTTP cURL / JSON :8080
                              ▼
                        Quarkus REST
                              │
                              │ JDBC MySQL :3307 (host)
                              ▼
                         MySQL Docker
                              │
                              ▼
                        Volumen mysql_datos
```

## Comprobación básica antes de la defensa

1. `docker compose ps` muestra `minimarket_mysql` saludable.
2. `http://localhost:8080/api/productos` devuelve JSON de 3 productos iniciales.
3. `http://localhost:8000` muestra tabla y permite registrar, editar y eliminar.
4. Tras eliminar, la API ya no muestra el producto; la consulta MySQL muestra `eliminado=1`.
5. Tomar capturas reales de todas las operaciones para el **informe técnico PDF** y publicar el código en un repositorio accesible, requeridos adicionalmente por la rúbrica.

**Nota sobre Maven Wrapper:** los scripts `mvnw` y `mvnw.cmd` incluidos realizan una instalación local automática de Maven a partir de `distributionUrl`. No dependen de un `mvn` global ni requieren `maven-wrapper.jar`; `bootstrap-maven.ps1` proporciona la descarga en Windows. Se necesita Internet la primera vez.
