<?php
/** @var array<string, array> $environments */
/** @var string|null $selectedEnvironment */
/** @var string $defaultUsername */
/** @var array|null $lastTest */
/** @var bool $isConnected */
/** @var array|null $lastInventario */
/** @var array $recentInventarios */
?>

<div class="page-wrap">
    <header class="inv-page-header">
        <h1 class="page-title">Conectar ao RM</h1>
        <p class="page-subtitle">Escolha o ambiente e entre com usuário e senha do TOTVS RM (GUSUARIO).</p>
    </header>

    <?php if ($isConnected && (!$lastTest || $lastTest['success'])): ?>
        <section class="panel inv-quick-panel" aria-label="Acesso rápido">
            <h2 class="section-label">Já conectado</h2>
            <?php $current = $environments[$selectedEnvironment] ?? null; ?>
            <?php if ($current): ?>
                <p class="inv-quick-status">
                    Ambiente <strong><?= e($current['label']) ?></strong>
                    <?php if (!empty($lastInventario['codinventario'])): ?>
                        · último inventário <strong><?= e($lastInventario['codinventario']) ?></strong>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
            <a href="inventario.php" class="btn btn-primary">Ir para leitura</a>
        </section>
    <?php endif; ?>

    <section class="panel" aria-labelledby="secao-conexao">
        <h2 id="secao-conexao" class="section-label">Conexão</h2>
        <form action="conectar.php" method="post" id="connect-form" novalidate>
            <div class="form-group">
                <span class="form-label">Ambiente</span>
                <div class="env-list">
                    <?php foreach ($environments as $key => $env): ?>
                        <label class="env-item <?= ($selectedEnvironment === $key) ? 'is-selected' : '' ?>">
                            <input type="radio" name="ambiente" value="<?= e($key) ?>"
                                <?= ($selectedEnvironment === $key) ? 'checked' : '' ?> required>
                            <span>
                                <span class="env-item-title"><?= e($env['label']) ?></span>
                                <span class="env-item-detail"><?= e($env['host']) ?> · <?= e($env['database']) ?></span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <label for="usuario" class="form-label">Usuário RM</label>
                <input type="text" class="form-control" id="usuario" name="usuario"
                       value="<?= e($defaultUsername) ?>" placeholder="CODUSUARIO do RM" required
                       autocomplete="username">
            </div>

            <div class="form-group">
                <label for="senha" class="form-label">Senha RM</label>
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
                    Conectado em <?= e($current['label']) ?> (<?= e($current['host']) ?>)
                </div>
            <?php elseif ($lastTest): ?>
                <div class="status-box <?= $lastTest['success'] ? 'is-ok' : 'is-error' ?>">
                    <?= e($lastTest['message']) ?>
                </div>
            <?php endif; ?>

            <div class="btn-row">
                <button type="submit" name="acao" value="testar" class="btn btn-secondary">Testar login</button>
                <button type="submit" name="acao" value="conectar" class="btn btn-primary">Entrar</button>
            </div>
        </form>
    </section>

    <?php if (!empty($recentInventarios)): ?>
        <section class="panel mt-3" aria-labelledby="secao-recentes">
            <h2 id="secao-recentes" class="section-label">Inventários recentes</h2>
            <p class="section-desc mb-2">Atalhos para retomar uma leitura sem digitar o código de novo.</p>
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
                                <strong>Inventário <?= e($item['codinventario']) ?></strong>
                                <span class="recent-list-meta">
                                    Local <?= e($item['codloc']) ?> · Qtd <?= e($item['quantidade']) ?>
                                    <?php if ($isConnected && isset($item['total'])): ?>
                                        · <?= (int) $item['total'] ?> itens no banco
                                    <?php endif; ?>
                                </span>
                            </span>
                            <span class="recent-list-action">Abrir →</span>
                        </a>
                        <?php if ($isConnected): ?>
                        <form action="inventario-item.php" method="post" class="recent-list-delete"
                              onsubmit="return confirm('Excluir o inventário <?= e($item['codinventario']) ?> e todos os itens gravados no banco?\n\nEsta ação não pode ser desfeita.');">
                            <input type="hidden" name="acao" value="excluir_inventario">
                            <input type="hidden" name="CODINVENTARIO" value="<?= e($item['codinventario']) ?>">
                            <input type="hidden" name="CODLOC" value="<?= e($item['codloc']) ?>">
                            <input type="hidden" name="QUANTIDADE" value="<?= e($item['quantidade']) ?>">
                            <button type="submit" class="btn-link btn-link-danger">Excluir</button>
                        </form>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
</div>
