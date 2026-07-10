<?php
/**
 * Diagnóstico: este servidor consegue falar com o RM Host (porta 8051)?
 * Abra no navegador: http://172.20.0.43:9080/diag_rm_api.php
 */
require __DIR__ . '/bootstrap.php';

header('Content-Type: text/html; charset=utf-8');

$envKey = array_key_first(EnvironmentManager::all()) ?: '';
$env = $envKey !== '' ? EnvironmentManager::get($envKey) : [];
$rows = RmAuth::diagnoseApi($env);
$curl = function_exists('curl_init');
$fopen = (bool) ini_get('allow_url_fopen');
$anyOk = false;
foreach ($rows as $r) {
    if (!empty($r['ok'])) {
        $anyOk = true;
        break;
    }
}
$transport = $rows[0]['transport'] ?? ($curl ? 'curl' : 'stream');
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Diagnóstico API RM</title>
    <style>
        body { font-family: system-ui, sans-serif; max-width: 720px; margin: 2rem auto; padding: 0 1rem; }
        .ok { color: #0a7; } .fail { color: #c00; }
        table { border-collapse: collapse; width: 100%; }
        td, th { border: 1px solid #ccc; padding: .5rem; text-align: left; font-size: .9rem; }
        code { background: #f4f4f4; padding: .1rem .3rem; }
    </style>
</head>
<body>
    <h1>Diagnóstico API RM</h1>
    <p>PHP curl: <strong class="<?= $curl ? 'ok' : 'fail' ?>"><?= $curl ? 'OK' : 'AUSENTE' ?></strong>
        · allow_url_fopen: <strong class="<?= $fopen ? 'ok' : 'fail' ?>"><?= $fopen ? 'ON' : 'OFF' ?></strong>
        · transporte: <code><?= htmlspecialchars($transport, ENT_QUOTES, 'UTF-8') ?></code>
    </p>
    <?php if (!$curl): ?>
        <p class="fail">Instale no servidor: <code>sudo apt install php8.3-curl &amp;&amp; sudo systemctl restart php8.3-fpm</code></p>
    <?php endif; ?>
    <p>Resultado geral:
        <?php if ($anyOk): ?>
            <strong class="ok">Host alcançável</strong> — o login deve funcionar pela API.
        <?php else: ?>
            <strong class="fail">Host inacessível</strong> — confira curl/fopen e a porta <code>TCP 8051</code>
            até o RM Host (<code>172.20.0.21</code>).
        <?php endif; ?>
    </p>
    <table>
        <thead>
            <tr><th>URL</th><th>HTTP</th><th>Status</th><th>Detalhe</th></tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><code><?= htmlspecialchars($r['url'], ENT_QUOTES, 'UTF-8') ?></code></td>
                <td><?= (int) $r['http'] ?></td>
                <td class="<?= $r['ok'] ? 'ok' : 'fail' ?>"><?= $r['ok'] ? 'OK' : 'FALHA' ?></td>
                <td><?= htmlspecialchars($r['detail'], ENT_QUOTES, 'UTF-8') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <p style="margin-top:1.5rem;font-size:.85rem;color:#666">
        Remova este arquivo após o diagnóstico se preferir.
    </p>
</body>
</html>
