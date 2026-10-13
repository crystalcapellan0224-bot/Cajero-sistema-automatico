<?php

declare(strict_types=1);

require_once __DIR__ . '/conexion.php';
require_once __DIR__ . '/plantilla.php';
require_once __DIR__ . '/chatbot.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function e(mixed $valor): string
{
    return htmlspecialchars((string) $valor, ENT_QUOTES, 'UTF-8');
}

function dinero(float $n): string
{
    return '$' . number_format($n, 2, '.', ',');
}

function redirigir(string $ruta): never
{
    header('Location: ' . $ruta);
    exit;
}

function mensaje(string $texto, string $tipo = 'ok'): void
{
    $_SESSION['mensaje'] = ['texto' => $texto, 'tipo' => $tipo];
}

function tomarMensaje(): ?array
{
    $m = $_SESSION['mensaje'] ?? null;
    unset($_SESSION['mensaje']);
    return $m;
}

function usuarioActual(): ?array
{
    return $_SESSION['usuario'] ?? null;
}

function sesionCajeroId(): ?int
{
    return $_SESSION['sesion_cajero_id'] ?? null;
}

function haySesionActiva(): bool
{
    return usuarioActual() !== null;
}

function exigirSesion(): void
{
    if (!haySesionActiva()) {
        mensaje('Debes iniciar sesion para continuar.', 'error');
        redirigir(RUTA_LOGIN);
    }
}

function esSupervisor(): bool
{
    $usuario = usuarioActual();
    return $usuario !== null && $usuario['rol'] === 'supervisor';
}

function exigirSupervisor(): void
{
    exigirSesion();

    if (!esSupervisor()) {
        mensaje('No tienes permisos para acceder a esa seccion.', 'error');
        redirigir(RUTA_CAJERO);
    }
}

function cuentaDe(int $usuarioId): ?array
{
    $stmt = bd()->prepare(
        'SELECT c.* FROM cuentas c WHERE c.usuario_id = ? LIMIT 1'
    );
    $stmt->execute([$usuarioId]);
    $cuenta = $stmt->fetch();

    return $cuenta ?: null;
}

function exigirCuentaActiva(): void
{
    $usuario = usuarioActual();

    if ($usuario === null) {
        redirigir(RUTA_CERRAR);
    }

    $stmt = bd()->prepare('SELECT activa FROM cuentas WHERE id = ?');
    $stmt->execute([(int) $usuario['cuenta_id']]);

    if ((int) $stmt->fetchColumn() !== 1) {
        mensaje('Tu cuenta esta inactiva. El supervisor la restringio, no puedes operar hasta que la habilite.', 'error');
        redirigir(RUTA_CAJERO);
    }
}

function saldoActual(int $cuentaId): float
{
    $stmt = bd()->prepare('SELECT saldo FROM cuentas WHERE id = ?');
    $stmt->execute([$cuentaId]);
    return (float) $stmt->fetchColumn();
}

