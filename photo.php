<?php
/*
 * Serves a photo staff uploaded (stored in the database by staff/menu.php or
 * staff/photos.php). Each upload gets a new id, so a URL never changes what it
 * shows and browsers can keep it for a year.
 */

require_once __DIR__ . '/includes/menu-edits.php';

$id = (int) ($_GET['id'] ?? 0);
$row = null;
if ($id > 0) {
    try {
        $row = menu_edits_query(function (PDO $pdo) use ($id) {
            $stmt = $pdo->prepare('SELECT mime, data, created_at FROM photos WHERE id = ?');
            $stmt->execute([$id]);
            return $stmt->fetch() ?: null;
        });
    } catch (Throwable $e) {
        $row = null;
    }
}

if (!$row) {
    http_response_code(404);
    header('Content-Type: text/plain');
    echo 'Photo not found';
    exit;
}

$data = is_resource($row['data']) ? stream_get_contents($row['data']) : (string) $row['data'];
$etag = '"p' . $id . '"';
header('Content-Type: ' . $row['mime']);
header('Cache-Control: public, max-age=31536000, immutable');
header('ETag: ' . $etag);
header('X-Content-Type-Options: nosniff');
if (($_SERVER['HTTP_IF_NONE_MATCH'] ?? '') === $etag) {
    http_response_code(304);
    exit;
}
header('Content-Length: ' . strlen($data));
echo $data;
