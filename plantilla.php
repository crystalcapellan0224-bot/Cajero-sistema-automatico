<?php

declare(strict_types=1);

require_once __DIR__ . '/conexion.php';

function renderizar(string $vista, array $datos = []): void
{
    echo plantilla(CARPETA_VISTAS . '/' . $vista . '.html', $datos);
}

function plantilla(string $ruta, array $datos = []): string
{
    if (!is_file($ruta)) {
        throw new RuntimeException('No se encontro la vista: ' . $ruta);
    }

    $html = (string) file_get_contents($ruta);

    $html = resolverParciales($html, $datos);
    $html = resolverBloques($html, $datos);
    $html = resolverVariables($html, $datos);

    return limpiarMarcadores($html);
}

function resolverParciales(string $html, array $datos): string
{
    return (string) preg_replace_callback(
        '/\{\{>\s*([\w]+)\s*\}\}/',
        static fn(array $m): string => plantilla(CARPETA_VISTAS . '/' . $m[1] . '.html', $datos),
        $html
    );
}

function resolverBloques(string $html, array $datos): string
{
    $html = resolverBloquesInvertidos($html, $datos);

    return (string) preg_replace_callback(
        '/\{\{#(\w+)\}\}(.*?)\{\{\/\1\}\}/s',
        static function (array $m) use ($datos): string {
            $filas = $datos[$m[1]] ?? [];

            if (!is_array($filas)) {
                return '';
            }

            $salida = '';

            foreach ($filas as $fila) {
                if (!is_array($fila)) {
                    continue;
                }

                $salida .= resolverBloques(resolverVariables($m[2], array_merge($datos, $fila)), array_merge($datos, $fila));
            }

            return $salida;
        },
        $html
    );
}

function resolverBloquesInvertidos(string $html, array $datos): string
{
    return (string) preg_replace_callback(
        '/\{\{\^(\w+)\}\}(.*?)\{\{\/\1\}\}/s',
        static function (array $m) use ($datos): string {
            $valor = $datos[$m[1]] ?? [];

            if (is_array($valor) ? $valor !== [] : (bool) $valor) {
                return '';
            }

            return resolverBloques(resolverVariables($m[2], $datos), $datos);
        },
        $html
    );
}

function resolverVariables(string $html, array $datos): string
{
    foreach ($datos as $clave => $valor) {
        if (is_array($valor)) {
            continue;
        }

        $html = str_replace('{{' . $clave . '}}', (string) $valor, $html);
    }

    return $html;
}

function limpiarMarcadores(string $html): string
{
    return (string) preg_replace('/\{\{\s*[\w]+\s*\}\}/', '', $html);
}
