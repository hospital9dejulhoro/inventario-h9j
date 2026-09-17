<?php

/**
 * Escapa saída HTML preservando o conteúdo exibido para dados normais.
 */
function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

/**
 * Normaliza texto vindo do banco para UTF-8.
 *
 * A conversão é condicional de propósito. O driver sqlsrv devolve Latin-1 ou
 * UTF-8 conforme o sistema e a configuração — no Windows e no Linux o padrão
 * difere. Convertendo sempre, um texto que já veio em UTF-8 é codificado duas
 * vezes e "LÍNGUA" chega na tela como "LÃNGUA": os bytes C3 8D do Í passam a
 * ser lidos como "Ã" mais um caractere de controle invisível.
 *
 * Texto ASCII puro já é UTF-8 válido e passa intacto.
 */
function encode_db_value($value)
{
    if ($value === null) {
        return '';
    }

    $texto = (string) $value;

    if ($texto === '') {
        return '';
    }

    if (function_exists('mb_check_encoding') && mb_check_encoding($texto, 'UTF-8')) {
        return $texto;
    }

    if (function_exists('mb_convert_encoding')) {
        return mb_convert_encoding($texto, 'UTF-8', 'ISO-8859-1');
    }

    return utf8_encode($texto);
}

/**
 * Formata quantidade no padrão pt-BR sem zeros à direita: 12 · 1,5 · 1.250.
 */
function formatar_quantidade(float $quantidade): string
{
    // number_format sempre emite 3 casas, então sempre há vírgula para aparar.
    $formatado = number_format($quantidade, 3, ',', '.');

    return rtrim(rtrim($formatado, '0'), ',');
}

/**
 * Converte quantidade digitada para número, ou null se não for um número.
 *
 * Aceita "1", "1,5", "1.5" e "1.250,75". Devolver null em vez de 0 é
 * proposital: quantidade ilegível precisa ser recusada na entrada. Gravada
 * como texto, ela ainda apareceria na lista de bipados, mas o TRY_CAST das
 * somas devolve NULL e ela some de todos os totais — o operador vê "gravado"
 * e o relatório não bate.
 */
function normalizar_quantidade($valor): ?float
{
    $texto = trim((string) $valor);

    if ($texto === '') {
        return null;
    }

    // Espaço comum e espaço não separável (o Excel e o Windows colam os dois).
    $texto = str_replace([' ', "\xC2\xA0"], '', $texto);

    if (strpos($texto, ',') !== false) {
        // Vírgula presente = separador decimal pt-BR; o ponto é milhar.
        $texto = str_replace(['.', ','], ['', '.'], $texto);
    }

    if (!is_numeric($texto)) {
        return null;
    }

    $numero = (float) $texto;

    if (!is_finite($numero)) {
        return null;
    }

    return $numero;
}

/**
 * Quantidade no formato que a coluna QUANTIDADE espera: ponto decimal, sem
 * notação científica e sem zeros à direita.
 *
 * (string) 0.0000001 sairia como "1.0E-7", que o TRY_CAST do relatório não lê.
 */
function quantidade_para_banco(float $quantidade): string
{
    $texto = number_format($quantidade, 4, '.', '');

    if (strpos($texto, '.') !== false) {
        $texto = rtrim(rtrim($texto, '0'), '.');
    }

    return ($texto === '' || $texto === '-') ? '0' : $texto;
}

/**
 * Obtém o nome do usuário do sistema operacional / servidor web.
 */
function detect_os_username(): string
{
    $candidates = [
        $_SERVER['AUTH_USER'] ?? null,
        $_SERVER['REMOTE_USER'] ?? null,
        $_SERVER['LOGON_USER'] ?? null,
        getenv('USERNAME'),
        getenv('USER'),
    ];

    $ignored = ['www-data', 'apache', 'nginx', 'nobody', 'daemon'];

    foreach ($candidates as $candidate) {
        if (!empty($candidate)) {
            $name = (string) $candidate;
            if (strpos($name, '\\') !== false) {
                $parts = explode('\\', $name);
                $name = end($parts);
            }
            if (!in_array(strtolower($name), $ignored, true)) {
                return $name;
            }
        }
    }

    return '';
}

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function flash_get(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $flash;
}

function base_path(): string
{
    global $appConfig;
    return rtrim((string) ($appConfig['base_path'] ?? ''), '/');
}

function url(string $path = ''): string
{
    $base = base_path();
    $path = ltrim($path, '/');

    if ($path === '') {
        return $base === '' ? '/' : $base . '/';
    }

    return $base === '' ? $path : $base . '/' . $path;
}

