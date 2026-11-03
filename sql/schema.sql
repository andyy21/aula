-- Aula · Esquema de base de datos (MySQL 5.7+ / MariaDB 10.3+)
-- Uso: crea una base de datos vacía (cotejamiento utf8mb4_unicode_ci), SELECCIÓNALA en phpMyAdmin
-- e importa schema.sql y después seed.sql. (Aquí ya no se crea la BD para que funcione igual en hosting.)

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS detalle_venta, ventas, recursos, productos, horario, inscripciones, materias, usuarios;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE usuarios (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre        VARCHAR(100) NOT NULL,
    email         VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    rol           ENUM('cliente','admin') NOT NULL DEFAULT 'cliente',
    creado_en     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE materias (
    id       INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    nombre   VARCHAR(100) NOT NULL,
    profesor VARCHAR(100) NOT NULL,
    icono    VARCHAR(50)  NOT NULL DEFAULT 'book',   -- nombre de ícono Lucide
    imagen   VARCHAR(100) NOT NULL DEFAULT 'fondo1.png' -- archivo en assets/img/
) ENGINE=InnoDB;

CREATE TABLE inscripciones (
    usuario_id INT UNSIGNED NOT NULL,
    materia_id INT UNSIGNED NOT NULL,
    PRIMARY KEY (usuario_id, materia_id),
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id) ON DELETE CASCADE,
    FOREIGN KEY (materia_id) REFERENCES materias(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Horario semanal (dia: 1 = lunes ... 5 = viernes)
CREATE TABLE horario (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    materia_id  INT UNSIGNED NOT NULL,
    dia         TINYINT UNSIGNED NOT NULL,
    hora_inicio TIME NOT NULL,
    hora_fin    TIME NOT NULL,
    FOREIGN KEY (materia_id) REFERENCES materias(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Paquetes de recursos que se venden (stock NULL = ilimitado)
CREATE TABLE productos (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    materia_id  INT UNSIGNED NOT NULL,
    titulo      VARCHAR(150) NOT NULL,
    descripcion TEXT NULL,
    precio      DECIMAL(10,2) NOT NULL,
    stock       INT NULL DEFAULT NULL,
    ventas      INT UNSIGNED NOT NULL DEFAULT 0,
    activo      TINYINT(1) NOT NULL DEFAULT 1,
    FOREIGN KEY (materia_id) REFERENCES materias(id) ON DELETE CASCADE
) ENGINE=InnoDB;

-- Archivos / enlaces dentro de cada paquete
CREATE TABLE recursos (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    producto_id INT UNSIGNED NOT NULL,
    titulo      VARCHAR(150) NOT NULL,
    tipo        ENUM('pdf','enlace') NOT NULL DEFAULT 'pdf',
    ruta_o_url  VARCHAR(255) NOT NULL,   -- pdf: nombre de archivo en storage/recursos/ · enlace: URL
    orden       INT NOT NULL DEFAULT 0,
    FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE ventas (
    id                INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    usuario_id        INT UNSIGNED NOT NULL,
    folio             VARCHAR(30) NOT NULL UNIQUE,
    total             DECIMAL(10,2) NOT NULL,
    estado            ENUM('pendiente','pagada','fallida','cancelada') NOT NULL DEFAULT 'pendiente',
    paypal_order_id   VARCHAR(64) NULL UNIQUE,
    paypal_capture_id VARCHAR(64) NULL,
    creado_en         TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    pagado_en         DATETIME NULL,
    FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
) ENGINE=InnoDB;

CREATE TABLE detalle_venta (
    venta_id        INT UNSIGNED NOT NULL,
    producto_id     INT UNSIGNED NOT NULL,
    precio_unitario DECIMAL(10,2) NOT NULL,   -- precio histórico al momento de comprar
    cantidad        INT UNSIGNED NOT NULL DEFAULT 1,
    PRIMARY KEY (venta_id, producto_id),
    FOREIGN KEY (venta_id)    REFERENCES ventas(id) ON DELETE CASCADE,
    FOREIGN KEY (producto_id) REFERENCES productos(id)
) ENGINE=InnoDB;
