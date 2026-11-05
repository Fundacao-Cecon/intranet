import type { NextConfig } from "next";

// ADR 0008: exportação estática servida pelo Nginx/Apache sob /novo até a troca (1.0.0).
// Sem Server Functions e sem autenticação em middleware.
const nextConfig: NextConfig = {
  output: "export",
  basePath: "/novo",
  trailingSlash: true,
  images: { unoptimized: true },
};

export default nextConfig;
