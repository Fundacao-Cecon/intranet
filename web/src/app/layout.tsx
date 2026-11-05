import type { Metadata } from "next";
import "./globals.css";

export const metadata: Metadata = {
  title: "Intranet FCECON",
  description: "Gerenciador de arquivos da Fundação CECON",
  robots: { index: false, follow: false },
};

export default function RootLayout({ children }: LayoutProps<"/">) {
  return (
    <html lang="pt-BR">
      <body>{children}</body>
    </html>
  );
}
