<?php

require_once APP_ROOT . DS . 'src' . DS . 'Pdf' . DS . 'fpdf.php';

/**
 * Relatório de contagem em PDF A4 (retrato) com logo e assinaturas.
 */
class RelatorioContagemPdf extends FPDF
{
    private string $titulo = 'Relatório de Contagem de Inventário';
    private string $codinventario = '';
    private string $localLabel = '';
    private string $ambiente = '';
    private string $geradoEm = '';
    private string $operador = '';
    private string $logoPath = '';

    public function __construct()
    {
        parent::__construct('P', 'mm', 'A4');
        $this->SetAutoPageBreak(true, 28);
        $this->SetMargins(14, 14, 14);
        $this->geradoEm = date('d/m/Y H:i');
    }

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
        $pdf->codinventario = (string) ($dados['codinventario'] ?? '');
        $pdf->localLabel = (string) ($dados['local_label'] ?? '');
        $pdf->ambiente = (string) ($dados['ambiente'] ?? '');
        $pdf->operador = (string) ($dados['operador'] ?? '');
        $logo = APP_ROOT . DS . 'assets' . DS . 'img' . DS . 'logo.png';
        if (!is_file($logo)) {
            $logo = APP_ROOT . DS . 'logo.png';
        }
        $pdf->logoPath = is_file($logo) ? $logo : '';

        $pdf->AliasNbPages();
        $pdf->AddPage();
        $pdf->desenharCabecalho();
        $pdf->desenharResumo($dados);
        $pdf->desenharTabela($dados['itens'] ?? []);
        $pdf->desenharAssinaturas();

