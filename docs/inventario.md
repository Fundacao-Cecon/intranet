# Inventário da Sprint 1

Levantamento em 14 de outubro de 2026. Este inventário descreve o checkout local e os pré-requisitos conhecidos; não comprova acesso ao ambiente de produção.

## Ambiente local confirmado

| Item | Resultado |
|---|---|
| Sistema de desenvolvimento | Windows, arquitetura AMD64 |
| Docker Desktop | 4.95.0 |
| Docker Engine | 29.8.2; backend Linux/amd64 em WSL2 |
| Compose | 5.6.0 |
| Recursos do engine | 12 CPUs lógicas; 8.239.165.440 bytes de memória reportados |
| Porta local | 8080 livre antes da inicialização do projeto; publicação restrita a 127.0.0.1 |
| Runtime PHP e Node | Executados em containers; instalação no host não é pré-requisito |

A instalação Docker é por usuário. O launcher `scripts/dev.ps1` detecta o CLI e inclui seu diretório no PATH do processo para encontrar o helper de credenciais, sem mudar o PATH global.

## Legado local

O SQLite foi aberto em modo somente leitura/imutável, sem consultar senhas, nomes de usuários ou detalhes dos eventos. A fonte é uma cópia versionada local, não um acesso ao servidor institucional.

| Artefato | Resultado |
|---|---|
| Arquivos legados rastreados | 59 arquivos; 100.283.841 bytes no total |
| SQLite `database.db` | 98.304 bytes |
| `users` | 6 registros; id, username, password, role, created_at, last_login |
| `logs` | 432 registros; id, data_hora, ip, usuario, acao, detalhe |
| `files_meta` | 55 registros; id, filepath, created_by, updated_by, created_at, updated_at |
| Arquivos em uploads | 55 arquivos; 96.121.935 bytes, incluindo .htaccess e lixeira |
| Arquivos na lixeira | 7 arquivos |

Hashes SHA-256 individuais foram registrados antes da integração em `artifacts/sprint-1/legacy-before.json`, ignorado pelo Git. A conferência final compara todos os arquivos com esse manifesto e com o histórico Git. Nenhuma importação de usuários/documentos é feita nesta sprint.

## Pendências da TI

| Informação | Estado | Responsável por papel | Impacto |
|---|---|---|---|
| Distribuição Linux e acesso à VM | Não disponível | TI | Homologação e implantação; não bloqueia Docker local |
| CPU, RAM, disco e arquitetura da VM | Não disponível | TI | Dimensionamento e plataforma da imagem final |
| Domínio, DNS e certificados | Não disponível | TI | HTTPS institucional da Sprint 6 |
| Limites de upload, lote, ZIP e concorrência | A definir | TI | Homologação das operações documentais |
| Destino, frequência e retenção de backup | A definir | TI | Ensaio de recuperação e corte |
| SLA, RPO e RTO | A definir | TI | Critérios operacionais de produção |
| Timezone efetivo do legado | Não confirmado | TI | Conversão temporal na importação da Sprint 5 |
| Cópia autorizada do acervo e SQLite de produção | Não disponibilizada | TI | Importação e comparação com ambiente real |

T-01 tem o levantamento local e as lacunas registradas. Os parâmetros externos continuam pendentes e devem ser confirmados antes das tarefas afetadas; não são substituídos por valores fictícios. Dados locais de teste são sintéticos e não tornam a migração homologada.
