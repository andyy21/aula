-- Aula · Datos de ejemplo (importar DESPUÉS de schema.sql)
-- Usuarios de prueba: la contraseña de TODOS es:  password
--   admin@aula.test (admin) · ana@aula.test (cliente demo) · y 4 compañeros más
-- Cambia estas contraseñas antes de publicar el sitio (ver README).
-- Importar con la base de datos seleccionada (igual que schema.sql).

INSERT INTO usuarios (id, nombre, email, password_hash, rol) VALUES
(1, 'Administrador',  'admin@aula.test',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin'),
(2, 'Ana Navarro',    'ana@aula.test',    '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cliente'),
(3, 'Alberto Cruz',   'alberto@aula.test','$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cliente'),
(4, 'Laura Mendoza',  'laura@aula.test',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cliente'),
(5, 'Jorge Pérez',    'jorge@aula.test',  '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cliente'),
(6, 'Rosa Salazar',   'rosa@aula.test',   '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'cliente');

INSERT INTO materias (id, nombre, profesor, icono, imagen) VALUES
(1, 'Cálculo Vectorial',   'Prof. Ramírez',   'function-square', 'fondo1.png'),
(2, 'Estructura de Datos', 'Prof. Hernández', 'network',         'fondo2.png'),
(3, 'Física Moderna',      'Prof. López',     'atom',            'fondo3.png'),
(4, 'Base de Datos',       'Prof. Torres',    'database',        'fondo4.png'),
(5, 'Contabilidad',        'Prof. Méndez',    'calculator',      'fondo5.png');

-- Ana (demo) cursa las 5 materias; los demás, algunas
INSERT INTO inscripciones (usuario_id, materia_id) VALUES
(2,1),(2,2),(2,3),(2,4),(2,5),
(3,1),(3,2),(3,4),
(4,1),(4,3),(4,5),
(5,2),(5,4),
(6,1),(6,2),(6,3),(6,4),(6,5);

-- Horario (1 = lunes ... 5 = viernes), tomado del diseño original
INSERT INTO horario (materia_id, dia, hora_inicio, hora_fin) VALUES
-- 08:00 - 09:00 Cálculo Vectorial (L, M, X, V)
(1,1,'08:00','09:00'),(1,2,'08:00','09:00'),(1,3,'08:00','09:00'),(1,5,'08:00','09:00'),
-- 10:00 - 11:00 Física Moderna (M, X, J, V)
(3,2,'10:00','11:00'),(3,3,'10:00','11:00'),(3,4,'10:00','11:00'),(3,5,'10:00','11:00'),
-- 12:00 - 13:00 Estructura de Datos (L, M, V) y Base de Datos (X)
(2,1,'12:00','13:00'),(2,2,'12:00','13:00'),(2,5,'12:00','13:00'),(4,3,'12:00','13:00'),
-- 14:00 - 15:00 Contabilidad (L, M, J)
(5,1,'14:00','15:00'),(5,2,'14:00','15:00'),(5,4,'14:00','15:00');

-- Paquetes de ejemplo (contenido propio del equipo; los precios son de prueba)
INSERT INTO productos (id, materia_id, titulo, descripcion, precio, stock, ventas, activo) VALUES
(1, 1, 'Cálculo Vectorial · Unidad 1',          'Resumen y guía de vectores en el espacio.',            49.00,  NULL, 0, 1),
(2, 1, 'Cálculo Vectorial · Integrales múltiples','Ejercicios resueltos paso a paso.',                  79.00,  NULL, 0, 1),
(3, 2, 'Estructura de Datos · Árboles binarios', 'Presentación y ejercicios de árboles.',               59.00,  NULL, 0, 1),
(4, 3, 'Física Moderna · Relatividad',           'Introducción a la relatividad con ejemplos.',         59.00,  NULL, 0, 1),
(5, 4, 'Base de Datos · Guía SQL',               'Consultas, joins y normalización.',                   69.00,  50,   0, 1),
(6, 5, 'Contabilidad · Estados financieros',     'Guía de balance general y estado de resultados.',     39.00,  NULL, 0, 1);

-- Recursos dentro de cada paquete. Los PDF son nombres de archivo que el admin
-- debe subir a storage/recursos/ (Fase 6). Los enlaces son URLs externas.
INSERT INTO recursos (producto_id, titulo, tipo, ruta_o_url, orden) VALUES
(1, 'Unidad 1 - Vectores en el espacio',      'pdf', 'calculo-unidad1.pdf',      1),
(1, 'Formulario de vectores',                 'pdf', 'calculo-formulario.pdf',   2),
(2, 'Ejercicios de integrales múltiples',     'pdf', 'calculo-integrales.pdf',   1),
(3, 'Árboles binarios - presentación',        'pdf', 'estructuras-arboles.pdf',  1),
(3, 'Visualizador de árboles (enlace)',       'enlace', 'https://visualgo.net/es/bst', 2),
(4, 'Introducción a la relatividad',          'pdf', 'fisica-relatividad.pdf',   1),
(5, 'Guía rápida de SQL',                     'pdf', 'bd-guia-sql.pdf',          1),
(6, 'Estados financieros - guía',             'pdf', 'conta-estados.pdf',        1);
