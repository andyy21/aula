/* ==========================================================================
   carrusel.js · Carrusel de avisos hecho a mano con jQuery
   Unidad 3 · Programación Web

   Analogía: una tira de fotos (la "pista") pasa detrás de una ventana
   (el "carrusel"). Solo vemos una foto a la vez. Para cambiar de aviso no
   movemos la ventana: deslizamos la tira hacia la izquierda con
   .animate({ left: ... }).

   Funciones:
   - Automático, con barra de progreso que muestra cuánto falta.
   - Flechas, puntos clickeables y contador "1 / 5".
   - Botón de pausa / reproducir.
   - Pausa al pasar el mouse o enfocar con el teclado.
   - Deslizar con el dedo en celular y flechas ← → del teclado.
   - Efecto de zoom suave en la imagen y entrada animada del texto.
   - Se pausa si la pestaña no se ve y respeta "reducir movimiento".
   ========================================================================== */
(function ($) {
    'use strict';

    var INTERVALO = 5000;   // ms que dura cada aviso
    var VELOCIDAD = 600;    // ms que dura el deslizamiento
    var UMBRAL    = 40;     // px mínimos de deslizamiento con el dedo

    $(function () {
        $('.carrusel').each(function () {
            var $car    = $(this);
            var $pista  = $car.find('.carrusel-pista');
            var $slides = $pista.children('.carrusel-slide');
            var $barra  = $car.find('.carrusel-progreso span');
            var $cuenta = $car.find('.carrusel-contador');
            var total   = $slides.length;
            if (total < 2) { return; }

            var actual        = 0;
            var pausaMouse    = false;   // mouse encima, foco o dedo
            var pausaUsuario  = false;   // botón de pausa
            var pestanaOculta = false;
            var reducir = window.matchMedia &&
                          window.matchMedia('(prefers-reduced-motion: reduce)').matches;

            // Con "reducir movimiento" el carrusel sigue cambiando solo,
            // pero sin deslizar ni hacer zoom (el cambio es instantáneo).
            if (reducir) { $car.addClass('sin-movimiento'); }

            // ---------- Puntos indicadores (se crean con JS) ----------
            var $puntos = $car.find('.carrusel-puntos');
            $slides.each(function (i) {
                $('<button type="button" class="carrusel-punto"></button>')
                    .attr('aria-label', 'Ir al aviso ' + (i + 1))
                    .on('click', function () { ir(i); })
                    .appendTo($puntos);
            });

            // ---------- Reloj del carrusel (temporizador en JS) ----------
            // Cada 50 ms, si el carrusel no está en pausa, suma tiempo y llena la barra.
            // Al llegar a INTERVALO ms pasa al siguiente aviso. Si hay pausa, el tiempo
            // se congela y al reanudar continúa donde se quedó.
            var PASO = 50;
            var transcurrido = 0;

            function dibujarBarra() {
                $barra.css('transform', 'scaleX(' + Math.min(transcurrido / INTERVALO, 1) + ')');
            }

            function reiniciarBarra() {
                transcurrido = 0;
                dibujarBarra();
            }

            setInterval(function () {
                if ($car.hasClass('pausado')) { return; }
                transcurrido += PASO;
                dibujarBarra();
                if (transcurrido >= INTERVALO) { ir(actual + 1); }
            }, PASO);

            function actualizarPausa() {
                var pausar = pausaMouse || pausaUsuario || pestanaOculta;
                $car.toggleClass('pausado', pausar);
                $car.toggleClass('pausa-usuario', pausaUsuario);
                $car.find('.carrusel-pausa').attr('aria-label',
                    pausaUsuario ? 'Reanudar carrusel' : 'Pausar carrusel');
            }

            // ---------- Mover la pista ----------
            function ir(indice) {
                // Da la vuelta: después del último vuelve al primero y viceversa.
                actual = (indice + total) % total;

                $pista.stop(true, false).animate(
                    { left: (-100 * actual) + '%' },
                    reducir ? 0 : VELOCIDAD
                );

                $slides.removeClass('activo').eq(actual).addClass('activo');

                $puntos.children()
                    .removeClass('activo').removeAttr('aria-current')
                    .eq(actual).addClass('activo').attr('aria-current', 'true');

                $cuenta.text((actual + 1) + ' / ' + total);

                // Los avisos que no se ven no deben recibir foco con Tab.
                $slides.attr('aria-hidden', 'true').find('a').attr('tabindex', '-1');
                $slides.eq(actual).removeAttr('aria-hidden').find('a').removeAttr('tabindex');

                reiniciarBarra();
            }

            // ---------- Flechas y botón de pausa ----------
            $car.find('.carrusel-ant').on('click', function () { ir(actual - 1); });
            $car.find('.carrusel-sig').on('click', function () { ir(actual + 1); });
            $car.find('.carrusel-pausa').on('click', function () {
                pausaUsuario = !pausaUsuario;
                actualizarPausa();
            });

            // ---------- Pausa: mouse y foco ----------
            $car.on('mouseenter focusin', function () { pausaMouse = true;  actualizarPausa(); });
            $car.on('mouseleave focusout', function () { pausaMouse = false; actualizarPausa(); });

            // ---------- Teclado (con el carrusel enfocado) ----------
            $car.on('keydown', function (ev) {
                if (ev.key === 'ArrowLeft')  { ir(actual - 1); ev.preventDefault(); }
                if (ev.key === 'ArrowRight') { ir(actual + 1); ev.preventDefault(); }
            });

            // ---------- Deslizar con el dedo ----------
            var inicioX = null, inicioY = null;
            $car.on('touchstart', function (ev) {
                var t = ev.originalEvent.touches[0];
                inicioX = t.clientX;
                inicioY = t.clientY;
                pausaMouse = true;
                actualizarPausa();
            });
            $car.on('touchend touchcancel', function (ev) {
                // El navegador móvil dispara un "mouseenter" falso justo después del toque;
                // esperamos un momento para que no deje el carrusel pausado para siempre.
                setTimeout(function () { pausaMouse = false; actualizarPausa(); }, 400);
                if (inicioX === null) { return; }
                var t  = ev.originalEvent.changedTouches[0];
                var dx = t.clientX - inicioX;
                var dy = t.clientY - inicioY;
                inicioX = inicioY = null;
                // Solo cuenta si el gesto fue más horizontal que vertical.
                if (Math.abs(dx) > UMBRAL && Math.abs(dx) > Math.abs(dy)) {
                    ir(dx < 0 ? actual + 1 : actual - 1);
                }
            });

            // ---------- Pausar si la pestaña no se ve ----------
            $(document).on('visibilitychange', function () {
                pestanaOculta = document.hidden;
                actualizarPausa();
            });

            // ---------- Inicio ----------
            actualizarPausa();
            ir(0);
        });
    });

})(jQuery);
