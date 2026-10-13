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
        redirigir('deposito.php');
    }

    $monto = round((float) $monto, 2);

    if ($monto > DEPOSITO_MAXIMO) {
        mensaje('El deposito maximo por operacion es ' . dinero(DEPOSITO_MAXIMO) . '.', 'error');
        redirigir('deposito.php');
    }

    try {
        $resultado = aplicarOperacion(
            $usuario['cuenta_id'],
            $usuario['id'],
            'deposito',
            $monto,
            'Deposito en efectivo',
            'VENTANILLA-01'
        );

        mensaje('Deposito de ' . dinero($monto) . ' realizado. Comprobante ' . $resultado['folio'] . '.');
        redirigir(RUTA_COMPROBANTE . '?id=' . $resultado['movimiento_id']);
    } catch (Throwable $error) {
        mensaje('No se pudo completar el deposito. Intenta de nuevo.', 'error');
        redirigir('deposito.php');
    }
}

renderizar('operacion', datosCabecera('Depositar dinero') + [
    'TITULO_PAGINA'   => 'Depositar dinero',
    'TEXTO_SALDO'     => 'Saldo actual',
    'SALDO'           => e(dinero($saldo)),
    'ACCION'          => e('deposito.php'),
    'ETIQUETA_MONTO'  => 'Monto a depositar',
    'MONTO_MAXIMO'    => e((string) DEPOSITO_MAXIMO),
    'TEXTO_BOTON'     => 'Confirmar deposito',
    'ATAJOS'          => atajosRapidos([200, 500, 1000, 2000]),
    'SCRIPT'          => e('assets/atajos.js'),
    'CUENTA_INACTIVA' => $cuentaActiva ? [] : [[]],
]);
