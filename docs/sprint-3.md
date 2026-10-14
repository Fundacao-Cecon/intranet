# Sprint 3 — Administração

**Data:** 14/10/2026. T-11 a T-14 concluídas tecnicamente no ambiente local. A consulta da auditoria e a validação técnica de T-15 estão implementadas; T-15 permanece em revisão, aguardando avaliação da TI e o aceite de T-10. Migração e implantação continuam pendentes.

## Entregas e critérios

| Tarefa | Resultado | Critérios cobertos no incremento |
|---|---|---|
| T-11 | Admin cadastra, lista e exclui contas; login único, perfis user/admin, senha confirmada e hash; autoexclusão recusada | CA-03; documentos, snapshots e auditoria sobrevivem à exclusão |
| T-12 | Pastas lógicas e renomeação por ID, com nome validado, destino ativo, colisões recusadas e extensão preservada | CA-08 e manutenção de identidade/autoria de CA-11 |
| T-13 | Upload por arquivo, staging privado, validação, reserva concorrente de nome, publicação e compensação | CA-07 e falhas pertinentes de CA-13 |
| T-14 | Formulários administrativos, confirmação de exclusão, progresso de transmissão e resultado por arquivo, tema e tela menor | Interface disponível de CA-15 e experiência de CA-07; avaliação institucional pendente |
| T-15 | Admin consulta os 50 eventos mais recentes; suíte e roteiro integrados executados | CA-12 nas operações disponíveis; aceite da TI pendente |

Rotas internas implementadas: GET/POST `/usuarios`, DELETE `/usuarios/{id}`, POST `/pastas`, PATCH `/itens/{id}`, POST `/documentos/upload` e GET `/auditoria`. Todas exigem sessão e permissão administrativa no servidor; alterações exigem CSRF. Formulários usam method spoofing para PATCH/DELETE. ZIP e lixeira não foram antecipados.

## Integridade e segurança

Gestão de usuários, criação de pastas e renomeação persistem alterações e auditoria na mesma transação. FKs SET NULL e snapshots conservam o histórico após excluir uma conta. O login não recebe senha padrão; criação e troca reutilizam a regra de pelo menos 12 caracteres e até 72 bytes. Senhas não aparecem em listagem, flash ou auditoria.

Mutações documentais obtêm primeiro a trava transacional PostgreSQL `62101401`, comum ao trigger de hierarquia, e depois os locks de linhas. Essa ordem também deve ser mantida na futura lixeira/reconciliação. A trava cobre a raiz lógica e evita concorrência na reserva dos nomes; é deliberadamente global nesta versão. Nomes ativos continuam protegidos pela constraint, incluindo a raiz. Nomes aceitam letras, números, espaços, traços, pontos, parênteses e sublinhado; caminhos, nomes ocultos e pontos consecutivos são recusados. Renomear um arquivo recebe o nome base e reaplica a extensão original. Nenhum byte ou filho é movido por renomeação.

Upload mantém a lista de extensões do legado, acrescentando validação do MIME detectado no conteúdo. DOCX/XLSX/PPTX e ODT/ODS também passam por conferência básica de estrutura ZIP, sem extração; isso não é uma inspeção antivírus. SVG e formatos fora da lista inline segura continuam como attachment. A chave física é um UUID independente do nome, sem extensão exigida pelo disco.

Limites locais em `config/documents.php`: **10 arquivos, 20 MiB por arquivo e 64 MiB por lote**. PHP usa upload_max_filesize=20M, post_max_size=80M e max_file_uploads=100; Nginx aceita até 80 MiB de corpo, deixando margem para multipart. A aplicação recusa lotes acima de dez, inclusive quando o PHP recebe mais arquivos. Limites de produção e capacidade ainda exigem homologação da TI. Novos limites PHP exigem rebuild da imagem e recriação dos serviços; dados e APP_KEY permanecem nos volumes/configuração existentes.

## Protocolo de publicação

1. Validar transmissão, tamanho, nome e extensão; criar manifesto privado com ID da operação.
2. Copiar para staging por stream e detectar MIME, tamanho e SHA-256 no arquivo recebido.
3. BEGIN; obter trava; revalidar pasta e ancestrais; reservar nome e inserir metadados ainda não confirmados.
4. Atualizar o manifesto com ID, chave, hash e fase; publicar via hard link atômico no mesmo volume, recusando destino existente.
5. Registrar autoria e auditoria; persistir fase `committing`; COMMIT; limpar staging e manifesto após confirmação.

