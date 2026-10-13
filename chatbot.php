<?php

declare(strict_types=1);

require_once __DIR__ . '/conexion.php';

function normalizar(string $texto): string
{
    $texto = strtolower(trim($texto));
    $texto = strtr($texto, [
        'á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u',
        'ü' => 'u', 'ñ' => 'n', '¿' => '', '?' => '', '¡' => '', '!' => '',
        ',' => '', '.' => '', ';' => '',
    ]);

    return (string) preg_replace('/\s+/', ' ', $texto);
}

function contiene(string $texto, array $palabras): bool
{
    foreach ($palabras as $palabra) {
        if (str_contains($texto, $palabra)) {
            return true;
        }
    }

    return false;
}

function sugerencias(array $pares): array
{
    $lista = [];

    foreach ($pares as $etiqueta => $pregunta) {
        $lista[] = ['ETIQUETA' => $etiqueta, 'PREGUNTA' => $pregunta];
    }

    return $lista;
}

function saludoInicial(): array
{
    $usuario = usuarioActual();

    $texto = $usuario === null
        ? "Hola, soy el asistente de CajaRapida.\nPuedo explicarte como usar el cajero paso a paso."
        : "Hola " . $usuario['nombre_completo'] . ", soy el asistente de CajaRapida.\n\n"
            . "Dime que necesitas hacer y te explico los pasos.";

    return [
        'categoria'   => 'saludo',
        'respuesta'   => $texto,
        'sugerencias' => sugerencias([
            'Como retiro dinero'  => 'como retiro dinero',
            'Como deposito'       => 'como deposito dinero',
            'Ver mi saldo'        => 'cual es mi saldo',
            'No me funciona'      => 'tengo un problema',
        ]),
    ];
}

function respuestaSaldo(): array
{
    $usuario = usuarioActual();

    if ($usuario === null) {
        return [
            'categoria'   => 'saldo',
            'respuesta'   => "Para ver tu saldo primero debes iniciar sesion.\n\n"
                . "Prueba con: cliente / 1234\n\n"
                . "Cuando estes dentro, abre la seccion Cajero y ahi aparece tu saldo disponible.",
            'sugerencias' => sugerencias([
                'Como inicio sesion' => 'como inicio sesion',
                'Ver el menu'         => 'que puedo hacer aqui',
            ]),
        ];
    }

    $saldo = saldoActual($usuario['cuenta_id']);

    return [
        'categoria'   => 'saldo',
        'respuesta'   => 'Tu saldo disponible es ' . dinero($saldo) . '\n'
            . 'Cuenta: ' . $usuario['numero_cuenta'],
        'sugerencias' => sugerencias([
            'Retirar dinero'  => 'como retiro dinero',
            'Ver movimientos' => 'como veo mis movimientos',
            'Depositar'       => 'como deposito dinero',
        ]),
    ];
}

function respuestaRetiro(): array
{
    $limite = dinero(RETIRO_MAXIMO);
    $usuario = usuarioActual();
    $extra = $usuario === null
        ? ''
        : "\n\nTu saldo actual es " . dinero(saldoActual($usuario['cuenta_id'])) . ".";

    return [
        'categoria'   => 'retiro',
        'respuesta'   => "Para retirar dinero:\n\n"
            . "1. En el menu elige la tarjeta Retirar dinero.\n"
            . "2. Escribe el monto (puedes usar los botones rapidos).\n"
            . "3. Presiona Confirmar retiro.\n\n"
            . "El maximo por operacion es " . $limite . "."
            . $extra
            . "\n\nSi pides mas de tu saldo, el sistema lo rechaza y tu saldo no cambia.",
        'sugerencias' => sugerencias([
            'Ver mi saldo'    => 'cual es mi saldo',
            'Me pide dinero' => 'dice saldo insuficiente',
            'Aayuda'         => 'ver menu',
        ]),
    ];
}

