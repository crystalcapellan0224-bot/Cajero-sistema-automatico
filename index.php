<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/funciones.php';

redirigir(haySesionActiva() ? RUTA_CAJERO : RUTA_LOGIN);
