/* ==========================================================================
   buscador.js · USO DEL DOM #3: buscador en vivo de materias
   Unidad 3 · Programación Web

   Qué hace: mientras escribes en el cuadro de búsqueda, muestra solo las
   materias cuyo nombre o profesor contiene lo escrito (sin importar
   mayúsculas ni acentos) y esconde las demás. Si no hay ninguna, crea un
   mensaje "Sin resultados".

   Operaciones del DOM que se ven aquí:
   - Seleccionar elementos:  $('.materia-card'), $('.fila-inscribir')
   - Leer su texto:          .text()
   - Mostrar/ocultar:        .toggleClass('oculto', ...)
   - Crear un elemento:      $('<p class="sin-resultados">')

   El campo se llama "q", así que js/validacion.js lo valida con regex
   (solo letras, números y @ . _ + -). Aquí solo disparamos esa validación.
   ========================================================================== */
(function ($) {
    'use strict';

    // "Matemáticas" -> "matematicas" (quita acentos y pasa a minúsculas)
    function limpiar(texto) {
        return String(texto).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
    }

    $(function () {
        var $campo = $('#buscar-materia');
        if (!$campo.length) { return; }

        var $items = $('.materia-card, .fila-inscribir');
        var $aviso = $('<p class="sin-resultados" hidden></p>').insertAfter($campo.closest('form'));

        // Enter no debe recargar la página.
        $campo.closest('form').on('submit', function (ev) { ev.preventDefault(); });

        function filtrar() {
            var buscado = limpiar($.trim($campo.val()));
            var visibles = 0;

            $items.each(function () {
                var $item = $(this);
                var coincide = limpiar($item.text()).indexOf(buscado) !== -1;
                $item.toggleClass('oculto', !coincide);
                if (coincide) { visibles++; }
            });

            // La tarjeta "Materias disponibles" y su título se esconden si quedó vacía.
            $('.fila-inscribir').closest('.card').each(function () {
                var $tarjeta = $(this);
                var hay = $tarjeta.find('.fila-inscribir:not(.oculto)').length > 0;
                $tarjeta.toggleClass('oculto', !hay);
                $tarjeta.prev('.subtitulo').toggleClass('oculto', !hay);
            });

            // Mensaje cuando no hay resultados (.text() evita meter HTML).
            if (visibles === 0) {
                $aviso.text('No encontramos materias para «' + $.trim($campo.val()) + '».').prop('hidden', false);
            } else {
                $aviso.prop('hidden', true);
            }
        }

        $campo.on('input', function () {
            filtrar();
            $campo.trigger('change');   // pide a validacion.js que revise el campo (regex)
        });
    });

})(jQuery);
