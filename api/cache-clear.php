<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use KnowledgeMap\Support\JsonResponse;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JsonResponse::error('Método no permitido. Usa POST.', 405);
}

$paths = [
    'entities' => km_config('paths.entity_cache'),
    'images' => km_config('paths.image_cache'),
];

$deleted = [];
$errors = [];

foreach ($paths as $key => $path) {
    $deleted[$key] = 0;
    if (!is_string($path) || $path === '' || !is_dir($path)) {
        $errors[] = [
            'cache' => $key,
            'message' => 'La carpeta de caché no existe.',
        ];
        continue;
    }

    foreach (glob(rtrim($path, '/\\') . '/*.json') ?: [] as $file) {
        if (!is_file($file)) {
            continue;
        }

        if (@unlink($file)) {
            $deleted[$key]++;
        } else {
            $errors[] = [
                'cache' => $key,
                'file' => basename($file),
                'message' => 'No se ha podido borrar el fichero.',
            ];
        }
    }
}

JsonResponse::send([
    'status' => 'ok',
    'message' => 'Caché limpiada.',
    'deleted' => $deleted,
    'errors' => $errors,
]);
