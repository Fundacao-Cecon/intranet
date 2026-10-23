<?php
namespace App\Controllers;

/** Gestão de usuários (somente admin). */
final class UsersController extends Controller
{
    public function create(): void
    {
        $this->requireAdmin();

        $login = trim($this->post('new_username'));
        list($ok, $msg) = $this->ctx->auth->createUser($login, $this->post('new_password'), $this->post('new_type'));
        if ($ok) {
            $this->ctx->logger->log('USER', 'Criou: ' . $login);
        }
        $this->flash($msg, $ok ? 'success' : 'error');
        $this->redirectBack();
    }

    public function delete(): void
    {
        $this->requireAdmin();

        $id = (int) $this->post('id');
        list($ok, $msg) = $this->ctx->auth->deleteUser($id);
        if ($ok) {
            $this->ctx->logger->log('USER', 'Deletou usuário ID: ' . $id);
        }
        $this->flash($msg, $ok ? 'success' : 'error');
        $this->redirectBack();
    }
}
