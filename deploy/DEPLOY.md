# Deploy no servidor 172.20.0.43 (Ubuntu 24.04)

## Visão geral

| Item | Valor |
|------|-------|
| Servidor | `172.20.0.43` |
| Usuário SSH | `adalton` |
| Pasta no servidor | `/var/www/inventario` |
| URL Inventário | `http://172.20.0.43:9080/` |
| Portas Docker | 80, 8080, 8081 — outros sistemas |
| Porta Inventário | **9080** (Nginx + PHP-FPM no host) |

---

## Passo 1 — Enviar arquivos do Windows para o servidor

No **PowerShell** do seu PC (onde está o XAMPP), execute:

```powershell
scp -r "c:\xampp\htdocs\www\inventario" adalton@172.20.0.43:/tmp/inventario
```

Informe a senha quando solicitado.

**Alternativa (WinSCP / FileZilla):** envie a pasta `inventario` inteira para `/tmp/inventario` no servidor.

---

## Passo 2 — No servidor (já conectado via SSH)

```bash
# Mover aplicação para o diretório web
sudo mkdir -p /var/www
sudo rm -rf /var/www/inventario
sudo mv /tmp/inventario /var/www/inventario

# IMPORTANTE: corrigir fim de linha Windows antes de executar
cd /var/www/inventario/deploy
sed -i 's/\r$//' *.sh
chmod +x *.sh

# Instalar Nginx, PHP-FPM e driver SQL Server
sudo bash install-ubuntu.sh
```

O script instala:
- Nginx + PHP-FPM (o servidor já usa Nginx, não Apache)
- Microsoft ODBC Driver 18
- Extensões `sqlsrv` e `pdo_sqlsrv`
- Site apontando para `/var/www/inventario`

---

## Passo 3 — Verificar instalação

```bash
# PHP com sqlsrv
php -m | grep sqlsrv

# Status do Apache
sudo systemctl status apache2

# Testar página
curl -I http://127.0.0.1/
```

No navegador: **http://172.20.0.43/**

---

## Passo 4 — Testar conexão com o RM

1. Abra `http://172.20.0.43/`
2. Selecione **Produção** (`172.20.0.10`) ou **Testes** (`172.20.0.15`)
3. Clique em **Testar Conexão**

Se falhar, verifique no servidor:

```bash
# O web server alcança o SQL Server?
nc -zv 172.20.0.10 1433
nc -zv 172.20.0.15 1433

# Logs de erro
sudo tail -f /var/log/nginx/inventario-error.log
```

---

## Atualizar a aplicação (deploy futuro)

No Windows:

```powershell
scp -r "c:\xampp\htdocs\www\inventario\*" adalton@172.20.0.43:/tmp/inventario-update/
```

No servidor:

```bash
sudo rsync -av --delete /tmp/inventario-update/ /var/www/inventario/ \
  --exclude 'deploy' \
  --exclude 'config/environments.php'

sudo chown -R www-data:www-data /var/www/inventario
sudo systemctl restart apache2
```

> O `config/environments.php` é preservado para não sobrescrever credenciais do servidor.

---

## Configuração de ambientes no servidor

Edite no servidor se necessário:

```bash
sudo nano /var/www/inventario/config/environments.php
```

```bash
sudo chown www-data:www-data /var/www/inventario/config/environments.php
```

---

## Firewall (se a página não abrir externamente)

```bash
sudo ufw allow 80/tcp
sudo ufw status
```

---

## Solução de problemas

| Problema | Ação |
|----------|------|
| `sqlsrv` não carrega | `sudo pecl install sqlsrv` e `sudo phpenmod sqlsrv` |
| Erro de conexão ODBC | `sudo ACCEPT_EULA=Y apt install msodbcsql18` |
| Página em branco | `sudo tail -50 /var/log/nginx/inventario-error.log` |
| Erro `$'\r': command not found` | `sed -i 's/\r$//' /var/www/inventario/deploy/*.sh` |
| Redirect HTTPS inesperado | Ver seção "Nginx com HTTPS" abaixo |
| Permissão negada | `sudo chown -R www-data:www-data /var/www/inventario` |
| RM inacessível | Liberar porta 1433 no firewall entre `172.20.0.43` e os DBs |

---

## Nginx com HTTPS (redirect 301)

Se `curl -I http://127.0.0.1/` retornar redirect para HTTPS, existe outro site Nginx ativo.
Após instalar, verifique:

```bash
ls -la /etc/nginx/sites-enabled/
sudo nginx -t
```

O site `inventario` deve estar habilitado. Se outro site capturar a porta 80,
desabilite o default ou ajuste o `server_name` no arquivo existente.

## Comandos rápidos (copiar e colar no servidor)

Se os arquivos já estão em `/var/www/inventario`:

```bash
cd /var/www/inventario/deploy
sed -i 's/\r$//' *.sh
chmod +x *.sh
sudo bash install-ubuntu.sh
php -m | grep sqlsrv
curl -I http://127.0.0.1/
```
