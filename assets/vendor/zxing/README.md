# ZXing para JavaScript

Leitor de código de barras por câmera, usado quando o navegador não tem o
`BarcodeDetector` nativo — na prática, iPhone e Safari.

- **Pacote:** `@zxing/library`
- **Versão:** 0.21.3
- **Origem:** https://cdn.jsdelivr.net/npm/@zxing/library@0.21.3/umd/index.min.js
- **Licença:** Apache 2.0 (arquivo `LICENSE` ao lado)

## Por que está aqui dentro

O sistema não tem build nem gerenciador de pacotes: o que o navegador usa está
no repositório, como já acontece com o FPDF em `src/Pdf`. Carregar de CDN
também deixaria a contagem dependendo de internet externa no meio do corredor
da farmácia, que é onde ela menos existe.

## Como é carregado

Não entra em toda página. `assets/js/camera-bipe.js` só busca este arquivo
quando a pessoa aciona a câmera E o navegador não tem leitor nativo. No Android
o arquivo nunca é baixado.

## Para atualizar

Baixe a mesma URL com a versão nova, troque os dois arquivos e confira que o
global `ZXing` continua exposto (é um bundle UMD).
