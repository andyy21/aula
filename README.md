# Aula · Dashboard estudiantil con tienda de recursos

Aplicación web en **PHP + MySQL** para estudiantes. Cada usuario ve sus materias, su horario y los **paquetes de recursos** (resúmenes, guías, ejercicios) de las materias en las que está inscrito. Los paquetes se compran con **PayPal (Sandbox)** y, al pagar, se desbloquean y se genera un **recibo en PDF**. Incluye un **panel de administrador** para gestionar productos, ventas y usuarios.

> Proyecto escolar de Programación Web. Los pagos funcionan con cuentas **Sandbox** de PayPal: no se mueve dinero real.

**Demo en línea:** _(pega aquí la URL del hosting cuando esté publicado)_

## Funciones

- Registro e inicio de sesión con roles `cliente` y `admin`.
- Materias dinámicas: el cliente se inscribe o se da de baja, y solo ve los recursos de sus materias.
- Paquetes de recursos por materia o unidad, con candado y precio hasta que se compran.
- Carrito y pago con PayPal (Orders v2): el servidor recalcula el total y verifica el monto antes de dar el acceso.
- Recibo en PDF (FPDF), visible solo para su dueño y el admin. Pantalla «Mis compras».
- Conteo de ventas por producto y stock opcional (vacío = ilimitado).
- Panel de administrador: resumen de ventas e ingresos, alta/edición/baja de productos, subida de PDF y enlaces, lista de ventas y lista de usuarios.

## Tecnologías

