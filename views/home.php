<?php
/** @var array<string, array> $environments */
/** @var string|null $selectedEnvironment */
/** @var string $defaultUsername */
/** @var array|null $lastTest */
/** @var bool $isConnected */
/** @var array|null $lastInventario */
/** @var array $recentInventarios */

if ($selectedEnvironment === null && $environments !== []) {
    $selectedEnvironment = array_key_first($environments);
}
?>

<div class="login-screen">
    <div class="login-atmosphere" aria-hidden="true"></div>

    <div class="login-shell">
        <header class="login-brand">
            <div class="login-brand-mark">
                <img src="assets/img/logo.png" alt="Hospital 9 de Julho de Rondônia" class="login-logo" width="280" height="auto">
            </div>
            <h1 class="login-title">Inventário</h1>
            <p class="login-tagline">Contagem de estoque · TOTVS RM</p>
        </header>

        <?php if ($isConnected && (!$lastTest || $lastTest['success'])): ?>
            <section class="login-panel login-panel--resume" aria-label="Acesso rápido">
                <?php $current = $environments[$selectedEnvironment] ?? null; ?>
                <p class="login-resume-text">
                    Você já está conectado
                    <?php if ($current): ?>
                        em <strong><?= e($current['label']) ?></strong>
                    <?php endif; ?>
                    <?php if (!empty($lastInventario['codinventario'])): ?>
                        · inventário <strong><?= e($lastInventario['codinventario']) ?></strong>
                    <?php endif; ?>
                </p>
                <a href="inventario.php" class="btn btn-primary btn-block">Continuar para leitura</a>
            </section>
        <?php endif; ?>

        <section class="login-panel" aria-labelledby="login-heading">
            <h2 id="login-heading" class="login-panel-title">Acessar</h2>

            <form action="conectar.php" method="post" id="connect-form" class="login-form" novalidate>
                <div class="form-group">
                    <label for="ambiente" class="form-label">Ambiente</label>
                    <div class="select-wrap">
                        <select class="form-control form-select" id="ambiente" name="ambiente" required>
                            <?php foreach ($environments as $key => $env): ?>
                                <option value="<?= e($key) ?>"
                                    <?= ($selectedEnvironment === $key) ? 'selected' : '' ?>>
                                    <?= e($env['label']) ?> — <?= e($env['database']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label for="usuario" class="form-label">Usuário</label>
                    <input type="text" class="form-control" id="usuario" name="usuario"
                           value="<?= e($defaultUsername) ?>" placeholder="Usuário do RM" required
                           autocomplete="username" autofocus>
                </div>

                <div class="form-group">
                    <label for="senha" class="form-label">Senha</label>
                    <input type="password" class="form-control" id="senha" name="senha"
                           placeholder="Senha do RM" required autocomplete="current-password">
                </div>

                <?php
                    $showConnected = $isConnected && $selectedEnvironment
                        && (!$lastTest || $lastTest['success']);
                ?>
                <?php if ($showConnected): ?>
                    <?php $current = $environments[$selectedEnvironment]; ?>
                    <div class="status-box is-ok">
                        Conectado em <?= e($current['label']) ?>
                    </div>
                <?php elseif ($lastTest): ?>
                    <div class="status-box <?= $lastTest['success'] ? 'is-ok' : 'is-error' ?>">
                        <?= e($lastTest['message']) ?>
                    </div>
                <?php endif; ?>

                <div class="btn-row login-actions">
                    <button type="submit" name="acao" value="conectar" class="btn btn-primary btn-block">Entrar</button>
                    <button type="submit" name="acao" value="testar" class="btn btn-secondary btn-block">Só testar</button>
                </div>
            </form>
        </section>

        <?php if (!empty($recentInventarios) && $isConnected): ?>
            <section class="login-panel login-panel--recent" aria-labelledby="secao-recentes">
                <h2 id="secao-recentes" class="login-panel-title">Inventários recentes</h2>
                <ul class="recent-list">
                    <?php foreach ($recentInventarios as $item): ?>
                        <li class="recent-list-item">
                            <a href="inventario.php?<?= e(http_build_query([
                                'CODLOC' => $item['codloc'],
                                'CODINVENTARIO' => $item['codinventario'],
                                'QUANTIDADE' => $item['quantidade'],
                                'aplicar' => '1',
                            ])) ?>">
                                <span>
                                    <strong><?= e($item['codinventario']) ?></strong>
                                    <span class="recent-list-meta">
                                        Local <?= e($item['codloc']) ?>
                                        <?php if (isset($item['total'])): ?>
                                            · <?= (int) $item['total'] ?> itens
                                        <?php endif; ?>
                                    </span>
                                </span>
                                <span class="recent-list-action">Abrir</span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endif; ?>

        <p class="login-foot">Hospital 9 de Julho · Rondônia</p>
    </div>
</div>
