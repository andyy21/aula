        </main>
    </div>
    <script>
        lucide.createIcons();
        // Reloj en vivo (hora del navegador)
        (function () {
            var el = document.getElementById('reloj');
            if (!el) return;
            function tick() {
                var d = new Date();
                el.textContent = String(d.getHours()).padStart(2, '0') + ':' + String(d.getMinutes()).padStart(2, '0');
            }
            setInterval(tick, 15000);
        })();
    </script>
</body>

</html>
