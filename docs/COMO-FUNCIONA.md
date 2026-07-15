# Como funcionam Inventário e RH

Documentação simples dos apps web do Hospital 9 de Julho integrados ao **TOTVS RM**.

| App | URL (servidor) | Pasta |
|-----|----------------|-------|
| Inventário | http://172.20.0.43:9080/ | `/var/www/inventario` |
| RH (escala) | http://172.20.0.43:9080/rh/ | `/var/www/rh` |

Local (XAMPP): `http://localhost/inventario/` e `http://localhost/rh/`.

---

## Visão geral

Os dois sistemas são **PHP** (sem framework), usam **SQL Server** (sqlsrv) e a mesma lógica de **login do usuário RM**.

```
Navegador → Nginx (.43:9080) → PHP-FPM → App PHP
                                      ├─ SQL Server (dados RM)
                                      └─ RM Host :8051 (login / API)
```

- **Inventário** — conta física de estoque (códigos de barras → tabelas `TINVENTARIO` / `TITMINVENTARIO`).
- **RH** — consulta de funcionários/horários e **escala de plantão** (grade mensal 12×36). Ainda **não grava** troca de horário de volta no Labore.

---

## Ambientes

Configurados em `config/environments.php` (**não vai para o Git** — use o `.example`):

| Chave | Uso | SQL típico |
|-------|-----|------------|
| `producao` | Produção | `172.20.0.10` / `CorporeRM` |
| `homologacao` | Homologação | `172.20.0.15` / `HomologaRM` |
| `testes` | Testes | `172.20.0.15` / `ontemrm` |

Cada ambiente tem usuário/senha **SQL** (conexão ao banco) e `api_url` (Host RM para autenticar o usuário da tela).

---

## Autenticação (parte principal)

Arquivo comum: `src/Domain/RmAuth.php` (inventário e RH devem ter a **mesma versão**).

### Credenciais da tela

- **Usuário / senha** = usuário do **RM** (`GUSUARIO.CODUSUARIO`), os mesmos do Desktop/Web TOTVS.
- Não é a senha SQL do `environments.php`.

### Fluxo do login

1. Usuário escolhe o ambiente e envia usuário/senha (`conectar.php`).
2. App testa a **conexão SQL** do ambiente.
3. `RmAuth::authenticate()` valida a senha nesta ordem:

```
1) REST  POST {api_url}/api/connect/token
         grant_type=password + username + password
         → se vier access_token = OK

2) Se a API responder 400/401/403 → "Senha inválida" (não tenta hash local)

3) SOAP wsDataServer (fallback)

4) Se a API estiver inacessível → erro de rede (porta 8051 / firewall)

5) GUSUARIO (bcrypt/legado) — só em casos residuais
```

4. Se OK: grava sessão (`SessionManager`) e libera o app.

### Host RM atual (importante)

| Item | Valor |
|------|--------|
| URL | **`https://172.20.0.20:8051`** |
| Endpoint | `/api/connect/token` |
| Protocolo | **HTTPS** (HTTP nessa porta falha com “plain HTTP request was sent to HTTPS port”) |

Exemplo no `environments.php`:

```php
'api_url' => 'https://172.20.0.20:8051',
```

O cliente PHP desliga verificação de certificado (Host interno / certificado autoassinado).

Hosts de fallback no código (só se o `api_url` falhar): `.20`, `.21`, `.30` — preferir sempre o `api_url` correto no ambiente.

### Mensagens comuns

| Mensagem | Significado |
|----------|-------------|
| Senha inválida (rejeitada pela API do RM) | Host respondeu; usuário/senha errados |
| Não foi possível alcançar a API do RM Host | Firewall / Host offline / URL errada |
| plain HTTP … HTTPS port | `api_url` está em `http://` — troque para `https://` |
| Usuário RM inativo | `GUSUARIO.STATUS <> 1` |

---

## Inventário — o que faz depois do login

1. Lista inventários **abertos** (`STATUS = 'A'`).
2. Usuário informa inventário, local de estoque e lê códigos de barras.
3. Grava itens nas tabelas RM de inventário (`InventarioRM` / `ZMDCODBARRAS`).

Sessão: precisa estar autenticado (`SessionManager::requireConnection()`).

---

## RH — o que faz depois do login

1. Painel / funcionários / horários (leituras Labore: `PFUNC`, `AHORARIO`, `PFHSTHOR`, etc.).
2. **Escala de plantão** (`escala-plantao.php`):
   - Colaboradores do setor vêm do RM.
   - Diurno / noturno a partir do horário cadastrado.
   - Grade dia a dia = **sugestão 12×36** + rascunho no navegador (`localStorage`).
   - Fotos: `PPESSOA.IDIMAGEM` → `GIMAGEM` via `foto.php`.
3. Troca de horário no Labore está **preparada**, mas **bloqueada** para gravação no RM.

---

## Deploy rápido

### Inventário (tem Git no servidor)

```bash
sudo bash /var/www/inventario/deploy/atualizar-servidor.sh
grep api_url /var/www/inventario/config/environments.php
# deve mostrar: https://172.20.0.20:8051
```

### RH (em geral **sem** `.git` — veio por SCP)

Após atualizar o inventário, alinhe auth e URL:

```bash
sudo cp /var/www/inventario/src/Domain/RmAuth.php /var/www/rh/src/Domain/RmAuth.php
sudo sed -i 's|http://172.20.0.20:8051|https://172.20.0.20:8051|g' /var/www/rh/config/environments.php
sudo sed -i 's|http://172.20.0.21:8051|https://172.20.0.20:8051|g' /var/www/rh/config/environments.php
sudo bash /var/www/rh/deploy/publicar-na-9080.sh
sudo systemctl restart php8.3-fpm
```

Ou envie a pasta do PC:

```powershell
scp -r c:\xampp\htdocs\www\rh\* adalton@172.20.0.43:/home/adalton/rh-upload/
```

No servidor: `rsync` para `/var/www/rh` (excluindo `config/environments.php`) + `publicar-na-9080.sh`.

---

## Arquivos-chave

| Arquivo | Função |
|---------|--------|
| `config/environments.php` | Hosts SQL + `api_url` |
| `conectar.php` | Recebe login e chama `RmAuth` |
| `src/Domain/RmAuth.php` | Validação usuário/senha no RM Host |
| `src/Http/SessionManager.php` | Sessão PHP |
| `src/Database/Connection.php` | sqlsrv |
| `src/Config/EnvironmentManager.php` | Lê ambientes |

Inventário: `inventario.php`, `src/Domain/InventarioRM.php`  
RH: `escala-plantao.php`, `src/Domain/GradeMensal.php`, `foto.php`

---

## Checklist se o login falhar

1. Confirmar usuário/senha no RM Desktop no **mesmo** ambiente.
2. `grep api_url config/environments.php` → `https://172.20.0.20:8051`
3. Do servidor: `curl -k -X POST https://172.20.0.20:8051/api/connect/token -H "Content-Type: application/json" -d '{"grant_type":"password","username":"SEU_USER","password":"SUA_SENHA"}'`
4. Inventário e RH com o **mesmo** `RmAuth.php`
5. Reiniciar PHP-FPM após mudar `environments.php`
