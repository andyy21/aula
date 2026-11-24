/* ==========================================================================
   password.js · USO DEL DOM #2: mostrar u ocultar la contraseña
   Unidad 3 · Programación Web

   Qué hace: a cada campo de contraseña le CREA un botón "Mostrar". Al
   hacer clic, cambia el atributo type del input entre "password" (puntitos)
   y "text" (letras visibles), y cambia el texto del botón.

   Operaciones del DOM que se ven aquí:
   - Seleccionar elementos:  $('input[type="password"]')
   - Crear elementos nuevos: $('<button>') y $('<div>')
   - Insertarlos en la página: .wrap(), .after()
   - Cambiar un atributo:    .attr('type', ...)
   - Cambiar texto:          .text(...)
   ========================================================================== */
(function ($) {
    'use strict';

    $(function () {
        $('input[type="password"]').each(function () {
            var $input = $(this);

            // 1. Envolver el input en un div (para poder colocar el botón encima).
            $input.wrap('<div class="campo-password"></div>');

            // 2. Crear el botón y colocarlo justo después del input.
            var $boton = $('<button type="button" class="ver-password"></button>')
                .text('Mostrar')
                .attr('aria-pressed', 'false')
                .attr('aria-label', 'Mostrar contraseña');
            $input.after($boton);

            // 3. Al hacer clic: cambiar el type del input y el texto del botón.
            $boton.on('click', function () {
                var oculta = $input.attr('type') === 'password';
                $input.attr('type', oculta ? 'text' : 'password');
                $boton
                    .text(oculta ? 'Ocultar' : 'Mostrar')
                    .attr('aria-pressed', oculta ? 'true' : 'false')
                    .attr('aria-label', oculta ? 'Ocultar contraseña' : 'Mostrar contraseña');
                $input.trigger('focus');
            });
        });
    });

})(jQuery);
