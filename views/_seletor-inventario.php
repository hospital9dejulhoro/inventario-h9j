<?php
/**
 * Escolha do inventário: lista, não campo livre.
 *
 * Quem cria inventário é o RM. Digitar um código à mão só podia dar em duas
 * coisas — acertar o que a lista já mostrava, ou errar e receber "não está
 * cadastrado". O campo livre ficou no banco como 142 códigos com contagem
 * gravada e nenhuma linha em TINVENTARIO: "21033001" e "23021,001" são a mesma
 * contagem digitada de dois jeitos, e "0013710175975" é um código de barras que
 * foi parar no campo do inventário.
 *
 * A contagem avulsa continua existindo, na seção ao lado — ela é a resposta
 * certa para "preciso contar agora e o RM ainda não abriu o inventário".
 *
 * @var array  $inventariosAbertos Lista vinda de InventarioRM::listarAbertos()
 * @var string $codinventario      O que está selecionado agora
 * @var string $codloc
 * @var string $nomeLocal
 */
$inventariosAbertos = $inventariosAbertos ?? [];
$codinventario = (string) ($codinventario ?? '');
$codloc = (string) ($codloc ?? '');
$nomeLocal = (string) ($nomeLocal ?? '');

// O inventário aberto agora pode não estar entre os abertos do RM: encerrado
// que se está consultando, ou contagem avulsa. Sem isto, abrir a tela apagaria
// a escolha da pessoa.
$naLista = false;
foreach ($inventariosAbertos as $aberto) {
    if (($aberto['codinventario'] ?? '') === $codinventario) {
        $naLista = true;
        break;
    }
}
$foraDaLista = ($codinventario !== '' && !$naLista);
?>
<div class="form-group">
    <label for="CODINVENTARIO" class="form-label">Inventário</label>
    <select name="CODINVENTARIO" id="CODINVENTARIO" class="form-control mono"
            data-inventario-picker required>
        <option value="">— escolha um inventário —</option>
        <?php if ($inventariosAbertos !== []): ?>
            <optgroup label="Em aberto no RM">
                <?php foreach ($inventariosAbertos as $aberto): ?>
                    <?php
                    $rotuloLocal = trim((string) ($aberto['codloc'] ?? ''));
                    if (($aberto['local_nome'] ?? '') !== '') {
                        $rotuloLocal .= ' — ' . $aberto['local_nome'];
                    }
                    ?>
                    <option value="<?= e($aberto['codinventario']) ?>"
                            <?= $aberto['codinventario'] === $codinventario ? 'selected' : '' ?>>
                        <?= e($aberto['codinventario']) ?><?= $rotuloLocal !== '' ? ' · ' . e($rotuloLocal) : '' ?>
                        · <?= (int) ($aberto['itens'] ?? 0) ?> itens
                    </option>
                <?php endforeach; ?>
            </optgroup>
        <?php endif; ?>
        <?php if ($foraDaLista): ?>
            <optgroup label="<?= ZMDCODBARRAS::ehCodigoAvulso($codinventario) ? 'Contagem avulsa' : 'Fora dos abertos' ?>">
                <option value="<?= e($codinventario) ?>" selected><?= e($codinventario) ?></option>
            </optgroup>
        <?php endif; ?>
    </select>
    <?php if ($inventariosAbertos === [] && !$foraDaLista): ?>
        <span class="form-hint">Nenhum inventário em aberto no RM. Abra um inventário no RM,
            ou use a contagem avulsa abaixo.</span>
    <?php endif; ?>
</div>
<div class="form-group">
    <label for="CODLOC" class="form-label">Local</label>
    <input type="text" name="CODLOC" id="CODLOC" class="form-control" maxlength="3"
           value="<?= e($codloc) ?>" readonly>
    <?php /* data-codloc-nome e o gancho que o app.js usa para escrever o nome
             do local quando a escolha muda. Sem ele a dica fica congelada. */ ?>
    <span class="form-hint<?= $nomeLocal !== '' ? ' is-ok' : '' ?>" id="codloc-nome" data-codloc-nome><?= e($nomeLocal !== '' ? $nomeLocal : 'Preenchido pelo inventário') ?></span>
</div>