PHP 8 (PDO), MySQL / MariaDB, JavaScript SDK de PayPal, cURL, [FPDF 1.9](http://www.fpdf.org) (incluida en `lib/fpdf/`), [Lucide](https://lucide.dev) (íconos, incluida como `lucide.js`), [jQuery 3.7.1](https://jquery.com) (cargado desde cdnjs), HTML y CSS propios.

## JavaScript del lado del cliente (Unidad 3)

Todo el JavaScript vive en la carpeta `js/` y usa jQuery. Mejora la experiencia en el navegador; **la validación real sigue haciéndose en PHP**.

| Requisito | Archivo | Qué hace |
| --------- | ------- | -------- |
| Validación con expresiones regulares | `js/validacion.js` | Valida todos los formularios del sitio (login, registro, panel de administrador, buscador y campos ocultos como ids, acción y token CSRF). Muestra el error junto a cada campo y bloquea el envío. |
| Carrusel con jQuery | `js/carrusel.js` | Carrusel de avisos en el inicio, hecho a mano: automático con barra de progreso, flechas, puntos, pausa, deslizar con el dedo y teclado. |
| Uso del DOM #1 | `js/reloj.js` | Reloj y fecha en vivo en la barra superior (reescribe el texto cada segundo). |
| Uso del DOM #2 | `js/password.js` | Crea un botón «Mostrar/Ocultar» junto a cada contraseña y cambia el atributo `type` del campo. |
| Uso del DOM #3 | `js/buscador.js` | Buscador en vivo en Mis Materias: muestra y oculta tarjetas mientras se escribe. |
| JS libre | `js/notas.js` | Las notas del inicio se guardan con `localStorage` (por usuario) y reaparecen al recargar. |

## Requisitos

- PHP 8.0 o superior con las extensiones `pdo_mysql`, `curl`, `mbstring` e `iconv`.
- MySQL 5.7+ o MariaDB 10.3+.
- Apache (los `.htaccess` protegen las carpetas internas).
- HTTPS si se publica con PayPal real. Para Sandbox en local no hace falta.
- Una app Sandbox de PayPal (Client ID y Secret) y una cuenta Sandbox **Personal** para comprar.

## Instalación en local (XAMPP)

1. Copia la carpeta del proyecto en `C:\xampp\htdocs\aula` (el nombre de la carpeta puede ser otro; la URL base se detecta sola).
2. Inicia **Apache** y **MySQL** desde el panel de XAMPP.
3. En phpMyAdmin crea una base de datos llamada `aula` con cotejamiento `utf8mb4_unicode_ci`. **Selecciónala** e importa primero `sql/schema.sql` y después `sql/seed.sql`.
4. Copia `config.example.php` como `config.local.php` y llena tus datos (base de datos y credenciales de PayPal Sandbox).
5. Abre `http://localhost/aula/`.

Si cURL marca un error de certificado SSL, descarga `cacert.pem` y pon su ruta en la opción `cacert` de `config.local.php`.

## Configurar PayPal Sandbox

1. Entra a [developer.paypal.com](https://developer.paypal.com) con tu cuenta de PayPal.
2. En **Apps & Credentials > Sandbox** crea una app y copia el **Client ID** y el **Secret** en `config.local.php`.
3. En **Testing tools > Sandbox accounts** usa (o crea) una cuenta **Personal**: con esa cuenta se paga. No pagues con la cuenta Business, que es la del vendedor.
4. Si tu cuenta Sandbox no acepta `MXN`, cambia `moneda` a `USD` en `config.local.php`.

El **Secret** vive solo en `config.local.php`, que está en `.gitignore` y no se sube al repositorio.

## Cuentas de prueba (datos de `sql/seed.sql`)

| Rol                            | Correo                                                | Contraseña |
| ------------------------------ | ----------------------------------------------------- | ---------- |
| Administrador                  | `admin@aula.test`                                     | `password` |
| Cliente (cursa las 5 materias) | `ana@aula.test`                                       | `password` |
| Otros clientes                 | `alberto@`, `laura@`, `jorge@`, `rosa@` + `aula.test` | `password` |

**Antes de publicar el sitio en internet, cambia la contraseña del administrador** (o elimina estas cuentas). El sistema guarda el hash con `password_hash()`. Para generar uno nuevo, abre una terminal donde esté PHP (en XAMPP: `C:\xampp\php`) y ejecuta:

```
php -r "echo password_hash('TuNuevaContraseña', PASSWORD_DEFAULT);"
```

Copia el resultado y actualízalo en phpMyAdmin, en la tabla `usuarios`, columna `password_hash` del administrador.

## Publicar en un hosting

1. Crea la base de datos y su usuario desde el panel del hosting e importa `sql/schema.sql` y `sql/seed.sql` desde su phpMyAdmin (con la base seleccionada).
2. Sube todos los archivos por FTP o por el administrador de archivos, **sin** `config.local.php`.
3. Crea `config.local.php` en el servidor a partir de `config.example.php` con los datos de la base del hosting y tus credenciales de PayPal.
4. Elige PHP 8 o superior en el panel y confirma que estén activas `pdo_mysql`, `curl`, `mbstring` e `iconv`.
5. Comprueba que la carpeta `storage/recursos/` permita escribir (la usa el panel de administrador para guardar los PDF).
6. Prueba el flujo completo: registro, compra con Sandbox, recibo y panel de administrador.

### Notas para hostings gratuitos (como InfinityFree)

- La primera visita puede mostrar una comprobación de JavaScript y agregar `?i=1` a la URL: es el sistema de seguridad del hosting y es normal.
- El servidor puede llamar a PayPal hacia afuera, y el `fetch` del checkout funciona porque va al mismo dominio. **No** se pueden usar *webhooks* (llamadas de PayPal hacia el sitio); este proyecto no los usa.
- Si aparece un error de certificado SSL al pagar, descarga `cacert.pem`, súbelo a `includes/` y en `config.local.php` pon `"cacert" => __DIR__ . "/includes/cacert.pem"` dentro de la sección `paypal`.

Para cobrar con dinero real hay que usar credenciales **Live**, cambiar `api_base` a `https://api-m.paypal.com` y tener HTTPS.

## Estructura

```
index.php, horario.php, materias.php, materia.php, recursos.php   páginas del cliente
login.php, registro.php, logout.php, inscripcion.php
carrito.php, checkout.php, compras.php, recibo.php, descargar.php
api/        crear-orden.php, capturar-orden.php (PayPal, solo servidor)
admin/      index.php, productos.php, producto.php, ventas.php, usuarios.php
includes/   db, auth, funciones, tienda, paypal, paquetes, admin, header, sidebar, footer
js/         validacion, carrusel, reloj, password, buscador, notas (jQuery)
lib/fpdf/   librería FPDF
storage/recursos/   PDF de los paquetes (no se abren directo: solo vía descargar.php)
sql/        schema.sql, seed.sql, limpiar-pruebas.sql
config.example.php  plantilla de configuración
```

## Seguridad

- Consultas con PDO preparado y contraseñas con `password_hash()` / `password_verify()`.
- Toda salida se escapa con `htmlspecialchars`; los formularios llevan token CSRF.
- Cada página de `admin/` verifica el rol; recibos y descargas verifican que la compra sea del usuario (o que sea admin).
- El total se recalcula en el servidor, se verifica el estado `COMPLETED` y el monto capturado en PayPal, y la captura es idempotente.
- Los PDF de `storage/recursos/` están bloqueados por `.htaccess`; las carpetas `includes/`, `lib/` y `sql/` también.
- Las credenciales reales van en `config.local.php`, fuera del repositorio.

## Contenido de los paquetes

Los PDF de `storage/recursos/` son archivos de **ejemplo** hechos para la demostración. Los paquetes reales deben ser material propio del equipo o con licencia que permita su venta; no se deben vender apuntes ajenos.

## Limitaciones conocidas

- Las tarjetas Resumen, Próximas clases, Tareas, Avisos y Progreso del inicio muestran datos de ejemplo. Las Notas se guardan solo en el navegador (`localStorage`), no en la base de datos.
- jQuery se carga desde un CDN, así que el sitio necesita internet para que funcione el JavaScript.
- La tabla `ventas` no guarda la moneda: los recibos usan la moneda actual de `config.local.php`.
- El recibo no es una factura fiscal.
- El panel de administrador solo muestra la lista de usuarios; no los crea ni los edita.

## Créditos y licencias

- Equipo: _Andrea y Samara_
- Materia: _Programación Web_
- [FPDF](http://www.fpdf.org): librería de uso libre.
- [Lucide](https://lucide.dev): íconos con licencia ISC.
