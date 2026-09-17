/* ============================================================================
   PROPOSTA — colunas de auditoria em ZMDCODBARRAS
   ============================================================================

   NAO EXECUTE SEM O AVAL DO DBA. Este script altera uma tabela do banco do RM.
   Ele esta aqui como proposta revisavel, nao como parte do deploy.

   ----------------------------------------------------------------------------
   PROBLEMA
   ----------------------------------------------------------------------------
   ZMDCODBARRAS guarda hoje: ID, CODIGOBARRAS, CODINVENTARIO, QUANTIDADE, CODLOC.

   Nao existe quem contou nem quando. Enquanto o inventario era contado por uma
   pessoa de cada vez, isso passava. Com 10 operadores no mesmo inventario:

     - Uma divergencia de contagem nao tem como ser rastreada ate quem contou.
     - O aviso de releitura ("3a leitura deste lote") nao consegue dizer se foi
       repeticao do proprio operador ou a contagem legitima de um colega. Hoje
       a tela avisa as duas possibilidades porque nao tem como distinguir.
     - "Excluir registro" apaga a linha de qualquer um, sem registro de quem
       apagou.
     - A ordenacao da listagem usa ID DESC como aproximacao de "mais recente",
       que funciona por acidente do IDENTITY, nao por desenho.

   ----------------------------------------------------------------------------
   PROPOSTA
   ----------------------------------------------------------------------------
   Duas colunas opcionais, com DEFAULT, para nao quebrar nada que ja grava:

     CODUSUARIO   VARCHAR(30) NULL  -- CODUSUARIO do RM que gravou a leitura
     DATAGRAVACAO DATETIME    NULL  -- DEFAULT GETDATE()

   Ambas aceitam NULL de proposito: as linhas que ja existem nao tem essa
   informacao e inventar um valor para elas seria pior que deixar em branco.

   Depois de aplicado, o codigo passa a preencher as duas em ZMDCODBARRAS::save()
   e em corrigirTotalProdutoLote(), e as telas podem mostrar "contado por FULANO
   as 14:32". Enquanto o script nao roda, o app continua funcionando igual.

   ----------------------------------------------------------------------------
   IMPACTO
   ----------------------------------------------------------------------------
   ALTER TABLE ADD com coluna NULL e uma operacao de metadados no SQL Server:
   nao reescreve a tabela e nao trava a contagem. O DEFAULT em coluna NULL
   tambem nao forca reescrita (SQL Server 2012+).

   Rode em CADA ambiente (CorporeRM, HomologaRM, ontemrm) separadamente.
   ========================================================================== */

SET NOCOUNT ON;

/* -- PASSO 1 -- Como esta hoje --------------------------------------------- */
SELECT
    c.name        AS COLUNA,
    t.name        AS TIPO,
    c.max_length  AS TAMANHO,
    c.is_nullable AS ACEITA_NULO
FROM sys.columns c
JOIN sys.types t ON t.user_type_id = c.user_type_id
WHERE c.object_id = OBJECT_ID('ZMDCODBARRAS')
ORDER BY c.column_id;

SELECT COUNT(*) AS LINHAS_HOJE FROM ZMDCODBARRAS;


/* -- PASSO 2 -- Aplicar (revise o PASSO 1 antes de descomentar) ------------- */
/*
BEGIN TRANSACTION;

IF NOT EXISTS (SELECT 1 FROM sys.columns
               WHERE object_id = OBJECT_ID('ZMDCODBARRAS') AND name = 'CODUSUARIO')
BEGIN
    ALTER TABLE ZMDCODBARRAS ADD CODUSUARIO VARCHAR(30) NULL;
END;

IF NOT EXISTS (SELECT 1 FROM sys.columns
               WHERE object_id = OBJECT_ID('ZMDCODBARRAS') AND name = 'DATAGRAVACAO')
BEGIN
    ALTER TABLE ZMDCODBARRAS ADD DATAGRAVACAO DATETIME NULL
        CONSTRAINT DF_ZMDCODBARRAS_DATAGRAVACAO DEFAULT (GETDATE());
END;

-- Confira e so entao confirme.
-- COMMIT TRANSACTION;
-- ROLLBACK TRANSACTION;
*/


/* -- PASSO 3 -- Indice de apoio (opcional, avalie o custo de escrita) -------
   Toda consulta de totais filtra por CODINVENTARIO. Se a tabela crescer, este
   indice ajuda; em troca, encarece um pouco cada bipagem.

IF NOT EXISTS (SELECT 1 FROM sys.indexes
               WHERE object_id = OBJECT_ID('ZMDCODBARRAS') AND name = 'IX_ZMDCODBARRAS_CODINVENTARIO')
BEGIN
    CREATE NONCLUSTERED INDEX IX_ZMDCODBARRAS_CODINVENTARIO
        ON ZMDCODBARRAS (CODINVENTARIO)
        INCLUDE (CODIGOBARRAS, QUANTIDADE, CODLOC);
END;
*/


/* -- PASSO 4 -- Verificacao ------------------------------------------------- */
SELECT
    SUM(CASE WHEN CODUSUARIO   IS NULL THEN 1 ELSE 0 END) AS SEM_USUARIO,
    SUM(CASE WHEN DATAGRAVACAO IS NULL THEN 1 ELSE 0 END) AS SEM_DATA,
    COUNT(*)                                              AS TOTAL
FROM ZMDCODBARRAS;
/* Esperado logo apos o ALTER: SEM_USUARIO = SEM_DATA = TOTAL (as linhas
   antigas nao tem a informacao). A partir da proxima bipagem, os numeros
   param de crescer. */
