# Inventário RM — Aplicação Unificada

Sistema unificado para leitura de código de barras em inventários do **TOTVS RM**, substituindo as pastas duplicadas `h9/` (produção) e `teste/` (homologação).

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

## Nova arquitetura

```
inventario/
├── index.php                 # Tela inicial — seleção de ambiente
├── conectar.php              # Processa teste/conexão
├── desconectar.php           # Encerra sessão
├── inventario.php            # Tela operacional de inventário
├── bootstrap.php             # Inicialização da aplicação
├── config/
│   └── environments.php      # Parâmetros por ambiente (host, banco, credenciais)
├── src/
│   ├── Config/
│   │   └── EnvironmentManager.php   # Gerenciamento de ambientes
│   ├── Database/
│   │   └── Connection.php           # Conexão SQL Server (sqlsrv)
│   ├── Domain/
│   │   └── ZMDCODBARRAS.php         # Regras de negócio do inventário
│   ├── Http/
│   │   └── SessionManager.php       # Sessão (ambiente, usuário, status)
│   └── Helpers/
│       └── functions.php            # Funções utilitárias
├── views/
│   ├── layout.php            # Layout base
│   ├── home.php              # Tela de seleção de ambiente
│   └── inventario.php        # Formulário e tabela de registros
└── assets/
    ├── css/app.css
    └── js/app.js
```

## Separação de responsabilidades

| Camada | Responsabilidade |
|--------|------------------|
| `config/environments.php` | Dados de conexão por ambiente |
| `EnvironmentManager` | Leitura, seleção e teste de ambientes |
| `SessionManager` | Estado da sessão (ambiente ativo, usuário, conexão) |
| `Connection` | Acesso ao SQL Server usando o ambiente da sessão |
| `ZMDCODBARRAS` | Regras de negócio (INSERT e SELECT com JOINs) |
| `views/` | Apresentação HTML |
| `index.php` / `inventario.php` | Controllers leves (orquestração) |

## Ambientes configurados

| Chave | Label | Host | Banco |
|-------|-------|------|-------|
| `producao` | Produção | 172.20.0.10 | CorporeRM |
| `testes` | Testes | 172.20.0.15 | ontemrm |

### Adicionar um novo ambiente

Edite apenas `config/environments.php`:

```php
'homologacao' => [
    'label'       => 'Homologação',
    'host'        => '172.20.0.20',
    'database'    => 'CorporeRM_HML',
    'usuario'     => 'rm',
    'senha'       => 'rm',
    'badge_class' => 'bg-info',
],
```

Nenhuma alteração de código é necessária — o seletor na tela inicial lista automaticamente todos os ambientes.

## Fluxo do usuário

1. Acessar `/inventario/`
2. Selecionar **Produção** ou **Testes**
3. Informar o nome do usuário (pré-preenchido com o usuário do SO, quando disponível)
4. Clicar em **Testar Conexão** ou **Conectar**
5. Na tela de inventário, informar CODLOC, inventário, quantidade e código de barras
6. Os registros são gravados e listados conforme a lógica original

## Regras de negócio preservadas

- INSERT em `ZMDCODBARRAS` com os mesmos campos: `CODIGOBARRAS`, `CODINVENTARIO`, `QUANTIDADE`, `CODLOC`
- SELECT com JOINs em `TPRODUTO`, `TPRODUTODEF` e `TLOTEPRD`
- `SUBSTRING` para extrair produto e lote do código de barras
- `TOP 1000` na listagem (comportamento da versão de produção `h9/`)
- Formulário via **GET** (compatível com leitores de código de barras)
- Validação HTML: CODLOC com 3 dígitos, código de barras com 13 dígitos

## Compatibilidade com versões anteriores

As pastas `h9/` e `teste/` foram mantidas com redirecionamento automático:

- `h9/` → ambiente **produção**
- `teste/` → ambiente **testes**

URLs antigas continuam funcionando, direcionando para a aplicação unificada.

## Decisões técnicas

1. **Sessão PHP** para ambiente ativo — evita expor credenciais na URL e permite trocar ambiente sem duplicar código.
2. **`EnvironmentManager`** centralizado — ponto único para teste de conexão e leitura de configuração.
3. **SQL compartilhado em `baseSelectSql()`** — elimina duplicação entre `listarPorInventario` e `listarTodos`.
4. **`encode_db_value()`** — substitui `utf8_encode()` (depreciado no PHP 8.2+) sem alterar o comportamento.
5. **Bootstrap 5** — interface moderna sem mudar o fluxo operacional.
6. **Overlay de carregamento** — feedback visual durante submissão de formulários.

## Requisitos

- PHP com extensão **sqlsrv** habilitada
- Acesso de rede aos servidores SQL Server dos ambientes RM
- Servidor web (Apache/XAMPP, IIS, Laragon)

## Melhorias futuras sugeridas

- Prepared statements (`sqlsrv_prepare`) para mitigar SQL injection
- Migrar gravação para POST com token CSRF (mantendo compatibilidade com leitores)
- Autenticação integrada ao Active Directory
- Log de auditoria por usuário e ambiente
- API REST para integração com coletores mobile

## Alterações em relação ao código original

| Item | Antes | Depois |
|------|-------|--------|
| Projetos | `h9/` + `teste/` duplicados | `inventario/` único |
| Configuração | `config.php` por pasta | `config/environments.php` centralizado |
| Seleção de ambiente | URL diferente por pasta | Tela inicial com escolha |
| Conexão | Fixa por pasta | Dinâmica via sessão |
| Views | HTML misturado no `index.php` | `views/` separadas |
| Duplicação SQL | Queries repetidas | `baseSelectSql()` + `mapearResultado()` |