function respuestaDeposito(): array
{
    return [
        'categoria'   => 'deposito',
        'respuesta'   => "Para depositar dinero:\n\n"
            . "1. En el menu elige la tarjeta Depositar dinero.\n"
            . "2. Escribe el monto.\n"
            . "3. Presiona Confirmar deposito.\n\n"
            . "El maximo por operacion es " . dinero(DEPOSITO_MAXIMO) . ".\n"
            . "Cada operacion queda guardada en la base de datos.",
        'sugerencias' => sugerencias([
            'Ver mi saldo'     => 'cual es mi saldo',
            'Ver movimientos'  => 'como veo mis movimientos',
            'Ver el menu'      => 'que puedo hacer aqui',
        ]),
    ];
}

function respuestaMovimientos(): array
{
    $usuario = usuarioActual();

    if ($usuario === null) {
        return [
            'categoria'   => 'movimientos',
            'respuesta'   => "Los movimientos se ven una vez que inicies sesion.\n"
                . "Estan en la pestana Movimientos del menu superior.",
            'sugerencias' => sugerencias([
                'Como inicio sesion' => 'como inicio sesion',
                'Ver el menu'         => 'que puedo hacer aqui',
            ]),
        ];
    }

    $movimientos = ultimosMovimientos($usuario['cuenta_id'], 3);

    if (!$movimientos) {
        return [
            'categoria'   => 'movimientos',
            'respuesta'   => "Todavia no tienes movimientos en esta cuenta.\n\n"
                . "Cuando retires o deposites, aqui verras el historial con fecha y saldo.",
            'sugerencias' => sugerencias([
                'Primer retiro' => 'como retiro dinero',
                'Mi saldo'      => 'cual es mi saldo',
            ]),
        ];
    }

    $texto = "Ultimos movimientos de tu cuenta:\n\n";

    foreach ($movimientos as $m) {
        $signo = $m['tipo'] === 'retiro' ? '-' : '+';
        $texto .= ($m['tipo'] === 'retiro' ? 'Retiro' : 'Deposito')
            . ': ' . $signo . dinero((float) $m['monto'])
            . '  (saldo ' . dinero((float) $m['saldo_resultante']) . ')'
            . '  ' . date('d/m/Y H:i', strtotime($m['creado_en'])) . "\n";
    }

    $texto .= "\nPuedes ver el historial completo en la pestana Movimientos.";

    return [
        'categoria'   => 'movimientos',
        'respuesta'   => $texto,
        'sugerencias' => sugerencias([
            'Ver mi saldo'    => 'cual es mi saldo',
            'Exportar'        => 'como descargo mi informacion',
            'Ver el menu'     => 'que puedo hacer aqui',
        ]),
    ];
}

function respuestaProblemas(): array
{
    return [
        'categoria'   => 'problemas',
        'respuesta'   => "Veamos. Estas son las problemas mas comunes:\n\n"
            . "• Dice error de conexion: el servidor no esta corriendo. "
            . "Abre iniciar-servidor.bat y espera a que abra el navegador.\n"
            . "• No carga la pagina: revisa que la URL sea http://localhost:8000/\n"
            . "• Pantalla en blanco: comprueba que MySQL este encendido.\n"
            . "• La pagina abre pero no hay datos: ejecuta instalar.php para crear la base.\n\n"
            . "Dime cual de estos te pasa y te ayudo paso a paso.",
        'sugerencias' => sugerencias([
            'Error de conexion' => 'me sale error de conexion',
            'No abre la pagina' => 'no carga la pagina',
            'Pantalla en blanco' => 'se me ve en blanco',
        ]),
    ];
}

function respuestaConexion(): array
{
    return [
        'categoria'   => 'problemas',
        'respuesta'   => "El error de conexion significa que el servidor PHP no esta corriendo.\n\n"
            . "Como resolverlo:\n"
            . "1. Abre la carpeta del proyecto.\n"
            . "2. Doble clic en iniciar-servidor.bat.\n"
            . "3. Espera el mensaje Starting web server.\n"
            . "4. El navegador se abre solo en http://localhost:8000/\n\n"
            . "Importante: la ventana negra debe quedar abierta. Si la cierras, el sistema se detiene.",
        'sugerencias' => sugerencias([
            'Ver el menu'  => 'que puedo hacer aqui',
            'Ayuda general' => 'que puedes hacer por mi',
        ]),
    ];
}

