# Inventário + RH via porta 80/443 (nginx-proxy do portal)

O inventário continua no host em **:9080** (Nginx + PHP-FPM).
O **nginx-proxy** do portal (`portal-colaborador`) na **80/443** encaminha as URLs públicas.

| URL pública | Backend | base_path |
|-------------|---------|-----------|
| **https://inventario.h9julho-ro.com.br/** | http://HOST:9080/ | `''` (raiz) |
| https://172.20.0.43/inventario/ | http://HOST:9080/ | `/inventario` (legado) |
| https://172.20.0.43/rh/ | http://HOST:9080/rh/ | `/rh` |

`HOST` = gateway Docker → host (quase sempre `172.17.0.1`).

---

## Subdomínio inventário (recomendado)

### 1. DNS

Apontar `inventario.h9julho-ro.com.br` → `172.20.0.43` (A record).

### 2. Template Nginx no portal

```bash
sudo tee /opt/portal-colaborador/deploy/nginx/templates/inventario.conf.template > /dev/null <<'EOF'
# Inventário RM — subdomínio
server {
    listen 80;
    server_name inventario.h9julho-ro.com.br;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl;
    http2 on;
    server_name inventario.h9julho-ro.com.br;

    ssl_certificate     /etc/nginx/certs/server.crt;
    ssl_certificate_key /etc/nginx/certs/server.key;
    ssl_protocols       TLSv1.2 TLSv1.3;

    location / {
        proxy_pass http://172.17.0.1:9080/;
        proxy_http_version 1.1;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
        proxy_read_timeout 120s;
        client_max_body_size 32m;
    }
}
EOF
```

Se o certificado interno **não** cobrir o nome `inventario.h9julho-ro.com.br`, o navegador avisa (certificado inválido). Peça à TI um cert com esse SAN ou wildcard `*.h9julho-ro.com.br`.

### 3. base_path na raiz do subdomínio

```bash
sudo tee /var/www/inventario/config/app.php > /dev/null <<'EOF'
<?php
return [
    'debug'     => false,
    'app_name'  => 'Inventário RM',
    'base_path' => '',
];
EOF
sudo systemctl restart php8.3-fpm
```

### 4. Aplicar

```bash
sudo docker restart nginx-proxy
sleep 3
sudo docker ps --filter name=nginx-proxy
sudo docker exec nginx-proxy nginx -t
sudo docker exec nginx-proxy grep -n 'inventario.h9julho' /etc/nginx/conf.d/inventario.conf
curl -skI https://inventario.h9julho-ro.com.br/ | head -10
```

URL final: **https://inventario.h9julho-ro.com.br/**

---

## Path legado `/inventario/` no IP (opcional)

Mantém https://172.20.0.43/inventario/ — mas com `base_path => ''` os links na raiz do subdomínio ficam corretos; no path `/inventario/` CSS/redirects podem quebrar. Prefira só o subdomínio.

Se precisar dos dois ao mesmo tempo, use `base_path => '/inventario'` e no subdomínio faça:

```nginx
# Não recomendado misturar; escolha um modo.
location / {
    proxy_pass http://172.17.0.1:9080/;
}
```

com app em path — ou só subdomínio com `base_path` vazio.

---

## RH (continua no path)

`location /rh/` no `portal.conf.template` → https://172.20.0.43/rh/  
(ou um subdomínio próprio no futuro).

---

## Problemas comuns

| Sintoma | Causa |
|---------|--------|
| DNS não resolve | Falta registro A no DNS |
| Certificado inválido | Cert sem o hostname do inventário |
| 502 Bad Gateway | Container não alcança `172.17.0.1:9080` |
| CSS quebrado no subdomínio | `base_path` ainda está `/inventario` — use `''` |
| Página do portal no inventário | `server_name` errado / template não carregou |
