# Inventário RM — Aplicação Unificada

Sistema unificado para leitura de código de barras em inventários do **TOTVS RM**, substituindo as pastas duplicadas `h9/` (produção) e `teste/` (homologação).

> **Como funciona (Inventário + RH + autenticação):** [docs/COMO-FUNCIONA.md](docs/COMO-FUNCIONA.md)

## Git — versionamento e deploy

### Primeira vez (desenvolvimento)

```bash
cd inventario
git init
git add .
git commit -m "Initial commit: inventário RM unificado"
```

Crie um repositório no GitHub/GitLab e envie:

```bash
git remote add origin https://github.com/hospital9dejulhoro/inventario-h9j.git
git branch -M main
git push -u origin main
```

### Configuração de credenciais (não vai para o Git)

O arquivo `config/environments.php` está no `.gitignore`. Use o exemplo:

```bash
cp config/environments.example.php config/environments.php
# Edite host, banco, usuário e senha
```

### Deploy no servidor via Git

```bash
# Primeira vez no servidor
sudo mv /var/www/inventario /var/www/inventario.bak
sudo git clone https://github.com/hospital9dejulhoro/inventario-h9j.git /var/www/inventario
cd /var/www/inventario
sudo cp config/environments.example.php config/environments.php
sudo nano config/environments.php
sudo chown -R www-data:www-data /var/www/inventario
```

Atualizações futuras:

```bash
cd /var/www/inventario
sudo git config --global --add safe.directory /var/www/inventario
sudo git pull
sudo chown -R www-data:www-data /var/www/inventario
sudo systemctl restart php8.3-fpm
```

Ou use o script: `sudo bash deploy/atualizar-servidor.sh`

### Produção

URL: `http://172.20.0.43:9080/`

---

## Arquitetura

```
inventario/
├── index.php                 # Login e seleção de ambiente
├── conectar.php              # Autentica no RM e abre a sessão
├── desconectar.php           # Encerra a sessão
├── inventario.php            # Leitura por bipe (13 dígitos)
├── inventario-item.php       # Editar / excluir registro e inventário (POST)
├── por-lote.php              # Contagem por lote, a partir da posição do local
├── por-lote-salvar.php       # Grava a contagem por lote (JSON)
├── sem-lote.php              # Folha dos produtos sem cadastro de lote
├── sem-lote-salvar.php       # Grava a contagem sem lote (JSON)
├── vincular-contagem.php     # Move uma contagem avulsa para o código do RM
├── contagem-totais.php       # Totais já contados, para as telas atualizarem (JSON)
├── relatorio.php             # Relatório de contagem + conferência (tela/CSV/PDF)
├── posicao.php               # Posição de estoque do local (tela/CSV/PDF)
├── bootstrap.php             # Sessão, configuração, autoload manual, erros
├── config/
│   ├── app.php               # base_path e debug
│   └── environments.php      # Conexão por ambiente (fora do Git)
├── src/
│   ├── Config/EnvironmentManager.php   # Ambientes, timeouts, teste de conexão
│   ├── Database/Connection.php         # SQL Server (sqlsrv) com parâmetros
│   ├── Database/DatabaseException.php  # Erro de banco: mensagem x detalhe
│   ├── Support/AppException.php        # Erro com mensagem exibível
│   ├── Domain/
│   │   ├── ZMDCODBARRAS.php            # Contagem: gravação, totais, avulsas
│   │   ├── InventarioRM.php            # TINVENTARIO / TITMINVENTARIO / posição
│   │   ├── ContextoInventario.php      # Resolve inventário+local das 3 telas
│   │   ├── LocaisEstoque.php           # Locais válidos
│   │   ├── RmAuth.php                  # Autenticação (API REST, SOAP, GUSUARIO)
│   │   ├── RelatorioPdfBase.php        # Chrome comum dos PDFs A4
│   │   ├── RelatorioContagemPdf.php
│   │   └── PosicaoEstoquePdf.php
│   ├── Http/SessionManager.php         # Estado da sessão
│   ├── Helpers/functions.php           # e(), CSRF, quantidades, log, erros
│   └── Pdf/                            # FPDF
├── views/                              # layout, home, inventario, por-lote,
│                                       # sem-lote, relatorio, posicao, _avulsas
└── assets/                             # css/app.css, js/{app,por-lote,sem-lote}.js
```

## Separação de responsabilidades

| Camada | Responsabilidade |
|--------|------------------|
| `config/environments.php` | Dados de conexão e timeouts por ambiente |
| `EnvironmentManager` | Ambientes, opções do driver, teste de conexão |
| `SessionManager` | Estado da sessão (ambiente, usuário, inventário atual) |
| `Connection` | Acesso ao SQL Server, sempre com parâmetros |
| `ContextoInventario` | Máscara AA.LLL.NNN, local, validação no RM, avulsa |
| `ZMDCODBARRAS` | Contagem gravada: inserir, corrigir, totalizar |
| `InventarioRM` | O que o RM diz: inventário, itens, posição de estoque |
| `RmAuth` | Autenticação do usuário no RM |
| `views/` | Apresentação HTML |
| Arquivos da raiz | Controllers leves (orquestração) |

## Ambientes configurados

| Chave | Label | Host | Banco |
|-------|-------|------|-------|
| `producao` | Produção | 172.20.0.10 | CorporeRM |
| `homologacao` | Homologação | 172.20.0.15 | HomologaRM |
| `testes` | Testes | 172.20.0.15 | ontemrm |

### Adicionar um novo ambiente

Edite apenas `config/environments.php` — nenhuma alteração de código é
necessária. Veja `config/environments.example.php` para a lista completa de
chaves, incluindo as opcionais `query_timeout`, `login_timeout` e
`api_fallbacks`.

