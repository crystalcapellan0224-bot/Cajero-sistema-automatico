<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/funciones.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirigir(RUTA_CAJERO);
}

$usuario = usuarioActual();
$sesionId = sesionCajeroId();

if ($usuario && $sesionId) {
    $resumen = resumenDeSesion($sesionId);

    if ($resumen && $resumen['estado'] === 'abierta') {
        $saldoFinal = saldoActual((int) $usuario['cuenta_id']);

        $pdo = bd();
        $pdo->beginTransaction();

        try {
            $pdo->prepare(
                'UPDATE sesiones_cajero
                 SET fecha_cierre = NOW(),
                     saldo_final = ?,
                     estado = ?
                 WHERE id = ?'
            )->execute([$saldoFinal, 'cerrada', $sesionId]);

            $pdo->prepare(
                'INSERT INTO movimientos (cuenta_id, usuario_id, tipo, monto, saldo_resultante, descripcion, cajero)
                 VALUES (?, ?, ?, ?, ?, ?, ?)'
            )->execute([
                $usuario['cuenta_id'],
                $usuario['id'],
                'cierre_sesion',
                0,
                $saldoFinal,
                'Cierre de sesion: ' . (int) $resumen['operaciones'] . ' operacion(es), '
                    . 'retirado ' . dinero((float) $resumen['retirado'])
                    . ', depositado ' . dinero((float) $resumen['depositado']),
                'SISTEMA',
            ]);

            $pdo->commit();
        } catch (Throwable $error) {
            $pdo->rollBack();
        }
    }
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}

session_destroy();

redirigir(RUTA_LOGIN . '?salida=1');
