<?php
$baseUrl = rtrim((getenv('APP_URL') ?: (isset($_ENV['APP_URL']) ? $_ENV['APP_URL'] : '')), '/');
if ($baseUrl === '') {
    $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $baseUrl = $proto . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . dirname($_SERVER['SCRIPT_NAME'] ?? '');
    $baseUrl = rtrim($baseUrl, '/');
}
$appUrl = $baseUrl;
$basePath = parse_url($baseUrl, PHP_URL_PATH);
if ($basePath === null || $basePath === '') $basePath = '/';
$basePath = rtrim($basePath, '/');
$v = (string) (filemtime(__DIR__ . '/assets/js/app.js') ?: time());
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no">
    <meta name="theme-color" content="#0f1419" id="meta-theme-color">
    <title>Бункер</title>
    <link rel="icon" href="<?= htmlspecialchars($basePath) ?>/favicons/favicon.ico" type="image/x-icon">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= htmlspecialchars($basePath) ?>/assets/css/style.css?v=<?= htmlspecialchars($v) ?>">
    <script>
        window.BUNKER_CONFIG = {
            baseUrl: <?= json_encode($baseUrl) ?>,
            appUrl: <?= json_encode($appUrl) ?>,
            basePath: <?= json_encode($basePath) ?>
        };
    </script>
</head>
<body>
    <div id="app"></div>
    <script src="<?= htmlspecialchars($basePath) ?>/assets/js/api.js?v=<?= htmlspecialchars($v) ?>"></script>
    <script src="<?= htmlspecialchars($basePath) ?>/assets/js/rt.js?v=<?= htmlspecialchars($v) ?>"></script>
    <script src="<?= htmlspecialchars($basePath) ?>/assets/js/app.js?v=<?= htmlspecialchars($v) ?>"></script>
</body>
</html>