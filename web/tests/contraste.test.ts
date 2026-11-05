// @vitest-environment node
//
// Contraste WCAG 2.1 AA (mínimo 4,5:1 para texto) dos pares de cores do tema claro e do escuro,
// lidos direto de src/app/globals.css, para um token novo ou alterado não furar a regra.
import { readFileSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, test } from "vitest";

const css = readFileSync(join(__dirname, "..", "src", "app", "globals.css"), "utf8");

function tokens(bloco: string): Record<string, string> {
  return Object.fromEntries([...bloco.matchAll(/--([\w-]+):\s*(#[0-9a-fA-F]{6})\s*;/g)].map((m) => [m[1], m[2]]));
}

function bloco(seletor: string): string {
  const inicio = css.indexOf(seletor);
  expect(inicio, `bloco ${seletor}`).toBeGreaterThanOrEqual(0);
  return css.slice(inicio, css.indexOf("}", inicio));
}

function luminancia(hex: string): number {
  const [r, g, b] = [1, 3, 5].map((i) => parseInt(hex.slice(i, i + 2), 16) / 255).map((c) => (c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4));
  return 0.2126 * r + 0.7152 * g + 0.0722 * b;
}

function contraste(a: string, b: string): number {
  const [claro, escuro] = [luminancia(a), luminancia(b)].sort((x, y) => y - x);
  return (claro + 0.05) / (escuro + 0.05);
}

// [texto, fundo] que aparecem juntos na interface.
const PARES: [string, string][] = [
  ["foreground", "background"],
  ["foreground", "superficie"],
  ["texto-secundario", "superficie"],
  ["acao-texto", "acao"], // texto do botão
  ["acao-texto", "acao-hover"], // botão com o mouse em cima
  ["erro-texto", "erro-fundo"],
  ["aviso-texto", "aviso-fundo"],
  ["erro-texto", "superficie"], // mensagem sem o fundo próprio
];

describe.each([
  ["tema claro", ":root {"],
  ["tema escuro", ':root[data-tema="escuro"] {'],
  ["tema escuro automático", ':root:not([data-tema="claro"]) {'],
])("contraste AA — %s", (_nome, seletor) => {
  const t = tokens(bloco(seletor));

  test.each(PARES)("%s sobre %s tem pelo menos 4,5:1", (texto, fundo) => {
    expect(t[texto], texto).toBeDefined();
    expect(t[fundo], fundo).toBeDefined();
    expect(contraste(t[texto], t[fundo])).toBeGreaterThanOrEqual(4.5);
  });

  test("a borda do campo de texto tem pelo menos 3:1 contra o fundo (WCAG 1.4.11)", () => {
    expect(contraste(t["borda-campo"], t["background"])).toBeGreaterThanOrEqual(3);
  });
});
