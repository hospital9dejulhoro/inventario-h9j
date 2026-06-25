# Acesso ao Inventário RM via porta 80 (Docker reverse proxy)

Quando a porta 9080 não é acessível externamente (ERR_CONNECTION_TIMED_OUT),
use o Nginx do Docker na porta 80 para encaminhar ao PHP no host.

## 1. Descobrir containers e gateway

No servidor:

```bash
docker ps --format "table {{.Names}}\t{{.Ports}}\t{{.Image}}"
docker network inspect bridge --format '{{range .IPAM.Config}}{{.Gateway}}{{end}}'
```

Anote o **Gateway** (geralmente `172.17.0.1`) e o container Nginx que expõe a porta 80.

## 2. Testar se o container alcança o inventário no host

```bash
GATEWAY=$(docker network inspect bridge --format '{{range .IPAM.Config}}{{.Gateway}}{{end}}')
docker run --rm curlimages/curl:latest curl -sI "http://${GATEWAY}:9080/"
```

Deve retornar `HTTP/1.1 200` e `Server: nginx`.

## 3. Adicionar rota no Nginx do Docker

Edite a config do container Nginx (caminho varia por projeto). Exemplo de bloco:

```nginx
# Inventário RM — proxy para PHP no host
location /inventario/ {
    proxy_pass http://172.17.0.1:9080/;
    proxy_http_version 1.1;
    proxy_set_header Host $host;
    proxy_set_header X-Real-IP $remote_addr;
    proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
    proxy_set_header X-Forwarded-Proto $scheme;
}
```

Substitua `172.17.0.1` pelo Gateway do passo 1.

Recarregue o Nginx do container:

```bash
docker exec <nome_nginx> nginx -t
docker exec <nome_nginx> nginx -s reload
```

## 4. Ajustar a aplicação para subpasta /inventario/

A aplicação precisa do BASE_PATH. Defina no servidor:

```bash
sudo tee /var/www/inventario/config/app.php > /dev/null <<'EOF'
<?php
return [
    'base_path' => '/inventario',
];
EOF
```

(Requer atualização do código — solicite deploy da versão com suporte a base_path.)

## Alternativa imediata (sem alterar código)

Peça à TI para liberar a porta **9080/TCP** entre sua rede (ex.: `172.20.3.0/24`) e o servidor `172.20.0.43`.

## Teste rápido no Windows

```powershell
Test-NetConnection 172.20.0.43 -Port 80
Test-NetConnection 172.20.0.43 -Port 9080
```

- Porta **80** aberta + **9080** falha → use proxy Docker ou liberação de firewall.
- Ambas falham → problema de rota/VPN até o servidor.
