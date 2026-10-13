<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/funciones.php';

if (haySesionActiva()) {
    redirigir(RUTA_CAJERO);
}

if (isset($_GET['salida'])) {
    mensaje('Sesion cerrada correctamente. El resumen quedo guardado en la base de datos.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $usuario = trim((string) ($_POST['usuario'] ?? ''));
    $clave = (string) ($_POST['clave'] ?? '');

    if ($usuario === '' || $clave === '') {
        mensaje('Escribe tu usuario y tu contrasena.', 'error');
        redirigir(RUTA_LOGIN);
    }

    $stmt = bd()->prepare('SELECT * FROM usuarios WHERE usuario = ? LIMIT 1');
    $stmt->execute([$usuario]);
    $fila = $stmt->fetch();

    if (!$fila) {
        mensaje('Usuario o contrasena incorrectos.', 'error');
        redirigir(RUTA_LOGIN);
    }

    if ((int) $fila['bloqueado'] === 1) {
        mensaje('Tu cuenta esta bloqueada por seguridad. Contacta al supervisor.', 'error');
        redirigir(RUTA_LOGIN);
    }

    if (!password_verify($clave, $fila['clave'])) {
        registrarIntentoFallido((int) $fila['id'], (int) $fila['intentos_fallidos']);
        redirigir(RUTA_LOGIN);
    }

    $cuenta = cuentaDe((int) $fila['id']);

    if (!$cuenta) {
        mensaje('Tu usuario no tiene una cuenta activa asociada.', 'error');
        redirigir(RUTA_LOGIN);
    }

    abrirSesionCajero($fila, $cuenta);

    mensaje('Bienvenido, ' . $fila['nombre_completo'] . '. Sesion iniciada.');
    redirigir(RUTA_CAJERO);
}

renderizar('login', datosCabecera('Iniciar sesion') + [
    'RUTA_LOGIN'      => e(RUTA_LOGIN),
    'USUARIO_VALOR'   => e($_POST['usuario'] ?? ''),
    'MAX_INTENTOS'    => (string) (int) MAX_INTENTOS_LOGIN,
]);
