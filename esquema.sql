-- =====================================================================
-- CajaRapida - Esquema de base de datos
-- Motor: MySQL 8.0+ / MariaDB 10.5+
-- Ejecutar con: mysql -u root < bd/esquema.sql
-- o desde el navegador en: http://localhost:8000/instalar.php
-- =====================================================================

CREATE DATABASE IF NOT EXISTS caja_rapida
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE caja_rapida;

CREATE TABLE IF NOT EXISTS usuarios (
  id                INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario           VARCHAR(50)  NOT NULL,
  clave             VARCHAR(255) NOT NULL,
  nombre_completo   VARCHAR(120) NOT NULL,
  rol               ENUM('cliente','supervisor') NOT NULL DEFAULT 'cliente',
  intentos_fallidos TINYINT UNSIGNED NOT NULL DEFAULT 0,
  bloqueado         TINYINT(1)   NOT NULL DEFAULT 0,
  ultimo_acceso     DATETIME     NULL,
  creado_en         TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_usuarios_usuario (usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cuentas (
  id           INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  usuario_id   INT UNSIGNED   NOT NULL,
  numero_cuenta CHAR(12)       NOT NULL,
  saldo        DECIMAL(12,2)  NOT NULL DEFAULT 0.00,
  activa       TINYINT(1)     NOT NULL DEFAULT 1,
  creado_en    TIMESTAMP      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_cuentas_numero (numero_cuenta),
  KEY idx_cuentas_usuario (usuario_id),
  CONSTRAINT fk_cuentas_usuario FOREIGN KEY (usuario_id)
    REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS movimientos (
  id               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  cuenta_id        INT UNSIGNED    NOT NULL,
  usuario_id       INT UNSIGNED    NOT NULL,
  tipo             ENUM('retiro','deposito','cierre_sesion') NOT NULL,
  monto            DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  saldo_resultante DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  descripcion      VARCHAR(160)    NOT NULL DEFAULT '',
  folio            VARCHAR(20)     NULL,
  referencia       VARCHAR(30)     NULL,
  cajero           VARCHAR(60)     NOT NULL DEFAULT '',
  creado_en        DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_movimientos_folio (folio),
  KEY idx_movimientos_cuenta (cuenta_id, id),
  KEY idx_movimientos_usuario (usuario_id),
  KEY idx_movimientos_referencia (referencia),
  CONSTRAINT fk_movimientos_cuenta FOREIGN KEY (cuenta_id)
    REFERENCES cuentas (id) ON DELETE CASCADE,
  CONSTRAINT fk_movimientos_usuario FOREIGN KEY (usuario_id)
    REFERENCES usuarios (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE movimientos
  ADD COLUMN folio VARCHAR(20) NULL,
  ADD COLUMN referencia VARCHAR(30) NULL;

ALTER TABLE movimientos
  ADD UNIQUE KEY uq_movimientos_folio (folio);

ALTER TABLE movimientos
  ADD KEY idx_movimientos_referencia (referencia);

CREATE TABLE IF NOT EXISTS sesiones_cajero (
  id             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id     INT UNSIGNED    NOT NULL,
  cuenta_id      INT UNSIGNED    NOT NULL,
  fecha_inicio   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  fecha_cierre   DATETIME        NULL,
  saldo_inicial  DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  saldo_final    DECIMAL(12,2)   NULL,
  operaciones    INT UNSIGNED    NOT NULL DEFAULT 0,
  retirado       DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  depositado     DECIMAL(12,2)   NOT NULL DEFAULT 0.00,
  estado         ENUM('abierta','cerrada') NOT NULL DEFAULT 'abierta',
  PRIMARY KEY (id),
  KEY idx_sesiones_usuario (usuario_id),
  KEY idx_sesiones_cuenta (cuenta_id),
  CONSTRAINT fk_sesiones_usuario FOREIGN KEY (usuario_id)
    REFERENCES usuarios (id) ON DELETE CASCADE,
  CONSTRAINT fk_sesiones_cuenta FOREIGN KEY (cuenta_id)
    REFERENCES cuentas (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


CREATE TABLE IF NOT EXISTS chat_mensajes (
  id          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario_id  INT UNSIGNED    NULL,
  pregunta    VARCHAR(255)    NOT NULL,
  respuesta   TEXT            NOT NULL,
  categoria   VARCHAR(40)     NOT NULL DEFAULT 'sin_clasificar',
  atendio     TINYINT(1)      NOT NULL DEFAULT 1,
  creado_en   DATETIME        NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_chat_usuario (usuario_id),
  KEY idx_chat_categoria (categoria),
  CONSTRAINT fk_chat_usuario FOREIGN KEY (usuario_id)
    REFERENCES usuarios (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO usuarios (usuario, clave, nombre_completo, rol) VALUES
  ('cliente',  '$2y$10$Nz6c5uWqXoqU4pnngAFOG.xG/V9Wk.52bBSKOn3BG30ii93ZOWF3q', 'Ana Torres Rincon', 'cliente'),
  ('juan',     '$2y$10$Nz6c5uWqXoqU4pnngAFOG.xG/V9Wk.52bBSKOn3BG30ii93ZOWF3q', 'Juan Perez Morales', 'cliente'),
  ('supervisor','$2y$10$3.fcHI5en4xF0vsgtKY.yu/2NVF6f6DicRmtM7vMNESiZNAqoYgCS', 'Laura Mendoza Cruz', 'supervisor')
ON DUPLICATE KEY UPDATE nombre_completo = VALUES(nombre_completo);

INSERT INTO cuentas (usuario_id, numero_cuenta, saldo)
SELECT id, 'CR00000001', 1000.00 FROM usuarios WHERE usuario = 'cliente'
ON DUPLICATE KEY UPDATE numero_cuenta = 'CR00000001';

INSERT INTO cuentas (usuario_id, numero_cuenta, saldo)
SELECT id, 'CR00000002', 2500.00 FROM usuarios WHERE usuario = 'juan'
ON DUPLICATE KEY UPDATE numero_cuenta = 'CR00000002';

INSERT INTO cuentas (usuario_id, numero_cuenta, saldo)
SELECT id, 'CR00000003', 0.00 FROM usuarios WHERE usuario = 'supervisor'
ON DUPLICATE KEY UPDATE numero_cuenta = 'CR00000003';
