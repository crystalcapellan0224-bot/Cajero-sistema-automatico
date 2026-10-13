<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/funciones.php';

exigirSupervisor();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    aplicarAccionSupervisor(
        (int) ($_POST['id'] ?? 0),
        (string) ($_POST['accion'] ?? '')
    );

    redirigir(RUTA_USUARIOS);
}

$usuarioActualId = (int) usuarioActual()['id'];
$filas = [];

foreach (usuariosConCuenta() as $u) {
    $bloqueado = (int) $u['bloqueado'] === 1;
    $propio = (int) $u['id'] === $usuarioActualId;
    $activo = (int) ($u['activa'] ?? 0) === 1;

    $acciones = [];

    if (!$propio) {
        $acciones[] = [
            'ID'        => (string) (int) $u['id'],
            'OPERACION' => $bloqueado ? 'desbloquear' : 'bloquear',
            'TEXTO'     => $bloqueado ? 'Habilitar acceso' : 'Bloquear acceso',
            'CLASE'     => $bloqueado ? 'btn-mini' : 'btn-mini btn-mini-peligro',
        ];

        if ((int) $u['intentos_fallidos'] > 0) {
            $acciones[] = [
                'ID'        => (string) (int) $u['id'],
                'OPERACION' => 'resetear',
                'TEXTO'     => 'Reiniciar intentos',
                'CLASE'     => 'btn-mini',
            ];
        }

        if ($u['cuenta_id'] !== null) {
            $acciones[] = [
                'ID'        => (string) (int) $u['cuenta_id'],
                'OPERACION' => $activo ? 'desactivar_cuenta' : 'activar_cuenta',
                'TEXTO'     => $activo ? 'Desactivar cuenta' : 'Activar cuenta',
                'CLASE'     => $activo ? 'btn-mini btn-mini-peligro' : 'btn-mini',
            ];
        }
    }

    $filas[] = [
        'ID'              => (string) (int) $u['id'],
        'USUARIO'         => e($u['usuario']),
        'NOMBRE'          => e($u['nombre_completo']),
        'ROL'             => e(ucfirst($u['rol'])),
        'CUENTA'          => e($u['numero_cuenta'] ?? 'sin cuenta'),
        'SALDO'           => e($u['saldo'] === null ? '-' : dinero((float) $u['saldo'])),
        'INTENTOS'        => (string) (int) $u['intentos_fallidos'],
        'MOVIMIENTOS'     => (string) (int) $u['movimientos'],
        'SESIONES'        => (string) (int) $u['sesiones'],
        'ULTIMO_ACCESO'   => e($u['ultimo_acceso'] ? date('d/m/Y H:i', strtotime($u['ultimo_acceso'])) : 'nunca'),
        'BLOQUEADO'       => $bloqueado ? [[]] : [],
        'ACTIVO'          => $bloqueado ? [] : [[]],
        'SIN_CUENTA'      => $activo ? [] : [[]],
        'CUENTA_INACTIVA' => $activo ? [] : [[]],
        'ACCIONES'        => $acciones,
        'PROPIO'          => $propio ? [[]] : [],
    ];
}

$stats = estadisticasSistema();

renderizar('usuarios', datosCabecera('Usuarios') + [
    'RUTA_USUARIOS' => e(RUTA_USUARIOS),
    'TOTAL_USUARIOS' => (string) $stats['total_usuarios'],
    'BLOQUEADOS'    => (string) $stats['bloqueados'],
    'CUENTAS_ACTIVAS' => (string) $stats['cuentas_activas'],
    'SESIONES_ABIERTAS' => (string) $stats['sesiones_abiertas'],
    'SALDO_TOTAL'   => e(dinero($stats['saldo_total'])),
    'USUARIOS'      => $filas,
]);
