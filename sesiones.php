<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/funciones.php';

exigirSupervisor();

$filas = [];

foreach (sesionesRegistradas() as $s) {
    $filas[] = [
        'ID'          => (string) (int) $s['id'],
        'USUARIO'     => e($s['usuario']),
        'CUENTA'      => e($s['numero_cuenta']),
        'INICIO'      => e(date('d/m/Y H:i', strtotime($s['fecha_inicio']))),
        'CIERRE'      => e($s['fecha_cierre'] ? date('d/m/Y H:i', strtotime($s['fecha_cierre'])) : 'sin cerrar'),
        'SALDO_INICIAL' => e(dinero((float) $s['saldo_inicial'])),
        'SALDO_FINAL' => e($s['saldo_final'] === null ? '-' : dinero((float) $s['saldo_final'])),
        'OPERACIONES' => (string) (int) $s['operaciones'],
        'RETIRADO'    => e(dinero((float) $s['retirado'])),
        'DEPOSITADO'  => e(dinero((float) $s['depositado'])),
        'ESTADO'      => e(ucfirst($s['estado'])),
        'CLASE_ESTADO' => $s['estado'] === 'abierta' ? 'tag-cierre_sesion' : 'tag-deposito',
    ];
}

renderizar('sesiones', datosCabecera('Sesiones') + [
    'RUTA_SESIONES' => e(RUTA_SESIONES),
    'TOTAL'     => (string) count($filas),
    'SESIONES'  => $filas,
]);
