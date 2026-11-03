-- Aula · Limpieza de datos de prueba ANTES de la entrega final.
-- Importar en phpMyAdmin con la base de datos `aula` seleccionada.
-- OJO: borra TODAS las ventas (y sus recibos). Después de esto nadie conserva acceso a paquetes comprados.

-- 1) Borra todas las ventas (detalle_venta se borra solo por ON DELETE CASCADE)
DELETE FROM ventas;
ALTER TABLE ventas AUTO_INCREMENT = 1;

-- 2) Contadores y stock como en seed.sql
UPDATE productos SET ventas = 0;
UPDATE productos SET stock = NULL WHERE id IN (1, 2, 3, 4, 6);
UPDATE productos SET stock = 50   WHERE id = 5;

-- 3) OPCIONAL: quita el signo "--" de las líneas que necesites.
-- Usuarios que creaste tú durante las pruebas (los del seed son los ids 1 al 6):
-- DELETE FROM usuarios WHERE id > 6;
-- Productos que creaste tú en el panel (los del seed son los ids 1 al 6; sus recursos se borran solos).
-- Los PDF que subiste quedan en storage/recursos/: bórralos a mano.
-- DELETE FROM productos WHERE id > 6;

-- 4) Revisión rápida (debe salir ventas = 0 y stock 50 solo en el producto 5)
SELECT id, titulo, stock, ventas, activo FROM productos ORDER BY id;
SELECT COUNT(*) AS ventas_restantes FROM ventas;
