<?php
require_once __DIR__ . '/_env_session.php';
bunker_session_start();
$uid = bunker_uid();
if (!$uid) {
    header('Content-Type: application/json; charset=utf-8');
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Не авторизован']);
    exit;
}
bunker_session_close();
if ($_SERVER['REQUEST_METHOD'] !== 'POST') bunker_fail('Метод не разрешён');
if (empty($_FILES['avatar']) || $_FILES['avatar']['error'] !== UPLOAD_ERR_OK) {
    bunker_fail($_FILES['avatar']['error'] === UPLOAD_ERR_INI_SIZE || $_FILES['avatar']['error'] === UPLOAD_ERR_FORM_SIZE ? 'Файл слишком большой' : 'Ошибка загрузки');
}
$file = $_FILES['avatar'];
$maxSize = (int) bunker_env('UPLOAD_MAX_SIZE', 2097152);
if ($file['size'] > $maxSize) bunker_fail('Максимум 2 МБ');
$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = $finfo->file($file['tmp_name']);
$allowed = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
if (!in_array($mime, $allowed, true)) bunker_fail('Только jpg, png, gif, webp');
$ext = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'][$mime];
$dir = bunker_env('UPLOAD_DIR', 'uploads');
if (!is_dir(__DIR__ . '/' . $dir)) mkdir(__DIR__ . '/' . $dir, 0755, true);
$path = $dir . '/avatar_' . $uid . '.' . $ext;
$fullPath = __DIR__ . '/' . $path;
if (!move_uploaded_file($file['tmp_name'], $fullPath)) bunker_fail('Не удалось сохранить файл');
$pdo = bunker_pdo();
$pdo->prepare('UPDATE users SET avatar_url = ? WHERE id = ?')->execute([$path, $uid]);
bunker_ok(['avatar_url' => $path]);