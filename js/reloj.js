/* ==========================================================================
   reloj.js · USO DEL DOM #1: reloj y fecha en vivo
   Unidad 3 · Programación Web

   Qué hace: cada segundo lee la hora del navegador y REESCRIBE el texto de
   dos elementos de la barra superior (#fecha y #reloj).

   Operaciones del DOM que se ven aquí:
   - Seleccionar elementos:  $('#reloj'), $('#fecha')
   - Modificar su contenido: .text(...)
   ========================================================================== */
(function ($) {
    'use strict';

    var DIAS  = ['Domingo', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado'];
    var MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio',
                 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    function dos(n) { return (n < 10 ? '0' : '') + n; }   // 7 -> "07"

    $(function () {
        var $reloj = $('#reloj');
        var $fecha = $('#fecha');
        if (!$reloj.length) { return; }                     // páginas sin barra superior

        function tick() {
            var d = new Date();
            // Hora con segundos: 14:05:09
            $reloj.text(dos(d.getHours()) + ':' + dos(d.getMinutes()) + ':' + dos(d.getSeconds()));
            // Fecha larga (igual que la del servidor): Martes, 24 de noviembre 2026
            if ($fecha.length) {
                $fecha.text(DIAS[d.getDay()] + ', ' + dos(d.getDate()) + ' de ' +
                            MESES[d.getMonth()] + ' ' + d.getFullYear());
            }
        }

        tick();
        setInterval(tick, 1000);
    });

})(jQuery);