function respuestaSaldoInsuficiente(): array
{
    $usuario = usuarioActual();
    $extra = $usuario === null ? '' : "\n\nTu saldo actual es " . dinero(saldoActual($usuario['cuenta_id'])) . '.';

    return [
        'categoria'   => 'retiro',
        'respuesta'   => "El cajero te dice saldo insuficiente cuando pides mas dinero del que tienes."
            . $extra
            . "\n\nSoluciones:\n"
            . "• Retira un monto mas pequeno.\n"
            . "• Deposita dinero primero.\n"
            . "• El saldo nunca baja de cero, el sistema no te dejara en negativo.",
        'sugerencias' => sugerencias([
            'Mi saldo'       => 'cual es mi saldo',
            'Depositar'      => 'como deposito dinero',
            'Ver movimientos' => 'como veo mis movimientos',
        ]),
    ];
}

function respuestaLimites(): array
{
    return [
        'categoria'   => 'limites',
        'respuesta'   => "Limites del sistema:\n\n"
            . "• Retiro maximo por operacion: " . dinero(RETIRO_MAXIMO) . "\n"
            . "• Deposito maximo por operacion: " . dinero(DEPOSITO_MAXIMO) . "\n"
            . "• Intentos de inicio de sesion: " . MAX_INTENTOS_LOGIN . "\n\n"
            . "Si te equivocas " . MAX_INTENTOS_LOGIN . " veces tu cuenta se bloquea y "
            . "un supervisor tiene que habilitarla de nuevo.",
        'sugerencias' => sugerencias([
            'Mi cuenta esta bloqueada' => 'mi cuenta esta bloqueada',
            'Ver el menu'              => 'que puedo hacer aqui',
        ]),
    ];
}

function respuestaBloqueo(): array
{
    $usuario = usuarioActual();

    if ($usuario === null) {
        return [
            'categoria'   => 'bloqueo',
            'respuesta'   => "Una cuenta se bloquea cuando alguien falla " . MAX_INTENTOS_LOGIN
                . " veces seguidas al escribir la contrasena.\n\n"
                . "Es una medida de seguridad. Para desbloquearla:\n"
                . "1. Entra con la cuenta supervisor / admin123\n"
                . "2. Abre la pestana Usuarios\n"
                . "3. Presiona Habilitar acceso en la fila del usuario.\n\n"
                . "Tambien le puedes pedir al supervisor que lo haga por ti.",
            'sugerencias' => sugerencias([
                'Entrar como supervisor' => 'como entra el supervisor',
                'Ver el menu'            => 'que puedo hacer aqui',
            ]),
        ];
    }

    return [
        'categoria'   => 'bloqueo',
        'respuesta'   => "Tu cuenta de usuario esta activa, asi que no hay bloqueo.\n\n"
            . "El bloqueo solo ocurre si fallas " . MAX_INTENTOS_LOGIN
            . " veces el inicio de sesion. Si te pasa, avisale al supervisor para que te habilite.",
        'sugerencias' => sugerencias([
            'Mi saldo'    => 'cual es mi saldo',
            'Ver el menu' => 'que puedo hacer aqui',
        ]),
    ];
}

function respuestaSesion(): array
{
    return [
        'categoria'   => 'sesion',
        'respuesta'   => "Para cerrar la sesion:\n\n"
            . "1. Presiona el boton Cerrar sesion, arriba a la derecha.\n"
            . "2. El sistema guarda un resumen en la base de datos: cuantas operaciones hiciste, "
            . "cuanto retiraste y cuanto depositaste.\n\n"
            . "Despues te devuelve al login. Vuelve a entrar cuando quieras.",
        'sugerencias' => sugerencias([
            'Ver el menu'  => 'que puedo hacer aqui',
            'Mi saldo'     => 'cual es mi saldo',
        ]),
    ];
}

function respuestaMenu(): array
{
    return [
        'categoria'   => 'menu',
        'respuesta'   => "En el cajero puedes:\n\n"
            . "• Retirar dinero\n"
            . "• Depositar dinero\n"
            . "• Consultar tu saldo\n"
            . "• Revisar tus movimientos\n"
            . "• Cerrar sesion\n\n"
            . "Si entras como supervisor, ademas puedes habilitar cuentas bloqueadas, "
            . "ver sesiones y auditar movimientos.",
        'sugerencias' => sugerencias([
            'Como retiro dinero' => 'como retiro dinero',
            'Ver mi saldo'       => 'cual es mi saldo',
            'Tengo un problema'  => 'tengo un problema',
        ]),
    ];
}