Cada arquivo tem sua transação. Leitores em outra conexão não veem os metadados antes do commit. Nomes repetidos recebem sufixos numéricos sem substituir o arquivo anterior. Staging e documentos estão no mesmo volume privado, como exige a publicação por hard link.

Falha conhecida antes do commit faz rollback e remove apenas os bytes criados pela operação. Uma colisão física não autoriza apagar o arquivo já existente. Falha de rollback/limpeza ou perda da confirmação do commit mantém manifesto e bytes para reconciliação; a resposta é `pending`, sem afirmar sucesso e sem apagar arquivos às cegas. Manifestos incluem versão, operação, fase, chaves internas, ID reservado, tamanho e hash quando disponíveis; não guardam senha ou conteúdo. Interrupção abrupta pode deixar manifesto na fase anterior; sua fase isolada não prova se houve commit.

Lote totalmente publicado: HTTP 200 / `success`. Lote parcial: HTTP 200 / `warning`, com resultado individual. Recusa total de validação: 422; falha total de infraestrutura/confirmação: 503. Recusas globais usam HTTP apropriado e `status`/`msg`. O progresso visual mede somente transmissão; publicação depende da resposta final. Erro de rede/timeout pode ter resultado ambíguo e não autoriza reenvio automático.

Inspeção operacional disponível, somente leitura:

```powershell
docker compose -f compose.yaml -f compose.development.yaml exec --user www-data -T app php artisan intranet:uploads:pending
```

O comando lista manifestos e indica existência de referência confirmada no banco. Não apaga, restaura ou reconcilia arquivos. A recuperação automática e o scheduler pertencem a T-20; preservar os manifestos/bytes até conferir banco, tamanho e hash é obrigatório. Não remover volumes para resolver uma pendência.

## Evidências executadas

- **75 testes e 398 assertions aprovados**, incluindo regressão das Sprints 1 e 2. PostgreSQL e storage de testes permanecem separados do desenvolvimento.
- Concorrência em processos/conexões PostgreSQL reais: nomes idênticos produziram dois nomes e duas chaves com bytes distintos. Observador independente não listou item com publicação ainda não confirmada.
- Commit realmente confirmado seguido de erro simulado de resposta: referência e bytes sobreviveram, com manifesto e resultado pending.
- Falhas de transmissão, staging/publicação/auditoria, MIME divergente, limites, pasta removida durante staging, colisão física e compensação verificadas. Exclusão de usuário preservou autoria/histórico; autoexclusão e acesso administrativo de user foram recusados.
- Edge: cadastro/login/exclusão de conta fictícia; sessão excluída recusada; pasta, renomeação, upload válido, parcial, inválido, sufixo, CSRF e auditoria conferidos. Nenhum erro JavaScript ou requisição externa. Desktop/tela menor revisados visualmente.
- Build PHP/assets e Pint aprovados; limites PHP conferidos no container. Web/app/db saudáveis; inspeção local terminou sem manifestos pendentes.

Relatórios e capturas estão em `artifacts/sprint-3`, ignorados. O acervo usado é fictício; não houve importação ou alteração do SQLite, PDFs e imagens legados. Não há migrations novas nesta sprint.

## Roteiro de avaliação da TI

Entrar em http://127.0.0.1:8080/login como `demo.admin`, usando as credenciais locais já existentes em `artifacts/sprint-2/demo-access.json`. Verificar cadastro/exclusão, recusa de autoexclusão, pastas, renomeação, três resultados de upload e os eventos em Auditoria. Entrar como `demo.user` e conferir que controles/rotas administrativas são recusados. Revisar tema e tela menor. Registrar avaliador por papel, data, observações, ajustes e decisão; **não há aceite da TI registrado** para T-10/T-15.

Referências: [planejamento](planejamento.md), [modelagem](modelagem.md), [diário](diario-de-bordo.md), [validação Laravel](https://laravel.com/docs/13.x/validation), [storage Laravel](https://laravel.com/docs/13.x/filesystem) e [locks PostgreSQL](https://www.postgresql.org/docs/current/explicit-locking.html). As orientações de ordem de locks do skill PostgreSQL também foram aplicadas.
