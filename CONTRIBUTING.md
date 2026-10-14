# Contribuição para a Intranet FCECON

## Antes de começar

Leia a [arquitetura](docs/arquitetura.md), a [modelagem](docs/modelagem.md) e o [planejamento](docs/planejamento.md). Cada tarefa tem dependências, requisitos e evidências próprias. O quadro começa com as tarefas em Backlog; organizar este repositório não conclui a fundação Laravel ou Docker.

O desenvolvedor responde pela execução e revisão técnica com apoio do Codex. A TI fornece infraestrutura, parâmetros institucionais e aceite dos marcos. Não há nomes pessoais ou responsáveis externos atribuídos automaticamente.

## Trabalho com Git

1. Verifique a branch e o estado do checkout antes de começar.
2. Parta de `origin/main` para uma nova tarefa, preservando alterações locais que ainda não tenham sido integradas.
3. Use uma branch identificável, como `feat/t-06-autenticacao`, `fix/t-13-upload` ou `docs/t-01-inventario`. As branches remotas existentes permanecem disponíveis.
4. Mantenha mudanças da tarefa, requisitos e testes relacionados no mesmo conjunto revisável.
5. Prepare a descrição do pull request com objetivo, IDs T/RF/RNF/CA e evidências. Publicar uma branch ou abrir um PR é uma ação distinta de organizar o checkout local.

Exemplo para começar uma tarefa com o checkout sem alterações pendentes:

```sh
git fetch origin
git switch --create feat/t-06-autenticacao --no-track origin/main
```

Os nomes de branch são exemplos; não troque de branch nem descarte alterações pendentes para repetir o comando. A branch `chore/organizar-repositorio` concentra a organização inicial.

Use commits descritivos, por exemplo `docs: organizar documentação do projeto` ou `feat: implementar login e logout (T-06)`. Não é necessário adicionar credenciais pessoais ao repositório.

## Critérios de revisão

- Mudança atende a tarefa e preserva as regras estabelecidas, incluindo os perfis `user`/`admin` e restauração para a raiz.
- Rotas e operações são autorizadas no servidor; não basta esconder controles na interface.
- Testes apropriados ao comportamento são executados, sem usar banco ou storage de produção. Mudanças apenas documentais exigem validação de links, referências e diagramas alterados.
- Evidência inclui o resultado obtido e as limitações reais; teste não executado não aparece como aprovado.
- Documentação relacionada acompanha mudanças de arquitetura, contrato ou fluxo.

Uma tarefa só passa a Concluída quando entrega, testes, revisão e evidências estão disponíveis. A TI valida os marcos e o corte conforme o planejamento.

## Dados operacionais e legado

O checkout inclui arquivos já versionados do legado. Preserve seus caminhos e conteúdo durante a organização. Não introduza alterações no SQLite, PDFs ou lixeira como efeito colateral de validação.

Novos arquivos `.env`, certificados e dados reais não devem integrar commits. Use os diretórios locais `backups/` e `artifacts/` para backups e pacotes de entrega, respectivamente; eles são ignorados pelo Git. Isso não cria nem executa uma rotina de backup.

O desenvolvimento futuro usa Docker e dados sintéticos. A entrada em produção exige inventário, ensaio de migração, restauração validada e aceite da TI; não se faz corte por simples troca de imagem.

## Modelos no GitHub

O repositório fornece um formulário de tarefa técnica em `.github/ISSUE_TEMPLATE/task.yml` e um modelo de PR em `.github/PULL_REQUEST_TEMPLATE.md`. Eles ajudam a registrar dependências e evidências quando forem publicados no GitHub. Criar um modelo não cria issues, não atribui usuários e não altera o status do backlog automaticamente.
