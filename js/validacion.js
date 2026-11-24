/* ==========================================================================
   validacion.js · Validación del lado del cliente con expresiones regulares
   Unidad 3 · Programación Web

   Idea general (la analogía del portero):
   El navegador es un portero que revisa tus datos ANTES de mandarlos al
   servidor. Si algo no cuadra, te lo dice junto al campo y no deja pasar el
   formulario. Pero el servidor (PHP) tiene su propio portero: este archivo
   solo mejora la experiencia, nunca reemplaza la validación de PHP.

   Cómo funciona:
   1. Cada campo se asocia con una regla. La regla se busca primero en el
      atributo data-regla del input y, si no lo tiene, por el atributo name.
   2. Una regla tiene: una expresión regular (re), un mensaje y, opcional,
      una función extra (extra) para cosas que una regex no puede decir
      (por ejemplo "mayor que 0" o "igual a la contraseña").
   3. Los campos ocultos (ids, acción, csrf) también se revisan con regex.
   ========================================================================== */
(function ($) {
    'use strict';

    // ---------- 1. Reglas (copian las del servidor) ----------
    // Letras de cualquier idioma (con acentos y ñ), espacios, apóstrofo, punto y guion.
    var RE_NOMBRE   = /^\p{L}[\p{L}' .\-]{2,99}$/u;
    var RE_EMAIL    = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/;
    var RE_PASSWORD = /^.{8,}$/;
    var RE_TEXTO    = /^\S(?:.{0,148}\S)?$/;            // 1 a 150 caracteres, sin espacios al inicio/fin
    var RE_TITULO   = /^\S(?:.{1,148}\S)$/;             // 3 a 150 caracteres
    var RE_PRECIO   = /^\d{1,6}([.,]\d{1,2})?$/;        // 49 · 49.5 · 49,50
    var RE_STOCK    = /^\d{1,6}$/;                      // entero; vacío = ilimitado
    var RE_URL      = /^https?:\/\/[^\s\/$.?#][^\s]*$/i;
    var RE_ID       = /^[1-9]\d{0,9}$/;                 // ids numéricos de la base de datos
    var RE_ACCION   = /^[a-z_]{3,30}$/;                 // inscribir, baja, quitar, guardar...
    var RE_CSRF     = /^[a-f0-9]{64}$/;                 // el token que genera PHP (bin2hex)
    var RE_BUSQUEDA = /^[\p{L}\p{N}@._+' \-]{0,100}$/u;

    var MAX_PDF = 10 * 1024 * 1024; // 10 MB, igual que MAX_PDF_BYTES en admin/producto.php

    var REGLAS = {
        // --- Visibles ---
        nombre:   { re: RE_NOMBRE, msg: 'Escribe tu nombre completo: solo letras y espacios (de 3 a 100 caracteres).' },
        email:    { re: RE_EMAIL, max: 150, msg: 'Escribe un correo válido, por ejemplo nombre@correo.com.' },
        password: { re: RE_PASSWORD, msg: 'La contraseña debe tener al menos 8 caracteres.' },
        confirma: {
            re: RE_PASSWORD,
            msg: 'Confirma tu contraseña (mínimo 8 caracteres).',
            extra: function (valor, $form) {
                return valor === $form.find('[name="password"]').val() || 'Las contraseñas no coinciden.';
            }
        },
        vacio:    { re: /\S/, msg: 'Este campo no puede estar vacío.' },   // login: contraseña
        titulo:   { re: RE_TITULO, msg: 'El título debe tener entre 3 y 150 caracteres.' },
        descripcion: { re: /^[\s\S]{0,1000}$/, msg: 'La descripción no puede pasar de 1000 caracteres.' },
        precio:   {
            re: RE_PRECIO,
            msg: 'Escribe un precio válido, por ejemplo 49 o 49.50.',
            extra: function (valor) {
                return parseFloat(valor.replace(',', '.')) > 0 || 'El precio debe ser mayor que 0.';
            }
        },
        stock:    { re: RE_STOCK, msg: 'El stock debe ser un número entero (o déjalo vacío para ilimitado).' },
        titulo_recurso: { re: RE_TEXTO, msg: 'Escribe un título para el recurso (máximo 150 caracteres).' },
        url:      { re: RE_URL, max: 255, msg: 'Escribe un enlace válido que empiece con http:// o https://' },
        q:        { re: RE_BUSQUEDA, msg: 'La búsqueda solo admite letras, números y los símbolos @ . _ + - (máx. 100).' },
        materia_id: { re: RE_ID, msg: 'Elige una materia válida.' },

        // --- Ocultos (si fallan, es que alguien manipuló el formulario) ---
        producto_id: { re: RE_ID },
        recurso_id:  { re: RE_ID },
        id:          { re: RE_ID },
        accion:      { re: RE_ACCION },
        csrf:        { re: RE_CSRF }
    };

    // Los campos "opcionales" solo se validan si traen algo escrito.
    // (El resto lo decide el atributo required de cada input.)

    // ---------- 2. Utilidades ----------
    function nombreDe($campo) {
        return String($campo.attr('name') || '').replace(/\[\]$/, ''); // materias[] -> materias
    }

    function reglaDe($campo) {
        return REGLAS[$campo.attr('data-regla')] || REGLAS[nombreDe($campo)] || null;
    }

    // Devuelve '' si el campo es válido, o el mensaje de error.
    function revisar($campo, $form) {
        var tipo = ($campo.attr('type') || '').toLowerCase();

        // Casillas de materias: el value debe ser un id numérico.
        if (tipo === 'checkbox' || tipo === 'radio') {
            if (nombreDe($campo) === 'materias') {
                return RE_ID.test($campo.val()) ? '' : 'Materia no válida.';
            }
            return '';
        }

        // Archivo PDF (extensión y tamaño).
        if (tipo === 'file') {
            var f = $campo[0].files && $campo[0].files[0];
            if (!f) {
                return $campo.prop('required') ? 'Elige un archivo PDF.' : '';
            }
            if (!/\.pdf$/i.test(f.name)) { return 'Solo se aceptan archivos .pdf.'; }
            if (f.size > MAX_PDF)        { return 'El PDF es demasiado grande (máximo 10 MB).'; }
            return '';
        }

        var regla = reglaDe($campo);
        if (!regla) { return ''; }

        var valor = String($campo.val() == null ? '' : $campo.val());
        if (tipo !== 'password' && tipo !== 'hidden') { valor = $.trim(valor); }

        // Vacío
        if (valor === '') {
            if ($campo.prop('required')) { return regla.msg || 'Este campo es obligatorio.'; }
            return '';
        }
        // Formato (expresión regular) y largo máximo
        if (!regla.re.test(valor))              { return regla.msg || 'Dato no válido.'; }
        if (regla.max && valor.length > regla.max) { return regla.msg; }
        // Comprobación extra
        if (regla.extra) {
            var r = regla.extra(valor, $form);
            if (r !== true) { return r; }
        }
        return '';
    }

    // ---------- 3. Mostrar y quitar errores ----------
    function marcar($campo, mensaje) {
        var $caja = $campo.closest('.campo');
        if (!$caja.length) { $caja = $campo.parent(); }
        $caja.find('.error-campo').remove();
        $campo.removeClass('invalido').removeAttr('aria-invalid');
        if (mensaje) {
            $campo.addClass('invalido').attr('aria-invalid', 'true');
            $('<small class="error-campo" role="alert"></small>').text(mensaje).appendTo($caja);
        }
    }

    function avisoGeneral($form, mensaje) {
        $form.find('.alerta-validacion').remove();
        if (mensaje) {
            $('<div class="alerta alerta-error alerta-validacion" role="alert"></div>')
                .text(mensaje).prependTo($form);
        }
    }

    // ---------- 4. Conectar los formularios ----------
    $(function () {
        $('form').each(function () {
            var $form = $(this);

            // novalidate: así los globitos del navegador no tapan nuestros mensajes.
            $form.attr('novalidate', 'novalidate');

            var $visibles = $form.find('input, select, textarea')
                .not('[type="hidden"], [type="submit"], [type="button"]');

            // Validar en vivo: al salir del campo, y luego mientras escribe.
            $visibles.on('blur change', function () {
                var $c = $(this);
                marcar($c, revisar($c, $form));
                // Si cambia la contraseña, revisa de nuevo la confirmación.
                if (nombreDe($c) === 'password') {
                    var $conf = $form.find('[name="confirma"]');
                    if ($conf.val() !== '') { marcar($conf, revisar($conf, $form)); }
                }
            });
            $visibles.on('input', function () {
                var $c = $(this);
                if ($c.hasClass('invalido')) { marcar($c, revisar($c, $form)); }
            });

            // Al enviar: revisar todo y bloquear si algo falla.
            $form.on('submit', function (ev) {
                var primero = null;
                var hayOcultoMalo = false;

                $visibles.each(function () {
                    var $c = $(this);
                    var msg = revisar($c, $form);
                    marcar($c, msg);
                    if (msg && !primero) { primero = $c; }
                });

                $form.find('input[type="hidden"]').each(function () {
                    if (revisar($(this), $form)) { hayOcultoMalo = true; }
                });

                if (hayOcultoMalo) {
                    avisoGeneral($form, 'Este formulario no es válido. Recarga la página e inténtalo de nuevo.');
                } else {
                    avisoGeneral($form, '');
                }

                if (primero || hayOcultoMalo) {
                    ev.preventDefault();
                    ev.stopImmediatePropagation();
                    if (primero) { primero.trigger('focus'); }
                }
            });
        });
    });

})(jQuery);
