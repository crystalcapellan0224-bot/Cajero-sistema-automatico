<?php

declare(strict_types=1);

function rutaEsquema(): string
{
    return dirname(__DIR__) . '/bd/esquema.sql';
}

function ejecutarEsquema(string $ruta): int
{
    $sql = file_get_contents($ruta);

    if ($sql === false) {
        throw new RuntimeException('No se pudo leer ' . $ruta);
    }

    $limpio = '';

    foreach (preg_split('/\R/', $sql) as $linea) {
        $inicio = ltrim($linea);

        if ($inicio === '' || str_starts_with($inicio, '--') || str_starts_with($inicio, '#')) {
            continue;
        }

        $limpio .= $linea . "\n";
    }

    $pdo = bdSinBase();
    $total = 0;

    foreach (explode(';', $limpio) as $sentencia) {
        $sentencia = trim($sentencia);

        if ($sentencia === '') {
            continue;
        }

        try {
            $pdo->exec($sentencia);
            $total++;
        } catch (PDOException $error) {
            if (yaAplicada($error)) {
                continue;
            }

            throw $error;
        }
    }

    return $total;
}

function yaAplicada(PDOException $error): bool
{
    if (!str_contains($error->getMessage(), 'Duplicate')) {
        return false;
    }

    return in_array((string) $error->getCode(), ['42S01', '42S11', '42S12', '42S21', '42000'], true);
}

function instalarBaseDeDatos(): int
{
    return ejecutarEsquema(rutaEsquema());
}

function baseDeDatosLista(): bool
{
    try {
        return (int) bd()->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() > 0;
    } catch (Throwable $error) {
        return false;
    }
}
