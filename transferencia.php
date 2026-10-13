<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/funciones.php';

exigirSesion();

$usuario = usuarioActual();
$saldo = saldoActual($usuario['cuenta_id']);
$cuentaActiva = (int) (cuentaDe((int) $usuario['id'])['activa'] ?? 0) === 1;
$destinoIngresado = trim((string) ($_POST['destino'] ?? ''));
$montoIngresado = trim((string) ($_POST['monto'] ?? ''));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirCuentaActiva();

    if (!is_numeric($montoIngresado) || (float) $montoIngresado <= 0) {
        mensaje('El monto debe ser un numero mayor a cero.', 'error');
        redirigir(RUTA_TRANSFERENCIA);
    }

    $monto = round((float) $montoIngresado, 2);

    if ($monto > TRANSFERENCIA_MAXIMO) {
        mensaje('El maximo por transferencia es ' . dinero(TRANSFERENCIA_MAXIMO) . '.', 'error');
        redirigir(RUTA_TRANSFERENCIA);
    }

    if ($monto > $saldo) {
        mensaje('Saldo insuficiente. Tienes ' . dinero($saldo) . ' disponibles.', 'error');
        redirigir(RUTA_TRANSFERENCIA);
    }

    $destino = buscarCuentaDestino($destinoIngresado, (int) $usuario['id']);

    if ($destino === null) {
        mensaje(
            'No se encontro una cuenta activa con ese usuario o numero de cuenta, '
            . 'o te equivocaste y es tu propia cuenta.',
            'error'
        );
        redirigir(RUTA_TRANSFERENCIA);
    }

    try {
        $resultado = aplicarTransferencia($usuario, $destino, $monto, 'CAJERO-01');

        mensaje(
            'Transferencia de ' . dinero($monto) . ' enviada a ' . $destino['nombre_completo']
            . '. Referencia ' . $resultado['referencia'] . '.'
        );

        redirigir(RUTA_COMPROBANTE . '?id=' . $resultado['movimiento_id']);
    } catch (DomainException $error) {
        mensaje($error->getMessage(), 'error');
        redirigir(RUTA_TRANSFERENCIA);
    } catch (Throwable $error) {
        mensaje('No se pudo completar la transferencia. Intenta de nuevo.', 'error');
        redirigir(RUTA_TRANSFERENCIA);
    }
}

$destino = $destinoIngresado === '' ? null : buscarCuentaDestino($destinoIngresado, (int) $usuario['id']);

renderizar('transferencia', datosCabecera('Transferencia') + [
    'RUTA_TRANSFERENCIA' => e(RUTA_TRANSFERENCIA),
    'SALDO'              => e(dinero($saldo)),
    'MONTO_MAXIMO'       => e((string) min(TRANSFERENCIA_MAXIMO, $saldo)),
    'DESTINO_VALOR'      => e($destinoIngresado),
    'MONTO_VALOR'        => e($montoIngresado),
    'DESTINO_ENCONTRADO' => $destino === null ? [] : [[
        'NOMBRE'   => e($destino['nombre_completo']),
        'USUARIO'  => e($destino['usuario']),
        'CUENTA'   => e($destino['numero_cuenta']),
        'SALDO'    => e(dinero((float) $destino['saldo'])),
    ]],
    'DESTINO_INVALIDO'   => $destinoIngresado !== '' && $destino === null ? [[]] : [],
    'ATAJOS'             => atajosRapidos([100, 500, 1000, 2500]),
    'SCRIPT'             => e('assets/atajos.js'),
    'CUENTA_INACTIVA'    => $cuentaActiva ? [] : [[]],
]);