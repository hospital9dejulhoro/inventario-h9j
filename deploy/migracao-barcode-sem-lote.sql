/* ============================================================================
   Migracao — alinhamento do CODIGOBARRAS dos itens contados pela aba "Sem lote"
   ============================================================================

   CONTEXTO
   Ate a correcao, ZMDCODBARRAS::barcodeSemLote() gravava o IDPRD em 7 digitos:
       pad7(IDPRD) + '000000'
   Mas todas as leituras usam SUBSTRING(CODIGOBARRAS, 0, 7), que no SQL Server
   comeca no 1o caractere e devolve 6 caracteres (start + length - 1), nao 7.
   Resultado: o item era lido como outro produto (IDPRD sem o ultimo digito).
   O formato correto e:
       pad6(IDPRD) + '0000000'

   AFETA  totaisPorProduto() (coluna "Ja" / "ja contado") e relatorioContagem()
          (relatorio em tela, CSV e PDF).

   ----------------------------------------------------------------------------
   ORDEM DE EXECUCAO — IMPORTANTE
   ----------------------------------------------------------------------------
   1. ANTES de publicar o codigo corrigido, rode o PASSO 0 e anote o @MaxId.
      Isso separa as linhas no formato antigo das que o codigo novo vai gravar.
      As duas formas sao indistinguiveis quando o IDPRD antigo termina em 0
      (ex.: antigo IDPRD 1230 e novo IDPRD 123 geram a mesma string).
   2. Publique o codigo corrigido.
   3. Rode os PASSOS 1 a 4 com o @MaxId anotado.

   Rode em CADA ambiente (CorporeRM, HomologaRM, ontemrm) separadamente.
   ========================================================================== */

SET NOCOUNT ON;

/* -- PASSO 0 -- ANTES do deploy: anote este numero -------------------------- */
SELECT MAX(ID) AS MaxIdAntesDoDeploy FROM ZMDCODBARRAS;


/* -- PASSO 1 -- Preview: o que seria alterado ------------------------------- */
DECLARE @MaxId INT = 0;   /* <<< substitua pelo valor do PASSO 0 */

;WITH candidatas AS (
    SELECT
        Z.ID,
        Z.CODINVENTARIO,
        Z.CODLOC,
        Z.QUANTIDADE,
        Z.CODIGOBARRAS                                   AS BarcodeAtual,
        CONVERT(INT, LEFT(Z.CODIGOBARRAS, 7))            AS IdprdCorreto,
        CONVERT(INT, SUBSTRING(Z.CODIGOBARRAS, 0, 7))    AS IdprdLidoHoje
    FROM ZMDCODBARRAS Z
    WHERE Z.ID <= @MaxId
      AND LEN(Z.CODIGOBARRAS) = 13
      AND Z.CODIGOBARRAS NOT LIKE '%[^0-9]%'
      AND RIGHT(Z.CODIGOBARRAS, 6) = '000000'            /* padrao "sem lote" */
)
SELECT
    C.*,
    RIGHT('000000' + CAST(C.IdprdCorreto AS VARCHAR(7)), 6) + '0000000' AS BarcodeNovo,
    P.NOMEFANTASIA                                        AS ProdutoCorreto,
    PX.NOMEFANTASIA                                       AS ProdutoContabilizadoHoje
FROM candidatas C
LEFT JOIN TPRODUTO P  ON P.IDPRD  = C.IdprdCorreto
LEFT JOIN TPRODUTO PX ON PX.IDPRD = C.IdprdLidoHoje
WHERE C.IdprdCorreto BETWEEN 1 AND 999999
  AND P.IDPRD IS NOT NULL                                /* produto real */
  AND NOT EXISTS (SELECT 1 FROM TLOTEPRD L WHERE L.IDPRD = C.IdprdCorreto)
ORDER BY C.CODINVENTARIO, C.ID;

/* Linhas que NAO serao migradas (revise manualmente antes de prosseguir):
   IDPRD acima de 999999 nao cabe no layout de 13 digitos. */
SELECT ID, CODINVENTARIO, CODIGOBARRAS, CONVERT(INT, LEFT(CODIGOBARRAS, 7)) AS IdprdCorreto
FROM ZMDCODBARRAS
WHERE ID <= @MaxId
  AND LEN(CODIGOBARRAS) = 13
  AND CODIGOBARRAS NOT LIKE '%[^0-9]%'
  AND RIGHT(CODIGOBARRAS, 6) = '000000'
  AND CONVERT(INT, LEFT(CODIGOBARRAS, 7)) > 999999;


/* -- PASSO 2 -- Backup completo da tabela ----------------------------------- */
/* Ajuste o sufixo da data. Guarde ate conferir o relatorio pos-migracao.
SELECT * INTO ZMDCODBARRAS_BKP_20260914 FROM ZMDCODBARRAS;
SELECT COUNT(*) AS LinhasNoBackup FROM ZMDCODBARRAS_BKP_20260914;
*/


/* -- PASSO 3 -- Correcao (revise o PASSO 1 antes de descomentar) ------------ */
/*
BEGIN TRANSACTION;

UPDATE Z
SET Z.CODIGOBARRAS =
        RIGHT('000000' + CAST(CONVERT(INT, LEFT(Z.CODIGOBARRAS, 7)) AS VARCHAR(7)), 6)
        + '0000000'
FROM ZMDCODBARRAS Z
WHERE Z.ID <= @MaxId
  AND LEN(Z.CODIGOBARRAS) = 13
  AND Z.CODIGOBARRAS NOT LIKE '%[^0-9]%'
  AND RIGHT(Z.CODIGOBARRAS, 6) = '000000'
  AND CONVERT(INT, LEFT(Z.CODIGOBARRAS, 7)) BETWEEN 1 AND 999999
  AND EXISTS (SELECT 1 FROM TPRODUTO P
               WHERE P.IDPRD = CONVERT(INT, LEFT(Z.CODIGOBARRAS, 7)))
  AND NOT EXISTS (SELECT 1 FROM TLOTEPRD L
                   WHERE L.IDPRD = CONVERT(INT, LEFT(Z.CODIGOBARRAS, 7)));

SELECT @@ROWCOUNT AS LinhasCorrigidas;

-- Confira o numero acima contra o PASSO 1. Batendo: COMMIT. Senao: ROLLBACK.
-- COMMIT TRANSACTION;
-- ROLLBACK TRANSACTION;
*/


/* -- PASSO 4 -- Verificacao pos-migracao ------------------------------------
   Todo item "sem lote" deve casar com um produto real de TPRODUTO.
   O ideal e esta consulta nao devolver nenhuma linha.                        */
SELECT Z.ID, Z.CODINVENTARIO, Z.CODIGOBARRAS,
       CONVERT(INT, SUBSTRING(Z.CODIGOBARRAS, 0, 7)) AS IdprdLido
FROM ZMDCODBARRAS Z
WHERE RIGHT(Z.CODIGOBARRAS, 7) = '0000000'
  AND NOT EXISTS (SELECT 1 FROM TPRODUTO P
                   WHERE P.IDPRD = CONVERT(INT, SUBSTRING(Z.CODIGOBARRAS, 0, 7)));
