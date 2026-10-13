<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/funciones.php';

exigirSesion();

$usuario = usuarioActual();
$cuentaId = $usuario['cuenta_id'];

$stmt = bd()->prepare(
    'SELECT id, tipo, monto, saldo_resultante, descripcion, cajero, folio, referencia, creado_en
     FROM movimientos
     WHERE cuenta_id = ?
     ORDER BY id DESC'
);
$stmt->execute([$cuentaId]);
$movimientos = $stmt->fetchAll();

$totales = ['retiro' => 0.0, 'deposito' => 0.0, 'cierre_sesion' => 0.0];

foreach ($movimientos as $m) {
    $totales[$m['tipo']] = ($totales[$m['tipo']] ?? 0.0) + (float) $m['monto'];
}

$filas = [];

foreach (filasMovimientos($movimientos) as $i => $fila) {
    $filas[] = $fila + ['NUMERO' => (string) ($i + 1)];
}

renderizar('movimientos', datosCabecera('Movimientos') + [
    'NUMERO_CUENTA'     => e($usuario['numero_cuenta']),
    'SALDO'             => e(dinero(saldoActual($cuentaId))),
    'TOTAL_RETIRADO'    => e(dinero($totales['retiro'])),
    'TOTAL_DEPOSITADO'  => e(dinero($totales['deposito'])),
    'TOTAL_OPERACIONES' => (string) count($movimientos),
    'VACIO'             => $movimientos ? [] : [[]],
    'TABLA'             => [['FILAS' => $filas]],
]);
