# MD Colaborativo

App PHP para subir, ver, editar y versionar documentos Markdown de forma colaborativa (sin login, con nombre de autor por edición). Diagramas Mermaid, tablas GFM, autocompletado de snippets en el editor.

## Requisitos

- PHP 8.2+ con extensión `pdo_mysql`
- MySQL/MariaDB
- Composer (para instalar `league/commonmark`)

## Instalación local

```bash
composer install
cp .env.example .env   # editar credenciales de tu MySQL local
mysql -u root -p < schema.sql       # o crear la BD primero y luego importar
php -S 127.0.0.1:8000 -t public
```

Abrir `http://127.0.0.1:8000`.

## Despliegue en Hostinger (md.pdsx.org)

1. **Base de datos**: en hPanel crear una base de datos MySQL (Sitios → Bases de datos), anotar host/usuario/password/nombre. Importar `schema.sql` desde phpMyAdmin (enlace disponible en hPanel).
2. **Dominio/subdominio**: configurar `md.pdsx.org` apuntando a una carpeta del hosting (ej. `public_html/md`), y en hPanel fijar el **document root** de ese dominio directamente a la carpeta `public/` del proyecto (no a la raíz) — así `src/`, `vendor/` y `.env` quedan fuera del árbol servible por el navegador.
3. **Versión de PHP**: en hPanel → PHP Configuration, seleccionar PHP 8.2 o 8.3.
4. **Subir archivos**: vía FTP/File Manager o `git clone`, subir todo el proyecto (incluyendo `vendor/`, ya generado localmente con `composer install`, si el plan no tiene acceso SSH para correr Composer en el servidor).
5. **.env**: crear en el servidor (fuera de `public/`) copiando `.env.example` con las credenciales reales de la base de datos de Hostinger. **Nunca subir `.env` a ningún repositorio, ni público ni privado.**
6. **Límites de subida**: en hPanel → PHP Options, subir `upload_max_filesize` y `post_max_size` si se esperan documentos grandes (por defecto ya son generosos).
7. Verificar que `md.pdsx.org` carga el listado de documentos sin errores.

## Estructura

- `public/` — todo lo servido por el navegador (document root del dominio)
- `src/` — lógica PHP (conexión DB, modelos, parser Markdown)
- `schema.sql` — esquema de base de datos
- `.env` — credenciales de conexión (no versionar; usar `.env.example` como plantilla)

## Interfaz

- Hoja única `public/assets/css/app.css`, con tokens de color, espaciado y
  tipografía en `:root` y variante clara automática vía `prefers-color-scheme`.
  Cambiar la paleta es cambiar esas variables.
- El `<head>` de todas las páginas sale de `htmlHead()` (en `bootstrap.php`);
  la barra de sesión, de `userbarHtml()`.
- Los assets se enlazan con `asset()`, que añade `?v=<mtime>`: por eso
  `public/.htaccess` los cachea un año sin riesgo de servir versiones viejas.
- Mermaid solo se descarga en documentos que realmente contienen diagramas,
  y hereda la paleta de la app.
- `APP_DEBUG=true` en `.env` muestra el detalle de las excepciones en pantalla.
  En producción debe quedar en `false`.

## Funcionamiento del versionado

Cada guardado crea una versión nueva (snapshot completo) con el nombre del autor. Nada se sobrescribe ni se borra. "Revertir" en el historial crea una nueva versión con el contenido de una versión anterior — no es un merge selectivo estilo git, es restaurar el documento completo al estado de esa versión.