function respuestaLogin(): array
{
    return [
        'categoria'   => 'login',
        'respuesta'   => "Para entrar al sistema:\n\n"
            . "1. Escribe tu usuario.\n"
            . "2. Escribe tu contrasena.\n"
            . "3. Presiona Entrar.\n\n"
            . "Cuentas de prueba:\n"
            . "• cliente / 1234\n"
            . "• juan / 1234\n"
            . "• supervisor / admin123\n\n"
            . "Tienes " . MAX_INTENTOS_LOGIN . " intentos antes de que se bloquee la cuenta.",
        'sugerencias' => sugerencias([
            'Ver el menu'   => 'que puedo hacer aqui',
            'Mi cuenta'     => 'mi cuenta esta bloqueada',
        ]),
    ];
}

function respuestaSupervisor(): array
{
    return [
        'categoria'   => 'supervisor',
        'respuesta'   => "El supervisor es la persona que administra el sistema.\n\n"
            . "Para entrar: usuario supervisor, contrasena admin123\n\n"
            . "Desde ahi puede:\n"
            . "• Habilitar o bloquear cuentas\n"
            . "• Activar o desactivar cuentas\n"
            . "• Reiniciar intentos fallidos\n"
            . "• Ver el historial de sesiones\n"
            . "• Auditar todos los movimientos\n"
            . "• Leer las consultas de este asistente",
        'sugerencias' => sugerencias([
            'Como inicio sesion' => 'como inicio sesion',
            'Ver el menu'         => 'que puedo hacer aqui',
        ]),
    ];
}

function respuestaDespedida(): array
{
    return [
        'categoria'   => 'despedida',
        'respuesta'   => "De nada, fue un gusto ayudarte.\n\n"
            . "Si te quedas atorado, vuelve a preguntarme las veces que necesites.",
        'sugerencias' => sugerencias([
            'Ver el menu'       => 'que puedo hacer aqui',
            'Cerrar sesion'     => 'como cierro sesion',
        ]),
    ];
}

function confirmarTransferencia(string $texto): ?bool
{
    if (!hayTransferenciaPendiente()) {
        return null;
    }

    $t = ' ' . normalizar($texto) . ' ';

    if (preg_match('/\b(no|canc\w+|dejalo|olvid\w+|nada|mejor no)\b/', $t) === 1) {
        return false;
    }

    if (preg_match('/\b(si|confirm\w+|hazlo|dale|adelante|acept\w+|ejecut\w+|procede|claro|vale|hecho)\b/', $t) === 1) {
        return true;
    }

    return null;
}