        $filename = 'contagem-' . preg_replace('/[^0-9A-Za-z._-]/', '-', $pdf->codinventario) . '.pdf';
        $pdf->Output('D', $filename);
    }

    public function Header(): void
    {
        // Cabeçalho completo só na 1ª página; nas seguintes, faixa curta
        if ($this->PageNo() === 1) {
            return;
        }
        $this->SetFont('Arial', 'B', 9);
        $this->SetTextColor(11, 92, 171);
        $this->Cell(0, 6, $this->t($this->titulo . ' — ' . $this->codinventario), 0, 1, 'L');
        $this->SetDrawColor(11, 92, 171);
        $this->SetLineWidth(0.3);
        $this->Line(14, $this->GetY(), 196, $this->GetY());
        $this->Ln(4);
        $this->SetTextColor(0, 0, 0);
    }

    public function Footer(): void
    {
        $this->SetY(-16);
        $this->SetDrawColor(200, 200, 200);
        $this->SetLineWidth(0.2);
        $this->Line(14, $this->GetY(), 196, $this->GetY());
        $this->Ln(2);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(100, 100, 100);
        $this->Cell(90, 5, $this->t('Hospital 9 de Julho — Inventário RM'), 0, 0, 'L');
        $this->Cell(92, 5, $this->t('Página ' . $this->PageNo() . '/{nb}'), 0, 0, 'R');
    }

    private function desenharCabecalho(): void
    {
        $y0 = $this->GetY();
        if ($this->logoPath !== '') {
            // Logo à esquerda (largura ~42mm, altura proporcional)
            $this->Image($this->logoPath, 14, $y0, 42);
        }

        $this->SetXY(60, $y0);
        $this->SetFont('Arial', 'B', 13);
        $this->SetTextColor(10, 61, 110);
        $this->Cell(122, 7, $this->t('Hospital 9 de Julho de Rondônia'), 0, 2, 'L');
        $this->SetFont('Arial', 'B', 11);
        $this->SetTextColor(11, 92, 171);
        $this->Cell(122, 6, $this->t($this->titulo), 0, 2, 'L');
        $this->SetFont('Arial', '', 9);
        $this->SetTextColor(80, 80, 80);
        $this->Cell(122, 5, $this->t('Gerado em ' . $this->geradoEm), 0, 2, 'L');
        if ($this->ambiente !== '') {
            $this->Cell(122, 5, $this->t('Ambiente: ' . $this->ambiente), 0, 2, 'L');
        }
        if ($this->operador !== '') {
            $this->Cell(122, 5, $this->t('Operador: ' . $this->operador), 0, 2, 'L');
        }

        $this->SetY(max($y0 + 28, $this->GetY() + 2));
        $this->SetDrawColor(11, 92, 171);
        $this->SetLineWidth(0.5);
        $this->Line(14, $this->GetY(), 196, $this->GetY());
        $this->Ln(5);
        $this->SetTextColor(0, 0, 0);
    }

    private function desenharResumo(array $dados): void
    {
        $totais = $dados['totais'] ?? [];
        $status = (string) ($dados['status_rm'] ?? '');

        $this->SetFont('Arial', 'B', 10);
        $this->Cell(0, 6, $this->t('Dados do inventário'), 0, 1, 'L');
        $this->SetFont('Arial', '', 9);

        $this->linhaInfo('Inventário', $this->codinventario);
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

    private function cabecalhoTabela(array $cols): void
    {
        $this->SetFont('Arial', 'B', 8);
        $this->SetFillColor(11, 92, 171);
        $this->SetTextColor(255, 255, 255);
        $this->SetDrawColor(11, 92, 171);
        foreach ($cols as $col) {
            $this->Cell($col[1], 7, $this->t($col[0]), 1, 0, 'C', true);
        }
        $this->Ln();
        $this->SetTextColor(0, 0, 0);
        $this->SetDrawColor(229, 231, 235);
    }

    private function desenharAssinaturas(): void
    {
        // Reserva espaço; se não couber, nova página
        $blocoH = 55;
        if ($this->GetY() + $blocoH > ($this->GetPageHeight() - 20)) {
            $this->AddPage();
        }

        $this->Ln(8);
        $this->SetDrawColor(11, 92, 171);
        $this->SetLineWidth(0.4);
        $this->Line(14, $this->GetY(), 196, $this->GetY());
        $this->Ln(5);

        $this->SetFont('Arial', 'B', 10);
        $this->SetTextColor(10, 61, 110);
        $this->Cell(0, 6, $this->t('Assinaturas'), 0, 1, 'L');
        $this->SetTextColor(0, 0, 0);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(90, 90, 90);
        $this->MultiCell(0, 4, $this->t('Declaro que a contagem acima confere com o inventário físico realizado.'), 0, 'L');
        $this->SetTextColor(0, 0, 0);
        $this->Ln(8);

        $y = $this->GetY();
        $w = 85;
        $gap = 12;
        $x1 = 14;
        $x2 = 14 + $w + $gap;

        $this->linhaAssinatura($x1, $y, $w, 'Responsável pela contagem', 'Nome / Assinatura');
        $this->linhaAssinatura($x2, $y, $w, 'Conferente / Supervisor', 'Nome / Assinatura');

        $this->SetY($y + 28);
        $this->Ln(4);
        $this->SetFont('Arial', '', 9);
        $this->Cell(40, 6, $this->t('Data: ____/____/________'), 0, 0, 'L');
        $this->Cell(0, 6, $this->t('Local: ________________________________'), 0, 1, 'L');
    }

    private function linhaAssinatura(float $x, float $y, float $w, string $titulo, string $subtitulo): void
    {
        $this->SetXY($x, $y);
        $this->SetFont('Arial', 'B', 8);
        $this->Cell($w, 5, $this->t($titulo), 0, 2, 'C');
        $this->Ln(12);
        $this->SetX($x);
        $this->SetDrawColor(60, 60, 60);
        $this->SetLineWidth(0.3);
        $this->Line($x + 4, $this->GetY(), $x + $w - 4, $this->GetY());
        $this->Ln(2);
        $this->SetX($x);
        $this->SetFont('Arial', '', 8);
        $this->SetTextColor(100, 100, 100);
        $this->Cell($w, 5, $this->t($subtitulo), 0, 0, 'C');
        $this->SetTextColor(0, 0, 0);
    }

    private function linhaInfo(string $label, string $valor): void
    {
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(32, 5, $this->t($label . ':'), 0, 0, 'L');
        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 5, $this->t($valor), 0, 1, 'L');
    }

    private function fmtQtd(float $q): string
    {
        $s = number_format($q, 3, ',', '.');
        return rtrim(rtrim($s, '0'), ',');
    }

    /** Converte UTF-8 → Latin1 para o FPDF. */
    private function t(string $s): string
    {
        $s = str_replace(["\r", "\n"], ['', ' '], $s);
        if (function_exists('mb_convert_encoding')) {
            $out = @mb_convert_encoding($s, 'ISO-8859-1', 'UTF-8');
            if (is_string($out) && $out !== '') {
                return $out;
            }
        }
        $out = @iconv('UTF-8', 'ISO-8859-1//TRANSLIT', $s);
        return is_string($out) ? $out : $s;
    }
}
