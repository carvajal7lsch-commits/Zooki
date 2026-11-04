<?php
require_once __DIR__ . '/../../config/App.php';
if (!defined('ZOOKI_MOTIVOS_REVISION')):
define('ZOOKI_MOTIVOS_REVISION', true);
?>
<link rel="stylesheet" href="css/motivos-revision.css?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>">
<script src="js/motivos-revision.js?v=<?= htmlspecialchars(App::assetVersion(), ENT_QUOTES, 'UTF-8') ?>" defer></script>
<?php endif; ?>
