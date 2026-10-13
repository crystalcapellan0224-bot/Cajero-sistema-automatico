<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/instalador.php';

$aviso = null;
$sentencias = 0;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        $sentencias = instalarBaseDeDatos();
        $aviso = ['TIPO' => 'ok', 'TEXTO' => 'Instalacion completada. Se ejecutaron ' . $sentencias . ' sentencias SQL.'];
    } catch (Throwable $error) {
        $aviso = ['TIPO' => 'error', 'TEXTO' => 'Error: ' . $error->getMessage()];
    }
}

$listo = baseDeDatosLista();
$totalTablas = 0;
$totalCuentas = 0;

if ($listo) {
    $totalTablas = (int) bd()->query(
        'SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = ' . bd()->quote(DB_NOMBRE)
    )->fetchColumn();
    $totalCuentas = (int) bd()->query('SELECT COUNT(*) FROM cuentas')->fetchColumn();
}

renderizar('instalar', datosCabecera('Instalar base de datos') + [
    'RUTA'          => e('instalar.php'),
    'DB_NOMBRE'     => e(DB_NOMBRE),
    'ALERTA'        => $aviso === null ? [] : [[$aviso]],
    'TEXTO_BOTON'   => e($listo ? 'Volver a ejecutar el esquema' : 'Instalar base de datos'),
    'ESTADO'        => $listo ? [[
        'VALOR'    => 'Instalada',
        'DETALLE'  => (string) $totalTablas,
        'EXTRA'    => (string) $totalCuentas,
        'SERVIDOR' => e(DB_HOST . ':' . DB_PUERTO),
    ]] : [],
]);
