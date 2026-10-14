# Intranet FCECON

Portal interno da Fundação Centro de Controle de Oncologia para consulta e administração de documentos institucionais.

O repositório reúne a aplicação PHP legada e a documentação de sua evolução para **Laravel 13, PHP 8.4, PostgreSQL e Docker**. A nova aplicação e os arquivos Docker ainda serão implementados; a organização do repositório não significa conclusão das tarefas do backlog.

## Documentação

| Documento | Conteúdo |
|---|---|
| [Arquitetura](docs/arquitetura.md) | Problemática, solução, regras de negócio, requisitos e decisões técnicas. |
| [Modelagem](docs/modelagem.md) | Nove diagramas dos fluxos de usuário, API interna, banco e desenvolvimento. |
| [Planejamento](docs/planejamento.md) | Trinta tarefas em seis sprints, dependências e critérios de aceite. |
| [Desenvolvimento](docs/desenvolvimento.md) | Preparação local, estado do ambiente e próximos passos. |
| [Índice da documentação](docs/README.md) | Ordem de leitura e relação entre os documentos. |

## Estrutura atual

```text
intranet/
├── .github/               # Modelos de tarefa e pull request
├── docs/                  # Arquitetura, modelagem e planejamento
├── index.php              # Aplicação legada
├── database.db            # Banco SQLite herdado do repositório
├── uploads/               # Documentos e lixeira do legado
├── fcecon_bg.png           # Imagem de fundo do legado
├── logotipo.png            # Identidade visual do legado
├── .editorconfig           # Padrões de edição
├── .gitattributes          # Tratamento de texto e arquivos binários
├── .gitignore              # Dados locais, dependências e artefatos
├── CONTRIBUTING.md         # Fluxo de contribuição
└── README.md
```

O legado permanece na posição original porque usa caminhos relativos para banco, uploads e imagens. A estrutura Laravel será introduzida como parte das tarefas T-02 a T-04, preservando o sistema e os dados existentes durante a transição. Não há diretórios vazios simulando uma aplicação pronta.

## Ambiente futuro

- Desenvolvimento: Docker Desktop com WSL2 e containers Linux no computador Windows.
- Aplicação: Laravel com Blade/JavaScript, PostgreSQL e documentos privados.
- Serviços Compose: `web`, `app`, `db` e `scheduler`.
- Produção: Docker Engine e Compose em VM Linux no servidor principal.
- Entrega: imagens versionadas em arquivo `.tar`, com manifesto e checksum, importadas na VM sem registry.

Leia o [guia de desenvolvimento](docs/desenvolvimento.md) para verificar a instalação do Docker. Ainda não há `compose.yaml` ou Dockerfile neste checkout; a inicialização dos serviços será documentada quando T-04 for implementada.

## Próximo marco

A Sprint 1 prepara a base técnica. Ao final da Sprint 2, a TI avalia login, perfis, navegação e download no Docker local. As seis sprints de duas semanas são uma hipótese de organização, sem data de início confirmada ou garantia de prazo.

## Código e dados

O `.gitignore` protege novos arquivos locais de ambiente, dependências, banco e uploads. Arquivos legados já rastreados, como `database.db` e documentos de `uploads`, continuam no histórico e no checkout; ignorar um caminho não remove arquivos rastreados. Sua separação definitiva do código será tratada na migração, com inventário e backup.

Use dados sintéticos nos testes. Segredos, certificados, backups e pacotes de imagens ficam fora do versionamento; a implantação fornece configuração e dados separadamente das imagens.

Para contribuir, consulte [CONTRIBUTING.md](CONTRIBUTING.md).
