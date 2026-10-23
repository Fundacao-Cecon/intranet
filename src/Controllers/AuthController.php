<?php
namespace App\Controllers;

/** Login, logout e troca de senha. */
final class AuthController extends Controller
{
    /** Tela de login (usuário não autenticado). */
    public function showLogin(): void
    {
        view('login', [
            'flash'    => $this->takeFlash(),
            'hasUsers' => $this->ctx->auth->hasUsers(),
        ]);
    }

    public function login(): void
    {
        list($ok, $erro) = $this->ctx->auth->attempt($this->post('user'), $this->post('pass'));
        if (!$ok) {
            $this->flash($erro, 'error');
        }
        $this->redirect();
    }

    public function logout(): void
    {
        $this->ctx->auth->logout();
        $this->redirect();
    }

    /**
     * Logado: troca a senha do próprio usuário (ignora qualquer nome enviado).
     * Deslogado: formulário público do login — exige a senha atual e conta no rate limit.
     */
    public function changePassword(): void
    {
        $username = $this->ctx->auth->check()
            ? (string) $this->ctx->auth->username()
            : $this->post('user_change');

        list($ok, $msg) = $this->ctx->auth->changePassword($username, $this->post('pass_old'), $this->post('pass_new'));
        $this->flash($msg, $ok ? 'success' : 'error');

        if ($this->ctx->auth->check()) {
            $this->redirectBack();
        }
        $this->redirect();
    }
}
