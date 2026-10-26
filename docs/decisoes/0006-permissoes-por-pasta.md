# ADR 0006 — Permissões por pasta (usuários e grupos)

**Status:** aceita

## Contexto
Até aqui todo usuário logado via todas as pastas. A intranet precisa de pastas de uso restrito (RH, financeiro, diretoria) sem criar uma instalação por departamento.

## Decisão
- Uma pasta pode ter uma **regra**: lista de usuários e de grupos admitidos (tabelas `folder_rules`, `folder_rule_users`, `folder_rule_groups`; grupos em `groups` e `group_members`). Sem regra, a pasta é livre, como antes.
- **Interseção na cadeia:** para entrar numa pasta é preciso passar por *todas* as regras dos ancestrais e da própria pasta. A regra de uma subpasta só estreita; nunca reabre o que o pai fechou.
- **Regra vazia = só admins.** Apagar um grupo ou um usuário nunca libera uma pasta: a regra encolhe e, no limite, vira "só admins" (falha fechada).
- **Admin ignora regras.**
- **Pasta fechada aparece com cadeado** para quem não tem acesso (escolha do produto): o nome é visível; tamanho, datas e autoria não. Abrir pela URL volta à raiz com "Acesso negado a esta pasta."; download e ZIP respondem 403.
- A checagem vive em um único ponto, `Controller::location()`, por onde passam listagem, download, ZIP e redirecionamentos.
- As regras seguem o caminho da pasta: renomear, mover para a lixeira e restaurar reescrevem os caminhos (`FolderAccess::renamed`), igual aos metadados de autoria. Excluir definitivamente apaga a regra.

## Consequências
- (+) Sem dependência nova; uma consulta por listagem (`lockedMap`) e uma por checagem de pasta.
- (+) Falha fechada: erro de configuração restringe, não libera.
- (−) O cadeado revela que a pasta existe e o nome dela. Quem precisar esconder até o nome deve trocar para "ocultar" (mudança só na view e em `lockedMap`).
- (−) Para liberar uma subpasta a alguém, o admin precisa liberar também as pastas acima dela; o modal avisa.
- Gatilho para revisar: permissões por *arquivo*, herança "mais próxima vence" ou ações diferentes por usuário (só ler / enviar).
