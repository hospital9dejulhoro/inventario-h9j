<?php
/** @var string $pageTitle */
/** @var string $bodyClass */
/** @var string $content */
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? 'Inventário RM') ?></title>
    <?php if ($bp = base_path()): ?>
    <base href="<?= e($bp) ?>/">
    <?php endif; ?>
    <link href="assets/css/app.css" rel="stylesheet">
</head>
<body class="<?= e($bodyClass ?? '') ?>">
<?php if (!empty($showNavbar)): ?>
<header class="app-navbar">
    <div class="container-fluid px-3 px-md-4 d-flex justify-content-between align-items-center">
        <a class="navbar-brand" href="index.php">Inventário RM</a>
        <?php if (SessionManager::isConnected()): ?>
            <?php $env = EnvironmentManager::getCurrent(); ?>
            <div class="d-flex align-items-center gap-3">
                <?php if (SessionManager::hasLastInventario()): ?>
                    <?php $lastInv = SessionManager::getLastInventario(); ?>
                    <a href="inventario.php" class="nav-inventario-link">
                        Inventário <?= e($lastInv['codinventario']) ?>
                    </a>
                <?php endif; ?>
                <span class="nav-meta">
                    <strong><?= e($env['label']) ?></strong>
                    · <?= e(SessionManager::getUsername() ?: 'Operador') ?>
                </span>
                <a href="desconectar.php" class="btn-ghost">Sair</a>
            </div>
        <?php endif; ?>
    </div>
</header>
<?php endif; ?>

<main class="app-main">
    <?php if ($flash = flash_get()): ?>
        <div class="flash-wrap">
            <div class="flash flash-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
        </div>
    <?php endif; ?>

    <?= $content ?>
</main>

<div id="loading-overlay" class="loading-overlay d-none" aria-hidden="true">
    <div class="loading-card">
        <div class="spinner"></div>
        <p>Processando...</p>
    </div>
</div>

<script src="assets/js/app.js"></script>
</body>
</html>
