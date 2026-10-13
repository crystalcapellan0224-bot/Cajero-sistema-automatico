<?php

declare(strict_types=1);

const APP_NOMBRE    = 'CajaRapida';
const APP_LEMA      = 'Sistema de cajero automatico con persistencia en MySQL';
const APP_VERSION   = '1.0.0';

const CARPETA_VISTAS = __DIR__ . '/vistas';

const DB_HOST       = '127.0.0.1';
const DB_PUERTO     = 3306;
const DB_NOMBRE     = 'caja_rapida';
const DB_USUARIO    = 'root';
const DB_CLAVE      = '';

const MAX_INTENTOS_LOGIN = 3;
const RETIRO_MAXIMO      = 5000.00;
const DEPOSITO_MAXIMO    = 10000.00;
const TRANSFERENCIA_MAXIMO = 8000.00;

const RUTA_LOGIN      = 'login.php';
const RUTA_CAJERO     = 'cajero.php';
const RUTA_MOVIMIENTOS = 'movimientos.php';
const RUTA_USUARIOS   = 'usuarios.php';
const RUTA_SESIONES    = 'sesiones.php';
const RUTA_AUDITORIA   = 'auditoria.php';
const RUTA_CHAT        = 'chat.php';
const RUTA_TRANSFERENCIA = 'transferencia.php';
const RUTA_COMPROBANTE   = 'comprobante.php';
const RUTA_CERRAR     = 'cerrar-sesion.php';

date_default_timezone_set('America/Mexico_City');
