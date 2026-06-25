<?php
/** @var array<string, array> $environments */
/** @var string|null $selectedEnvironment */
/** @var string $defaultUsername */
/** @var array|null $lastTest */
/** @var bool $isConnected */
?>

<div class="page-wrap">
    <h1 class="page-title">Conectar ao RM</h1>
    <p class="page-subtitle">Escolha o ambiente e informe seu nome para iniciar.</p>

    <div class="panel">
        <form action="conectar.php" method="post" id="connect-form" novalidate>
            <div class="form-group">
                <span class="form-label">Ambiente</span>
                <div class="env-list">
                    <?php foreach ($environments as $key => $env): ?>
                        <label class="env-item <?= ($selectedEnvironment === $key) ? 'is-selected' : '' ?>">
                            <input type="radio"
                                   name="ambiente"
                                   value="<?= e($key) ?>"
                                <?= ($selectedEnvironment === $key) ? 'checked' : '' ?>
                                   required>
                            <span>
                                <span class="env-item-title"><?= e($env['label']) ?></span>
                                <span class="env-item-detail"><?= e($env['host']) ?> · <?= e($env['database']) ?></span>
                            </span>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="form-group">
                <label for="usuario" class="form-label">Seu nome</label>
                <input type="text"
                       class="form-control"
                       id="usuario"
                       name="usuario"
                       value="<?= e($defaultUsername) ?>"
                       placeholder="Ex.: João Silva"
                       required>
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
                <button type="submit" name="acao" value="testar" class="btn btn-secondary">
                    Testar conexão
                </button>
                <button type="submit" name="acao" value="conectar" class="btn btn-primary">
                    Conectar
                </button>
                <?php if ($isConnected && (!$lastTest || $lastTest['success'])): ?>
                    <a href="inventario.php" class="btn btn-primary">Abrir inventário</a>
                <?php endif; ?>
            </div>
        </form>
    </div>
</div>
