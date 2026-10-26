# Usuários e permissões

| Ação | `user` | `admin` |
|---|:-:|:-:|
| Entrar, navegar pastas (inclusive pela árvore lateral), buscar | ✅ | ✅ |
| Ver / baixar arquivo, baixar ZIP | ✅ | ✅ |
| Alterar a própria senha (🔑) | ✅ | ✅ |
| Upload, criar pasta, renomear | — | ✅ |
| Mover arquivos e pastas entre pastas (botão, modal ou arrastar e soltar); soltar arquivos do computador numa pasta para enviar | — | ✅ |
| Mover para a lixeira | — | ✅ |
| Ver lixeira, restaurar, excluir definitivo, esvaziar | — | ✅ |
| Criar / excluir usuários, ver logs (Painel Master) | — | ✅ |
| Criar grupos e definir quem acessa cada pasta | — | ✅ |
| Entrar em pasta com acesso restrito | só se listado (ou no grupo) | ✅ sempre |

## Acesso por pasta

Cada pasta pode ser **livre** (padrão, herda da pasta de cima) ou **restrita** a usuários e grupos escolhidos pelo admin (botão de cadeado na linha da pasta; grupos em Painel Master → Grupos).

- Para entrar numa pasta é preciso passar por **todas** as regras do caminho: a regra de uma subpasta só estreita, nunca reabre o que a pasta de cima fechou.
- Restringir sem marcar ninguém deixa a pasta **só para admins**. Admin sempre entra.
- Quem não tem acesso vê a pasta com cadeado e a etiqueta "restrita"; ao abrir (inclusive pela URL) volta ao início com "Acesso negado a esta pasta."; download e ZIP respondem 403.
- A regra acompanha a pasta ao renomear, ir para a lixeira e ser restaurada. Excluir o grupo ou o usuário só tira acesso, nunca libera.
- Decisão e motivos: [ADR 0006](decisoes/0006-permissoes-por-pasta.md).

Regras:
- O perfil é lido do banco a cada requisição: mudar ou apagar um usuário vale imediatamente.
- Não é possível excluir a si mesmo nem o último admin.
- Sessão expira após 8 h de inatividade (`session_lifetime`) **ou** 12 h depois do login, mesmo com uso contínuo (`session_absolute`). Ao expirar, a tela de login mostra "Sua sessão expirou".
- 5 falhas de login (ou de troca de senha) em 15 min para o mesmo usuário+IP bloqueiam novas tentativas por 15 min.
- Senha: mínimo 8 caracteres.
