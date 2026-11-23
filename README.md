# TecnoStock

Sistema web de control de inventario desarrollado en **PHP** con **MySQL/MariaDB**, pensado para ejecutarse con **XAMPP**.

## Funcionalidades

- Inicio de sesión con correo y clave (contraseñas hasheadas con `password_hash`).
- Gestión de productos: listado con búsqueda, alta, edición y desactivación.
- Registro de movimientos de inventario (entradas y salidas) con historial.
- Control de stock actual y stock mínimo por producto.
- Categorías de productos.
- Interfaz con Bootstrap 5.

## Estructura del repositorio

| Archivo / carpeta | Descripción |
| --- | --- |
| `index.php` | Inicio de sesión |
| `auth.php` | Verificación de sesión |
| `conexion.php` | Conexión PDO a MySQL |
| `inicio.php` | Panel principal |
| `productos.php` | Listado y búsqueda de productos |
| `producto_form.php` | Alta y edición de productos |
| `movimientos.php` | Entradas, salidas e historial |
| `header.php` / `footer.php` | Plantilla HTML compartida |
| `logout.php` | Cierre de sesión |
| `sql/tecnostock.sql` | Base de datos, tablas y datos iniciales (archivo único) |

## Requisitos

- XAMPP (Apache, PHP 7.4 o superior, MySQL/MariaDB)
- Navegador web

## Instalación

1. Copia esta carpeta dentro de `xampp/htdocs/` (por ejemplo `htdocs/Tecnostock`).
2. Inicia Apache y MySQL desde el panel de XAMPP.
3. Importa el script SQL desde phpMyAdmin o la línea de comandos:
   ```bash
   mysql -u root < sql/tecnostock.sql
   ```
4. Ajusta los datos de conexión en `conexion.php` si tu MySQL usa otro usuario o contraseña:
   ```php
   $host = 'localhost';
   $db   = 'tecnostock';
   $user = 'root';
   $pass = '';
   ```
5. Abre `http://localhost/Tecnostock/` en el navegador.

## Usuario inicial

El script `sql/tecnostock.sql` crea el usuario:

- Correo: `admin@tecnostock.cl`
- Clave: `admin123`

> Nota: el usuario por defecto de XAMPP es `root` sin contraseña. En un entorno real cambia estas credenciales.

## Tecnologías

PHP · PDO · MySQL · Bootstrap 5 · XAMPP
