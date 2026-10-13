<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/funciones.php';

exigirSesion();

$id = (int) ($_GET['id'] ?? 0);
$movimiento = $id > 0 ? movimientoPorId($id) : null;

if (!$movimiento || !puedeVerComprobante($movimiento, usuarioActual())) {
    mensaje('El comprobante solicitado no existe o no te pertenece.', 'error');
    redirigir(RUTA_CAJERO);
}

$etiqueta = ['retiro' => 'RETIRO DE EFECTIVO', 'deposito' => 'DEPOSITO EN EFECTIVO'][$movimiento['tipo']]
    ?? 'COMPROBANTE DE MOVIMIENTO';

$tituloPagina = 'Comprobante ' . ($movimiento['folio'] ?? 'sin folio');

renderizar('comprobante', datosCabecera($tituloPagina) + [
    'FOLIO'        => e($movimiento['folio'] ?? 'SIN FOLIO'),
    'ETIQUETA'     => e($etiqueta),
    'FECHA'        => e(date('d/m/Y', strtotime($movimiento['creado_en']))),
    'HORA'         => e(date('H:i:s', strtotime($movimiento['creado_en']))),
    'CAJERO'       => e($movimiento['cajero']),
    'CLIENTE'      => e($movimiento['nombre_completo']),
    'LOGIN_CLIENTE' => e($movimiento['usuario']),
    'CUENTA'       => e($movimiento['numero_cuenta']),
    'MONTO'        => e(dinero((float) $movimiento['monto'])),
    'SALDO'        => e(dinero((float) $movimiento['saldo_resultante'])),
    'DESCRIPCION'  => e($movimiento['descripcion']),
    'REFERENCIA'   => e($movimiento['referencia'] ?? ''),
    'HAY_REFERENCIA' => $movimiento['referencia'] ? [[]] : [],
    'ES_INGRESO'   => $movimiento['tipo'] === 'deposito' ? [[]] : [],
    'RUTA_MOVIMIENTOS' => e(RUTA_MOVIMIENTOS),
    'SCRIPT_COMPROBANTE' => e('assets/comprobante.js'),
]);