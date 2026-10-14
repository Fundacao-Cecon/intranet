# Sprint 2 — Acesso e consulta

**Data:** 14/10/2026. T-06 a T-09 implementadas e verificadas localmente. T-10 está em revisão, aguardando demonstração acompanhada e aceite da TI. Não houve importação do legado ou implantação na VM.

## Entrega e critérios

| Tarefa | Entrega e evidências | Critérios |
|---|---|---|
| T-06 | Login por username/senha, sessão regenerada, último acesso, logout e senha pública/autenticada com comprovação | CA-01 e CA-02 |
| T-07 | CSRF obrigatório, rate limit, sessão vinculada à credencial e policies de leitura/administração | Proteções de acesso de CA-01 e CSRF/entradas de CA-13 |
| T-08 | Raiz/pasta por ID, breadcrumbs, filtro de nomes sem diferenciar caixa, pastas primeiro e metadados disponíveis | CA-04 e leitura de CA-11 |
| T-09 | Streams autenticados por ID, miniaturas raster, attachment seguro e suporte a intervalos de bytes | CA-05 e download individual de CA-06 |
| T-10 | Roteiro técnico executado nos dois perfis, evidências locais e dados fictícios disponíveis | Aceite da TI pendente |

Login, senha, recusas de permissão e início de leitura têm auditoria. ZIP, upload, manutenção do acervo, administração de usuários, consulta de auditoria e lixeira seguem as sprints próprias. Os endpoints administrativos futuros não estão publicados; as policies reutilizáveis foram testadas, inclusive por uma rota administrativa criada somente dentro da suíte. Isso não antecipa a entrega da Sprint 3.

## Decisões de implementação

- `AccessService` valida credenciais e mantém senha/último acesso e auditoria na mesma transação. Falha da auditoria impede anunciar sucesso e reverte os dados. Senhas nunca são repopuladas em formulários, gravadas em eventos ou devolvidas nas respostas.
- Senhas novas exigem pelo menos 12 caracteres e no máximo 72 bytes, para respeitar o hash bcrypt. O formulário anterior ao login exige username e senha atual. Dentro da sessão, somente a conta autenticada pode ser alterada, mesmo com username adulterado na requisição.
- A sessão armazena um fingerprint da credencial. Cada requisição verifica esse fingerprint e o perfil; mudança de senha invalida sessões antigas. Logout invalida a sessão e renova o token CSRF, mesmo se o registro de auditoria falhar; nessa situação registra aviso operacional. Não existe opção de sessão permanente.
- Limites: cinco envios por minuto por username/IP e trinta por IP, separadamente para login e troca de senha. As chaves de rate limit são derivadas, sem registrar a senha. Esses parâmetros locais podem ser revisados na homologação operacional.
- Laravel 13 pode aceitar a indicação same-origin como proteção de origem. `StrictCsrf` exige o token também nesse caso, para cumprir o contrato planejado de CSRF ausente/inválido recusado.
- `ItemHierarchy` percorre o item e seus ancestrais, recusando removidos, pais ausentes/arquivo e ciclos. A policy verifica essa cadeia antes de abrir bytes. A consulta lista apenas filhos ativos de um destino previamente autorizado.
- O filtro principal roda no navegador sobre os nomes da pasta atual. O GET com `q` fornece alternativa no servidor, com escape de curingas SQL; o link de limpar filtro volta à pasta completa. Blade escapa nomes e snapshots.
- `PrivateFileReader` aceita somente chaves relativas à área `documents`, rejeita traversal e symlinks para fora da área e confirma que o caminho resolvido permanece nessa área. Nome e caminho do cliente não são usados para localizar o arquivo.
- PDF, PNG/JPEG/GIF/WebP, MP4 e TXT podem ser inline quando extensão e MIME detectado concordam. HTML, SVG, formatos desconhecidos ou divergentes são attachment. Metadado MIME no banco não determina a interpretação do navegador. A reprodução de MP4 depende dos codecs aceitos pelo navegador; a suíte valida MIME e ranges com um container sintético.
- Streams usam um arquivo aberto, blocos limitados e ranges simples, sem carregar todo o documento na memória. Miniaturas são o raster original autenticado exibido em tamanho reduzido pela interface; não há geração de derivados nesta sprint.
- Respostas privadas usam no-store, nosniff e Content-Disposition seguro. A política de conteúdo preserva o visualizador nativo de PDF e a mídia, sem permitir scripts da aplicação nos arquivos. Auditoria registra `*_STARTED`, sem afirmar que o navegador recebeu o arquivo inteiro. Arquivos ausentes/falhas de leitura registram resultado operacional sem caminhos físicos.
- Interface em português, tema persistente e listagem em cartões nas telas menores. Sessão local usa cookie HttpOnly/SameSite e arquivos privados. HTTPS/cookie Secure na VM dependem da implantação futura.

