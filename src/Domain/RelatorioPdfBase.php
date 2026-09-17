<?php

require_once APP_ROOT . DS . 'src' . DS . 'Pdf' . DS . 'fpdf.php';

/**
 * Chrome compartilhado dos relatórios em PDF A4: logo, cabeçalho, rodapé
 * paginado, cabeçalho de tabela, bloco de assinaturas e a conversão de UTF-8
 * para Latin-1 que o FPDF exige.
 *
 * Cada relatório concreto entra só com o resumo e a tabela dele.
 */
abstract class RelatorioPdfBase extends FPDF
{
    protected string $titulo = 'Relatório';
    /** Identificador mostrado na faixa das páginas seguintes (inventário, local...). */
    protected string $referencia = '';
    protected string $localLabel = '';
    protected string $ambiente = '';
    protected string $geradoEm = '';
    protected string $operador = '';
    protected string $logoPath = '';

    /** Logo do cabeçalho; vazio se não houver arquivo. */
    protected static function acharLogo(): string
    {
        $logo = APP_ROOT . DS . 'assets' . DS . 'img' . DS . 'logo.png';

        return is_file($logo) ? $logo : '';
    }

    public function __construct()
    {
        parent::__construct('P', 'mm', 'A4');
        $this->SetAutoPageBreak(true, 28);
        $this->SetMargins(14, 14, 14);
        $this->geradoEm = date('d/m/Y H:i');
    }

    public function Header(): void
    {
        // Cabeçalho completo só na 1ª página; nas seguintes, faixa curta
        if ($this->PageNo() === 1) {
            return;
        }
        $this->SetFont('Arial', 'B', 9);
        $this->SetTextColor(11, 92, 171);
        $this->Cell(0, 6, $this->t($this->titulo . ' — ' . $this->referencia), 0, 1, 'L');
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

    protected function desenharCabecalho(): void
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

    protected function cabecalhoTabela(array $cols): void
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

    protected function desenharAssinaturas(): void
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

    protected function linhaAssinatura(float $x, float $y, float $w, string $titulo, string $subtitulo): void
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

    protected function linhaInfo(string $label, string $valor): void
    {
        $this->SetFont('Arial', 'B', 9);
        $this->Cell(32, 5, $this->t($label . ':'), 0, 0, 'L');
        $this->SetFont('Arial', '', 9);
        $this->Cell(0, 5, $this->t($valor), 0, 1, 'L');
    }

    protected function fmtQtd(float $q): string
    {
        $s = number_format($q, 3, ',', '.');
        return rtrim(rtrim($s, '0'), ',');
    }

    /** Converte UTF-8 → Latin1 para o FPDF. */
    protected function t(string $s): string
    {
        $s = str_replace(["\r", "\n"], ['', ' '], $s);

        // Travessao, aspas curvas e reticencias nao existem em Latin-1 e virariam
        // "?" na pagina. Troca pelos equivalentes ASCII antes de converter.
        $s = strtr($s, [
            "\u{2014}" => '-',  "\u{2013}" => '-',
            "\u{201C}" => '"',  "\u{201D}" => '"',
            "\u{2018}" => "'",  "\u{2019}" => "'",
            "\u{2026}" => '...', "\u{00A0}" => ' ',
        ]);
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
