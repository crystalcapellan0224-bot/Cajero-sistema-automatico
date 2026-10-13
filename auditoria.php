<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/funciones.php';

exigirSupervisor();

$stmt = bd()->prepare(
    'SELECT m.tipo, m.descripcion, m.monto, m.saldo_resultante, m.cajero, m.creado_en,
            u.usuario, c.numero_cuenta
     FROM movimientos m
     JOIN usuarios u ON u.id = m.usuario_id
     JOIN cuentas c ON c.id = m.cuenta_id
     WHERE m.tipo IN (?, ?)
     ORDER BY m.id DESC
     LIMIT ?'
);
$stmt->bindValue(1, 'retiro', PDO::PARAM_STR);
$stmt->bindValue(2, 'deposito', PDO::PARAM_STR);
$stmt->bindValue(3, 200, PDO::PARAM_INT);
$stmt->execute();

$aportes = $stmt->fetchAll();

if (isset($_GET['tipo'], $_GET['cuenta']) && $_GET['tipo'] !== '' && $_GET['cuenta'] !== '') {
    $stmt = bd()->prepare(
        'SELECT m.tipo, m.monto, m.saldo_resultante, m.descripcion, m.cajero, m.creado_en,
                u.usuario, c.numero_cuenta
         FROM movimientos m
         JOIN usuarios u ON u.id = m.usuario_id
         JOIN cuentas c ON c.id = m.cuenta_id
         WHERE m.cuenta_id = ? AND m.tipo = ?
         ORDER BY m.id DESC'
    );
    $stmt->execute([(int) $_GET['cuenta'], (string) $_GET['tipo']]);
    $aportes = $stmt->fetchAll();
}

$filas = [];
$totales = ['retiro' => 0.0, 'deposito' => 0.0];

foreach ($aportes as $m) {
    $totales[$m['tipo']] += (float) $m['monto'];

    $filas[] = [
        'TIPO'        => e($m['tipo']),
        'TEXTO'       => e(ucfirst(str_replace('_', ' ', $m['tipo']))),
        'USUARIO'     => e($m['usuario']),
        'CUENTA'      => e($m['numero_cuenta']),
        'DESCRIPCION' => e($m['descripcion']),
        'CAJERO'      => e($m['cajero']),
        'MONTO'       => e(($m['tipo'] === 'retiro' ? '-' : '+') . dinero((float) $m['monto'])),
        'CLASE_MONTO' => $m['tipo'] === 'retiro' ? 'negativo' : 'positivo',
        'SALDO'       => e(dinero((float) $m['saldo_resultante'])),
        'FECHA'       => e(date('d/m/Y H:i', strtotime($m['creado_en']))),
    ];
}

$cuentas = bd()->query(
    'SELECT id, numero_cuenta FROM cuentas ORDER BY numero_cuenta'
)->fetchAll();

$cuentaSeleccionada = (string) ($_GET['cuenta'] ?? '');
$tipoSeleccionado = (string) ($_GET['tipo'] ?? '');

$listaCuentas = [];

foreach ($cuentas as $c) {
    $listaCuentas[] = [
        'ID'          => (string) (int) $c['id'],
        'NUMERO'      => e($c['numero_cuenta']),
        'SELECCIONADO' => (string) $c['id'] === $cuentaSeleccionada ? [[]] : [],
    ];
}

$consultas = [];

foreach (ultimasConsultasChat(12) as $c) {
    $consultas[] = [
        'USUARIO'   => e($c['usuario'] ?? 'visitante'),
        'PREGUNTA'  => e($c['pregunta']),
        'CATEGORIA' => e($c['categoria']),
        'CLASE'     => (int) $c['atendio'] === 1 ? 'tag-deposito' : 'tag-cierre_sesion',
        'ESTADO'    => (int) $c['atendio'] === 1 ? 'Atendida' : 'Sin clasificar',
        'FECHA'     => e(date('d/m/Y H:i', strtotime($c['creado_en']))),
    ];
}

renderizar('auditoria', datosCabecera('Auditoria') + [
    'RUTA_AUDITORIA'    => e(RUTA_AUDITORIA),
    'CUENTAS'          => $listaCuentas,
    'TIPO_RET'         => $tipoSeleccionado === 'retiro' ? [[]] : [],
    'TIPO_DEP'         => $tipoSeleccionado === 'deposito' ? [[]] : [],
    'SIN_TIPO'         => $tipoSeleccionado === '' ? [[]] : [],
    'TOTAL_RETIRADO'   => e(dinero($totales['retiro'])),
    'TOTAL_DEPOSITADO' => e(dinero($totales['deposito'])),
    'TOTAL_MOVIMIENTOS' => (string) count($filas),
    'VACIO'            => $filas ? [] : [[]],
    'CON_MOVIMIENTOS'  => $filas ? [['FILAS' => $filas]] : [],
    'HAY_CONSULTAS'    => $consultas ? [[]] : [],
    'CONSULTAS'        => $consultas,
]);
