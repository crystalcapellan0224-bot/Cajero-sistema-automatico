<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/funciones.php';

exigirSesion();

$usuario = usuarioActual();
$saldo = saldoActual($usuario['cuenta_id']);
$cuentaActiva = (int) (cuentaDe((int) $usuario['id'])['activa'] ?? 0) === 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirCuentaActiva();

    $monto = trim((string) ($_POST['monto'] ?? ''));

    if (!is_numeric($monto) || (float) $monto <= 0) {
        mensaje('El monto debe ser un numero mayor a cero.', 'error');
        redirigir('retiro.php');
    }

    $monto = round((float) $monto, 2);

    if ($monto > RETIRO_MAXIMO) {
        mensaje('El retiro maximo por operacion es ' . dinero(RETIRO_MAXIMO) . '.', 'error');
        redirigir('retiro.php');
    }

    if ($monto > $saldo) {
        mensaje('Saldo insuficiente. Tienes ' . dinero($saldo) . ' disponibles.', 'error');
        redirigir('retiro.php');
    }

    try {
        $resultado = aplicarOperacion(
            $usuario['cuenta_id'],
            $usuario['id'],
            'retiro',
            $monto,
            'Retiro desde el cajero automatico',
            'CAJERO-01'
        );

        mensaje('Retiro de ' . dinero($monto) . ' realizado. Comprobante ' . $resultado['folio'] . '.');
        redirigir(RUTA_COMPROBANTE . '?id=' . $resultado['movimiento_id']);
    } catch (Throwable $error) {
        mensaje('No se pudo completar el retiro. Intenta de nuevo.', 'error');
        redirigir('retiro.php');
    }
}

renderizar('operacion', datosCabecera('Retirar dinero') + [
    'TITULO_PAGINA'   => 'Retirar dinero',
    'TEXTO_SALDO'     => 'Saldo disponible',
    'SALDO'           => e(dinero($saldo)),
    'ACCION'          => e('retiro.php'),
    'ETIQUETA_MONTO'  => 'Monto a retirar',
    'MONTO_MAXIMO'    => e((string) min(RETIRO_MAXIMO, $saldo)),
    'TEXTO_BOTON'     => 'Confirmar retiro',
    'ATAJOS'          => atajosRapidos([200, 500, 1000, 2000]),
    'SCRIPT'          => e('assets/atajos.js'),
    'CUENTA_INACTIVA' => $cuentaActiva ? [] : [[]],
]);
