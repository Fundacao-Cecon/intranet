// Servidor local de MESMO DOMÍNIO para experimentar o front exportado com a API real, como em
// produção (docs/transicao.md): serve `out/` sob /novo e repassa /api e /sanctum para o Laravel.
// Só para desenvolvimento; sem dependências.
//
//   1) php artisan serve            (em api/, porta 8000)
//   2) npm run build                (gera out/)
//   3) npm run dev:proxy            (http://localhost:8080/novo/)
//
// A API precisa liberar a origem: SANCTUM_STATEFUL_DOMAINS=localhost:8080 no .env dela.
import { createReadStream, existsSync, statSync } from "node:fs";
import { createServer, request } from "node:http";
import { extname, join, normalize } from "node:path";
import { fileURLToPath } from "node:url";

const PORTA = Number(process.env.PORT ?? 8080);
const API = new URL(process.env.API_URL ?? "http://127.0.0.1:8000");
const RAIZ = join(fileURLToPath(new URL(".", import.meta.url)), "..", "out");

const TIPOS = {
  ".html": "text/html; charset=utf-8",
  ".js": "text/javascript; charset=utf-8",
  ".css": "text/css; charset=utf-8",
  ".svg": "image/svg+xml",
  ".ico": "image/x-icon",
  ".json": "application/json; charset=utf-8",
  ".txt": "text/plain; charset=utf-8",
};

function repassar(req, res) {
  const saida = request(
    { host: API.hostname, port: API.port, path: req.url, method: req.method, headers: req.headers },
    (resp) => {
      res.writeHead(resp.statusCode ?? 502, resp.headers);
      resp.pipe(res);
    },
  );
  saida.on("error", () => {
    res.writeHead(502, { "Content-Type": "application/json" });
    res.end(JSON.stringify({ message: "API indisponível em " + API.origin }));
  });
  req.pipe(saida);
}

function servir(req, res) {
  const caminho = decodeURIComponent(new URL(req.url, "http://x").pathname);
  const relativo = normalize(caminho.replace(/^\/novo\/?/, "")).replace(/^([/\\])+/, "");
  let arquivo = join(RAIZ, relativo);
  if (!arquivo.startsWith(RAIZ)) return res.writeHead(403).end(); // sem sair de out/
  if (existsSync(arquivo) && statSync(arquivo).isDirectory()) arquivo = join(arquivo, "index.html");
  if (!existsSync(arquivo)) return res.writeHead(404).end("não encontrado");
  res.writeHead(200, { "Content-Type": TIPOS[extname(arquivo)] ?? "application/octet-stream" });
  createReadStream(arquivo).pipe(res);
}

createServer((req, res) => {
  if (req.url.startsWith("/api/") || req.url.startsWith("/sanctum/")) return repassar(req, res);
  if (req.url === "/") return res.writeHead(302, { Location: "/novo/" }).end();
  if (req.url.startsWith("/novo")) return servir(req, res);
  res.writeHead(404).end();
}).listen(PORTA, () => console.log(`http://localhost:${PORTA}/novo/  →  API ${API.origin}`));
