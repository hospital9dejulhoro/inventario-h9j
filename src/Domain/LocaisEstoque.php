<?php

/**
 * Locais de estoque válidos para inventário.
 */
class LocaisEstoque
{
    /** @var array<string, string> código => descrição */
    private static $locais = [
        '002' => 'UTI GERAL',
        '003' => 'PRONTO ATENDIMENTO',
        '008' => 'ADMINISTRAÇÃO',
        '009' => 'NUTRIÇÃO',
        '010' => 'GERAL ADMINISTRATIVO',
        '011' => 'GERENCIA ENFERMAGEM',
        '012' => 'TI TECNOLOGIA INFORMAÇÃO',
        '013' => 'CENTRO CIRURGICO',
        '014' => 'SEGURANCA DO TRABALHO',
        '017' => 'POSTO B',
        '018' => 'POSTO C',
        '019' => 'POSTO D',
        '021' => 'CARRINHO POSTO B',
        '022' => 'CARRINHO POSTO C',
        '024' => 'CARRINHO UTI',
        '027' => 'CDR',
        '028' => 'ALMOXARIFADO FARMACIA',
        '029' => 'CARRINHO CENTRO CIRURGICO',
        '031' => 'LACTÁRIO (DIETA ENTERAL)',
        '033' => 'CARRINHO POSTO D',
        '035' => 'CARRINHO PRONTO ATENDIMENTO',
        '036' => 'CDR II',
        '050' => 'NOVECATE',
        '051' => 'ENDOSCOPIA',
        '052' => 'RADIOLOGIA',
        '060' => 'TOMOGRAFIA',
        '065' => 'FARMACIA GERAL',
        '067' => 'CARRINHO TOMOGRAFIA',
        '068' => 'AMBULANCIA',
        '075' => 'CENTRAL DE MISTURAS INTRAVENOSAS (CMIV)',
        '076' => 'INTERNAÇÃO',
    ];

    /**
     * @return array<string, string>
     */
    public static function todos(): array
    {
        return self::$locais;
    }

    /**
     * Normaliza para 3 dígitos (ex.: 65 → 065).
     */
    public static function normalizar(string $codloc): string
    {
        $digits = preg_replace('/\D/', '', trim($codloc));
        if ($digits === '') {
            return '';
        }

        return str_pad(substr($digits, -3), 3, '0', STR_PAD_LEFT);
    }

    public static function existe(string $codloc): bool
    {
        $codloc = self::normalizar($codloc);
        return $codloc !== '' && isset(self::$locais[$codloc]);
    }

    public static function nome(string $codloc): string
    {
        $codloc = self::normalizar($codloc);
        return self::$locais[$codloc] ?? '';
    }

    /**
     * @return array{valid: bool, codloc: string, nome: string, error: string}
     */
    public static function validar(string $codloc): array
    {
        $codloc = self::normalizar($codloc);

        if ($codloc === '' || strlen($codloc) !== 3) {
            return [
                'valid'  => false,
                'codloc' => $codloc,
                'nome'   => '',
                'error'  => 'Informe o local de estoque com 3 dígitos.',
            ];
        }

        if (!isset(self::$locais[$codloc])) {
            return [
                'valid'  => false,
                'codloc' => $codloc,
                'nome'   => '',
                'error'  => "Local de estoque {$codloc} não é válido. Use um código cadastrado (ex.: 065 — FARMACIA GERAL).",
            ];
        }

        return [
            'valid'  => true,
            'codloc' => $codloc,
            'nome'   => self::$locais[$codloc],
            'error'  => '',
        ];
    }
}
