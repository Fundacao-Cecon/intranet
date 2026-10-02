<?php
/**
 * GERENCIADOR INTRANET - VERSÃO FINAL (METADADOS + CORREÇÃO UI)
 */

// Aumenta limites para arquivos grandes
ini_set('upload_max_filesize', '2048M');
ini_set('post_max_size', '2048M');
ini_set('memory_limit', '1024M');
ini_set('max_execution_time', '1200');

// Prevenção de erro de upload
if ($_SERVER['REQUEST_METHOD'] == 'POST' && empty($_POST) && empty($_FILES) && $_SERVER['CONTENT_LENGTH'] > 0) {
    die("Erro: O arquivo enviado excede o limite do servidor (post_max_size).");
}

session_start();

// ==================================================================
// 1. BANCO DE DADOS E CONFIGURAÇÕES
// ==================================================================

$dbFile = 'database.db';
$pastaRaiz = 'uploads/';
$pastaLixeira = 'uploads/.lixeira/';

if (!is_dir($pastaRaiz)) mkdir($pastaRaiz, 0777, true);
if (!is_dir($pastaLixeira)) mkdir($pastaLixeira, 0777, true);

if (!file_exists($pastaRaiz . '.htaccess')) {
    file_put_contents($pastaRaiz . '.htaccess', "Options -Indexes\n<FilesMatch \"\.(php|phtml|php5|phar|inc)\">\nOrder Deny,Allow\nDeny from all\n</FilesMatch>");
}

try {
    $db = new PDO("sqlite:" . $dbFile);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Tabela de usuários
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        role TEXT NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        last_login DATETIME
    )");

    // Tabela de Logs
    $db->exec("CREATE TABLE IF NOT EXISTS logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        data_hora DATETIME DEFAULT CURRENT_TIMESTAMP,
        ip TEXT,
        usuario TEXT,
        acao TEXT,
        detalhe TEXT
    )");

    // NOVA TABELA: Metadados de Arquivos (Quem criou/editou)
    $db->exec("CREATE TABLE IF NOT EXISTS files_meta (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        filepath TEXT UNIQUE,
        created_by TEXT,
        updated_by TEXT,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Cria usuários padrão se não existirem
    $stmt = $db->query("SELECT COUNT(*) FROM users");
    if ($stmt->fetchColumn() == 0) {
        $usuariosIniciais = [
            ['user' => 'adm',      'pass' => 'Fcecon2015', 'role' => 'admin'],
            ['user' => 'Vinicius', 'pass' => 'v06M0917',   'role' => 'admin'],
            ['user' => 'Fcecon',   'pass' => 'Fcecon',     'role' => 'user']
        ];
        $insert = $db->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
        foreach ($usuariosIniciais as $u) {
            $insert->execute([$u['user'], password_hash($u['pass'], PASSWORD_DEFAULT), $u['role']]);
        }
    }

} catch (PDOException $e) {
    die("Erro crítico no Banco de Dados: " . $e->getMessage());
}

$isAdmin = (isset($_SESSION['tipo']) && $_SESSION['tipo'] === 'admin');

$extensoesPermitidas = [
    'jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico',
    'pdf', 'txt', 'csv', 'rtf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'odt', 'ods',
    'dwg', 'dxf', 'xml', 'json', 'log', 'msg', 'eml', 'ofx',
    'mp4', 'mov', 'avi', 'mp3', 'wav',
    'zip', 'rar', '7z', 'tar', 'gz'
];

// ==================================================================
// 2. FUNÇÕES
// ==================================================================

function h($string) { return htmlspecialchars($string, ENT_QUOTES, 'UTF-8'); }

function limparNome($nome) {
    $nomeLimpo = preg_replace('/[^\p{L}\p{N}\s\-_.]/u', '', $nome);
    return str_replace(['..', '/'], '', $nomeLimpo);
}

function registrarLog($acao, $detalhe) {
    global $db;
    $ip = $_SERVER['REMOTE_ADDR'];
    $usuario = $_SESSION['usuario'] ?? 'Visitante';
    $stmt = $db->prepare("INSERT INTO logs (ip, usuario, acao, detalhe) VALUES (?, ?, ?, ?)");
    $stmt->execute([$ip, $usuario, $acao, $detalhe]);
}

function formatarTamanho($bytes) {
    if ($bytes >= 1073741824) return number_format($bytes / 1073741824, 2) . ' GB';
    if ($bytes >= 1048576) return number_format($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return number_format($bytes / 1024, 2) . ' KB';
    return $bytes . ' bytes';
}

function renomearSeExistir($caminho, $nome) {
    $info = pathinfo($nome);
    $ext = isset($info['extension']) ? '.' . $info['extension'] : '';
    $nomeBase = limparNome($info['filename']);
    if (empty($nomeBase)) $nomeBase = "arquivo";
    $nomeFinal = $nomeBase . $ext;
    $cont = 1;
    while (file_exists($caminho . $nomeFinal)) { $nomeFinal = $nomeBase . " (" . $cont . ")" . $ext; $cont++; }
    return $nomeFinal;
}

function rrmdir($dir) { 
   if (is_dir($dir)) { 
     $objects = scandir($dir); 
     foreach ($objects as $object) { 
       if ($object != "." && $object != "..") { 
         if (is_dir($dir. DIRECTORY_SEPARATOR .$object) && !is_link($dir."/".$object)) rrmdir($dir. DIRECTORY_SEPARATOR .$object);
         else unlink($dir. DIRECTORY_SEPARATOR .$object); 
       } 
     }
     rmdir($dir); 
   } 
}

// --- NOVAS FUNÇÕES DE METADADOS ---

function getRelPath($fullPath) {
    global $pastaRaiz;
    $cleanPath = str_replace(['\\', '//'], '/', $fullPath);
    $cleanRoot = str_replace(['\\', '//'], '/', $pastaRaiz);
    return trim(str_replace($cleanRoot, '', $cleanPath), '/');
}

function setFileMeta($fullPath, $acao, $user) {
    global $db;
    $relPath = getRelPath($fullPath);
    
    if ($acao === 'create') {
        $stmt = $db->prepare("INSERT OR REPLACE INTO files_meta (filepath, created_by, updated_by, created_at, updated_at) VALUES (?, ?, ?, DATETIME('now', 'localtime'), DATETIME('now', 'localtime'))");
        $stmt->execute([$relPath, $user, $user]);
    } elseif ($acao === 'update') {
        $stmt = $db->prepare("UPDATE files_meta SET updated_by = ?, updated_at = DATETIME('now', 'localtime') WHERE filepath = ?");
        $stmt->execute([$user, $relPath]);
        
        if ($stmt->rowCount() == 0) {
            $stmtInsert = $db->prepare("INSERT INTO files_meta (filepath, created_by, updated_by, created_at, updated_at) VALUES (?, ?, ?, DATETIME('now', 'localtime'), DATETIME('now', 'localtime'))");
            $stmtInsert->execute([$relPath, $user, $user]);
        }
    }
}

function renameFileMeta($oldFullPath, $newFullPath, $user) {
    global $db;
    $oldRel = getRelPath($oldFullPath);
    $newRel = getRelPath($newFullPath);
    
    $stmt = $db->prepare("UPDATE files_meta SET filepath = ?, updated_by = ?, updated_at = DATETIME('now', 'localtime') WHERE filepath = ?");
    $stmt->execute([$newRel, $user, $oldRel]);
    
    if (is_dir($newFullPath)) {
        $logs = $db->prepare("SELECT id, filepath FROM files_meta WHERE filepath LIKE ?");
        $logs->execute([$oldRel . '/%']);
        $children = $logs->fetchAll();
        
        foreach ($children as $child) {
            $newChildPath = preg_replace('/^' . preg_quote($oldRel, '/') . '/', $newRel, $child['filepath'], 1);
            $upd = $db->prepare("UPDATE files_meta SET filepath = ? WHERE id = ?");
            $upd->execute([$newChildPath, $child['id']]);
        }
    }
}

function deleteFileMeta($fullPath) {
    global $db;
    $rel = getRelPath($fullPath);
    $stmt = $db->prepare("DELETE FROM files_meta WHERE filepath = ? OR filepath LIKE ?");
    $stmt->execute([$rel, $rel . '/%']);
}

// ==================================================================
// 3. AUTENTICAÇÃO E LÓGICA
// ==================================================================

if (isset($_GET['logout'])) { session_destroy(); header("Location: index.php"); exit; }
$erroLogin = ""; $msgSucesso = ""; $msgTexto = ""; $msgTipo = "";

// LOGIN
if (isset($_POST['login_user'])) {
    $u = $_POST['user']; $p = $_POST['pass'];
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$u]);
    $user = $stmt->fetch();

    if ($user && password_verify($p, $user['password'])) {
        $_SESSION['usuario'] = $user['username'];
        $_SESSION['tipo'] = $user['role'];
        $_SESSION['user_id'] = $user['id'];
        
        $updLogin = $db->prepare("UPDATE users SET last_login = DATETIME('now', 'localtime') WHERE id = ?");
        $updLogin->execute([$user['id']]);

        registrarLog("LOGIN", "Entrou no sistema");
        header("Location: index.php"); exit;
    } else { $erroLogin = "Usuário ou senha incorretos."; }
}