function respuestaTransferencia(string $pregunta): array
{
    $usuario = usuarioActual();

    if ($usuario === null) {
        return [
            'categoria'   => 'transferencia',
            'respuesta'   => "Las transferencias solo se pueden hacer con tu sesion iniciada.\n\n"
                . "Entra con cliente / 1234 y despues escribeme algo como:\n"
                . "transfiere 100 a juan",
            'sugerencias' => sugerencias([
                'Como inicio sesion' => 'como inicio sesion',
                'Ver el menu'         => 'que puedo hacer aqui',
            ]),
        ];
    }

    $saldo = saldoActual($usuario['cuenta_id']);
    $monto = extraerMonto($pregunta);
    $destinoTexto = extraerDestino($pregunta);

    if ($monto === null) {
        return [
            'categoria'   => 'transferencia',
            'respuesta'   => "Entiendo que quieres transferir, pero no encontre el monto.\n\n"
                . "Escribeme el importe con el nombre del destino, por ejemplo:\n"
                . "transfiere 250 a juan\n\n"
                . 'Tu saldo disponible es ' . dinero($saldo) . '.',
            'sugerencias' => sugerencias([
                'Ayuda'    => 'ayuda',
                'Mi saldo' => 'cual es mi saldo',
            ]),
        ];
    }

    if ($monto > TRANSFERENCIA_MAXIMO) {
        return [
            'categoria'   => 'transferencia',
            'respuesta'   => 'El maximo por transferencia es ' . dinero(TRANSFERENCIA_MAXIMO) . ".\n\n"
                . 'Dividelo en varias transferencias o usa el formulario de Transferencia.',
            'sugerencias' => sugerencias([
                'Ver limites' => 'cuales son los limites',
                'Mi saldo'    => 'cual es mi saldo',
            ]),
        ];
    }

    if ($monto > $saldo) {
        return [
            'categoria'   => 'transferencia',
            'respuesta'   => 'No puedes transferir ' . dinero($monto) . ' porque solo tienes '
                . dinero($saldo) . ' disponibles.',
            'sugerencias' => sugerencias([
                'Ver movimientos' => 'como veo mis movimientos',
                'Mi saldo'        => 'cual es mi saldo',
            ]),
        ];
    }

    if ($destinoTexto === null) {
        return [
            'categoria'   => 'transferencia',
            'respuesta'   => 'Falta el destino de los ' . dinero($monto) . ".\n\n"
                . "Dime a quien quieres transferir, por ejemplo:\n"
                . "transfiere " . number_format($monto, 2, '.', '') . ' a juan',
            'sugerencias' => sugerencias([
                'Ayuda'    => 'ayuda',
                'Mi saldo' => 'cual es mi saldo',
            ]),
        ];
    }

    $destino = buscarCuentaDestino($destinoTexto, (int) $usuario['id']);

    if ($destino === null) {
        return [
            'categoria'   => 'transferencia',
            'respuesta'   => 'No encontre ninguna cuenta activa con "' . $destinoTexto . "\".\n\n"
                . "El destino puede ser el nombre de usuario (juan) o el numero de cuenta "
                . '(CR00000002). No puedes transferir a tu propia cuenta.',
            'sugerencias' => sugerencias([
                'Mi saldo' => 'cual es mi saldo',
                'Ayuda'    => 'ayuda',
            ]),
        ];
    }

    guardarTransferenciaPendiente([
        'monto'       => $monto,
        'destino_id'  => (int) $destino['id'],
        'destino_txt' => $destinoTexto,
    ]);

    return [
        'categoria'   => 'transferencia',
        'respuesta'   => "Revisa los datos antes de confirmar:\n\n"
            . 'Monto: ' . dinero($monto) . "\n"
            . 'Destinatario: ' . $destino['nombre_completo'] . ' (' . $destino['usuario'] . ")\n"
            . 'Cuenta destino: ' . $destino['numero_cuenta'] . "\n"
            . 'Tu saldo actual: ' . dinero($saldo) . "\n\n"
            . "Responde SI para ejecutar la transferencia o NO para cancelar.",
        'sugerencias' => sugerencias([
            'Si, transferir' => 'si',
            'No, cancelar'   => 'no',
        ]),
    ];
}

