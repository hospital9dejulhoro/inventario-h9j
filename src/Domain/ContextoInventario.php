<?php

/**
 * Resolve o inventário e o local ativos a partir da URL ou da sessão.
 *
 * Normaliza a máscara AA.LLL.NNN, sincroniza o CODLOC, valida o local e
 * confirma no RM — ou reconhece a faixa 9xx como contagem avulsa.
 *
 * As três telas de contagem (leitura, por lote e sem lote) passam por aqui.
 * Enquanto eram três cópias, a tela "sem lote" ficou sem o ramo de contagem
 * avulsa e recusava códigos que as outras duas aceitavam.
 */
class ContextoInventario
{
    /** @var string */
    public $codloc = '';

    /** @var string */
    public $codinventario = '';

    /** @var bool Veio do último inventário da sessão, não da URL. */
    public $retomadoDaSessao = false;

    /** @var bool Validado no RM E confirmado por ação do usuário (URL ou Aplicar). */
    public $ativo = false;

    /** @var string STATUS do inventário no RM. */
    public $statusRm = '';

    /** @var string */
    public $nomeLocal = '';

    /** @var bool Contagem avulsa: codigo bem formado que nao existe no RM. */
    public $avulso = false;

    /**
     * @param string $script Página que recebe os redirects de validação.
     * @param array<string, string> $paramsExtra Parâmetros preservados nos redirects.
     * @param bool $forcarValidacao Trata a requisição como ação do usuário mesmo
     *     sem `aplicar` na URL — é o caso do bipe, que chega com o código de
     *     barras e precisa reclamar em voz alta se o inventário não servir.
     */
    public static function resolver(string $script, array $paramsExtra = [], bool $forcarValidacao = false): self
    {
        $ctx = new self();
        $ctx->codloc = isset($_GET['CODLOC']) ? (string) $_GET['CODLOC'] : '';
        $ctx->codinventario = isset($_GET['CODINVENTARIO']) ? (string) $_GET['CODINVENTARIO'] : '';

        $deveValidar = isset($_GET['aplicar']) || $forcarValidacao;
        $veioDaUrl = isset($_GET['CODINVENTARIO']) && trim((string) $_GET['CODINVENTARIO']) !== '';

        if ($ctx->codinventario === '' && SessionManager::hasLastInventario()) {
            $last = SessionManager::getLastInventario();
            $ctx->codloc = (string) ($last['codloc'] ?? '');
            $ctx->codinventario = (string) ($last['codinventario'] ?? '');
            $ctx->retomadoDaSessao = true;
        }

        $parsed = null;
        if ($ctx->codinventario !== '') {
            $parsed = ZMDCODBARRAS::parseCodigoInventario($ctx->codinventario);

            if ($parsed['valid']) {
                $ctx->codinventario = $parsed['formatted'];
                $ctx->codloc = $parsed['codloc'];
            } elseif ($deveValidar) {
                flash_set('danger', $parsed['error']);
                $ctx->redirecionar($script, $paramsExtra);
            } else {
                // Sem ação do usuário: só formata o que dá e segue sem reclamar.
                $ctx->codinventario = ZMDCODBARRAS::formatCodigoInventario($ctx->codinventario);
                $digits = preg_replace('/\D/', '', $ctx->codinventario);
                if (strlen($digits) >= 5) {
                    $ctx->codloc = substr($digits, 2, 3);
                }
                $parsed = ZMDCODBARRAS::parseCodigoInventario($ctx->codinventario);
            }
        }

        if ($ctx->codloc !== '') {
            $local = LocaisEstoque::validar($ctx->codloc);
            if ($local['valid']) {
                $ctx->codloc = $local['codloc'];
            } elseif ($deveValidar) {
                flash_set('danger', $local['error']);
                $ctx->redirecionar($script, $paramsExtra);
            }
        }

        $mascaraOk = is_array($parsed) && !empty($parsed['valid']);

        if ($mascaraOk && $ctx->codloc !== '') {
            // Faixa 9xx e contagem avulsa: vale contar sem o inventario existir no
            // RM. O codigo segue o mesmo formato, entao quando o RM criar o
            // inventario de verdade basta mover a contagem para o codigo dele.
            if (ZMDCODBARRAS::ehCodigoAvulso($ctx->codinventario)) {
                $ctx->avulso = true;
                $ctx->ativo = $deveValidar || $veioDaUrl;
            } else {
                $rm = InventarioRM::validarParaUso($ctx->codinventario, $ctx->codloc);

                if ($rm['valid']) {
                    $ctx->statusRm = $rm['status'];
                    // Sessão sozinha só pré-preenche; entrar na lista exige URL ou Aplicar.
                    $ctx->ativo = $deveValidar || $veioDaUrl;
                } elseif ($deveValidar) {
                    flash_set('danger', $rm['error']);
                    $ctx->redirecionar($script, $paramsExtra);
                } elseif ($veioDaUrl) {
                    // Código veio num link mas não serve: avisa sem entrar na
                    // tela de contagem. Calado, o usuário fica olhando uma tela
                    // de seleção sem entender por que o código dele sumiu.
                    flash_set('warning', $rm['error']);
                }
            }
        }

        $ctx->nomeLocal = LocaisEstoque::nome($ctx->codloc);

        return $ctx;
    }

    /**
     * @param array<string, string> $extra
     * @return array<string, string>
     */
    public function params(array $extra = []): array
    {
        return array_merge([
            'CODLOC'        => $this->codloc,
            'CODINVENTARIO' => $this->codinventario,
        ], $extra);
    }

    /**
     * @param array<string, string> $extra
     */
    private function redirecionar(string $script, array $extra): void
    {
        redirect_to($script . '?' . http_build_query($this->params($extra)));
    }
}
