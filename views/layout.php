<?php
/** @var string $pageTitle */
/** @var string $bodyClass */
/** @var string $content */

// Aba ativa da navbar. Calculado aqui porque as telas de contagem compartilham
// a classe page-inventory: a condicao tem de excluir cada sub-tela pelo nome, e
// esquecer uma acendia o item errado.
$bodyClassAtual = (string) ($bodyClass ?? '');
$navSemLote = str_contains($bodyClassAtual, 'page-sem-lote');
$navPorLote = str_contains($bodyClassAtual, 'page-por-lote');
$navLeitura = str_contains($bodyClassAtual, 'page-inventory') && !$navSemLote && !$navPorLote;
$navPosicao = str_contains($bodyClassAtual, 'page-posicao');
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle ?? 'Inventário RM') ?></title>
    <link rel="icon" href="assets/favicon.svg" type="image/svg+xml">
    <link rel="icon" href="favicon.ico" sizes="any">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <?php if ($bp = base_path()): ?>
    <base href="<?= e($bp) ?>/">
    <?php endif; ?>
    <link href="assets/css/app.css" rel="stylesheet">
</head>
<body class="<?= e($bodyClass ?? '') ?>">
<?php if (!empty($showNavbar)): ?>
<header class="app-navbar">
    <div class="container-fluid px-3 px-md-4 d-flex justify-content-between align-items-center">
        <a class="navbar-brand" href="<?= SessionManager::isConnected() ? 'inventario.php' : 'index.php' ?>">Inventário RM</a>
        <?php if (SessionManager::isConnected()): ?>
            <?php $env = EnvironmentManager::getCurrent(); ?>
            <div class="d-flex align-items-center gap-3">
                <span class="nav-meta">
                    <strong><?= e($env['label']) ?></strong>
                    · <?= e(SessionManager::getDisplayName() ?: 'Operador') ?>
                    <?php if (SessionManager::getUsername() !== '' && SessionManager::getDisplayName() !== SessionManager::getUsername()): ?>
                        <span class="nav-meta-user">(<?= e(SessionManager::getUsername()) ?>)</span>
                    <?php endif; ?>
                    <?php /* Perfil a vista: e por ele que se decide quem apaga,
                             e sem mostrar ninguem sabe o que por na configuracao. */ ?>
                    <?php $resumoPerfis = PerfisRM::resumo(SessionManager::getPerfis()); ?>
                    <?php if ($resumoPerfis !== ''): ?>
                        <span class="nav-meta-user" title="Perfis no RM (GUSRPERFIL)">· <?= e($resumoPerfis) ?></span>
                    <?php endif; ?>
                </span>
                <a href="inventario.php" class="btn-ghost<?= $navLeitura ? ' is-nav-on' : '' ?>">Leitura</a>
                <a href="por-lote.php" class="btn-ghost<?= $navPorLote ? ' is-nav-on' : '' ?>">Por lote</a>
                <a href="sem-lote.php" class="btn-ghost<?= $navSemLote ? ' is-nav-on' : '' ?>">Sem lote</a>
                <a href="relatorio.php" class="btn-ghost">Relatório</a>
                <a href="posicao.php" class="btn-ghost<?= $navPosicao ? ' is-nav-on' : '' ?>">Posição</a>
                <a href="index.php?config=1" class="btn-ghost">Configuração</a>
                <a href="desconectar.php" class="btn-ghost">Sair</a>
            </div>
        <?php endif; ?>
    </div>
</header>
<?php endif; ?>

<main class="app-main">
    <?php if ($flash = flash_get()): ?>
        <div class="flash-wrap">
            <div class="flash flash-<?= e($flash['type']) ?>"
                 data-flash-type="<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
        </div>
    <?php endif; ?>

    <?= $content ?>
</main>

<?php /*
     Aviso que não dá para não ver.

     A mensagem no topo da página deixou de servir quando a tela de leitura
     passou a rolar sozinha até o campo de bipagem: o aviso fica acima da
     dobra, e quem está bipando em sequência não volta lá para conferir. Uma
     recusa — produto fora do estoque, lote faltando — passava batida, e só
     aparecia no fim, na conferência.

     O modal exige um toque para sumir. A mensagem no topo continua, como
     registro do que aconteceu depois que o modal fecha.
*/ ?>
<div id="aviso-modal" class="modal hidden" aria-hidden="true" role="alertdialog"
     aria-labelledby="aviso-modal-titulo" aria-describedby="aviso-modal-texto">
    <div class="modal-backdrop" data-fechar-aviso></div>
    <div class="modal-panel aviso-panel">
        <h3 id="aviso-modal-titulo" class="modal-title aviso-titulo">Atenção</h3>
        <p id="aviso-modal-texto" class="aviso-texto"></p>
        <div class="btn-row">
            <button type="button" class="btn btn-primary aviso-ok" id="aviso-modal-ok"
                    data-fechar-aviso>Entendi</button>
        </div>
    </div>
</div>

<div id="loading-overlay" class="loading-overlay d-none" aria-hidden="true">
    <div class="loading-card">
        <div class="spinner"></div>
        <p>Processando...</p>
    </div>
</div>

<script src="assets/js/app.js"></script>
</body>
</html>