function aplicarOperacion(
    int $cuentaId,
    int $usuarioId,
    string $tipo,
    float $monto,
    string $descripcion,
    string $cajero
): array {
    $pdo = bd();
    $pdo->beginTransaction();

    try {
        $saldo = bloquearSaldo($pdo, $cuentaId);
        $nuevoSaldo = $tipo === 'retiro' ? $saldo - $monto : $saldo + $monto;

        if ($nuevoSaldo < 0) {
            throw new DomainException('Saldo insuficiente.');
        }

        $pdo->prepare('UPDATE cuentas SET saldo = ? WHERE id = ?')->execute([$nuevoSaldo, $cuentaId]);

        $stmt = $pdo->prepare(
            'INSERT INTO movimientos (cuenta_id, usuario_id, tipo, monto, saldo_resultante, descripcion, cajero)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$cuentaId, $usuarioId, $tipo, $monto, $nuevoSaldo, $descripcion, $cajero]);

        $movimientoId = (int) $pdo->lastInsertId();
        $folio = asignarFolio($pdo, $movimientoId, $tipo);

        actualizarContadoresSesion($pdo, $tipo, $monto);

        $pdo->commit();

        return [
            'saldo'          => $nuevoSaldo,
            'movimiento_id'  => $movimientoId,
            'folio'          => $folio,
        ];
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
}

function bloquearSaldo(PDO $pdo, int $cuentaId): float
{
    $stmt = $pdo->prepare('SELECT saldo FROM cuentas WHERE id = ? AND activa = 1 FOR UPDATE');
    $stmt->execute([$cuentaId]);
    $saldo = $stmt->fetchColumn();

    if ($saldo === false) {
        throw new RuntimeException('La cuenta no existe o esta inactiva.');
    }

    return (float) $saldo;
}

function asignarFolio(PDO $pdo, int $movimientoId, string $tipo): string
{
    $prefijo = ['retiro' => 'TKT', 'deposito' => 'DEP', 'cierre_sesion' => 'CIERRE'][$tipo] ?? 'DOC';
    $folio = $prefijo . '-' . str_pad((string) $movimientoId, 6, '0', STR_PAD_LEFT);

    $pdo->prepare('UPDATE movimientos SET folio = ? WHERE id = ?')->execute([$folio, $movimientoId]);

    return $folio;
}

function actualizarContadoresSesion(PDO $pdo, string $tipo, float $monto): void
{
    $sesionId = sesionCajeroId();

    if ($sesionId === null) {
        return;
    }

    $pdo->prepare(
        'UPDATE sesiones_cajero
         SET operaciones = operaciones + 1,
             retirado  = retirado  + ?,
             depositado = depositado + ?
         WHERE id = ? AND estado = ?'
    )->execute([
        $tipo === 'retiro' ? $monto : 0,
        $tipo === 'deposito' ? $monto : 0,
        $sesionId,
        'abierta',
    ]);
}

function buscarCuentaDestino(string $dato, int $usuarioOrigenId): ?array
{
    $dato = trim($dato);

    if ($dato === '') {
        return null;
    }

    $stmt = bd()->prepare(
        'SELECT c.id, c.numero_cuenta, c.saldo, c.usuario_id, u.usuario, u.nombre_completo
         FROM cuentas c
         JOIN usuarios u ON u.id = c.usuario_id
         WHERE (u.usuario = ? OR c.numero_cuenta = ?) AND c.activa = 1 AND u.bloqueado = 0
         LIMIT 1'
    );
    $stmt->execute([$dato, $dato]);
    $cuenta = $stmt->fetch();

    if (!$cuenta || (int) $cuenta['usuario_id'] === $usuarioOrigenId) {
        return null;
    }

    return $cuenta;
}

function cuentaPorId(int $cuentaId): ?array
{
    $stmt = bd()->prepare(
        'SELECT c.id, c.numero_cuenta, c.saldo, c.usuario_id, u.usuario, u.nombre_completo
         FROM cuentas c
         JOIN usuarios u ON u.id = c.usuario_id
         WHERE c.id = ? AND c.activa = 1 AND u.bloqueado = 0
         LIMIT 1'
    );
    $stmt->execute([$cuentaId]);
    $cuenta = $stmt->fetch();

    return $cuenta ?: null;
}

function aplicarTransferencia(
    array $origen,
    array $destino,
    float $monto,
    string $cajero
): array {
    $origenUsuarioId = (int) ($origen['usuario_id'] ?? $origen['id'] ?? 0);
    $origenCuentaId = (int) ($origen['id'] ?? 0);

    $pdo = bd();
    $pdo->beginTransaction();

    try {
        $ids = [$origenCuentaId, (int) $destino['id']];
        sort($ids);

        foreach ($ids as $id) {
            bloquearSaldo($pdo, $id);
        }

        $saldoOrigen = saldoBloqueado($pdo, $origenCuentaId);
        $saldoDestino = saldoBloqueado($pdo, (int) $destino['id']);

        if ($monto > $saldoOrigen) {
            throw new DomainException('Saldo insuficiente. Tienes ' . dinero($saldoOrigen) . ' disponibles.');
        }

        $nuevoOrigen = $saldoOrigen - $monto;
        $nuevoDestino = $saldoDestino + $monto;

        $pdo->prepare('UPDATE cuentas SET saldo = ? WHERE id = ?')->execute([$nuevoOrigen, $origenCuentaId]);
        $pdo->prepare('UPDATE cuentas SET saldo = ? WHERE id = ?')->execute([$nuevoDestino, $destino['id']]);

        $stmt = $pdo->prepare(
            'INSERT INTO movimientos (cuenta_id, usuario_id, tipo, monto, saldo_resultante, descripcion, cajero)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );

        $stmt->execute([
            $origenCuentaId,
            $origenUsuarioId,
            'retiro',
            $monto,
            $nuevoOrigen,
            'Transferencia enviada a ' . $destino['usuario'] . ' (cuenta ' . $destino['numero_cuenta'] . ')',
            $cajero,
        ]);

        $envioId = (int) $pdo->lastInsertId();
        $referencia = 'TRF-' . str_pad((string) $envioId, 6, '0', STR_PAD_LEFT);

        $stmt->execute([
            $destino['id'],
            $destino['usuario_id'],
            'deposito',
            $monto,
            $nuevoDestino,
            'Transferencia recibida de ' . $origen['usuario'] . ' (cuenta ' . $origen['numero_cuenta'] . ')',
            $cajero,
        ]);

        $reciboId = (int) $pdo->lastInsertId();

        $pdo->prepare(
            'UPDATE movimientos
             SET referencia = ?, folio = ?
             WHERE id = ?'
        )->execute([
            $referencia,
            $referencia,
            $envioId,
        ]);

        $pdo->prepare(
            'UPDATE movimientos
             SET referencia = ?, folio = ?
             WHERE id = ?'
        )->execute([
            $referencia,
            'TRF-' . str_pad((string) $reciboId, 6, '0', STR_PAD_LEFT),
            $reciboId,
        ]);

        actualizarContadoresSesion($pdo, 'retiro', $monto);

        $pdo->commit();

        return [
            'referencia'    => $referencia,
            'saldo_origen'  => $nuevoOrigen,
            'saldo_destino' => $nuevoDestino,
            'movimiento_id' => $envioId,
            'recibo_id'     => $reciboId,
        ];
    } catch (Throwable $error) {
        $pdo->rollBack();
        throw $error;
    }
}

function saldoBloqueado(PDO $pdo, int $cuentaId): float
{
    $stmt = $pdo->prepare('SELECT saldo FROM cuentas WHERE id = ?');
    $stmt->execute([$cuentaId]);

    return (float) $stmt->fetchColumn();
}

function movimientoPorId(int $id): ?array
{
    $stmt = bd()->prepare(
        'SELECT m.*, c.numero_cuenta, u.nombre_completo, u.usuario
         FROM movimientos m
         JOIN cuentas c ON c.id = m.cuenta_id
         JOIN usuarios u ON u.id = m.usuario_id
         WHERE m.id = ?'
    );
    $stmt->execute([$id]);

    $movimiento = $stmt->fetch();

    return $movimiento ?: null;
}

function puedeVerComprobante(array $movimiento, array $usuario): bool
{
    if ($usuario['rol'] === 'supervisor') {
        return true;
    }

    return (int) $movimiento['usuario_id'] === (int) $usuario['id']
        && (int) $movimiento['cuenta_id'] === (int) $usuario['cuenta_id'];
}

function ultimoComprobante(int $cuentaId): ?array
{
    $stmt = bd()->prepare(
        'SELECT id, folio FROM movimientos
         WHERE cuenta_id = ? AND folio IS NOT NULL
         ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$cuentaId]);
    $fila = $stmt->fetch();

    return $fila === false ? null : ['id' => (int) $fila['id'], 'folio' => $fila['folio']];
}

function resumenDeSesion(int $sesionId): ?array
{
    $stmt = bd()->prepare(
        'SELECT s.*, c.numero_cuenta
         FROM sesiones_cajero s
         JOIN cuentas c ON c.id = s.cuenta_id
         WHERE s.id = ?'
    );
    $stmt->execute([$sesionId]);
    $fila = $stmt->fetch();

    return $fila ?: null;
}

function ultimosMovimientos(int $cuentaId, int $limite = 5): array
{
    $stmt = bd()->prepare(
        'SELECT id, tipo, monto, saldo_resultante, descripcion, cajero, folio, referencia, creado_en
         FROM movimientos
         WHERE cuenta_id = ?
         ORDER BY id DESC
         LIMIT ?'
    );
    $stmt->bindValue(1, $cuentaId, PDO::PARAM_INT);
    $stmt->bindValue(2, $limite, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function navActual(): string
{
    $ruta = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    return $ruta;
}

function menuNavegacion(): array
{
    $usuario = usuarioActual();

    if ($usuario === null) {
        return [];
    }

    $ruta = navActual();

    $enlaces = [
        ['URL' => e(RUTA_CAJERO), 'TEXTO' => 'Cajero', 'CLASE' => $ruta === RUTA_CAJERO ? 'activo' : ''],
['URL' => e(RUTA_MOVIMIENTOS), 'TEXTO' => 'Movimientos', 'CLASE' => $ruta === RUTA_MOVIMIENTOS ? 'activo' : ''],
        ['URL' => e(RUTA_CHAT), 'TEXTO' => 'Asistente', 'CLASE' => $ruta === RUTA_CHAT ? 'activo' : ''],
    ];

    if ($usuario['rol'] === 'cliente') {
        $enlaces[] = [
            'URL'   => e(RUTA_TRANSFERENCIA),
            'TEXTO' => 'Transferencia',
            'CLASE' => $ruta === RUTA_TRANSFERENCIA ? 'activo' : '',
        ];
    }

    if ($usuario['rol'] === 'supervisor') {
        $enlaces[] = ['URL' => e(RUTA_USUARIOS), 'TEXTO' => 'Usuarios', 'CLASE' => $ruta === RUTA_USUARIOS ? 'activo' : ''];
        $enlaces[] = ['URL' => e(RUTA_SESIONES), 'TEXTO' => 'Sesiones', 'CLASE' => $ruta === RUTA_SESIONES ? 'activo' : ''];
        $enlaces[] = ['URL' => e(RUTA_AUDITORIA), 'TEXTO' => 'Auditoria', 'CLASE' => $ruta === RUTA_AUDITORIA ? 'activo' : ''];
    }

    return [['ENLACES' => $enlaces]];
}

function aplicarAccionSupervisor(int $id, string $accion): void
{
    if ($id <= 0) {
        mensaje('Solicitud no valida.', 'error');
        return;
    }

    $pdo = bd();

    if ($accion === 'desbloquear' || $accion === 'bloquear') {
        $bloqueado = $accion === 'bloquear' ? 1 : 0;

        if ($bloqueado === 1 && esPropio($id)) {
            mensaje('No puedes bloquear tu propia cuenta de supervisor.', 'error');
            return;
        }

        $pdo->prepare('UPDATE usuarios SET bloqueado = ?, intentos_fallidos = 0 WHERE id = ?')
            ->execute([$bloqueado, $id]);

        mensaje($bloqueado === 1
            ? 'Acceso bloqueado. El usuario no puede iniciar sesion.'
            : 'Acceso habilitado. El usuario ya puede iniciar sesion.');

        return;
    }

    if ($accion === 'resetear') {
        $pdo->prepare('UPDATE usuarios SET intentos_fallidos = 0 WHERE id = ?')->execute([$id]);
        mensaje('Intentos de acceso reiniciados.');
        return;
    }

    if ($accion === 'activar_cuenta' || $accion === 'desactivar_cuenta') {
        $activa = $accion === 'activar_cuenta' ? 1 : 0;

        $pdo->prepare('UPDATE cuentas SET activa = ? WHERE id = ?')->execute([$activa, $id]);

        mensaje($activa === 1
            ? 'Cuenta activada. El usuario puede volver a operar.'
            : 'Cuenta desactivada. El usuario no puede retirar ni depositar.');

        return;
    }

    mensaje('Accion desconocida.', 'error');
}

function esPropio(int $usuarioId): bool
{
    $usuario = usuarioActual();
    return $usuario !== null && (int) $usuario['id'] === $usuarioId;
}

function estadisticasSistema(): array
{
    $pdo = bd();

    $stmt = $pdo->prepare('SELECT COUNT(*) FROM sesiones_cajero WHERE estado = ?');
    $stmt->execute(['abierta']);

    return [
        'total_usuarios'    => (int) $pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn(),
        'bloqueados'        => (int) $pdo->query('SELECT COUNT(*) FROM usuarios WHERE bloqueado = 1')->fetchColumn(),
        'cuentas_activas'   => (int) $pdo->query('SELECT COUNT(*) FROM cuentas WHERE activa = 1')->fetchColumn(),
        'sesiones_abiertas' => (int) $stmt->fetchColumn(),
        'movimientos'       => (int) $pdo->query('SELECT COUNT(*) FROM movimientos')->fetchColumn(),
        'saldo_total'       => (float) $pdo->query('SELECT COALESCE(SUM(saldo), 0) FROM cuentas')->fetchColumn(),
    ];
}

function usuariosConCuenta(): array
{
    return bd()->query(
        'SELECT u.id, u.usuario, u.nombre_completo, u.rol, u.intentos_fallidos, u.bloqueado,
                u.ultimo_acceso, c.id AS cuenta_id, c.numero_cuenta, c.saldo, c.activa,
                (SELECT COUNT(*) FROM movimientos m WHERE m.usuario_id = u.id) AS movimientos,
                (SELECT COUNT(*) FROM sesiones_cajero s WHERE s.usuario_id = u.id) AS sesiones
         FROM usuarios u
         LEFT JOIN cuentas c ON c.usuario_id = u.id
         ORDER BY u.bloqueado DESC, u.id'
    )->fetchAll();
}

function sesionesRegistradas(int $limite = 100): array
{
    $stmt = bd()->prepare(
        'SELECT s.id, s.fecha_inicio, s.fecha_cierre, s.saldo_inicial, s.saldo_final,
                s.operaciones, s.retirado, s.depositado, s.estado,
                u.usuario, c.numero_cuenta
         FROM sesiones_cajero s
         JOIN usuarios u ON u.id = s.usuario_id
         JOIN cuentas c ON c.id = s.cuenta_id
         ORDER BY s.id DESC
         LIMIT ?'
    );
    $stmt->bindValue(1, $limite, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}

function registrarIntentoFallido(int $usuarioId, int $intentosActuales): void
{
    $intentos = $intentosActuales + 1;
    $bloqueado = $intentos >= MAX_INTENTOS_LOGIN ? 1 : 0;

    bd()->prepare('UPDATE usuarios SET intentos_fallidos = ?, bloqueado = ? WHERE id = ?')
        ->execute([$intentos, $bloqueado, $usuarioId]);

    if ($bloqueado === 1) {
        mensaje('Cuenta bloqueada: superaste los ' . MAX_INTENTOS_LOGIN . ' intentos permitidos.', 'error');
        return;
    }

    $restantes = MAX_INTENTOS_LOGIN - $intentos;

    mensaje(
        'Contrasena incorrecta. Te ' . ($restantes === 1 ? 'queda' : 'quedan')
        . ' ' . $restantes . ($restantes === 1 ? ' intento.' : ' intentos.'),
        'error'
    );
}

function abrirSesionCajero(array $fila, array $cuenta): void
{
    bd()->prepare('UPDATE usuarios SET intentos_fallidos = 0, bloqueado = 0, ultimo_acceso = NOW() WHERE id = ?')
        ->execute([$fila['id']]);

    $stmt = bd()->prepare(
        'INSERT INTO sesiones_cajero (usuario_id, cuenta_id, fecha_inicio, saldo_inicial)
         VALUES (?, ?, NOW(), ?)'
    );
    $stmt->execute([$fila['id'], $cuenta['id'], $cuenta['saldo']]);

    $sesionCajeroId = (int) bd()->lastInsertId();

    session_regenerate_id(true);

    $_SESSION['usuario'] = [
        'id'              => (int) $fila['id'],
        'usuario'         => $fila['usuario'],
        'nombre_completo' => $fila['nombre_completo'],
        'rol'             => $fila['rol'],
        'cuenta_id'       => (int) $cuenta['id'],
        'numero_cuenta'   => $cuenta['numero_cuenta'],
    ];
    $_SESSION['sesion_cajero_id'] = $sesionCajeroId;
}

function datosCabecera(string $titulo): array
{
    $usuario = usuarioActual();
    $aviso = tomarMensaje();

    return [
        'TITULO'      => e($titulo),
        'APP_NOMBRE'  => e(APP_NOMBRE),
        'APP_LEMA'    => e(APP_LEMA),
        'APP_VERSION' => e(APP_VERSION),
        'PHP_VERSION' => e(PHP_VERSION),
        'CSS'         => e('styles.css'),
        'SCRIPT_CHAT' => e('assets/chat.js'),
        'RUTA_CERRAR' => e(RUTA_CERRAR),
        'RUTA_CAJERO' => e(RUTA_CAJERO),
        'RUTA_CHAT'   => e(RUTA_CHAT),
        'NAV'         => menuNavegacion(),
        'ALERTA'      => $aviso
            ? [['TIPO' => e($aviso['tipo']), 'TEXTO' => e($aviso['texto'])]]
            : [],
        'USUARIO'     => $usuario === null ? [] : [[
            'NOMBRE' => e($usuario['nombre_completo']),
            'ROL'    => e(ucfirst($usuario['rol'])),
            'FECHA'  => e(date('d/m/Y H:i')),
        ]],
        'CHAT_MENSAJES'    => $usuario === null ? [] : mensajesChatWidget(),
        'CHAT_SUGERENCIAS' => $usuario === null ? [] : sugerenciasChatWidget(),
    ];
}

function mensajesChatWidget(): array
{
    $historial = $_SESSION['chat'] ?? [];
    $salida = [];

    foreach (array_slice($historial, -4) as $m) {
        $salida[] = [
            'BURBUJA' => $m['ROL'] === 'bot' ? 'bot' : 'usuario',
            'TEXTO'   => e($m['TEXTO']),
        ];
    }

    return $salida;
}

function sugerenciasChatWidget(): array
{
    $historial = $_SESSION['chat'] ?? [];
    $salida = [];

    foreach (array_reverse($historial) as $m) {
        if ($m['ROL'] !== 'bot' || empty($m['SUGERENCIAS'])) {
            continue;
        }

        foreach ($m['SUGERENCIAS'] as $s) {
            $salida[] = ['PREGUNTA' => e($s['PREGUNTA']), 'ETIQUETA' => e($s['ETIQUETA'])];
        }

        break;
    }

    return $salida;
}

function filasMovimientos(array $movimientos): array
{
    $filas = [];

    foreach ($movimientos as $m) {
        $filas[] = [
            'TIPO'     => e($m['tipo']),
            'TEXTO'    => e(ucfirst(str_replace('_', ' ', $m['tipo']))),
            'DESCRIPCION' => e($m['descripcion']),
            'CAJERO'   => e($m['cajero'] ?? ''),
            'MONTO'    => e(dinero((float) $m['monto'])),
            'MONTO_CON_SIGNO' => e(($m['tipo'] === 'retiro' ? '-' : '+') . dinero((float) $m['monto'])),
            'CLASE_MONTO' => $m['tipo'] === 'retiro' ? 'negativo' : 'positivo',
            'SALDO'    => e(dinero((float) $m['saldo_resultante'])),
            'FECHA'    => e(date('d/m/Y H:i', strtotime($m['creado_en']))),
            'ID'       => (string) (int) $m['id'],
            'FOLIO'    => e($m['folio'] ?? ''),
            'REFERENCIA' => e($m['referencia'] ?? ''),
            'COMPROBANTE' => ($m['folio'] ?? null) === null ? [] : [[
                'URL' => e(RUTA_COMPROBANTE . '?id=' . $m['id']),
            ]],
        ];
    }

    return $filas;
}

function atajosRapidos(array $montos): array
{
    return array_map(
        static fn(float $m): array => ['MONTO' => (string) (int) $m],
        $montos
    );
}
