<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use KnowledgeMap\Support\JsonResponse;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    JsonResponse::error('Método no permitido. Usa POST.', 405);
}

if (!isset($_FILES['file'])) {
    JsonResponse::error('No se ha recibido ningún fichero.', 400);
}

$file = $_FILES['file'];

if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    JsonResponse::error('Error al subir el fichero.', 400, ['upload_error' => $file['error'] ?? null]);
}

$maxSize = (int) km_config('upload.max_size_bytes', 5242880);
if (($file['size'] ?? 0) > $maxSize) {
    JsonResponse::error('El fichero supera el tamaño máximo permitido.', 413);
}

$originalName = (string) ($file['name'] ?? 'upload');
$extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
$allowed = km_config('upload.allowed_extensions', []);

if (!in_array($extension, $allowed, true)) {
    JsonResponse::error('Extensión no permitida.', 400, [
        'extension' => $extension,
        'allowed' => $allowed,
    ]);
}

$tmpName = (string) ($file['tmp_name'] ?? '');
if ($tmpName === '' || !is_uploaded_file($tmpName)) {
    JsonResponse::error('El fichero temporal no es válido.', 400);
}

$safeBaseName = preg_replace('/[^a-zA-Z0-9._-]+/', '-', pathinfo($originalName, PATHINFO_FILENAME));
$safeBaseName = trim((string) $safeBaseName, '-_');
if ($safeBaseName === '') {
    $safeBaseName = 'triples';
}

$fileId = date('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '-' . $safeBaseName . '.' . $extension;
$target = rtrim((string) km_config('paths.uploads'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $fileId;

if (!move_uploaded_file($tmpName, $target)) {
    JsonResponse::error('No se ha podido guardar el fichero subido.', 500);
}

JsonResponse::send([
    'status' => 'ok',
    'file_id' => $fileId,
    'original_name' => $originalName,
    'size_bytes' => (int) $file['size'],
    'graph_url' => 'api/graph.php?file=' . rawurlencode($fileId),
]);
