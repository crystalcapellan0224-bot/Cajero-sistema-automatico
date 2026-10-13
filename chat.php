<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/funciones.php';
require_once __DIR__ . '/includes/chatbot.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $accion = (string) ($_POST['accion'] ?? '');

    if ($accion === 'preguntar') {
        $pregunta = trim((string) ($_POST['pregunta'] ?? ''));

        if ($pregunta === '') {
            $respuesta = ['categoria' => 'sin_clasificar', 'respuesta' => 'Escribe tu pregunta.', 'sugerencias' => []];
        } else {
            $respuesta = clasificarPregunta($pregunta);

            try {
                registrarChat($pregunta, $respuesta);
            } catch (Throwable $error) {
                $respuesta['respuesta'] .= "\n\n(No se pudo guardar el registro de esta consulta.)";
            }
        }

        agregarChat($pregunta, $respuesta);
    }

    if ($accion === 'limpiar') {
        limpiarChat();
    }

    redirigir(RUTA_CHAT);
}

$historial = historialChat();

if (!$historial) {
    agregarChat('', saludoInicial());
    $historial = historialChat();
}

$mensajes = [];
$sugerencias = [];

foreach ($historial as $m) {
    $mensajes[] = [
        'ROL'       => e($m['ROL']),
        'TEXTO'     => e($m['TEXTO']),
        'BURBUJA'   => $m['ROL'] === 'bot' ? 'burbuja bot' : 'burbuja usuario',
        'CATEGORIA' => e($m['CATEGORIA']),
    ];

    if ($m['ROL'] === 'bot' && $m['SUGERENCIAS']) {
        $sugerencias = $m['SUGERENCIAS'];
    }
}

$botones = [];

foreach ($sugerencias as $s) {
    $botones[] = ['ETIQUETA' => e($s['ETIQUETA']), 'PREGUNTA' => e($s['PREGUNTA'])];
}

renderizar('chat', datosCabecera('Asistente') + [
    'RUTA_CHAT'  => e(RUTA_CHAT),
    'SCRIPT_CHAT' => e('assets/chat.js'),
    'MENSAJES'   => $mensajes,
    'SUGERENCIAS' => $botones ? [['BOTONES' => $botones]] : [],
    'TOTAL'      => (string) count($mensajes),
]);