## Verificação executada

**52 testes e 259 assertions aprovados** no PostgreSQL/storage isolados, incluindo a base da Sprint 1. Build de assets e Pint aprovados. Casos novos cobrem credenciais inválidas, dois perfis, regeneração, logout, troca pública/autenticada, senha atual incorreta, confirmação/limites, sessões antigas, CSRF real, limitação de tentativas, policies, autor ausente, hierarquia removida, traversal, bytes/cabeçalhos, formatos perigosos, miniaturas, ranges e falhas de auditoria.

No Edge, com os dois perfis: login → pasta de demonstração → filtro → download TXT → visualização TXT/PDF → miniatura → tema persistente → subpasta/breadcrumbs → logout. Nenhum erro JavaScript ou requisição externa foi observado. A senha foi alterada antes e depois do login em uma conta fictícia, uma sessão anterior foi recusada e a senha original de demonstração foi restaurada ao terminar. PDF renderizado e telas desktop/mobile conferidas visualmente.

Uma falha de permissão do seed local foi identificada no navegador: diretório criado por root não podia ser lido pelo PHP-FPM. O comando agora exige `www-data`; a demonstração local existente teve a propriedade corrigida. Nenhum arquivo legado foi alterado. Evidências geradas ficam em `artifacts/sprint-2`, ignoradas pelo Git.

## Demonstrar e registrar aceite — T-10

Abra http://127.0.0.1:8080. Neste computador, contas e credenciais aleatórias estão no arquivo local ignorado `artifacts/sprint-2/demo-access.json`. Não usar contas do SQLite legado.

Em um ambiente local novo, após iniciar os serviços:

```powershell
./scripts/dev.ps1 demo
```

O comando cria `demo.user` e `demo.admin`, uma pasta e arquivos fictícios, e salva as credenciais somente no arquivo local. Recusa ambientes diferentes de local/intranet_dev e não sobrescreve contas existentes. A demonstração é opt-in; iniciar containers ou executar migrations não cria contas. Para um ambiente já preparado, consultar o arquivo existente; senhas perdidas devem ser trocadas por procedimento local controlado.

| Passo | Resultado esperado | Verificação técnica | TI |
|---|---|---|---|
| Login inválido e válido nos dois perfis | Recusa genérica ou entrada com último acesso atualizado | Aprovada | Pendente |
| Senha pública e autenticada | Exige senha atual; nova senha funciona e sessão antiga encerra | Aprovada | Pendente |
| Abrir raiz e subpastas | Breadcrumbs corretos, pastas antes dos arquivos | Aprovada | Pendente |
| Digitar nome em outra caixa | Filtra apenas a pasta atual | Aprovada | Pendente |
| Conferir autoria, tamanho e datas | Dados disponíveis exibidos; ausentes permanecem `-` | Aprovada | Pendente |
| Visualizar TXT/PDF/imagem e baixar | Conteúdo e nome preservados, sem URL pública | Aprovada | Pendente |
| Logout e tentativa de leitura | Solicita novo login e não entrega bytes | Aprovada | Pendente |
| Tema e tela menor | Preferência persiste; consulta e ações utilizáveis | Aprovada | Pendente |

Registrar data, papel do avaliador, observações, ajustes solicitados e decisão da TI após a demonstração. Não há aceite institucional registrado nesta entrega. Consulte [planejamento](planejamento.md), [desenvolvimento](desenvolvimento.md) e [diário](diario-de-bordo.md).

Referências técnicas consultadas: [autenticação Laravel 13](https://laravel.com/docs/13.x/authentication), [proteção CSRF](https://laravel.com/docs/13.x/csrf) e [respostas HTTP](https://laravel.com/docs/13.x/responses). O middleware instalado também foi inspecionado para confirmar o comportamento de origem e tokens.
