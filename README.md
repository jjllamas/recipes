# Recipe Planner

Aplicación web para gestionar recetas y planificar menús semanales. Permite crear un recetario personal, organizar el menú de cada semana por día y tipo de comida, y generar automáticamente la lista de la compra.

## Características

- Registro e inicio de sesión de usuarios (multi-usuario, datos aislados por cuenta)
- Recetario con categorías, ingredientes, tiempos, dificultad y calorías
- Planificador de menú semanal (vista tabla y vista acordeón)
- Generación de lista de la compra a partir del menú de la semana
- Relleno automático de huecos del menú con sugerencias aleatorias

## Tecnologías

- PHP 8+ con MySQLi
- MySQL / MariaDB
- Bootstrap 5.3
- Laragon (entorno local recomendado)

## Requisitos previos

- PHP >= 8.0
- MySQL / MariaDB
- Servidor web (Apache/Nginx) — Laragon lo incluye todo

## Instalación

### 1. Clonar el repositorio

```bash
git clone <url-del-repo> C:/laragon/www/recipes
```

### 2. Crear la base de datos

Importa el esquema SQL en tu gestor (phpMyAdmin, TablePlus, etc.) o ejecuta:

```sql
CREATE DATABASE recipes CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Las tablas necesarias son:

| Tabla | Descripción |
|---|---|
| `users` | Cuentas de usuario |
| `recipes` | Recetas |
| `categories` | Categorías (Breakfast, Snack, Main dish…) |
| `recipe_categories` | Relación receta ↔ categoría |
| `ingredients` | Catálogo de ingredientes |
| `recipe_ingredients` | Ingredientes por receta con cantidad y unidad |
| `menu_planner` | Asignación de recetas al menú semanal |

### 3. Configurar la conexión a la base de datos

Copia el archivo de ejemplo y edítalo con tus credenciales:

```bash
cp includes/db_config.example.php includes/db_config.local.php
```

```php
// includes/db_config.local.php
return [
    'host'     => 'localhost',
    'user'     => 'root',
    'password' => '',
    'database' => 'recipes',
    'charset'  => 'utf8mb4',
];
```

### 4. Ajustar la URL base

Si la carpeta del proyecto no se llama `recipes` o usas un dominio propio, edita:

```php
// includes/config.php
define('BASE_URL', '/recipes'); // ← ajusta aquí
```

### 5. Acceder a la aplicación

```
http://localhost/recipes/
```

## Estructura del proyecto

```
recipes/
├── index.php                  # Redirección inteligente (login / menú)
├── favicon_recipe.jpg
├── includes/
│   ├── config.php             # BASE_URL y configuración global
│   ├── session.php            # Guard de autenticación + conexión DB
│   ├── db_connect.php         # Instancia MySQLi ($conn)
│   ├── db_config.local.php    # Credenciales locales (no versionar)
│   ├── db_config.example.php  # Plantilla de credenciales
│   ├── header.php             # HTML head + navbar Bootstrap
│   └── footer.php             # Bootstrap JS + cierre HTML
├── auth/
│   ├── login.php
│   ├── register.php
│   └── logout.php
├── recipes/
│   ├── index.php              # Lista de recetas (filtros + paginación)
│   ├── create.php
│   ├── edit.php
│   └── details.php
├── menu/
│   ├── index.php              # Vista tabla semanal
│   ├── planner.php            # Vista acordeón por día
│   └── shopping_list.php
└── api/                       # Endpoints AJAX (responden texto plano)
    ├── add_to_menu.php
    ├── delete_from_menu.php
    └── assign_recipe.php
```

## Seguridad

- Contraseñas hasheadas con `password_hash` (bcrypt)
- Todas las consultas SQL usan prepared statements
- Cada recurso verifica que pertenece al usuario autenticado antes de mostrarlo o modificarlo
- `db_config.local.php` está excluido del control de versiones (`.gitignore`)

## Notas de desarrollo

- Modificar `BASE_URL` en `includes/config.php` es el único cambio necesario para mover la app a otra ruta o dominio.
- Los endpoints de `api/` no devuelven HTML — no incluir `header.php` en ellos.
- La lista de la compra agrupa cantidades por ingrediente **y** unidad; si el mismo ingrediente aparece con unidades distintas se lista por separado en lugar de sumar cantidades incompatibles.
