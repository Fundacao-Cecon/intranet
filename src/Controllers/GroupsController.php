<?php
namespace App\Controllers;

/** Grupos de usuários para as regras de pasta (somente admin). */
final class GroupsController extends Controller
{
    public function create(): void
    {
        $this->requireAdmin();

        $name = trim($this->post('group_name'));
        list($ok, $msg) = $this->ctx->groups->create($name);
        if ($ok) {
            $this->ctx->logger->log('GRUPO', 'Criou: ' . $name);
        }
        $this->flash($msg, $ok ? 'success' : 'error');
        $this->redirectBack();
    }

    public function delete(): void
    {
        $this->requireAdmin();

        $id = (int) $this->post('id');
        list($ok, $msg) = $this->ctx->groups->delete($id);
        if ($ok) {
            $this->ctx->logger->log('GRUPO', 'Excluiu grupo ID: ' . $id);
        }
        $this->flash($msg, $ok ? 'success' : 'error');
        $this->redirectBack();
    }

    public function setMembers(): void
    {
        $this->requireAdmin();

        $id = (int) $this->post('id');
        list($ok, $msg) = $this->ctx->groups->setMembers($id, $this->postIds('members'));
        if ($ok) {
            $this->ctx->logger->log('GRUPO', 'Membros do grupo ID: ' . $id);
        }
        $this->flash($msg, $ok ? 'success' : 'error');
        $this->redirectBack();
    }
}