## Fluxo do usuário

1. Acessar a aplicação e escolher **Produção**, **Homologação** ou **Testes**
2. Entrar com usuário e senha do RM (validados pela API do RM Host)
3. Escolher um inventário em aberto, digitar o código `AA.LLL.NNN`, ou abrir
   uma **contagem avulsa** quando o inventário ainda não existe no RM
4. Contar por um dos três caminhos:
   - **Leitura** — bipar a etiqueta de 13 dígitos
   - **Por lote** — escolher a linha na posição do local e digitar a quantidade
   - **Sem lote** — folha dos produtos que não têm cadastro de lote
5. Conferir em **Relatório** (totais, conferência do local) e exportar CSV/PDF
6. Quando o RM criar o inventário de verdade, **Vincular ao RM** move a
   contagem avulsa para o código definitivo — ou **Descartar**, se a contagem
   não prestar

## Contagem avulsa

Um código avulso é um `AA.LLL.NNN` bem formado com `NNN` a partir de 900 que
simplesmente não existe em `TINVENTARIO`. Manter o mesmo formato tem três
motivos: cabe na coluna `CODINVENTARIO` como qualquer outro, todas as telas e
relatórios já sabem ler, e quando o inventário de verdade for criado no RM
basta mover a contagem.

As três telas de contagem aceitam avulsas. Como não há itens gerados no RM
contra os quais conferir, a lista de produtos vem da posição de estoque do
local — inclusive na tela **Sem lote**, que para uma avulsa lista os produtos
sem `TLOTEPRD` com posição no local (com a opção de incluir os zerados).

## Várias pessoas contando ao mesmo tempo

- Bipar acumula: duas leituras do mesmo lote somam, porque contar duas caixas
  é legítimo. O aviso de releitura não acusa ninguém — pode ter sido um colega.
- **Corrigir o total** substitui, e roda numa transação: quem grava por último
  vence. Sem ela, duas correções simultâneas do mesmo lote somavam em vez de
  substituir, e os dois operadores liam "Total corrigido".
- Se o total gravado sair diferente do pedido, a tela avisa: alguém mexeu
  naquele lote entre a leitura da tela e o clique.
- **Atualizar totais** recarrega a coluna "Já contado" sem recarregar a
  página, e ela se atualiza sozinha quando a aba volta ao foco. Sem polling:
  dez telas consultando em laço ocupariam os processos do PHP-FPM à toa.
- Excluir inventário e descartar avulsa exigem digitar o código — o que some
  é a contagem de todos.
- `pm.max_children` do PHP-FPM é quantas requisições são atendidas ao mesmo
  tempo. O padrão do Ubuntu é 5; `deploy/install-ubuntu.sh` ajusta para 16.

## Regras de negócio

- Layout do código de barras: `IDPRD` nos dígitos 1-6, `IDLOTE` nos 8-12.
  `SUBSTRING(CODIGOBARRAS, 0, 7)` no SQL Server devolve 6 caracteres, não 7.
- Gravação recusada quando o código não tem 13 dígitos numéricos ou a
  quantidade não é um número: qualquer uma das duas coisas quebraria os totais
  do inventário inteiro, não só a linha.
- Item só é contado se pertencer ao inventário naquele local (`TITMINVENTARIO`),
  exceto em contagem avulsa.
- Releitura do mesmo produto/lote grava, mas avisa — contar duas caixas do
  mesmo lote é legítimo; bipar duas vezes por engano é o erro mais comum.
- Formulário de leitura via **GET** (compatível com leitores de código de barras).

## Segurança

- Toda consulta usa parâmetros (`sqlsrv_query` com bind), nunca concatenação.
- Todo POST que altera dados exige token CSRF (campo `_token` ou cabeçalho
  `X-CSRF-Token`).
- Cookie de sessão com `HttpOnly`, `SameSite=Lax` e `Secure` sob HTTPS;
  identificador renovado no login.
- Senha conferida contra bcrypt ou os formatos legados do RM — nunca
  comparação com texto puro.
- Autenticação só contra o `api_url` do ambiente escolhido. Hosts alternativos
  existem apenas se declarados em `api_fallbacks`.

## Erros e tempo de resposta

- Consulta que falha levanta `DatabaseException`: o operador lê uma frase, o
  log do PHP recebe o `SQLSTATE` e o SQL completo. Antes a falha virava "zero
  linhas" e a tela dizia "inventário não cadastrado no RM".
- Endpoints JSON respondem JSON mesmo quando quebram — nada de HTML no meio da
  resposta.
- `query_timeout` faz a consulta estourar no SQL Server antes do PHP-FPM, de
  modo que a tela mostra "estreite o filtro" em vez de um 504 em branco.
- As telas pesadas (posição, conferência, exportações) levantam o próprio teto
  de tempo e memória.
- Listas grandes têm teto (`LIMITE_LOTES`, `LIMITE_ITENS`, `LIMITE_LISTAGEM`) e
  avisam na tela quando foram cortadas.

## Requisitos

- PHP 8.0+ (o servidor roda 8.3) com as extensões **sqlsrv** e **mbstring**
- Acesso de rede ao SQL Server do ambiente e ao RM Host (porta 8051)
- Servidor web (Apache/XAMPP, Nginx + PHP-FPM, IIS)

## Melhorias futuras sugeridas

- Autenticação integrada ao Active Directory
- Log de auditoria por usuário e ambiente (script pronto para revisão do DBA em
  `deploy/migracao-auditoria-contagem.sql`)
- API REST para integração com coletores mobile
- Paginação no servidor para locais acima do teto de linhas
- Reserva atômica do número da contagem avulsa (hoje dois operadores
  simultâneos podem escolher o mesmo número)