function respuestaConfirmacionTransferencia(string $texto, bool $confirmar): array
{
    $pendiente = tomarTransferenciaPendiente();

    if ($pendiente === null) {
        return respuestaSinEntender();
    }

    if ($confirmar === false) {
        return [
            'categoria'   => 'transferencia',
            'respuesta'   => "Transferencia cancelada. No se movio nada de tu saldo.",
            'sugerencias' => sugerencias([
                'Mi saldo' => 'cual es mi saldo',
                'Ayuda'    => 'que puedo hacer aqui',
            ]),
        ];
    }

    $usuario = usuarioActual();

    if ($usuario === null) {
        return [
            'categoria'   => 'transferencia',
            'respuesta'   => 'Tu sesion se cerro antes de confirmar. La transferencia no se realizo.',
            'sugerencias' => sugerencias([
                'Como inicio sesion' => 'como inicio sesion',
            ]),
        ];
    }

    $destino = cuentaPorId($pendiente['destino_id']);

    if ($destino === null) {
        return [
            'categoria'   => 'transferencia',
            'respuesta'   => 'La cuenta destino ya no esta disponible. La transferencia no se realizo.',
            'sugerencias' => sugerencias([
                'Mi saldo' => 'cual es mi saldo',
            ]),
        ];
    }

    try {
        $resultado = aplicarTransferencia(
            $usuario,
            $destino,
            (float) $pendiente['monto'],
            'CHAT'
        );
    } catch (DomainException $error) {
        return [
            'categoria'   => 'transferencia',
            'respuesta'   => 'No se pudo hacer la transferencia: ' . $error->getMessage(),
            'sugerencias' => sugerencias([
                'Mi saldo' => 'cual es mi saldo',
            ]),
        ];
    } catch (Throwable $error) {
        return [
            'categoria'   => 'transferencia',
            'respuesta'   => 'Ocurrio un error al registrar la transferencia. Intenta de nuevo.',
            'sugerencias' => sugerencias([
                'Mi saldo' => 'cual es mi saldo',
            ]),
        ];
    }

    return [
        'categoria'   => 'transferencia',
        'respuesta'   => "Transferencia realizada.\n\n"
            . 'Referencia: ' . $resultado['referencia'] . "\n"
            . 'Enviado a: ' . $destino['nombre_completo'] . ' (' . $destino['usuario'] . ")\n"
            . 'Monto: ' . dinero((float) $pendiente['monto']) . "\n"
            . 'Tu nuevo saldo: ' . dinero((float) $resultado['saldo_origen']) . "\n\n"
            . 'Puedes abrir el comprobante en Movimientos.',
        'sugerencias' => sugerencias([
            'Ver movimientos' => 'como veo mis movimientos',
            'Mi saldo'        => 'cual es mi saldo',
        ]),
    ];
}

function guardarTransferenciaPendiente(array $datos): void
{
    $_SESSION['chat_transferencia'] = $datos;
}

function tomarTransferenciaPendiente(): ?array
{
    $pendiente = $_SESSION['chat_transferencia'] ?? null;
    unset($_SESSION['chat_transferencia']);

    return is_array($pendiente) ? $pendiente : null;
}

function hayTransferenciaPendiente(): bool
{
    return is_array($_SESSION['chat_transferencia'] ?? null);
}

function extraerMonto(string $pregunta): ?float
{
    if (preg_match('/(\d+(?:[.,]\d{1,2})?)/', $pregunta, $coincidencia) !== 1) {
        return null;
    }

    $monto = (float) str_replace(',', '.', $coincidencia[1]);

    return $monto > 0 ? round($monto, 2) : null;
}

function extraerDestino(string $pregunta): ?string
{
    if (preg_match('/\ba\s+([a-z0-9_]+)/i', $pregunta, $coincidencia) === 1) {
        return trim($coincidencia[1]);
    }

    if (preg_match('/\b(?:hacia|para)\s+([a-z0-9_]+)/i', $pregunta, $coincidencia) === 1) {
        return trim($coincidencia[1]);
    }

    if (preg_match('/\b(?:cuenta\s+)?(CR\d{6,})/i', $pregunta, $coincidencia) === 1) {
        return strtoupper(trim($coincidencia[1]));
    }

    return null;
}

function respuestaSinEntender(): array
{
    return [
        'categoria'   => 'sin_clasificar',
        'respuesta'   => "No entendi tu pregunta. Prueba con alguna de estas opciones:\n\n"
            . "• Como retiro dinero\n"
            . "• Como deposito dinero\n"
            . "• Cual es mi saldo\n"
            . "• Mi cuenta esta bloqueada\n"
            . "• Me sale error de conexion\n"
            . "• Como cierro sesion",
        'sugerencias' => sugerencias([
            'Ver el menu'   => 'que puedo hacer aqui',
            'Tengo un problema' => 'tengo un problema',
        ]),
    ];
}

