# Marca — imagotipo da Intranet FCECON

Parte do design system do projeto. Define o imagotipo (símbolo + logotipo), suas cores, proporções, tamanhos mínimos, versões para fundo claro e escuro e como usá-lo no HTML. Arquivos em [`public/assets/img/brand/`](../../public/assets/img/brand/).

<picture>
  <img src="../../public/assets/img/brand/imagotipo.svg" alt="Imagotipo da Intranet FCECON: caranguejo com carapaça em forma de pasta, seguido de FCECON e INTRANET" width="460">
</picture>

> **Estado:** proposta de 26/10/2026, criada no repositório. Antes do uso oficial, a comunicação da Fundação CECON precisa aprovar (ver [Origem e aprovação](#origem-e-aprovação)). A tela de login do sistema atual continua com o `logotipo.png`; o imagotipo entra na nova interface (tarefa T029 do [kanban](../kanban.md)).

## 1. Conceito

A Fundação usa um **caranguejo** como símbolo (o `logotipo.png` do sistema atual). O imagotipo da intranet mantém essa referência e a traduz para o que o sistema faz: **guardar e organizar arquivos**.

- **Carapaça = pasta.** O corpo do caranguejo é o painel de uma pasta, com um **documento vazado** no centro (o recorte deixa ver o fundo).
- **Pinças abertas.** Ficam levantadas e abertas: acolhem, não agarram.
- **Patas em três traços** de cada lado, no mesmo traço arredondado do logotipo.
- **Logotipo em traço único.** `FCECON` em maiúsculas de traço contínuo e arredondado, com `INTRANET` menor, abaixo, espaçado. As letras são **desenhadas em curvas** (não dependem de nenhuma fonte instalada nem de licença).

## 2. Anatomia

| Parte | Descrição | Cor (versão clara) |
|---|---|---|
| Carapaça com documento | Pasta oval com recorte de documento de canto dobrado | Verde CECON |
| Pinças e patas | Traços de 4,4 unidades com pontas redondas, em grade de 64 | Grafite |
| `FCECON` | Altura de 24 unidades, traço de 4,6 | Grafite |
| `INTRANET` | Altura de 9,5 unidades, traço de 1,9, espaçamento de 6,6 | Verde CECON |

Proporção do conjunto horizontal: **230 × 64**. O símbolo ocupa um quadrado de 64 × 64 e o texto começa 18 unidades à sua direita, centrado na vertical.

## 3. Arquivos

| Arquivo | Uso |
|---|---|
| `imagotipo.svg` | Padrão, para fundo claro |
| `imagotipo-escuro.svg` | Fundo escuro (tema escuro do sistema) |
| `imagotipo-mono.svg` | Uma cor só, herda `currentColor` (uso inline no HTML) |
| `imagotipo-branco.svg` | Branco, sobre fundo colorido ou foto |
| `simbolo.svg`, `-escuro`, `-mono`, `-branco` | Só o caranguejo, quando o espaço é pequeno |
| `favicon.svg` | Ícone de aba: símbolo simplificado, **sem o documento vazado**, traço mais grosso e **todo em verde** (lê em aba clara e escura) |

Todos são SVG puro, sem fontes, sem scripts, com `<title>` e `<desc>` para leitores de tela.

## 4. Cores

| Token | Valor | Uso | Contraste |
|---|---|---|---|
| `--marca-verde` | `#2E7D4A` | Carapaça e `INTRANET` (fundo claro) | 5,1 : 1 sobre branco |
| `--marca-grafite` | `#2F3033` | Pinças, patas e `FCECON` (fundo claro) | 13 : 1 sobre branco |
| `--marca-verde-tema-escuro` | `#5CC389` | Carapaça e `INTRANET` (fundo escuro) | 7,5 : 1 sobre `#16212D` |
| `--marca-linha-tema-escuro` | `#E4E6EB` | Pinças, patas e `FCECON` (fundo escuro) | 13 : 1 sobre `#16212D` |

A paleta vem do logotipo institucional (verde da carapaça e contorno grafite), com o verde levemente escurecido para atingir **contraste mínimo de 4,5 : 1** (WCAG 2.1 AA) em texto pequeno.

**Relação com as cores da interface.** O azul de ação (`#0b5fd6`, `--accent-color`) continua sendo a cor de botões, links e foco. O verde da marca **não** é cor de ação nem de sucesso: o verde semântico de sucesso é outro (`--success-color: #2e7d32`) e deve ser usado só para estados. Na dúvida, a marca aparece no topo da página, e a interface usa o azul.

## 5. Tipografia

- **Logotipo:** desenhado, sem fonte. Não digite "FCECON" em outra fonte para imitar o logotipo; use o arquivo.
- **Interface:** continua a pilha de sistema (`-apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif`). A nova interface (Next.js) não precisa de fonte da web; se vier a precisar, decidir na ADR do front.
- **Texto ao lado do imagotipo** (nome do sistema, versão): corpo normal, cor `--text-secondary`, nunca em caixa alta espaçada (isso é do `INTRANET`).

## 6. Área de respeito

Mantenha livre, em volta do imagotipo, uma margem de **x = 1/4 da altura do símbolo** (a largura do documento vazado, cerca de 15 unidades de 64). Nada de texto, borda ou imagem entra nessa faixa.

```
          x
      ┌───────────────────────────────┐
   x  │  [símbolo]  FCECON            │  x
      │             INTRANET          │
      └───────────────────────────────┘
                     x
```

## 7. Tamanhos mínimos

| Versão | Mínimo em tela | Mínimo impresso |
|---|---|---|
| Imagotipo horizontal | **140 px** de largura (abaixo disso o `INTRANET` perde a leitura) | 35 mm |
| Símbolo completo | **24 px** | 8 mm |
| Favicon | **16 px** (usa `favicon.svg`) | — |

Abaixo de 140 px de largura, troque o imagotipo pelo **símbolo**.

## 8. Quando usar cada versão

| Fundo | Versão |
|---|---|
| Branco ou cinza claro (`#f0f2f5`) | `imagotipo.svg` |
| Escuro (`#16212d`, `#242526`, `#18191a`) | `imagotipo-escuro.svg` |
| Uma cor definida pelo contexto (carimbo, relatório em preto e branco, documento impresso) | `imagotipo-mono.svg` com a cor do texto |
| Cor de destaque, foto ou vídeo (com contraste suficiente) | `imagotipo-branco.svg` |

## 9. Faça e não faça

| ✅ Faça | ❌ Não faça |
|---|---|
| Use os arquivos SVG como estão | Redesenhar o caranguejo ou digitar o texto em outra fonte |
| Respeite a área de respeito e os tamanhos mínimos | Esticar, girar, inclinar ou aplicar sombra, brilho ou contorno |
| Troque pela versão escura no tema escuro | Usar a versão clara sobre fundo escuro (o grafite some) |
| Use o símbolo sozinho em espaços pequenos | Usar só o logotipo sem o símbolo no cabeçalho do sistema |
| Dê `alt="Intranet FCECON"` à imagem | Colocar o imagotipo sobre foto movimentada sem a versão branca e um fundo de proteção |
| Mantenha o verde da marca fora de botões e alertas | Misturar verde da marca com verde de sucesso |

## 10. Uso no HTML

**Imagem simples, com tema claro e escuro** (o sistema atual alterna a classe `dark-mode` no `body`):

```html
<img class="marca marca-clara" src="assets/img/brand/imagotipo.svg" alt="Intranet FCECON" width="161" height="45">
<img class="marca marca-escura" src="assets/img/brand/imagotipo-escuro.svg" alt="" width="161" height="45">
```

```css
.marca-escura { display: none; }
body.dark-mode .marca-clara { display: none; }
body.dark-mode .marca-escura { display: inline; }
```

Na nova interface (que segue `prefers-color-scheme`), use `<picture>`:

```html
<picture>
  <source media="(prefers-color-scheme: dark)" srcset="/novo/brand/imagotipo-escuro.svg">
  <img src="/novo/brand/imagotipo.svg" alt="Intranet FCECON" width="161" height="45">
</picture>
```

**Favicon:**

```html
<link rel="icon" href="assets/img/brand/favicon.svg" type="image/svg+xml">
```

**Símbolo ao lado do nome em texto** (decorativo, escondido de leitores de tela para não repetir o nome):

```html
<img src="assets/img/brand/simbolo.svg" alt="" width="28" height="28"> <span>Intranet FCECON</span>
```

**Uma cor só, herdando o texto:** coloque o conteúdo de `imagotipo-mono.svg` inline e defina `color` no contêiner.

## 11. Acessibilidade

- Todo arquivo traz `<title>` e `<desc>`. Em `<img>`, o `alt` é o texto lido: `Intranet FCECON`.
- Quando o símbolo estiver ao lado do nome em texto, use `alt=""` para não repetir.
- Contraste de cada versão contra o seu fundo: ver a tabela da seção 4 (todas ≥ 4,5 : 1).
- Não dependa só da cor: o símbolo tem forma própria e o logotipo é texto.

## 12. Origem e aprovação

- O **caranguejo** é a referência visual da Fundação CECON (o `logotipo.png` do sistema atual). Este imagotipo é um **desenho novo, geométrico e simplificado**, que cita a ideia sem copiar o contorno do logotipo institucional.
- O logotipo institucional **não foi alterado nem substituído**. O imagotipo da intranet é uma marca de **produto** e deve conviver com o logotipo da Fundação, não competir com ele (por exemplo, no rodapé: "Mantido pela TI da Fundação CECON").
- **Pendente:** validação pela comunicação da Fundação antes de uso oficial. O desenho **não está registrado** como marca.

## 13. Como a marca foi construída

Geometria em grade de 64 × 64: carapaça `M10 38 C10 27 19 21 32 21 C45 21 54 27 54 38 C54 48 46 55 32 55 C18 55 10 48 10 38 Z`, documento vazado de 15 × 18 unidades com canto dobrado (regra `evenodd`), pinças e patas em traço de 4,4 com pontas redondas e espelhadas no eixo vertical. O logotipo usa letras de traço único (`stroke-linecap: round`). Para gerar variantes novas, mude só as cores; não altere as formas.

## 14. Referências

[arquitetura do front](../revisao-de-arquitetura.md) · [roadmap](../roadmap.md) · [kanban](../kanban.md)
