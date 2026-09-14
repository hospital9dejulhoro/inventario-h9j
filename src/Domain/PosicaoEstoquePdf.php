<?php

require_once APP_ROOT . DS . 'src' . DS . 'Domain' . DS . 'RelatorioPdfBase.php';

/**
 * Posição de estoque por lote em PDF A4 (retrato), para impressão.
 *
 * Custo médio e valor financeiro ficam de fora de propósito: esta é a folha que
 * circula pela prateleira, e valor numa folha de conferência tanto vaza
 * informação de custo quanto tenta quem está contando a "bater" com o esperado.
 * Quem precisa do financeiro usa o CSV ou a tela.
 */
class PosicaoEstoquePdf extends RelatorioPdfBase
{
    protected string $titulo = 'Posição de Estoque por Lote';

    /** @var string */
    private string $grupoLabel = '';

    /**
     * @param array{
     *   codloc: string,
     *   local_label?: string,
     *   grupo_label?: string,
     *   ambiente?: string,
     *   operador?: string,
     *   totais: array{produtos: int, lotes: int, quantidade: float},
     *   itens: array<int, array<string, mixed>>
     * } $dados
     */
    public static function gerar(array $dados): void
    {
        $pdf = new self();
        $pdf->referencia = (string) ($dados['codloc'] ?? '');
        $pdf->localLabel = (string) ($dados['local_label'] ?? '');
        $pdf->grupoLabel = (string) ($dados['grupo_label'] ?? '');
        $pdf->ambiente = (string) ($dados['ambiente'] ?? '');
        $pdf->operador = (string) ($dados['operador'] ?? '');
        $pdf->logoPath = self::acharLogo();

        $pdf->AliasNbPages();
        $pdf->AddPage();
        $pdf->desenharCabecalho();
        $pdf->desenharResumo($dados);
        $pdf->desenharTabela($dados['itens'] ?? []);
        $pdf->desenharAssinaturas();

        $filename = 'posicao-' . preg_replace('/[^0-9A-Za-z._-]/', '-', $pdf->referencia)
            . '-' . date('Ymd-Hi') . '.pdf';
        $pdf->Output('D', $filename);
    }

    private function desenharResumo(array $dados): void
    {
        $totais = $dados['totais'] ?? [];

        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 6, $this->t('Dados da posição'), 0, 1, 'L');
        $this->SetFont('Arial', '', 9);

        if ($this->localLabel !== '') {
            $this->linhaInfo('Local', $this->localLabel);
        }
        if ($this->grupoLabel !== '') {
            $this->linhaInfo('Grupo contábil', $this->grupoLabel);
        }
        $this->linhaInfo('Referência', 'Saldo do momento da emissão (sem data de corte)');

        $this->Ln(3);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 6, $this->t('Totais'), 0, 1, 'L');

        $w = 59.3;
        $h = 16;
        $x = 14;
        $y = $this->GetY();
        $cards = [
            ['Produtos', (string) (int) ($totais['produtos'] ?? 0)],
            ['Lotes', (string) (int) ($totais['lotes'] ?? 0)],
            ['Quantidade', $this->fmtQtd((float) ($totais['quantidade'] ?? 0))],
        ];
        foreach ($cards as $i => $card) {
            $cx = $x + ($i * ($w + 2));
            $this->SetFillColor(248, 251, 255);
            $this->SetDrawColor(229, 231, 235);
            $this->Rect($cx, $y, $w, $h, 'DF');
            $this->SetXY($cx + 2, $y + 2.5);
            $this->SetFont('Arial', 'B', 12);
            $this->SetTextColor(10, 61, 110);
            $this->Cell($w - 4, 6, $this->t($card[1]), 0, 2, 'L');
            $this->SetFont('Arial', '', 8);
            $this->SetTextColor(100, 100, 100);
            $this->Cell($w - 4, 5, $this->t($card[0]), 0, 0, 'L');
        }
        $this->SetTextColor(0, 0, 0);
        $this->SetY($y + $h + 6);
    }

    /**
     * @param array<int, array<string, mixed>> $itens
     */
    private function desenharTabela(array $itens): void
    {
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 6, $this->t('Lotes em estoque'), 0, 1, 'L');
        $this->Ln(1);

        // A4 útil ~182mm (210 - 14*2). Sem custo e sem valor: sobra largura
        // para o nome do produto, que e o que se procura na prateleira.
        $cols = [
            ['Produto', 62],
            ['ID', 16],
            ['Grupo', 32],
            ['Lote', 24],
            ['Validade', 20],
            ['Und', 12],
            ['Saldo', 16],
        ];

        $this->cabecalhoTabela($cols);

        if ($itens === []) {
            $this->SetFont('Arial', 'I', 9);
            $this->Cell(182, 8, $this->t('Nenhum lote com saldo neste local.'), 1, 1, 'C');
            return;
        }

        $fill = false;
        foreach ($itens as $item) {
            $nome = trim((string) ($item['nome'] ?? ''));
            if ($nome === '') {
                $nome = 'ID ' . (int) ($item['idprd'] ?? 0);
            }
            $nomePdf = $this->t($nome);
            if ($this->GetStringWidth($nomePdf) > 60) {
                while ($this->GetStringWidth($nomePdf . '...') > 60 && strlen($nomePdf) > 3) {
                    $nomePdf = substr($nomePdf, 0, -1);
                }
                $nomePdf .= '...';
            }

            $grupo = trim((string) ($item['grupo_nome'] ?? ''));
            if ($grupo === '') {
                $grupo = trim((string) ($item['grupo_cod'] ?? ''));
            }
            $grupoPdf = $this->t($grupo !== '' ? $grupo : '-');
            if ($this->GetStringWidth($grupoPdf) > 30) {
                while ($this->GetStringWidth($grupoPdf . '...') > 30 && strlen($grupoPdf) > 3) {
                    $grupoPdf = substr($grupoPdf, 0, -1);
                }
                $grupoPdf .= '...';
            }

            $rowH = 7;
            if ($this->GetY() + $rowH > ($this->GetPageHeight() - 28)) {
                $this->AddPage();
                $this->cabecalhoTabela($cols);
            }

            $this->SetFillColor($fill ? 248 : 255, $fill ? 250 : 255, $fill ? 252 : 255);
            $this->SetFont('Arial', '', 8);
            $this->SetDrawColor(229, 231, 235);

            $lote = trim((string) ($item['numlote'] ?? ''));
            $validade = trim((string) ($item['validade'] ?? ''));

            $this->Cell(62, $rowH, $nomePdf, 1, 0, 'L', true);
            $this->Cell(16, $rowH, $this->t((string) (int) ($item['idprd'] ?? 0)), 1, 0, 'C', true);
            $this->Cell(32, $rowH, $grupoPdf, 1, 0, 'L', true);
            $this->Cell(24, $rowH, $this->t($lote !== '' ? $lote : '-'), 1, 0, 'C', true);
            $this->Cell(20, $rowH, $this->t($validade !== '' ? $validade : '-'), 1, 0, 'C', true);
            $this->Cell(12, $rowH, $this->t((string) ($item['und'] ?? '')), 1, 0, 'C', true);
            $this->Cell(16, $rowH, $this->t($this->fmtQtd((float) ($item['saldo'] ?? 0))), 1, 1, 'R', true);

            $fill = !$fill;
        }
    }
}
