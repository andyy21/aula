/* ==========================================================================
   notas.js · JS LIBRE: notas del dashboard guardadas con localStorage
   Unidad 3 · Programación Web

   Qué hace: lo que escribes en las 4 notas (Idea, Recordatorio, Nota,
   Pendiente) se guarda en el navegador y reaparece al recargar o volver
   otro día. Cada nota tiene contador de caracteres y botón "Borrar".

   localStorage es una "libreta" del navegador: guarda pares clave/valor de
   texto y no se borra al cerrar la pestaña. Los datos NO viajan al servidor.

   La clave incluye el id del usuario (data-usuario), para que en una misma
   computadora cada persona vea solo sus propias notas.
   ========================================================================== */
(function ($) {
    'use strict';

    var MAX = 500;          // caracteres por nota
    var ESPERA = 400;       // ms sin escribir antes de guardar

    $(function () {
        var $grid = $('.notas-grid[data-usuario]');
        if (!$grid.length) { return; }

        var clave = 'aula_notas_' + String($grid.attr('data-usuario')).replace(/\D/g, '');

        // Leer lo guardado (si localStorage falla o el JSON está dañado, empezamos vacío).
        var guardadas = {};
        try {
            var crudo = localStorage.getItem(clave);
            if (crudo) { guardadas = JSON.parse(crudo) || {}; }
        } catch (e) { guardadas = {}; }

        function guardar() {
            try { localStorage.setItem(clave, JSON.stringify(guardadas)); return true; }
            catch (e) { return false; }
        }

        $grid.find('textarea[data-nota]').each(function () {
            var $area = $(this);
            var id = $area.attr('data-nota');
            var temporizador = null;

            $area.attr('maxlength', MAX);

            // Pie de la nota: contador + estado + botón borrar (creados con JS).
            var $contador = $('<span class="nota-contador"></span>');
            var $estado   = $('<span class="nota-estado" aria-live="polite"></span>');
            var $borrar   = $('<button type="button" class="nota-borrar">Borrar</button>');
            $('<div class="nota-pie"></div>').append($contador, $estado, $borrar).insertAfter($area);

            function pintarContador() {
                $contador.text($area.val().length + '/' + MAX);
            }

            // Mostrar lo que había guardado.
            if (typeof guardadas[id] === 'string') { $area.val(guardadas[id].slice(0, MAX)); }
            pintarContador();

            // Guardar poco después de dejar de escribir.
            $area.on('input', function () {
                pintarContador();
                $estado.text('Guardando…');
                clearTimeout(temporizador);
                temporizador = setTimeout(function () {
                    guardadas[id] = $area.val();
                    $estado.text(guardar() ? 'Guardado ✓' : 'No se pudo guardar');
                }, ESPERA);
            });

            $borrar.on('click', function () {
                if ($area.val() === '' || window.confirm('¿Borrar esta nota?')) {
                    $area.val('');
                    delete guardadas[id];
                    guardar();
                    pintarContador();
                    $estado.text('Borrada');
                }
            });
        });
    });

})(jQuery);
