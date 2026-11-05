// @vitest-environment node
//
// Guardas da ADR 0008: o front é uma exportação estática servida sob /novo, sem servidor Node,
// sem Server Functions e sem autenticação em middleware (CVE-2025-29927 e CVE-2025-55182).
import { existsSync, readdirSync, readFileSync, statSync } from "node:fs";
import { join } from "node:path";
import { describe, expect, test } from "vitest";

import nextConfig from "../next.config";

const src = join(__dirname, "..", "src");

function arquivos(dir: string): string[] {
  return readdirSync(dir).flatMap((nome) => {
    const caminho = join(dir, nome);
    return statSync(caminho).isDirectory() ? arquivos(caminho) : [caminho];
  });
}

describe("ADR 0008: exportação estática", () => {
  test("next.config exporta estático sob /novo", () => {
    expect(nextConfig.output).toBe("export");
    expect(nextConfig.basePath).toBe("/novo");
  });

  test("não há middleware nem proxy do Next", () => {
    for (const nome of ["middleware", "proxy"]) {
      for (const ext of ["ts", "js"]) {
        expect(existsSync(join(src, `${nome}.${ext}`)), `${nome}.${ext}`).toBe(false);
        expect(existsSync(join(src, "..", `${nome}.${ext}`)), `${nome}.${ext}`).toBe(false);
      }
    }
  });

  test("nenhum arquivo declara Server Functions ('use server')", () => {
    const codigo = arquivos(src).filter((f) => /\.(ts|tsx|js|jsx)$/.test(f));
    expect(codigo.length).toBeGreaterThan(0);
    const infratores = codigo.filter((f) =>
      /^\s*["']use server["']/m.test(readFileSync(f, "utf8")),
    );
    expect(infratores).toEqual([]);
  });
});
