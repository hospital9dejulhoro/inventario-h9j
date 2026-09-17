<?php

/**
 * Erro cuja mensagem pode ser mostrada ao usuário.
 *
 * O tratador de exceções do bootstrap só repassa para a tela a mensagem de um
 * AppException. Qualquer outra vira um texto genérico, porque mensagens de
 * exceção comuns carregam caminho de arquivo, nome de tabela e afins.
 *
 * detalhe() é o texto técnico que vai para o log e nunca para a tela.
 */
class AppException extends RuntimeException
{
    /** @var string */
    private $detalhe;

    public function __construct(string $mensagem, string $detalhe = '')
    {
        parent::__construct($mensagem);
        $this->detalhe = $detalhe !== '' ? $detalhe : $mensagem;
    }

    public function detalhe(): string
    {
        return $this->detalhe;
    }
}
