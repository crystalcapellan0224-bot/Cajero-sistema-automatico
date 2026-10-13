<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/funciones.php';

exigirSesion();

$usuario = usuarioActual();
$cuenta = cuentaDe($usuario['cuenta_id']);
$movimientos = ultimosMovimientos($usuario['cuenta_id'], 5);
$resumen = resumenDeSesion(sesionCajeroId());
$ultimo = ultimoComprobante($usuario['cuenta_id']);

renderizar('cajero', datosCabecera('Cajero') + [
    'SALDO'              => e(dinero((float) $cuenta['saldo'])),
    'NUMERO_CUENTA'      => e($cuenta['numero_cuenta']),
    'ES_CLIENTE'         => $usuario['rol'] === 'cliente' ? [[]] : [],
    'CUENTA_INACTIVA'   => (int) ($cuenta['activa'] ?? 0) === 1 ? [] : [[]],
    'RUTA_TRANSFERENCIA' => e(RUTA_TRANSFERENCIA),
    'TIENE_COMPROBANTE'  => $ultimo === null ? [] : [[]],
    'FOLIO_ULTIMO'       => e((string) ($ultimo['folio'] ?? '')),
    'URL_COMPROBANTE'    => e(RUTA_COMPROBANTE . '?id=' . (int) ($ultimo['id'] ?? 0)),
    'SESION_INICIO'      => e(date('d/m/Y H:i', strtotime($resumen['fecha_inicio']))),
    'SESION_OPERACIONES' => (string) (int) $resumen['operaciones'],
    'SESION_RETIRADO'    => e(dinero((float) $resumen['retirado'])),
    'SESION_DEPOSITADO'  => e(dinero((float) $resumen['depositado'])),
    'RETIRO_MAXIMO'      => e(dinero(RETIRO_MAXIMO)),
    'DEPOSITO_MAXIMO'    => e(dinero(DEPOSITO_MAXIMO)),
    'RUTA_RETIRO'        => e('retiro.php'),
    'RUTA_DEPOSITO'      => e('deposito.php'),
    'RUTA_MOVIMIENTOS'   => e(RUTA_MOVIMIENTOS),
    'VACIO'              => $movimientos ? [] : [[]],
    'MOVIMIENTOS'        => [[
        'VACIO' => $movimientos ? [] : [[]],
        'FILAS' => filasMovimientos($movimientos),
    ]],
]);
