<?php

require_once APP_ROOT . DS . 'src' . DS . 'Domain' . DS . 'RelatorioPdfBase.php';

/**
 * Relatório de contagem em PDF A4 (retrato) com logo e assinaturas.
 */
class RelatorioContagemPdf extends RelatorioPdfBase
{
    protected string $titulo = 'Relatório de Contagem de Inventário';

    /**
     * @param array{
     *   codinventario: string,
     *   local_label?: string,
     *   ambiente?: string,
     *   operador?: string,
     *   status_rm?: string,
     *   totais: array{bipagens: int, quantidade: float, produtos: int, lotes: int},
     *   itens: array<int, array{idprd: int, nome: string, und: string, lote: string, codloc: string, bipagens: int, quantidade: float}>
     * } $dados
     */
    public static function gerar(array $dados): void
    {
        $pdf = new self();
        $pdf->referencia = (string) ($dados['codinventario'] ?? '');
        $pdf->localLabel = (string) ($dados['local_label'] ?? '');
        $pdf->ambiente = (string) ($dados['ambiente'] ?? '');
        $pdf->operador = (string) ($dados['operador'] ?? '');
        $pdf->logoPath = self::acharLogo();

        $pdf->AliasNbPages();
        $pdf->AddPage();
        $pdf->desenharCabecalho();
        $pdf->desenharResumo($dados);
        $pdf->desenharTabela($dados['itens'] ?? []);
        $pdf->desenharAssinaturas();

        $filename = 'contagem-' . preg_replace('/[^0-9A-Za-z._-]/', '-', $pdf->referencia) . '.pdf';
        $pdf->Output('D', $filename);
    }

    private function desenharResumo(array $dados): void
    {
        $totais = $dados['totais'] ?? [];
        $status = (string) ($dados['status_rm'] ?? '');

        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 6, $this->t('Dados do inventário'), 0, 1, 'L');
        $this->SetFont('Arial', '', 9);

        $this->linhaInfo('Inventário', $this->referencia);
        if ($this->localLabel !== '') {
            $this->linhaInfo('Local', $this->localLabel);
        }
        if ($status !== '') {
            $this->linhaInfo('Status RM', $status);
        }

        $this->Ln(3);
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 6, $this->t('Totais da contagem'), 0, 1, 'L');

        $w = 45.5;
        $h = 16;
        $x = 14;
        $y = $this->GetY();
        $cards = [
            ['Bipagens', (string) (int) ($totais['bipagens'] ?? 0)],
            ['Quantidade', $this->fmtQtd((float) ($totais['quantidade'] ?? 0))],
            ['Produtos', (string) (int) ($totais['produtos'] ?? 0)],
            ['Lotes', (string) (int) ($totais['lotes'] ?? 0)],
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
     * @param array<int, array{idprd: int, nome: string, und: string, lote: string, codloc: string, bipagens: int, quantidade: float}> $itens
     */
    private function desenharTabela(array $itens): void
    {
        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 6, $this->t('Itens contados (agrupados por produto / lote / local)'), 0, 1, 'L');
        $this->Ln(1);

        // Larguras A4 útil ~182mm (210 - 14*2)
        $cols = [
            ['Produto', 68],
            ['ID', 16],
            ['Lote', 24],
            ['Local', 16],
            ['Und', 12],
            ['Bip.', 16],
            ['Qtd', 30],
        ];

        $this->cabecalhoTabela($cols);

        if ($itens === []) {
            $this->SetFont('Arial', 'I', 9);
            $this->Cell(182, 8, $this->t('Nenhuma bipagem neste inventário.'), 1, 1, 'C');
            return;
        }

        $fill = false;
        foreach ($itens as $item) {
            $nome = trim((string) ($item['nome'] ?? ''));
            if ($nome === '') {
                $nome = '—';
            }
            // Uma linha por item (A4); corta nome longo
            $nomePdf = $this->t($nome);
            if ($this->GetStringWidth($nomePdf) > 66) {
                while ($this->GetStringWidth($nomePdf . '...') > 66 && strlen($nomePdf) > 3) {
                    $nomePdf = substr($nomePdf, 0, -1);
                }
                $nomePdf .= '...';
            }
            $rowH = 7;

            if ($this->GetY() + $rowH > ($this->GetPageHeight() - 28)) {
                $this->AddPage();
                $this->cabecalhoTabela($cols);
            }

            if ($fill) {
                $this->SetFillColor(248, 250, 252);
            } else {
                $this->SetFillColor(255, 255, 255);
            }

            $this->SetFont('Arial', '', 8);
            $this->SetDrawColor(229, 231, 235);
            $lote = trim((string) ($item['lote'] ?? ''));

            $this->Cell(68, $rowH, $nomePdf, 1, 0, 'L', true);
            $this->Cell(16, $rowH, $this->t((string) (int) ($item['idprd'] ?? 0)), 1, 0, 'C', true);
            $this->Cell(24, $rowH, $this->t($lote !== '' ? $lote : '—'), 1, 0, 'C', true);
            $this->Cell(16, $rowH, $this->t((string) ($item['codloc'] ?? '')), 1, 0, 'C', true);
            $this->Cell(12, $rowH, $this->t((string) ($item['und'] ?? '')), 1, 0, 'C', true);
            $this->Cell(16, $rowH, $this->t((string) (int) ($item['bipagens'] ?? 0)), 1, 0, 'C', true);
            $this->Cell(30, $rowH, $this->t($this->fmtQtd((float) ($item['quantidade'] ?? 0))), 1, 1, 'R', true);

            $fill = !$fill;
        }
    }
}