function clasificarPregunta(string $pregunta): array
{
    $texto = normalizar($pregunta);

    if ($texto === '') {
        return respuestaMenu();
    }

    if (contiene($texto, ['hola', 'buenas', 'buenos dias', 'buenas tardes', 'hey', 'ola', 'saludos'])) {
        return saludoInicial();
    }

    if (contiene($texto, ['gracias', 'genial', 'perfecto', 'excelente'])) {
        return respuestaDespedida();
    }

    if (confirmarTransferencia($texto) !== null) {
        return respuestaConfirmacionTransferencia($texto, confirmarTransferencia($texto));
    }

    if (contiene($texto, [
        'transf', 'transfer', 'transferir', 'transferencia',
        'envi', 'manda', 'mandar', 'abona', 'pasa', 'a otra cuenta',
        'de una cuenta a otra',
    ])) {
        return respuestaTransferencia($pregunta);
    }

    if (contiene($texto, ['bloquead', 'bloqueo', 'no puedo entrar', 'no me deja entrar'])) {
        return respuestaBloqueo();
    }

    if (contiene($texto, ['supervisor', 'administrador', 'admin'])) {
        return respuestaSupervisor();
    }

    if (contiene($texto, ['saldo insuficiente', 'no tengo saldo', 'rechaz', 'me dice que no', 'monto invalido', 'monto no valido'])) {
        return respuestaSaldoInsuficiente();
    }

    if (contiene($texto, ['error de conexion', 'no conecta', 'se cae', 'no abre', 'no carga', 'error de'])) {
        return respuestaConexion();
    }

    if (contiene($texto, ['problema', 'falla', 'no funciona', 'ayuda', 'trouble', 'no me funciona'])) {
        return respuestaProblemas();
    }

    if (contiene($texto, ['saldo', 'cuanto tengo', 'debo', 'disponible'])) {
        return respuestaSaldo();
    }

    if (contiene($texto, ['retiro', 'retirar', 'sacar', 'cajero', 'billete', 'efectivo'])) {
        return respuestaRetiro();
    }

    if (contiene($texto, ['deposit', 'abonar', 'ingresar dinero', 'poner dinero', 'recargar'])) {
        return respuestaDeposito();
    }

    if (contiene($texto, ['movimiento', 'historial', 'transaccion', 'cuanto he retirado', 'mis operaciones'])) {
        return respuestaMovimientos();
    }

    if (contiene($texto, ['limite', 'maximo', 'tope', 'cuanto puedo', 'hasta cuanto'])) {
        return respuestaLimites();
    }

    if (contiene($texto, ['cerrar sesion', 'salir', 'logout', 'terminar'])) {
        return respuestaSesion();
    }

    if (contiene($texto, ['que puedo hacer', 'que hay', 'menu', 'opciones', 'que se puede'])) {
        return respuestaMenu();
    }

    if (contiene($texto, ['iniciar sesion', 'como entro', 'como me registro', 'login', 'entrar'])) {
        return respuestaLogin();
    }

    return respuestaSinEntender();
}

function registrarChat(string $pregunta, array $respuesta): void
{
    $usuario = usuarioActual();

    bd()->prepare(
        'INSERT INTO chat_mensajes (usuario_id, pregunta, respuesta, categoria, atendio)
         VALUES (?, ?, ?, ?, ?)'
    )->execute([
        $usuario['id'] ?? null,
        mb_substr($pregunta, 0, 255),
        $respuesta['respuesta'],
        $respuesta['categoria'],
        $respuesta['categoria'] === 'sin_clasificar' ? 0 : 1,
    ]);
}

function historialChat(): array
{
    return $_SESSION['chat'] ?? [];
}

function agregarChat(string $pregunta, array $respuesta): void
{
    $_SESSION['chat'][] = [
        'ROL'        => 'user',
        'TEXTO'      => $pregunta,
        'CATEGORIA'  => '',
        'SUGERENCIAS' => [],
    ];

    $_SESSION['chat'][] = [
        'ROL'        => 'bot',
        'TEXTO'      => $respuesta['respuesta'],
        'CATEGORIA'  => $respuesta['categoria'],
        'SUGERENCIAS' => $respuesta['sugerencias'],
    ];

    $_SESSION['chat'] = array_slice($_SESSION['chat'], -40);
}

function limpiarChat(): void
{
    unset($_SESSION['chat']);
}

function ultimasConsultasChat(int $limite = 15): array
{
    $stmt = bd()->prepare(
        'SELECT c.pregunta, c.categoria, c.atendio, c.creado_en, u.usuario
         FROM chat_mensajes c
         LEFT JOIN usuarios u ON u.id = c.usuario_id
         ORDER BY c.id DESC
         LIMIT ?'
    );
    $stmt->bindValue(1, $limite, PDO::PARAM_INT);
    $stmt->execute();

    return $stmt->fetchAll();
}