function redirect_to(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

/* -------------------------------------------------------------------------
 * Registro de erros
 * ---------------------------------------------------------------------- */

/**
 * Manda o erro para o log do PHP com um prefixo identificável.
 *
 * Tudo que falha passa por aqui. Antes a causa real era descartada
 * (Connection::manipula zerava a mensagem) e só sobrava um "tente novamente"
 * na tela, sem nada no servidor para investigar depois.
 */
function log_erro(string $contexto, string $mensagem): void
{
    $usuario = '';
    if (!empty($_SESSION['rm_username'])) {
        $usuario = ' [' . $_SESSION['rm_username'] . ']';
    }

    error_log('[inventario]' . $usuario . ' ' . $contexto . ': ' . preg_replace('/\s+/', ' ', $mensagem));
}

/* -------------------------------------------------------------------------
 * Proteção CSRF
 * ---------------------------------------------------------------------- */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return (string) $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="_token" value="' . e(csrf_token()) . '">';
}

/**
 * Aceita o token no corpo do POST ou no cabeçalho X-CSRF-Token (fetch).
 */
function csrf_valido(): bool
{
    $esperado = (string) ($_SESSION['csrf_token'] ?? '');

    if ($esperado === '') {
        return false;
    }

    $enviado = (string) ($_POST['_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');

    return $enviado !== '' && hash_equals($esperado, $enviado);
}

/**
 * Barra a requisição quando o token não confere.
 *
 * Em tela, avisa e devolve para $voltar. Em endpoint JSON, responde 419.
 */
function csrf_exigir(string $voltar = 'index.php'): void
{
    if (csrf_valido()) {
        return;
    }

    log_erro('csrf', 'token ausente ou invalido em ' . ($_SERVER['REQUEST_URI'] ?? '?'));

    $mensagem = 'Sessão expirada ou página aberta há muito tempo. Recarregue a tela e tente de novo.';

    if (app_em_modo_json()) {
        http_response_code(419);
        echo json_encode(['ok' => false, 'message' => $mensagem]);
        exit;
    }

    flash_set('danger', $mensagem);
    redirect_to($voltar);
}

/* -------------------------------------------------------------------------
 * Tratamento de exceções
 * ---------------------------------------------------------------------- */

/**
 * Endpoints que respondem JSON marcam isso aqui para que um erro não derrube
 * a resposta com HTML no meio — o fetch cairia no catch com "Unexpected
 * token" em vez da mensagem real.
 */
function app_modo_json(): void
{
    $GLOBALS['app_json_mode'] = true;
}

function app_em_modo_json(): bool
{
    return !empty($GLOBALS['app_json_mode']);
}

/**
 * Levanta o teto de tempo e memória das telas pesadas (posição de um local
 * grande, conferência, exportações). O padrão de 30 s do PHP-FPM derruba a
 * contagem no meio quando o local tem milhares de lotes.
 */
function app_operacao_demorada(int $segundos = 300): void
{
    @set_time_limit($segundos);
    @ini_set('memory_limit', '512M');
    // A saída parcial de um CSV grande não pode ficar presa num buffer.
    @ini_set('zlib.output_compression', '0');
}

/**
 * Último recurso: registra no log e mostra algo legível em vez de uma página
 * em branco (display_errors fica desligado em produção).
 */
function app_tratar_excecao(Throwable $e): void
{
    $detalhe = ($e instanceof AppException && $e->detalhe() !== '')
        ? $e->detalhe()
        : $e->getMessage();

    log_erro(get_class($e), $detalhe . ' @ ' . $e->getFile() . ':' . $e->getLine());

    $publica = $e instanceof AppException
        ? $e->getMessage()
        : 'Ocorreu um erro inesperado. Tente novamente; se persistir, avise a TI.';

    $debug = !empty($GLOBALS['appConfig']['debug']);

    if (app_em_modo_json()) {
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode([
            'ok'      => false,
            'message' => $publica . ($debug ? ' [' . $detalhe . ']' : ''),
        ]);
        exit;
    }

    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/html; charset=utf-8');
    }

    echo '<!doctype html><meta charset="utf-8"><title>Erro</title>';
    echo '<div style="font:16px/1.5 system-ui,sans-serif;max-width:40rem;margin:4rem auto;padding:0 1rem">';
    echo '<h1 style="font-size:1.25rem">Não foi possível concluir a operação</h1>';
    echo '<p>' . e($publica) . '</p>';
    if ($debug) {
        echo '<pre style="white-space:pre-wrap;background:#f4f4f5;padding:1rem;border-radius:.5rem;font-size:.85rem">'
            . e($detalhe) . "\n" . e($e->getFile() . ':' . $e->getLine()) . '</pre>';
    }
    echo '<p><a href="' . e(url('index.php')) . '">Voltar ao início</a></p></div>';
    exit;
}