// ALTERAR SENHA
if (isset($_POST['change_pwd_public'])) {
    $u = $_POST['user_change']; $old = $_POST['pass_old']; $new = $_POST['pass_new'];
    $stmt = $db->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$u]);
    $user = $stmt->fetch();

    if ($user && password_verify($old, $user['password'])) {
        $newHash = password_hash($new, PASSWORD_DEFAULT);
        $upd = $db->prepare("UPDATE users SET password = ? WHERE id = ?");
        $upd->execute([$newHash, $user['id']]);
        registrarLog("SENHA", "Alterou a própria senha: $u"); 
        $msgSucesso = "Senha atualizada com sucesso!";
    } else { $erroLogin = "Dados inválidos."; }
}

// TELA DE LOGIN
if (!isset($_SESSION['usuario'])) {
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Login - Intranet</title>
    <link rel="icon" href="logotipo.png">
<style>
    :root { --container: #fff; --text: #1c1e21; --text-secondary: #65676b; --border: #dddfe2; --blue: #1877f2; --input-bg: #f5f6f7; }
    body.dark-mode { --container: #242526; --text: #e4e6eb; --text-secondary: #b0b3b8; --border: #3e4042; --blue: #2d88ff; --input-bg: #3a3b3c; }
    
    body { 
        font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
        background: url('fcecon_bg.png') no-repeat center center fixed;
        background-size: cover;
        color: var(--text); 
        display: flex; justify-content: center; align-items: center; 
        min-height: 100vh; margin: 0; transition: 0.3s; 
    }
    
    .login-card { 
        background: rgba(255, 255, 255, 0.95); 
        padding: 2.5rem 2rem; border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.3); 
        width: 100%; max-width: 400px; text-align: center; border: 1px solid var(--border); position: relative; 
    }
    body.dark-mode .login-card { background: rgba(36, 37, 38, 0.95); }

    .brand-icon { 
        max-width: 120px; height: auto; margin-bottom: 1rem; display: block; margin-left: auto; margin-right: auto;
    }

    h2 { margin-bottom: 1.5rem; color: var(--text); font-weight: 700; }
    label { display: block; text-align: left; margin-bottom: 5px; font-size: 0.9rem; font-weight: 500; color: var(--text); opacity: 0.8; }
    input { width: 100%; padding: 12px 15px; margin-bottom: 15px; border: 1px solid var(--border); border-radius: 8px; box-sizing: border-box; font-size: 15px; transition: 0.2s; background: var(--input-bg); color: var(--text); }
    input:focus { border-color: var(--blue); outline: none; box-shadow: 0 0 0 3px rgba(24, 119, 242, 0.15); }
    button.btn-login { width: 100%; padding: 14px; background: var(--blue); color: white; border: none; border-radius: 8px; font-weight: 700; cursor: pointer; font-size: 16px; margin-top: 10px; transition: 0.2s; }
    button.btn-login:hover { filter: brightness(1.1); transform: translateY(-1px); }
    .error { color: #d32f2f; background: #ffebee; padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; border: 1px solid #ffcdd2; }
    .success { color: #2e7d32; background: #edf7ed; padding: 12px; border-radius: 6px; margin-bottom: 20px; font-size: 14px; border: 1px solid #c8e6c9; }
    .link-action { display: block; margin-top: 20px; color: var(--blue); text-decoration: none; font-size: 14px; cursor: pointer; font-weight: 500; }
    .link-action:hover { text-decoration: underline; }
    
    .footer-credit { 
        margin-top: 25px; padding-top: 15px; border-top: 1px solid var(--border); 
        font-size: 13px; color: var(--text-secondary); opacity: 0.9; line-height: 1.5;
    }
    .footer-credit strong { color: var(--blue); font-weight: 700; display: block; }
    
    #formChange { display: none; }
    .theme-toggle-card { position: absolute; top: 20px; right: 20px; background: none; border: none; font-size: 22px; cursor: pointer; color: var(--text); opacity: 0.6; transition: 0.2s; z-index: 10; }
    .theme-toggle-card:hover { opacity: 1; transform: rotate(15deg); }
</style>
</head>
<body>
    <div class="login-card">
        <button class="theme-toggle-card" id="toggleBtn" onclick="toggleTheme()">🌙</button>
        <img src="logotipo.png" alt="Logo" class="brand-icon">
        <?php if($erroLogin) echo "<div class='error'>$erroLogin</div>"; ?>
        <?php if($msgSucesso) echo "<div class='success'>$msgSucesso</div>"; ?>
        <div id="formLogin">
            <h2>Acesso Intranet</h2>
            <form method="POST">
                <div style="text-align:left;"><label>Usuário</label><input type="text" name="user" required autofocus></div>
                <div style="text-align:left;"><label>Senha</label><input type="password" name="pass" required></div>
                <button type="submit" name="login_user" class="btn-login">Entrar</button>
            </form>
            <a class="link-action" onclick="toggleForms()">Alterar Senha</a>
        </div>
        <div id="formChange">
            <h2>Nova Senha</h2>
            <form method="POST">
                <div style="text-align:left;"><label>Seu Usuário</label><input type="text" name="user_change" required></div>
                <div style="text-align:left;"><label>Senha Atual</label><input type="password" name="pass_old" required></div>
                <div style="text-align:left;"><label>Nova Senha</label><input type="password" name="pass_new" required></div>
                <button type="submit" name="change_pwd_public" class="btn-login">Salvar</button>
            </form>
            <a class="link-action" onclick="toggleForms()">Voltar ao Login</a>
        </div>
        <div class="footer-credit">Desenvolvido por <strong>Tecnologia da Informação</strong></div>
    </div>
<script>
    function toggleForms(){ var l=document.getElementById('formLogin'),c=document.getElementById('formChange'); if(l.style.display==='none'){l.style.display='block';c.style.display='none'}else{l.style.display='none';c.style.display='block'} }
    const body = document.body; const toggleBtn = document.getElementById('toggleBtn');
    if (localStorage.getItem('tema') === 'dark') { body.classList.add('dark-mode'); toggleBtn.innerHTML = '☀️'; }
    function toggleTheme() { body.classList.toggle('dark-mode'); localStorage.setItem('tema', body.classList.contains('dark-mode')?'dark':'light'); toggleBtn.innerHTML = body.classList.contains('dark-mode')?'☀️':'🌙'; }
</script>
</body>
</html>
<?php exit; } 

// ==================================================================
// 5. LÓGICA DO SISTEMA
// ==================================================================

$vendoLixeira = (isset($_GET['mode']) && $_GET['mode'] === 'trash' && $isAdmin);
$raizAtual = $vendoLixeira ? $pastaLixeira : $pastaRaiz;
$subPasta = isset($_GET['dir']) ? str_replace(['../', '..\\', '..'], '', $_GET['dir']) : '';
$subPasta = trim($subPasta, '/\\');
$pastaAtual = !empty($subPasta) ? $raizAtual . $subPasta . '/' : $raizAtual;

if (realpath($pastaAtual) === false || strpos(realpath($pastaAtual), realpath($raizAtual)) !== 0 || !is_dir($pastaAtual)) {
    $pastaAtual = $raizAtual; $subPasta = '';
}

// AÇÕES LIXEIRA
if ($isAdmin && $vendoLixeira) {
    if (isset($_GET['restore'])) { 
        $item = basename($_GET['restore']); 
        $origem = $pastaAtual . $item; 
        if (file_exists($origem)) { 
            $nomeFinal = renomearSeExistir($pastaRaiz, $item); 
            if (rename($origem, $pastaRaiz . $nomeFinal)) { 
                renameFileMeta($origem, $pastaRaiz . $nomeFinal, $_SESSION['usuario']);
                registrarLog("RESTAURAR", $item); 
                $msgTexto = "Restaurado!"; $msgTipo = "success"; 
            } 
        } 
    }
    if (isset($_GET['perm_delete'])) { 
        $item = basename($_GET['perm_delete']); 
        $alvo = $pastaAtual . $item; 
        if (file_exists($alvo)) { 
            deleteFileMeta($alvo);
            if (is_file($alvo)) unlink($alvo); 
            elseif (is_dir($alvo)) rrmdir($alvo); 
            $msgTexto = "Excluído."; $msgTipo = "success"; 
        } 
        registrarLog("LIXEIRA", "Delete: $item"); 
    }
    if (isset($_POST['empty_trash_confirm'])) { 
        $lixo = array_diff(scandir($pastaLixeira), ['.', '..', '.htaccess']); 
        foreach($lixo as $l) { 
            $p = $pastaLixeira . $l; 
            deleteFileMeta($p); 
            if(is_file($p)) unlink($p); elseif(is_dir($p)) rrmdir($p); 
        } 
        registrarLog("LIXEIRA", "Esvaziada"); $msgTexto = "Lixeira limpa!"; $msgTipo = "success"; 
    }
}

// AÇÕES NORMAIS
if (!$vendoLixeira) {
    if ($isAdmin && isset($_GET['deletar_user_id'])) { 
        $uid = (int)$_GET['deletar_user_id'];
        if ($uid !== $_SESSION['user_id']) {
            $stmt = $db->prepare("DELETE FROM users WHERE id = ?");
            if ($stmt->execute([$uid])) {
                registrarLog("USER", "Deletou usuário ID: $uid");
                $msgTexto = "Usuário excluído."; $msgTipo = "success";
            }
        } else {
             $msgTexto = "Não pode se excluir."; $msgTipo = "error";
        }
    }

    if ($isAdmin && isset($_GET['deletar'])) { 
        $item = basename($_GET['deletar']); 
        $origem = $pastaAtual . $item; 
        if (file_exists($origem)) { 
            $dest = renomearSeExistir($pastaLixeira, $item); 
            if (rename($origem, $pastaLixeira . $dest)) { 
                renameFileMeta($origem, $pastaLixeira . $dest, $_SESSION['usuario']);
                registrarLog("LIXEIRA", "Movido: $item"); $msgTexto = "Movido p/ lixeira."; $msgTipo = "success"; 
            } else { $msgTexto = "Erro ao mover."; $msgTipo = "error"; } 
        } 
    }
    
    if ($isAdmin) {
        if (isset($_POST['nova_pasta'])) { 
            $nome = limparNome($_POST['nome_pasta']); 
            if (!empty($nome) && !file_exists($pastaAtual . $nome)) { 
                if(mkdir($pastaAtual . $nome, 0777, true)) { 
                    setFileMeta($pastaAtual . $nome, 'create', $_SESSION['usuario']);
                    registrarLog("PASTA", $nome); $msgTexto = "Criada!"; $msgTipo = "success"; 
                } 
            } else { $msgTexto = "Nome inválido."; $msgTipo = "error"; } 
        }
        
        if (isset($_POST['criar_novo_usuario'])) { 
            $u = trim($_POST['new_username']); $p = $_POST['new_password']; $t = $_POST['new_type']; 
            if (!empty($u) && !empty($p)) {
                $check = $db->prepare("SELECT id FROM users WHERE username = ?");
                $check->execute([$u]);
                if (!$check->fetch()) {
                    $hash = password_hash($p, PASSWORD_DEFAULT);
                    $stmt = $db->prepare("INSERT INTO users (username, password, role) VALUES (?, ?, ?)");
                    $stmt->execute([$u, $hash, $t]);
                    registrarLog("USER", "Criou: $u"); $msgTexto = "Criado!"; $msgTipo = "success";
                } else { $msgTexto = "Já existe."; $msgTipo = "error"; }
            } 
        }

        if (isset($_POST['rename_item'])) { 
            $ant = basename($_POST['old_name']); 
            $newBase = limparNome($_POST['new_name']); 
            $orig = $pastaAtual . $ant; 
            if (file_exists($orig) && !empty($newBase)) { 
                $new = $newBase; 
                if (is_file($orig)) { 
                    $ext = pathinfo($ant, PATHINFO_EXTENSION); 
                    if (pathinfo($newBase, PATHINFO_EXTENSION) !== $ext) $new .= "." . $ext; 
                } 
                $dest = $pastaAtual . $new; 
                if (!file_exists($dest) && rename($orig, $dest)) { 
                    renameFileMeta($orig, $dest, $_SESSION['usuario']);
                    registrarLog("RENAME", "$ant -> $new"); $msgTexto = "Renomeado!"; $msgTipo = "success"; 
                } else { $msgTexto = "Erro/Existe."; $msgTipo = "error"; } 
            } 
        }
        
        // UPLOAD
        if (isset($_POST['upload']) || isset($_FILES['arquivos'])) { 
            if (!empty($_FILES['arquivos']['name'][0])) { 
                $tot = count($_FILES['arquivos']['name']); $suc = 0; $bloq = 0; 
                for ($i = 0; $i < $tot; $i++) { 
                    if ($_FILES['arquivos']['error'][$i] === UPLOAD_ERR_OK) { 
                        $nom = $_FILES['arquivos']['name'][$i]; 
                        $ext = strtolower(pathinfo($nom, PATHINFO_EXTENSION)); 
                        if (in_array($ext, $extensoesPermitidas)) { 
                            $fin = renomearSeExistir($pastaAtual, $nom); 
                            if (move_uploaded_file($_FILES['arquivos']['tmp_name'][$i], $pastaAtual . $fin)) { 
                                setFileMeta($pastaAtual . $fin, 'create', $_SESSION['usuario']);
                                registrarLog("UPLOAD", $fin); $suc++; 
                            } 
                        } else { $bloq++; registrarLog("BLOQUEIO", $nom); } 
                    } 
                } 
                if(isset($_POST['ajax_mode'])) { echo json_encode(['status' => ($suc>0 && $bloq==0)?'success':'warning', 'msg' => "$suc enviados, $bloq bloqueados"]); exit; } else { $msgTexto = "$suc enviados."; $msgTipo = $bloq > 0 ? "warning" : "success"; } 
            } 
        }
    }
}

if (isset($_GET['baixar_arquivo'])) { $arq = basename($_GET['baixar_arquivo']); $full = $pastaAtual . $arq; if (is_file($full) && !in_array($arq, ['.htaccess', 'users.json', 'sistema_logs.txt', 'database.db'])) { registrarLog("DOWNLOAD", $arq); header('Content-Type: application/octet-stream'); header('Content-Disposition: attachment; filename="'.basename($full).'"'); header('Content-Length: '.filesize($full)); readfile($full); exit; } }
if (isset($_POST['baixar_zip']) && !empty($_POST['selecionados']) && class_exists('ZipArchive')) { $zip = new ZipArchive(); $nmZ = "dl_".date('YmdHis').".zip"; $tmpZ = sys_get_temp_dir().'/'.$nmZ; if ($zip->open($tmpZ, ZipArchive::CREATE)===TRUE) { $cnt = 0; foreach ($_POST['selecionados'] as $it) { $p = $pastaAtual . basename($it); if (is_file($p)) { $zip->addFile($p, basename($it)); $cnt++; } } $zip->close(); if ($cnt > 0) { registrarLog("ZIP", "$cnt arquivos"); header('Content-Type: application/zip'); header('Content-disposition: attachment; filename='.$nmZ); header('Content-Length: '.filesize($tmpZ)); readfile($tmpZ); unlink($tmpZ); exit; } else { $msgTexto = "Nada selecionado."; $msgTipo = "error"; } } }

$itensRaw = scandir($pastaAtual); $pastas = []; $arquivos = [];
$ocultos = ['.', '..', '.htaccess', 'users.json', 'sistema_logs.txt', '.lixeira', 'database.db'];
foreach ($itensRaw as $item) {
    if (in_array($item, $ocultos)) continue;
    if (is_dir($pastaAtual . $item)) $pastas[] = $item; else $arquivos[] = $item;
}
natcasesort($pastas); natcasesort($arquivos);
$listaFinal = array_merge($pastas, $arquivos);

$breadcrumbs = []; if (!empty($subPasta)) { $parts = explode('/', trim($subPasta, '/')); $acc = ''; foreach ($parts as $part) { $acc .= $part . '/'; $breadcrumbs[] = ['nome' => $part, 'path' => $acc]; } }

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <title>Intranet</title>
    <link rel="icon" href="logotipo.png">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        /* CSS DO PAINEL PRINCIPAL */
        :root { 
            --bg-body: #f0f2f5; --bg-container: #ffffff; --text-primary: #1c1e21; --text-secondary: #65676b;
            --border-color: #dddfe2; --accent-color: #1877f2; --success-color: #2e7d32; --danger-color: #d32f2f; --warning-color: #ed6c02;
            --hover-bg: #f5f6f7; --shadow: 0 2px 8px rgba(0,0,0,0.08);
            --trash-bg: #fff3cd; --trash-text: #664d03; --trash-border: #ffeeba;
            --btn-view-bg: #e3f2fd; --btn-view-text: #1877f2; --btn-view-border: #bbdefb;
            --stat-card-bg: #f8f9fa;
            --btn-new-user: #2ecc71; --btn-trash: #34495e; --btn-key: #3498db;
        }
        body.dark-mode { 
            --bg-body: #18191a; --bg-container: #242526; --text-primary: #e4e6eb; --text-secondary: #b0b3b8;
            --border-color: #3e4042; --accent-color: #4791ff; --hover-bg: #3a3b3c; --shadow: 0 2px 8px rgba(0,0,0,0.2);
            --trash-bg: #423508; --trash-text: #ffdf7e; --trash-border: #5e4b12;
            --btn-view-bg: #1c2b40; --btn-view-text: #6ba4ff; --btn-view-border: #2d4669;
            --stat-card-bg: #2f3031;
        }

        * { box-sizing: border-box; }
        
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; 
            background: url('fcecon_bg.png') no-repeat center center fixed;
            background-size: cover;
            color: var(--text-primary); 
            margin: 0; padding: 20px; 
            transition: background-color 0.3s; 
        }
        a { text-decoration: none; color: var(--accent-color); cursor: pointer; }
        
        .main-container { 
            max-width: 1200px; margin: 0 auto; 
            background: rgba(255, 255, 255, 0.95);
            border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.3);
            border: 1px solid var(--border-color); overflow: hidden; padding-bottom: 50px;
        }
        body.dark-mode .main-container { background: rgba(36, 37, 38, 0.95); }

        .top-bar { display: flex; justify-content: space-between; align-items: center; padding: 15px 25px; border-bottom: 1px solid var(--border-color); background: transparent; }
        .user-area { display: flex; align-items: center; gap: 15px; }
        .user-badge { display: flex; align-items: center; gap: 8px; background: var(--hover-bg); padding: 6px 12px; border-radius: 20px; font-weight: 500; font-size: 14px; border: 1px solid var(--border-color); }
        .btn-header { padding: 6px 12px; border-radius: 6px; font-weight: 600; font-size: 13px; transition: 0.2s; display: inline-flex; align-items: center; gap: 6px; cursor: pointer; border: 1px solid var(--border-color); background: var(--bg-container); color: var(--text-primary); }
        .btn-header:hover { background: var(--hover-bg); }
        .btn-logout { color: var(--danger-color); border-color: var(--danger-color); }
        .btn-logout:hover { background: #fff5f5; } body.dark-mode .btn-logout:hover { background: #2a1212; }

        .btn-theme-header { background: none; border: none; font-size: 20px; cursor: pointer; padding: 8px; border-radius: 50%; transition: 0.2s; display: flex; align-items: center; justify-content: center; color: var(--text-primary); }
        .btn-theme-header:hover { background-color: var(--hover-bg); }

        .content-body { padding: 25px; }
        .nav-bar { display: flex; gap: 15px; align-items: center; background: var(--hover-bg); padding: 10px 15px; border-radius: 8px; border: 1px solid var(--border-color); flex-wrap: wrap; margin-bottom: 20px; }
        .breadcrumbs { flex-grow: 1; display: flex; align-items: center; flex-wrap: wrap; gap: 5px; font-size: 14px; font-weight: 500; }
        .search-box input { padding: 8px 12px; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-container); color: var(--text-primary); width: 200px; }

        .actions-toolbar { display: flex; gap: 20px; margin-bottom: 25px; align-items: flex-start; }
        .upload-section { flex: 2; } .folder-section { flex: 1; min-width: 250px; max-width: 350px; }
        @media (max-width: 768px) { .actions-toolbar { flex-direction: column; } .folder-section { max-width: none; width: 100%; } }

        .upload-dropzone { border: 2px dashed var(--border-color); border-radius: 12px; padding: 30px 20px; text-align: center; background: var(--hover-bg); cursor: pointer; position: relative; display: flex; flex-direction: column; align-items: center; justify-content: center; min-height: 150px; transition: 0.2s; }
        .upload-dropzone:hover, .upload-dropzone.dragover { border-color: var(--accent-color); background: rgba(24, 119, 242, 0.05); }
        .upload-dropzone input[type="file"] { position: absolute; width: 100%; height: 100%; top:0; left:0; opacity: 0; cursor: pointer; }
        
        .btn-start-upload { width: 100%; padding: 12px; margin-top: 15px; background: var(--success-color); color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; display: none; }
        .progress-bar-container { height: 6px; background: var(--border-color); border-radius: 3px; margin-top: 15px; overflow: hidden; display: none; }
        .progress-bar-fill { height: 100%; background: var(--success-color); width: 0%; }

        .action-card { background: var(--bg-container); padding: 20px; border-radius: 12px; border: 1px solid var(--border-color); }
        .action-title { font-weight: 600; margin-bottom: 15px; display: flex; align-items: center; gap: 8px; }
        .input-group { display: flex; gap: 10px; }
        .form-input { flex-grow: 1; padding: 10px; border: 1px solid var(--border-color); border-radius: 6px; background: var(--bg-container); color: var(--text-primary); }
        .btn-primary { padding: 10px 20px; background: var(--accent-color); color: white; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; white-space: nowrap; }

        .file-list-container { border: 1px solid var(--border-color); border-radius: 12px; overflow: hidden; background: var(--bg-container); }
        
        /* HEADER ATUALIZADO COM NOVAS COLUNAS */
        .file-list-header { display: flex; padding: 12px 15px; background: var(--hover-bg); border-bottom: 1px solid var(--border-color); font-weight: 600; font-size: 14px; color: var(--text-secondary); }
        .col-check { width: 40px; text-align: center; } 
        .col-icon { width: 50px; text-align: center; } 
        .col-name { flex-grow: 1; min-width: 200px; } 
        .col-user { width: 120px; text-align: left; padding-left: 10px; font-size: 13px; color: var(--text-secondary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: flex; align-items: center; }
        .col-size { width: 90px; text-align: right; } 
        .col-date { width: 120px; text-align: right; } 
        .col-actions { width: 150px; text-align: right; }
        
        .file-item { display: flex; align-items: center; padding: 12px 15px; border-bottom: 1px solid var(--border-color); transition: 0.1s; }
        .file-item:hover { background: var(--hover-bg); }
        
        .file-item .col-check, .file-item .col-icon, .file-item .col-name, .file-item .col-size, .file-item .col-date, .file-item .col-actions, .file-item .col-user { display: flex; align-items: center; height: 40px; }
        .file-item .col-check { justify-content: center; } .file-item .col-icon { justify-content: center; font-size: 24px; }
        .file-item .col-name a { font-weight: 500; color: var(--text-primary); display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; max-width: 100%; }
        .file-item .col-size, .file-item .col-date { justify-content: flex-end; font-size: 13px; color: var(--text-secondary); }
        .file-item .col-actions { justify-content: flex-end; gap: 5px; }

        .btn-icon { width: 32px; height: 32px; display: flex; align-items: center; justify-content: center; border-radius: 4px; border: 1px solid var(--border-color); background: var(--bg-container); color: var(--text-secondary); transition: 0.2s; cursor: pointer; }
        .btn-icon:hover { background: var(--hover-bg); color: var(--accent-color); border-color: var(--accent-color); }
        .btn-icon.view { background: var(--btn-view-bg); color: var(--btn-view-text); border-color: var(--btn-view-border); }
        .btn-icon.view:hover { background: var(--accent-color); color: white; border-color: var(--accent-color); }
        .btn-icon.delete:hover { color: var(--danger-color); border-color: var(--danger-color); background: #fff5f5; } body.dark-mode .btn-icon.delete:hover { background: #2a1212; }
        .file-thumb { width: 36px; height: 36px; object-fit: cover; border-radius: 4px; }
        .file-checkbox { transform: scale(1.2); cursor: pointer; accent-color: var(--accent-color); }

        .empty-state { padding: 50px 20px; text-align: center; color: var(--text-secondary); }
        .empty-state i { font-size: 60px; margin-bottom: 20px; opacity: 0.3; color: var(--text-primary); }
        .file-list-footer { padding: 15px; background: var(--hover-bg); border-top: 1px solid var(--border-color); }
        .btn-zip { width: 100%; padding: 12px; background: #6c5ce7; color: white; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; display: none; }

        .container-trash { border: 2px solid var(--warning-color); }
        .trash-alert-bar { background: var(--trash-bg); color: var(--trash-text); border: 1px solid var(--trash-border); padding: 15px 20px; border-radius: 8px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; }
        .trash-actions { display: flex; gap: 10px; }
        .btn-danger { background: var(--danger-color); color: white; border: none; }
        
        .footer-credit { 
            margin-top: 30px; font-size: 12px; color: var(--text-secondary); 
            border-top: 1px solid var(--border-color); padding-top: 15px; 
            text-align: center; opacity: 0.7; 
        }
        .footer-credit strong { color: var(--blue); font-weight: 700; }

        /* MODAIS */
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.5); z-index: 1000; display: none; justify-content: center; align-items: center; backdrop-filter: blur(3px); }
        .modal-overlay.active { display: flex; }
        .modal-box { background: var(--bg-container); width: 90%; max-width: 700px; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.2); overflow: hidden; }
        .modal-header { padding: 15px 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; background: var(--hover-bg); }
        .modal-header h3 { margin: 0; display: flex; align-items: center; gap: 10px; }
        .modal-close { background: none; border: none; font-size: 24px; color: var(--text-secondary); cursor: pointer; }
        .modal-body { padding: 20px; max-height: 70vh; overflow-y: auto; }
        
        .modal-toolbar { display: flex; gap: 10px; margin-bottom: 20px; padding-bottom: 20px; border-bottom: 1px solid var(--border-color); }
        .btn-admin-action { padding: 8px 12px; border-radius: 6px; font-weight: 600; font-size: 13px; cursor: pointer; border: none; display: flex; align-items: center; gap: 6px; color: white; }
        
        /* CORREÇÃO DO BOTÃO VERDE */
        .btn-pattern-green { background-color: var(--btn-new-user); color: white; border: none; border-radius: 8px; padding: 10px 20px; font-weight: 700; font-size: 14px; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: 0.2s; }
        .btn-pattern-green:hover { opacity: 0.9; transform: translateY(-1px); }
        
        .btn-pattern-dark { background-color: var(--btn-trash); color: white; border: none; border-radius: 8px; padding: 10px 20px; font-weight: 700; font-size: 14px; cursor: pointer; display: flex; align-items: center; gap: 8px; text-decoration: none; transition: 0.2s; }
        .btn-pattern-dark:hover { opacity: 0.9; transform: translateY(-1px); }
        .btn-pattern-blue { background-color: var(--btn-key); color: white; border: none; border-radius: 8px; padding: 10px 20px; font-weight: 700; font-size: 14px; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: 0.2s; }
        .btn-pattern-blue:hover { opacity: 0.9; transform: translateY(-1px); }
        .btn-pattern-list { background-color: #8e44ad; color: white; border: none; border-radius: 8px; padding: 10px 20px; font-weight: 700; font-size: 14px; cursor: pointer; display: flex; align-items: center; gap: 8px; transition: 0.2s; }
        .btn-pattern-list:hover { opacity: 0.9; transform: translateY(-1px); }

        .log-table-container { max-height: 300px; overflow-y: auto; border: 1px solid var(--border-color); border-radius: 8px; }
        .log-table { width: 100%; border-collapse: collapse; font-size: 13px; }
        .log-table th, .log-table td { padding: 10px; text-align: left; border-bottom: 1px solid var(--border-color); }
        .log-table th { background: var(--hover-bg); position: sticky; top: 0; font-weight: 600; }

        #toast-container { position: fixed; bottom: 20px; right: 20px; z-index: 2000; }
        .toast { padding: 15px 20px; border-radius: 8px; background: var(--bg-container); box-shadow: 0 5px 15px rgba(0,0,0,0.2); border-left: 5px solid; display: flex; gap: 10px; align-items: center; margin-top: 10px; min-width: 250px;}
        .toast.success { border-color: var(--success-color); }
        .toast.error { border-color: var(--danger-color); }
        .lightbox-content { max-width: 90%; max-height: 85vh; margin: auto; display: block; border-radius: 5px; box-shadow: 0 0 20px rgba(0,0,0,0.5); }
    </style>
</head>
<body class="<?php echo $vendoLixeira ? 'trash-view' : ''; ?>">
    <div id="toast-container"></div>

    <div id="lightboxModal" class="modal-overlay">
        <div style="position: relative; width: 100%; height: 100%; display: flex; justify-content: center; align-items: center;" onclick="if(event.target===this)closeModal('lightboxModal')">
            <img id="lightboxImg" class="lightbox-content" src="">
            <span onclick="closeModal('lightboxModal')" style="position: absolute; top: 20px; right: 30px; color: white; font-size: 40px; cursor: pointer;">&times;</span>
        </div>
    </div>

    <div class="main-container <?php echo $vendoLixeira ? 'container-trash' : ''; ?>">
        
        <div class="top-bar">
            <h2 style="margin:0; display:flex; align-items:center; gap:10px;">
                <?php echo $vendoLixeira ? '🗑️ Lixeira' : '📂 Intranet'; ?>
            </h2>
            <div class="user-area">
                <button class="btn-theme-header" id="themeToggleBtn" onclick="toggleTheme()" title="Alternar Tema">🌙</button>
                
                <div class="user-badge"><i class="fas fa-user-circle"></i> <span><?php echo htmlspecialchars($_SESSION['usuario']); ?></span></div>
                
                <?php if ($isAdmin && !$vendoLixeira): ?>
                <button onclick="openModal('adminModal')" class="btn-header" style="background: #4a5568; color: white; border:none;"><i class="fas fa-tools"></i> Painel Master</button>
                <?php endif; ?>
                <a href="?logout=1" class="btn-header btn-logout"><i class="fas fa-sign-out-alt"></i> Sair</a>
            </div>
        </div>

        <div class="content-body">
            
            <?php if (!$vendoLixeira): ?>
            <div class="page-header">
                <div class="nav-bar">
                    <div class="breadcrumbs">
                        <a href="?dir="><i class="fas fa-home"></i> Início</a>
                        <?php foreach ($breadcrumbs as $crumb): ?>
                            <span class="separator">/</span> <a href="?dir=<?php echo urlencode($crumb['path']); ?>"><?php echo h($crumb['nome']); ?></a>
                        <?php endforeach; ?>
                    </div>
                    <div class="search-box"><i class="fas fa-search"></i> <input type="text" id="searchInput" onkeyup="filtrarArquivos()" placeholder="Buscar..."></div>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($vendoLixeira): ?>
            <div class="trash-alert-bar">
                <div><i class="fas fa-exclamation-triangle"></i> Arquivos na lixeira.</div>
                <div class="trash-actions">
                    <a href="?" class="btn-header">Voltar</a>
                    <?php if (!empty($listaFinal)): ?>
                    <form method="POST" onsubmit="return confirm('Tem certeza?');" style="margin:0;"><button type="submit" name="empty_trash_confirm" class="btn-header btn-danger"><i class="fas fa-trash-restore-alt"></i> Esvaziar Tudo</button></form>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if ($isAdmin && !$vendoLixeira): ?>
            <div class="actions-toolbar">
                <div class="upload-section">
                    <form id="uploadForm" method="POST" enctype="multipart/form-data">
                        <div class="upload-dropzone" id="dropZone">
                            <i class="fas fa-cloud-upload-alt"></i>
                            <h3 style="margin:0">Arraste arquivos aqui</h3>
                            <input type="file" name="arquivos[]" id="fileInput" multiple>
                        </div>
                        <div class="progress-bar-container" id="progressContainer"><div class="progress-bar-fill" id="progressBar"></div></div>
                        <button type="submit" class="btn-start-upload" id="btnUploadStart">Iniciar Upload</button>
                        <input type="hidden" name="upload" value="1"><input type="hidden" name="ajax_mode" value="1">
                    </form>
                </div>
                <div class="folder-section">
                    <div class="action-card">
                        <div class="action-title"><i class="fas fa-folder-plus" style="color: #f39c12;"></i> Nova Pasta</div>
                        <form method="POST"><div class="input-group"><input type="text" name="nome_pasta" class="form-input" placeholder="Nome..." required autocomplete="off"><button type="submit" name="nova_pasta" class="btn-primary">Criar</button></div></form>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <form method="POST" id="fileListForm">
                <div class="file-list-container">
                    <div class="file-list-header">
                        <div class="col-check"><?php if (!$vendoLixeira): ?><input type="checkbox" id="selectAll" class="file-checkbox" onclick="toggleSelectAll()"><?php endif; ?></div>
                        <div class="col-icon">Tipo</div> 
                        <div class="col-name">Nome</div>
                        
                        <div class="col-user">Criado Por</div>
                        <div class="col-user">Editado Por</div>
                        
                        <div class="col-size">Tamanho</div> 
                        <div class="col-date">Data</div> 
                        <div class="col-actions">Ações</div>
                    </div>
                    <ul id="fileList" style="list-style:none; padding:0; margin:0;">
                        <?php if (empty($listaFinal)): ?>
                            <li class="empty-state"><i class="fas <?php echo $vendoLixeira ? 'fa-trash-alt' : 'fa-folder-open'; ?>"></i><h3><?php echo $vendoLixeira ? 'Lixeira Vazia' : 'Esta pasta está vazia'; ?></h3></li>
                        <?php else: ?>
                            <?php foreach ($listaFinal as $item): 
                                $caminhoCompleto = $pastaAtual . $item;
                                $isDir = is_dir($caminhoCompleto);
                                $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
                                $tamanho = $isDir ? '-' : formatarTamanho(filesize($caminhoCompleto));
                                $dataMod = date('d/m/Y H:i', filemtime($caminhoCompleto));
                                $linkRelativo = $raizAtual . ($subPasta ? $subPasta . '/' : '') . $item;
                                
                                // BUSCA METADADOS
                                $relPathLoop = trim($subPasta . '/' . $item, '/\\');
                                if (empty($subPasta)) $relPathLoop = $item;
                                $stmtMeta = $db->prepare("SELECT created_by, updated_by FROM files_meta WHERE filepath = ?");
                                $stmtMeta->execute([$relPathLoop]);
                                $metaData = $stmtMeta->fetch();
                                $criadoPor = $metaData['created_by'] ?? '-';
                                $editadoPor = $metaData['updated_by'] ?? '-';
                                
                                $linkNavegacao = '#';
                                if ($isDir && !$vendoLixeira) $linkNavegacao = "?dir=" . urlencode(trim($subPasta . '/' . $item, '/'));
                                elseif (!$isDir && !$vendoLixeira) $linkNavegacao = "?dir=".urlencode($subPasta)."&baixar_arquivo=".urlencode($item);

                                $icon = $isDir ? '<i class="fas fa-folder" style="color:#f39c12;"></i>' : '<i class="fas fa-file" style="color:#999;"></i>';
                                if (!$isDir && in_array($ext, ['jpg','jpeg','png','gif','webp'])) $icon = '<img src="'.$linkRelativo.'" class="file-thumb">';
                                elseif ($ext=='pdf') $icon='<i class="fas fa-file-pdf" style="color:#e74c3c;"></i>';
                            ?>
                            <li class="file-item filterable-item">
                                <div class="col-check"><?php if (!$isDir && !$vendoLixeira): ?><input type="checkbox" name="selecionados[]" value="<?php echo h($item); ?>" class="file-checkbox item-check" onclick="updateZipButton()"><?php endif; ?></div>
                                <div class="col-icon"><?php echo $icon; ?></div>
                                <div class="col-name"><a href="<?php echo $linkNavegacao; ?>"><?php echo h($item); ?></a></div>
                                
                                <div class="col-user" title="<?php echo $criadoPor; ?>"><small><?php echo h($criadoPor); ?></small></div>
                                <div class="col-user" title="<?php echo $editadoPor; ?>"><small><?php echo h($editadoPor); ?></small></div>
                                
                                <div class="col-size"><?php echo $tamanho; ?></div>
                                <div class="col-date"><?php echo $dataMod; ?></div>
                                <div class="col-actions">
                                    <?php if ($vendoLixeira && $isAdmin): ?>
                                        <a href="?mode=trash&restore=<?php echo urlencode($item); ?>" class="btn-icon" title="Restaurar"><i class="fas fa-trash-restore"></i></a>
                                        <a href="?mode=trash&perm_delete=<?php echo urlencode($item); ?>" class="btn-icon delete" title="Excluir" onclick="return confirm('Permanente?');"><i class="fas fa-times"></i></a>
                                    <?php else: ?>
                                        <?php if (!$isDir): ?>
                                            <?php if(in_array($ext, ['jpg','jpeg','png','gif','webp'])): ?>
                                                <button type="button" class="btn-icon view" onclick="openLightbox('<?php echo $linkRelativo; ?>')"><i class="fas fa-eye"></i></button>
                                            <?php elseif(in_array($ext, ['pdf','mp4','txt'])): ?>
                                                <a href="<?php echo $linkRelativo; ?>" target="_blank" class="btn-icon view"><i class="fas fa-eye"></i></a>
                                            <?php else: ?>
                                                 <a href="<?php echo $linkNavegacao; ?>" class="btn-icon view" title="Baixar/Ver"><i class="fas fa-eye"></i></a>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                        
                                        <?php if ($isAdmin): ?>
                                            <button type="button" class="btn-icon" onclick="openRenameModal('<?php echo h($item); ?>')"><i class="fas fa-edit"></i></button>
                                            <a href="?dir=<?php echo urlencode($subPasta); ?>&deletar=<?php echo urlencode($item); ?>" class="btn-icon delete"><i class="fas fa-trash"></i></a>
                                        <?php elseif (!$isDir): ?>
                                             <a href="<?php echo $linkNavegacao; ?>" class="btn-icon"><i class="fas fa-download"></i></a>
                                        <?php endif; ?>
                                    <?php endif; ?>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </ul>
                    <?php if (!$vendoLixeira && !empty($arquivos)): ?>
                    <div class="file-list-footer"><button type="submit" name="baixar_zip" id="btnZip" class="btn-zip">Baixar Selecionados (ZIP)</button></div>
                    <?php endif; ?>
                </div>
            </form>
            
            <div class="footer-credit">Desenvolvido por <strong>Tecnologia da Informação</strong></div>
        </div>
    </div>

    <?php if ($isAdmin): ?>
    <div id="adminModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header"><h3><i class="fas fa-cogs"></i> Painel Master</h3><button class="modal-close" onclick="closeModal('adminModal')">&times;</button></div>
            <div class="modal-body">
                <div class="modal-toolbar" style="display: flex; gap: 10px; margin-bottom: 20px; align-items: center; flex-wrap: wrap;">
                    
                    <button onclick="openModal('createUserModal'); closeModal('adminModal');" class="btn-pattern-green"><i class="fas fa-user-plus"></i> Novo Usuário</button>
                    
                    <button onclick="openModal('listUsersModal'); closeModal('adminModal');" class="btn-pattern-list"><span>👥</span> Usuários</button>
                    <button onclick="openModal('changePasswordModal'); closeModal('adminModal');" class="btn-pattern-blue"><span>🔑</span> Senha</button>
                    <a href="?mode=trash" class="btn-pattern-dark"><span>🗑️</span> Lixeira</a>
                </div>
                
                <h4 style="margin-top:20px;"><i class="fas fa-list-alt"></i> Logs do Sistema</h4>
                <div class="log-table-container">
                    <table class="log-table">
                        <thead><tr><th>Data</th><th>IP</th><th>Usuário</th><th>Ação</th><th>Alvo</th></tr></thead>
                        <tbody>
                        <?php 
                        try {
                            $stmtLogs = $db->query("SELECT * FROM logs ORDER BY id DESC LIMIT 50");
                            $logs = $stmtLogs->fetchAll();
                            foreach($logs as $log) {
                                $dataFmt = date('d/m H:i', strtotime($log['data_hora']));
                                echo "<tr><td>{$dataFmt}</td><td>".h($log['ip'])."</td><td><strong>".h($log['usuario'])."</strong></td><td>".h($log['acao'])."</td><td>".h($log['detalhe'])."</td></tr>";
                            }
                        } catch (Exception $e) { echo "<tr><td colspan='5'>Erro ao ler logs</td></tr>"; }
                        ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div id="listUsersModal" class="modal-overlay">
        <div class="modal-box">
            <div class="modal-header"><h3><i class="fas fa-users"></i> Lista de Usuários</h3><button class="modal-close" onclick="closeModal('listUsersModal'); openModal('adminModal');">&times;</button></div>
            <div class="modal-body">
                <div class="log-table-container">
                    <table class="log-table">
                        <thead><tr><th>Usuário</th><th>Tipo</th><th>Último Acesso</th><th>Ações</th></tr></thead>
                        <tbody>
                        <?php 
                        try {
                            $stmtUsers = $db->query("SELECT id, username, role, last_login FROM users ORDER BY username ASC");
                            $allUsers = $stmtUsers->fetchAll();
                            foreach($allUsers as $u) {
                                $lastLogin = $u['last_login'] ? date('d/m/Y H:i', strtotime($u['last_login'])) : '<span style="color:#999;font-style:italic">Nunca</span>';
                                $badgeColor = $u['role'] === 'admin' ? '#e74c3c' : '#2ecc71';
                                $badge = "<span style='background:$badgeColor; color:white; padding:2px 8px; border-radius:10px; font-size:11px'>".strtoupper($u['role'])."</span>";
                                $btnDel = ($u['id'] != $_SESSION['user_id']) ? "<a href='?deletar_user_id={$u['id']}' class='btn-icon delete' style='display:inline-flex; width:25px; height:25px;' onclick='return confirm(\"Excluir usuário?\");'><i class='fas fa-trash' style='font-size:12px'></i></a>" : "<span style='color:#ccc'>-</span>";
                                echo "<tr><td><strong>".h($u['username'])."</strong></td><td>$badge</td><td>$lastLogin</td><td style='text-align:center'>$btnDel</td></tr>";
                            }
                        } catch (Exception $e) { echo "<tr><td colspan='4'>Erro ao listar usuários</td></tr>"; }
                        ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <div id="renameModal" class="modal-overlay"><div class="modal-box" style="max-width:400px"><div class="modal-header"><h3>Renomear</h3><button class="modal-close" onclick="closeModal('renameModal')">&times;</button></div><div class="modal-body"><form method="POST"><input type="hidden" name="old_name" id="oldNameInput"><input type="text" name="new_name" id="newNameInput" class="form-input" required><button type="submit" name="rename_item" class="btn-primary" style="width:100%; margin-top:10px;">Salvar</button></form></div></div></div>
    <div id="createUserModal" class="modal-overlay"><div class="modal-box" style="max-width:400px"><div class="modal-header"><h3>Novo Usuário</h3><button class="modal-close" onclick="closeModal('createUserModal')">&times;</button></div><div class="modal-body"><form method="POST"><input type="text" name="new_username" class="form-input" placeholder="Login" required style="width:100%; margin-bottom:10px;"><input type="password" name="new_password" class="form-input" placeholder="Senha" required style="width:100%; margin-bottom:10px;"><select name="new_type" class="form-input" style="width:100%; margin-bottom:15px;"><option value="user">Usuário</option><option value="admin">Admin</option></select><button type="submit" name="criar_novo_usuario" class="btn-primary" style="width:100%">Criar</button></form></div></div></div>
    <?php endif; ?>
    
    <div id="changePasswordModal" class="modal-overlay"><div class="modal-box" style="max-width:400px"><div class="modal-header"><h3>Alterar Senha</h3><button class="modal-close" onclick="closeModal('changePasswordModal')">&times;</button></div><div class="modal-body"><form method="POST"><input type="hidden" name="user_change" value="<?php echo htmlspecialchars($_SESSION['usuario']); ?>"><input type="password" name="pass_old" class="form-input" placeholder="Atual" required style="width:100%; margin-bottom:10px;"><input type="password" name="pass_new" class="form-input" placeholder="Nova" required style="width:100%; margin-bottom:15px;"><button type="submit" name="change_pwd_public" class="btn-primary" style="width:100%">Salvar</button></form></div></div></div>

<script>
    const body = document.body; const themeBtn = document.getElementById('themeToggleBtn');
    if (localStorage.getItem('tema') === 'dark') { body.classList.add('dark-mode'); themeBtn.innerHTML = '☀️'; }
    function toggleTheme() { body.classList.toggle('dark-mode'); localStorage.setItem('tema', body.classList.contains('dark-mode')?'dark':'light'); themeBtn.innerHTML = body.classList.contains('dark-mode')?'☀️':'🌙'; }
    function openModal(id){ document.getElementById(id).classList.add('active'); }
    function closeModal(id){ document.getElementById(id).classList.remove('active'); }
    window.onclick = (e) => { if(e.target.classList.contains('modal-overlay')) e.target.classList.remove('active'); }
    function openLightbox(src){ document.getElementById('lightboxImg').src=src; openModal('lightboxModal'); }
    function openRenameModal(n){ document.getElementById('oldNameInput').value=n; document.getElementById('newNameInput').value=n; openModal('renameModal'); }
    function toggleSelectAll(){ const c=document.getElementById('selectAll').checked; document.querySelectorAll('.item-check').forEach(cb=>cb.checked=c); updateZipButton(); }
    function updateZipButton(){ const c=document.querySelectorAll('.item-check:checked').length; const b=document.getElementById('btnZip'); if(b){b.style.display=c>0?'block':'none'; b.innerText=`Baixar ${c} (ZIP)`;} }
    function filtrarArquivos(){ const term=document.getElementById('searchInput').value.toUpperCase(); document.querySelectorAll('.filterable-item').forEach(i=>{ const name=i.querySelector('.col-name a').innerText.toUpperCase(); i.style.display=name.indexOf(term)>-1?'':'none'; }); }
    
    const dropZone=document.getElementById('dropZone'), fileInput=document.getElementById('fileInput'), btnUpload=document.getElementById('btnUploadStart');
    if(dropZone){
        fileInput.onchange=()=>{ if(fileInput.files.length>0){ dropZone.querySelector('h3').innerText=fileInput.files.length+" selecionados"; btnUpload.style.display='block'; }};
        document.getElementById('uploadForm').onsubmit=(e)=>{ 
            if(fileInput.files.length==0) return; 
            e.preventDefault(); 
            document.getElementById('progressContainer').style.display='block'; 
            const xhr=new XMLHttpRequest(); 
            xhr.upload.onprogress=(e)=>{ if(e.lengthComputable) document.getElementById('progressBar').style.width=Math.round((e.loaded/e.total)*100)+'%'; }; 
            xhr.onload=()=>{ 
                try {
                    const resp = JSON.parse(xhr.responseText);
                    showToast(resp.msg, resp.status);
                    setTimeout(() => window.location.reload(), 1500);
                } catch(e) { window.location.reload(); }
            }; 
            xhr.open('POST','',true); 
            xhr.send(new FormData(e.target)); 
        }
    }
    
    function showToast(m,t='info'){ const d=document.createElement('div'); d.className='toast '+t; d.innerHTML=`<span>${m}</span><i class="fas fa-times toast-close" onclick="this.parentElement.remove()"></i>`; document.getElementById('toast-container').appendChild(d); setTimeout(()=>d.remove(),4000); }
    <?php if($msgTexto != ""): ?> showToast("<?php echo addslashes($msgTexto); ?>", "<?php echo $msgTipo?:'info'; ?>"); <?php endif; ?>
</script>
</body>
</html>
