# Usuários e permissões

| Ação | `user` | `admin` |
|---|:-:|:-:|
| Entrar, navegar pastas, buscar | ✅ | ✅ |
| Ver / baixar arquivo, baixar ZIP | ✅ | ✅ |
| Alterar a própria senha (🔑) | ✅ | ✅ |
| Upload, criar pasta, renomear | — | ✅ |
| Mover para a lixeira | — | ✅ |
| Ver lixeira, restaurar, excluir definitivo, esvaziar | — | ✅ |
| Criar / excluir usuários, ver logs (Painel Master) | — | ✅ |

Regras:
- O perfil é lido do banco a cada requisição: mudar ou apagar um usuário vale imediatamente.
- Não é possível excluir a si mesmo nem o último admin.
- Sessão expira após 8 h de inatividade (`config['auth']['session_lifetime']`).
- 5 falhas de login (ou de troca de senha) em 15 min para o mesmo usuário+IP bloqueiam novas tentativas por 15 min.
- Senha: mínimo 8 caracteres.
