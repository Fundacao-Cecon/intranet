# Sprint 1 — Fundação

Entrega técnica local em 14/10/2026, tarefas T-01 a T-05. O aceite institucional e os parâmetros da VM continuam pendentes conforme [inventário](inventario.md). Login, perfis aplicados às rotas, leitura de documentos, publicação e reconciliação ainda pertencem às sprints seguintes.

## Entregas e requisitos

| Tarefa | Resultado e evidência |
|---|---|
| T-01 | Docker/WSL2 e legado inventariados; lacunas da TI identificadas, sem declarar CA-14/15/17/19 aprovados |
| T-02 | Laravel 13.36.0/PHP 8.4, Blade e Vite 8.3.4, assets locais, lockfiles e exemplo sem segredos; base de RNF-05/07 e RF-13 |
| T-03 | Migration PostgreSQL real e testes de constraints; base de RF-03/11/12, RNF-04/05/09 e CA-03/08/11/12 |
| T-04 | Docker local web/app/db, scheduler opcional, rede e volumes isolados; CA-16 local verificado |
| T-05 | AuditLogger, factories e fixture sintéticas, 25 testes/52 assertions; base de RF-12, RNF-05/09 e CA-12 |

CA amplos permanecem parcialmente cobertos até a implementação dos casos de uso. Não houve importação de produção, criação automática de usuários, implantação ou pacote de imagens de produção.

## Modelo físico implementado

PostgreSQL guarda metadados, relações e auditoria. Bytes ficam somente no volume privado; não existe link público de storage.

| Entidade | Campos e integridade |
|---|---|
| users | bigint identity, username único com collation C, password_hash, role restrito a user/admin, timestamps opcionais e last_login |
| items | bigint identity, parent_id opcional, kind file/folder, name, storage_key único, size_bytes, media_type, autores opcionais e snapshots, timestamps e lixeira |
| audit_logs | bigint identity, ator/item opcionais, snapshots, action, detail textual estruturado, outcome, ip inet e occurred_at |

Nomes ativos são únicos por pasta, inclusive na raiz com `NULLS NOT DISTINCT`, e deixam de reservar o nome ao ir para lixeira. `trash_name` é único entre removidos. Comparação C é sensível a maiúsculas; normalização/sufixos pertencem ao serviço futuro. Arquivos exigem chave e tamanho não negativo; pastas não guardam bytes. Estado de lixeira exige deleted_at e trash_name juntos.

FK do pai restringe exclusão enquanto houver filhos. Trigger rejeita pai arquivo, ciclos e conversão de pasta com filhos em arquivo, usando advisory lock transacional para coordenar alterações da hierarquia. FKs de autoria/auditoria usam SET NULL, preservando snapshots após remoção. Todas as FKs consultáveis têm índices. Snapshots devem ser preenchidos pelos serviços; factories e auditoria já fazem isso. Filtragem de ancestrais removidos e operações de lixeira serão implementadas nas tarefas funcionais, não são garantidas pelo simples model Item.

`intranet_migrator` cria o esquema; `intranet_app` executa DML e usa sequências, sem DDL. Migration up e rollback foram ensaiados apenas em bancos sintéticos desta sprint.

## Auditoria

AuditLogger valida action, outcome e IP e aceita contexto apenas com item_name, username, file_count e reason_code escalares. Rejeita outras chaves e payloads aninhados antes de gravar; senhas e conteúdo não são parâmetros de contexto. Chamadores devem fornecer somente alvos e valores textuais apropriados. O evento conserva snapshots e participa da transação do chamador. Integração com login/upload/download e auditoria administrativa ainda depende dos próximos serviços.

## Verificações executadas

- Build PHP, Composer com lock e sem avisos de vulnerabilidade, build npm/Vite e Composer validate --strict aprovados.
- Pint aplicado aos diretórios Laravel; migration up/rollback/up verificada.
- Suíte PostgreSQL isolada: **25 testes, 52 assertions**, sem warnings. Cobertura de perfis, hash, constraints, hierarquia, snapshots, transações, recusa de contexto inseguro, ausência de privilégio DDL, HTTP e storage sintético.
- Inicialização web/app/db saudável. Somente web publica 127.0.0.1:8080; scheduler não iniciado.
- Navegador Edge: título e conteúdo corretos, sem erros JavaScript ou requisições externas. Assets servidos localmente.
- HTTP `/` e `/up`: 200; `/database.db`, `/uploads/test.pdf`, `/storage/test.txt`: 404; `/.env`: 403.
- Banco desligado: `/up` retorna 500; recuperação do banco retorna serviços saudáveis. Testes de falha DB/disco verificam mensagem genérica sem detalhes de conexão.
- APP_ENV=testing apontado ao banco de desenvolvimento foi recusado na inicialização, antes dos testes.
- Recriação forçada de db/app/web preservou registro sintético, arquivo privado criptografado e fingerprint da APP_KEY; sondas removidas depois.
- Hashes SHA-256 dos **59 arquivos legados** conferidos antes/depois, todos iguais. SQLite não foi importado ou alterado.

Relatórios locais e captura do navegador estão em artifacts/sprint-1, ignorados. Reprodução e comandos estão em [desenvolvimento](desenvolvimento.md). Imagens oficiais fixadas por digest em docker/images.env; produção sem mounts, exportação completa .tar, checksum, manifesto e retorno na VM serão validados na Sprint 6.
