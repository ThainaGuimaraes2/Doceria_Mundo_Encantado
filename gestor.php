<?php
declare(strict_types=1);
session_start();

if (($_SESSION['tipo'] ?? '') !== 'gestor') {
    http_response_code(403);
    exit('Acesso negado. Entre com uma conta de gestor.');
}

const DB_HOST = '192.168.8.7';
const DB_NAME = 'mtecpi5';
const DB_USER = 'mtecpi5';
const DB_PASS = 'mtecpi5';
const UPLOAD_DIR = __DIR__ . '/uploads/funcionarios/';
const UPLOAD_URL = 'uploads/funcionarios/';

function h(?string $value): string {
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
    $pdo = new PDO($dsn, DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);

    // Usa a tabela funcionarios já integrada ao login do sistema.
    // Adiciona a coluna Foto somente se ela ainda não existir.
    $employeeColumns = $pdo->query('SHOW COLUMNS FROM funcionarios')->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('Foto', $employeeColumns, true)) {
        $pdo->exec('ALTER TABLE funcionarios ADD COLUMN Foto VARCHAR(255) NULL AFTER perfil');
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS gestor_ingredientes (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        nome VARCHAR(120) NOT NULL,
        quantidade DECIMAL(12,3) NOT NULL DEFAULT 0,
        unidade VARCHAR(30) NOT NULL,
        criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_ingrediente_nome_unidade (nome, unidade)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    return $pdo;
}

function csrfToken(): string {
    if (empty($_SESSION['gestor_csrf'])) {
        $_SESSION['gestor_csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['gestor_csrf'];
}

function verifyCsrf(): void {
    $token = $_POST['csrf'] ?? '';
    if (!is_string($token) || !hash_equals(csrfToken(), $token)) {
        throw new RuntimeException('Sua sessão expirou. Atualize a página e tente novamente.');
    }
}

function redirectWith(string $message, string $type = 'success'): never {
    $_SESSION['gestor_flash'] = ['message' => $message, 'type' => $type];
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

function saveEmployeePhoto(?string $oldPhoto = null): ?string {
    if (!isset($_FILES['foto']) || $_FILES['foto']['error'] === UPLOAD_ERR_NO_FILE) {
        return $oldPhoto;
    }

    $file = $_FILES['foto'];
    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Não foi possível enviar a foto.');
    }
    if ($file['size'] > 3 * 1024 * 1024) {
        throw new RuntimeException('A foto deve ter no máximo 3 MB.');
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    if (!isset($allowed[$mime])) {
        throw new RuntimeException('Formato inválido. Use JPG, PNG ou WEBP.');
    }

    if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0755, true) && !is_dir(UPLOAD_DIR)) {
        throw new RuntimeException('Não foi possível criar a pasta de fotos.');
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $filename)) {
        throw new RuntimeException('Falha ao salvar a foto no servidor.');
    }

    if ($oldPhoto) {
        $oldPath = UPLOAD_DIR . basename($oldPhoto);
        if (is_file($oldPath)) @unlink($oldPath);
    }
    return $filename;
}

function removeEmployeePhoto(?string $photo): void {
    if ($photo) {
        $path = UPLOAD_DIR . basename($photo);
        if (is_file($path)) @unlink($path);
    }
}


const PRODUCT_UPLOAD_DIR = __DIR__ . '/uploads/produtos/';
const PRODUCT_UPLOAD_URL = 'uploads/produtos/';

function saveProductImage(?string $oldImage = null): ?string {
    if (!isset($_FILES['imagem_produto']) || $_FILES['imagem_produto']['error'] === UPLOAD_ERR_NO_FILE) {
        return $oldImage;
    }
    $file = $_FILES['imagem_produto'];
    if ($file['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('Não foi possível enviar a imagem do produto.');
    if ($file['size'] > 5 * 1024 * 1024) throw new RuntimeException('A imagem do produto deve ter no máximo 5 MB.');
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);
    $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    if (!isset($allowed[$mime])) throw new RuntimeException('Formato inválido. Use JPG, PNG ou WEBP.');
    if (!is_dir(PRODUCT_UPLOAD_DIR) && !mkdir(PRODUCT_UPLOAD_DIR, 0755, true) && !is_dir(PRODUCT_UPLOAD_DIR)) {
        throw new RuntimeException('Não foi possível criar a pasta de imagens dos produtos.');
    }
    $filename = bin2hex(random_bytes(16)) . '.' . $allowed[$mime];
    if (!move_uploaded_file($file['tmp_name'], PRODUCT_UPLOAD_DIR . $filename)) {
        throw new RuntimeException('Falha ao salvar a imagem do produto.');
    }
    if ($oldImage && str_starts_with($oldImage, PRODUCT_UPLOAD_URL)) {
        $oldPath = PRODUCT_UPLOAD_DIR . basename($oldImage);
        if (is_file($oldPath)) @unlink($oldPath);
    }
    return PRODUCT_UPLOAD_URL . $filename;
}

function removeProductImage(?string $image): void {
    if ($image && str_starts_with($image, PRODUCT_UPLOAD_URL)) {
        $path = PRODUCT_UPLOAD_DIR . basename($image);
        if (is_file($path)) @unlink($path);
    }
}

$pdo = null;
$dbError = null;
try {
    $pdo = db();
} catch (Throwable $e) {
    $dbError = 'Não foi possível conectar ao banco de dados. Confira as configurações DB_HOST, DB_NAME, DB_USER e DB_PASS no início do arquivo.';
}

if ($pdo && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verifyCsrf();
        $action = $_POST['action'] ?? '';

        if ($action === 'save_employee') {
            $id = (int)($_POST['id'] ?? 0);
            $nome = trim((string)($_POST['nome'] ?? ''));
            $email = trim((string)($_POST['email'] ?? ''));
            $cpf = trim((string)($_POST['cpf'] ?? ''));
            $senha = (string)($_POST['senha'] ?? '');
            $perfil = (string)($_POST['perfil'] ?? 'funcionarios');

            if ($nome === '' || $email === '' || $cpf === '') {
                throw new RuntimeException('Preencha nome, e-mail e CPF.');
            }
            if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 40) {
                throw new RuntimeException('Informe um e-mail válido com até 40 caracteres.');
            }
            if (strlen($nome) > 100) throw new RuntimeException('O nome deve ter até 100 caracteres.');
            if (strlen($cpf) > 14) throw new RuntimeException('O CPF deve ter até 14 caracteres.');
            if (!in_array($perfil, ['funcionarios', 'gestor'], true)) {
                throw new RuntimeException('Selecione um perfil válido.');
            }

            $existing = null;
            if ($id > 0) {
                $stmt = $pdo->prepare('SELECT id_funcionarios, Foto, Senha FROM funcionarios WHERE id_funcionarios = ?');
                $stmt->execute([$id]);
                $existing = $stmt->fetch();
                if (!$existing) throw new RuntimeException('Cadastro não encontrado.');
            }
            if ($id === 0 && $senha === '') throw new RuntimeException('Informe uma senha para o novo cadastro.');
            if ($senha !== '' && strlen($senha) < 6) throw new RuntimeException('A senha deve ter pelo menos 6 caracteres.');

            $duplicate = $pdo->prepare('SELECT COUNT(*) FROM funcionarios WHERE (Email = ? OR CPF = ?) AND id_funcionarios <> ?');
            $duplicate->execute([$email, $cpf, $id]);
            if ((int)$duplicate->fetchColumn() > 0) throw new RuntimeException('Já existe um cadastro com esse e-mail ou CPF.');

            $oldPhoto = $existing['Foto'] ?? null;
            $photo = saveEmployeePhoto($oldPhoto);
            if ($id > 0) {
                if ($senha !== '') {
                    $hash = password_hash($senha, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare('UPDATE funcionarios SET Nome=?, Email=?, Senha=?, CPF=?, perfil=?, Foto=? WHERE id_funcionarios=?');
                    $stmt->execute([$nome, $email, $hash, $cpf, $perfil, $photo, $id]);
                } else {
                    $stmt = $pdo->prepare('UPDATE funcionarios SET Nome=?, Email=?, CPF=?, perfil=?, Foto=? WHERE id_funcionarios=?');
                    $stmt->execute([$nome, $email, $cpf, $perfil, $photo, $id]);
                }
                redirectWith('Cadastro atualizado com sucesso.');
            }

            $hash = password_hash($senha, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare('INSERT INTO funcionarios (Nome, Email, Senha, CPF, perfil, Foto) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([$nome, $email, $hash, $cpf, $perfil, $photo]);
            redirectWith($perfil === 'gestor' ? 'Novo gestor cadastrado com sucesso.' : 'Funcionário cadastrado com sucesso.');
        }

        if ($action === 'delete_employee') {
            $id = (int)($_POST['id'] ?? 0);
            if ($id === (int)($_SESSION['id'] ?? 0)) {
                throw new RuntimeException('Você não pode excluir o próprio usuário enquanto estiver conectado.');
            }
            $stmt = $pdo->prepare('SELECT Foto FROM funcionarios WHERE id_funcionarios=?');
            $stmt->execute([$id]);
            $employee = $stmt->fetch();
            if (!$employee) throw new RuntimeException('Cadastro não encontrado.');
            $pdo->prepare('DELETE FROM funcionarios WHERE id_funcionarios=?')->execute([$id]);
            removeEmployeePhoto($employee['Foto'] ?? null);
            redirectWith('Cadastro excluído com sucesso.');
        }

        if ($action === 'save_ingredient') {
            $id = (int)($_POST['id'] ?? 0);
            $nome = trim((string)($_POST['nome_ingrediente'] ?? ''));
            $quantidadeRaw = str_replace(',', '.', trim((string)($_POST['quantidade'] ?? '')));
            $unidade = trim((string)($_POST['unidade'] ?? ''));

            if ($nome === '' || $unidade === '' || $quantidadeRaw === '' || !is_numeric($quantidadeRaw)) {
                throw new RuntimeException('Informe nome, quantidade numérica e unidade do ingrediente.');
            }
            $quantidade = (float)$quantidadeRaw;
            if ($quantidade < 0) throw new RuntimeException('A quantidade não pode ser negativa.');

            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE gestor_ingredientes SET nome=?, quantidade=?, unidade=? WHERE id=?');
                $stmt->execute([$nome, $quantidade, $unidade, $id]);
                redirectWith('Ingrediente atualizado com sucesso.');
            } else {
                $stmt = $pdo->prepare('INSERT INTO gestor_ingredientes (nome,quantidade,unidade) VALUES (?,?,?)');
                $stmt->execute([$nome, $quantidade, $unidade]);
                redirectWith('Ingrediente cadastrado com sucesso.');
            }
        }

        if ($action === 'delete_ingredient') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare('DELETE FROM gestor_ingredientes WHERE id=?');
            $stmt->execute([$id]);
            if ($stmt->rowCount() === 0) throw new RuntimeException('Ingrediente não encontrado.');
            redirectWith('Ingrediente excluído. Atenção: esta tabela independente não atualiza automaticamente receitas de outras tabelas.');
        }


        if ($action === 'save_product') {
            $id = (int)($_POST['id'] ?? 0);
            $nome = trim((string)($_POST['nome_produto'] ?? ''));
            $precoRaw = str_replace(',', '.', trim((string)($_POST['preco_produto'] ?? '')));
            if ($nome === '' || $precoRaw === '' || !is_numeric($precoRaw) || (float)$precoRaw < 0) {
                throw new RuntimeException('Informe o nome e um preço válido para o produto.');
            }
            $oldImage = null;
            if ($id > 0) {
                $stmt = $pdo->prepare('SELECT imagem_url FROM produto WHERE id_produto = ?');
                $stmt->execute([$id]);
                $existing = $stmt->fetch();
                if (!$existing) throw new RuntimeException('Produto não encontrado.');
                $oldImage = $existing['imagem_url'] ?? null;
            }
            $image = saveProductImage($oldImage);
            $columns = $pdo->query('SHOW COLUMNS FROM produto')->fetchAll(PDO::FETCH_COLUMN);
            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE produto SET nome=?, preco=?, imagem_url=? WHERE id_produto=?');
                $stmt->execute([$nome, $precoRaw, $image, $id]);
                redirectWith('Produto atualizado com sucesso.');
            }
            if (in_array('id_funcionarios', $columns, true)) {
                $stmt = $pdo->prepare('INSERT INTO produto (nome, preco, imagem_url, id_funcionarios) VALUES (?, ?, ?, ?)');
                $stmt->execute([$nome, $precoRaw, $image, $_SESSION['id'] ?? null]);
            } else {
                $stmt = $pdo->prepare('INSERT INTO produto (nome, preco, imagem_url) VALUES (?, ?, ?)');
                $stmt->execute([$nome, $precoRaw, $image]);
            }
            redirectWith('Produto cadastrado e enviado ao cardápio com sucesso.');
        }

        if ($action === 'delete_product') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare('SELECT imagem_url FROM produto WHERE id_produto=?');
            $stmt->execute([$id]);
            $product = $stmt->fetch();
            if (!$product) throw new RuntimeException('Produto não encontrado.');
            $check = $pdo->prepare('SELECT COUNT(*) FROM receita WHERE id_produto=?');
            $check->execute([$id]);
            if ((int)$check->fetchColumn() > 0) throw new RuntimeException('Este produto possui receitas cadastradas. Exclua primeiro as receitas vinculadas.');
            $pdo->prepare('DELETE FROM produto WHERE id_produto=?')->execute([$id]);
            removeProductImage($product['imagem_url'] ?? null);
            redirectWith('Produto excluído com sucesso.');
        }

        if ($action === 'save_recipe') {
            $id = (int)($_POST['id'] ?? 0);
            $idProduto = (int)($_POST['id_produto'] ?? 0);
            $nome = trim((string)($_POST['nome_receita'] ?? ''));
            $descricao = trim((string)($_POST['descricao_receita'] ?? ''));
            $modo = trim((string)($_POST['modo_preparo'] ?? ''));
            if ($idProduto <= 0 || $nome === '') throw new RuntimeException('Selecione um produto e informe o nome da receita.');
            $check = $pdo->prepare('SELECT COUNT(*) FROM produto WHERE id_produto=?');
            $check->execute([$idProduto]);
            if (!(int)$check->fetchColumn()) throw new RuntimeException('Produto selecionado não encontrado.');
            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE receita SET id_produto=?, nome_receita=?, descricao=?, modo_preparo=? WHERE id_receita=?');
                $stmt->execute([$idProduto, $nome, $descricao ?: null, $modo ?: null, $id]);
                redirectWith('Receita atualizada com sucesso.');
            }
            $stmt = $pdo->prepare('INSERT INTO receita (id_produto, nome_receita, descricao, modo_preparo) VALUES (?, ?, ?, ?)');
            $stmt->execute([$idProduto, $nome, $descricao ?: null, $modo ?: null]);
            redirectWith('Receita cadastrada com sucesso.');
        }

        if ($action === 'delete_recipe') {
            $id = (int)($_POST['id'] ?? 0);
            $pdo->beginTransaction();
            $pdo->prepare('DELETE FROM unidades WHERE id_receita=?')->execute([$id]);
            $stmt = $pdo->prepare('DELETE FROM receita WHERE id_receita=?');
            $stmt->execute([$id]);
            if ($stmt->rowCount() === 0) { $pdo->rollBack(); throw new RuntimeException('Receita não encontrada.'); }
            $pdo->commit();
            redirectWith('Receita e suas unidades foram excluídas.');
        }

        if ($action === 'save_unit') {
            $id = (int)($_POST['id'] ?? 0);
            $idReceita = (int)($_POST['id_receita'] ?? 0);
            $idIngrediente = (int)($_POST['id_ingredientes'] ?? 0);
            $quantidadeRaw = str_replace(',', '.', trim((string)($_POST['quantidade_unidade'] ?? '')));
            $sigla = trim((string)($_POST['sigla_quantidade'] ?? ''));
            $unidadesPermitidas = ['g', 'kg', 'mg', 'ml', 'L', 'un', 'pacote', 'caixa', 'colher_sopa', 'colher_cha', 'xicara'];
            if ($idReceita <= 0 || $idIngrediente <= 0 || $quantidadeRaw === '' || !is_numeric($quantidadeRaw) || (float)$quantidadeRaw <= 0 || !in_array($sigla, $unidadesPermitidas, true)) {
                throw new RuntimeException('Selecione uma unidade de medida válida e informe receita, ingrediente e quantidade corretamente.');
            }
            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE unidades SET id_receita=?, id_ingredientes=?, quantidade=?, sigla_quantidade=? WHERE id_unidades=?');
                $stmt->execute([$idReceita, $idIngrediente, $quantidadeRaw, $sigla, $id]);
                redirectWith('Unidade da receita atualizada com sucesso.');
            }
            $stmt = $pdo->prepare('INSERT INTO unidades (id_receita, id_ingredientes, quantidade, sigla_quantidade) VALUES (?, ?, ?, ?)');
            $stmt->execute([$idReceita, $idIngrediente, $quantidadeRaw, $sigla]);
            redirectWith('Ingrediente adicionado à receita com sucesso.');
        }

        if ($action === 'delete_unit') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare('DELETE FROM unidades WHERE id_unidades=?');
            $stmt->execute([$id]);
            if ($stmt->rowCount() === 0) throw new RuntimeException('Unidade não encontrada.');
            redirectWith('Ingrediente removido da receita.');
        }

        throw new RuntimeException('Ação inválida.');
    } catch (PDOException $e) {
        $message = $e->getCode() === '23000'
            ? 'Já existe um ingrediente com esse nome e unidade.'
            : 'O banco de dados não conseguiu concluir a operação. Verifique os dados e tente novamente.';
        redirectWith($message, 'error');
    } catch (Throwable $e) {
        redirectWith($e->getMessage(), 'error');
    }
}

$flash = $_SESSION['gestor_flash'] ?? null;
unset($_SESSION['gestor_flash']);

$employees = [];
$ingredients = [];
$products = [];
$recipes = [];
$recipeUnits = [];
$realIngredients = [];
if ($pdo) {
    $employees = $pdo->query('SELECT id_funcionarios, Nome, Email, CPF, perfil, Foto FROM funcionarios ORDER BY Nome')->fetchAll();
    $ingredients = $pdo->query('SELECT * FROM gestor_ingredientes ORDER BY nome')->fetchAll();
    $products = $pdo->query('SELECT id_produto, nome, preco, imagem_url FROM produto ORDER BY nome')->fetchAll();
    $recipes = $pdo->query('SELECT r.id_receita, r.id_produto, r.nome_receita, r.descricao, r.modo_preparo, p.nome AS produto_nome FROM receita r INNER JOIN produto p ON p.id_produto=r.id_produto ORDER BY p.nome, r.nome_receita')->fetchAll();
    $realIngredients = $pdo->query('SELECT id_ingredientes, nome FROM ingredientes ORDER BY nome')->fetchAll();
    $recipeUnits = $pdo->query('SELECT u.id_unidades, u.id_receita, u.id_ingredientes, u.quantidade, u.sigla_quantidade, i.nome AS ingrediente_nome FROM unidades u INNER JOIN ingredientes i ON i.id_ingredientes=u.id_ingredientes ORDER BY u.id_receita, u.id_unidades')->fetchAll();
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Painel do Gestor | Doceria Mundo Encantado</title>
<style>
:root {
    --pink: #f8e1e5;
    --pink-dark: #efd0d7;
    --brown: #704b50;
    --brown-button: #80545d;
    --cream: #fffdf9;
    --border: #e5cfd2;
    --muted: #8b7074;
}
* { box-sizing: border-box; }
body {
    margin: 0;
    background: #f7f3f3;
    color: var(--brown);
    font-family: Georgia, "Times New Roman", serif;
}
header {
    background: var(--pink);
    border-bottom: 1px solid var(--border);
    padding: 22px 6%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 18px;
}
.brand { font-size: 23px; }
.brand small {
    display: block;
    font: 10px Arial, sans-serif;
    letter-spacing: 4px;
    margin-top: 4px;
}
header a { color: var(--brown); text-decoration: none; }
main { max-width: 1180px; margin: 38px auto; padding: 0 20px 50px; }
.eyebrow { font: bold 11px Arial, sans-serif; letter-spacing: 3px; }
h1 { font-size: 36px; font-weight: normal; margin: 10px 0; }
.intro { color: var(--muted); line-height: 1.6; margin-bottom: 28px; }
.notice {
    padding: 13px 16px; border-radius: 5px; margin: 18px 0;
    background: #e8f4e8; color: #315c37;
}
.notice.error { background: #fbe9e8; color: #8b3333; }
.tabs { display: flex; flex-wrap: wrap; gap: 10px; margin: 24px 0; }
.tab {
    border: 1px solid var(--border); background: var(--cream);
    color: var(--brown); padding: 11px 18px; border-radius: 25px;
    cursor: pointer; font: 15px Georgia, serif;
}
.tab.active { background: #f4d7a0; border-color: #f4d7a0; }
.panel { display: none; }
.panel.active { display: block; }
.layout {
    display: grid; grid-template-columns: minmax(260px, .8fr) minmax(0, 1.5fr);
    gap: 22px; align-items: start;
}
.card {
    background: var(--cream); border: 1px solid #eee3e2;
    border-radius: 7px; padding: 23px; box-shadow: 0 5px 18px #704b500b;
}
h2 { font-size: 23px; font-weight: normal; margin: 0 0 18px; }
label { display: block; font-weight: bold; margin: 13px 0 7px; }
input, select {
    width: 100%; padding: 11px 12px; border: 1px solid #d9c8c8;
    border-radius: 4px; background: white; color: var(--brown);
    font: 15px Georgia, serif;
}
input:focus, select:focus { outline: 2px solid #eacbd2; border-color: #a8757e; }
button, .button {
    display: inline-block; border: 0; border-radius: 4px;
    background: var(--brown-button); color: white; padding: 11px 16px;
    font: bold 14px Georgia, serif; cursor: pointer; text-decoration: none;
}
button:hover, .button:hover { background: #633e46; }
.secondary { background: transparent; color: var(--brown-button); border: 1px solid var(--brown-button); }
.danger { background: #a34e55; }
.actions { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 16px; }
.table-wrap { overflow-x: auto; }
table { width: 100%; border-collapse: collapse; min-width: 560px; }
th, td { padding: 12px 10px; text-align: left; border-bottom: 1px solid #eee3e2; vertical-align: middle; }
th { font-size: 13px; color: var(--muted); }
td { font-size: 14px; }
.person { display: flex; align-items: center; gap: 10px; }
.avatar {
    width: 46px; height: 46px; border-radius: 50%; object-fit: cover;
    background: var(--pink); display: inline-flex; align-items: center;
    justify-content: center; overflow: hidden; flex-shrink: 0;
}
.avatar img { width: 100%; height: 100%; object-fit: cover; }
.small-form { display: inline; }
.small-form button { padding: 7px 10px; font-size: 12px; }
.hint { font-size: 12px; color: var(--muted); line-height: 1.5; }
.empty { padding: 22px; text-align: center; color: var(--muted); }
.product-thumb { width:54px; height:54px; object-fit:cover; border-radius:7px; background:var(--pink); }
.recipe-card { background:var(--cream); border:1px solid #eee3e2; border-radius:7px; padding:18px; margin:14px 0; }
.recipe-card h3 { margin:0 0 7px; font-weight:normal; font-size:19px; }
.recipe-meta { color:var(--muted); font-size:13px; line-height:1.5; }
.inline-form { display:inline; }
.unit-list { margin:12px 0; padding-left:20px; }
.unit-list li { padding:5px 0; }

@media (max-width: 820px) {
    .layout { grid-template-columns: 1fr; }
    header { padding: 18px 5%; }
    h1 { font-size: 30px; }
}
</style>
</head>
<body>
<header>
    <a class="brand" href="#">
        Doceria Mundo
        <small>ENCANTADO</small>
    </a>
    <span>Painel administrativo</span>
</header>

<main>
    <span class="eyebrow">ÁREA ADMINISTRATIVA</span>
    <h1>Painel do gestor</h1>
    <p class="intro">Gerencie os funcionários e os ingredientes cadastrados na doceria.</p>

    <?php if ($dbError): ?>
        <div class="notice error"><?= h($dbError) ?></div>
    <?php endif; ?>

    <?php if ($flash): ?>
        <div class="notice <?= h($flash['type'] === 'error' ? 'error' : '') ?>"><?= h($flash['message']) ?></div>
    <?php endif; ?>

    <?php if ($pdo): ?>
    <nav class="tabs" aria-label="Seções do painel">
        <button class="tab active" type="button" data-tab="funcionarios">Funcionários</button>
        <button class="tab" type="button" data-tab="produtos">Produtos</button>
        <button class="tab" type="button" data-tab="receitas">Receitas e unidades</button>
        <button class="tab" type="button" data-tab="ingredientes">Ingredientes (controle antigo)</button>
    </nav>

    <section class="panel active" id="funcionarios">
        <div class="layout">
            <div class="card">
                <h2 id="employee-title">Adicionar funcionário ou gestor</h2>
                <form method="post" enctype="multipart/form-data">
                    <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
                    <input type="hidden" name="action" value="save_employee">
                    <input type="hidden" name="id" id="employee-id" value="0">

                    <label for="nome">Nome *</label>
                    <input id="nome" name="nome" maxlength="100" required>

                    <label for="cpf-func">CPF *</label>
                    <input id="cpf-func" name="cpf" maxlength="14" required placeholder="000.000.000-00">

                    <label for="email-func">E-mail *</label>
                    <input id="email-func" name="email" type="email" maxlength="40" required>

                    <label for="senha-func">Senha <span id="senha-obrigatoria">*</span></label>
                    <input id="senha-func" name="senha" type="password" minlength="6" autocomplete="new-password" placeholder="Mínimo de 6 caracteres">
                    <p class="hint" id="senha-ajuda">Obrigatória para novos cadastros. Ao editar, deixe vazia para manter a senha atual.</p>

                    <label for="perfil-func">Tipo de acesso *</label>
                    <select id="perfil-func" name="perfil" required>
                        <option value="funcionarios">Funcionário</option>
                        <option value="gestor">Gestor</option>
                    </select>
                    <p class="hint">Você pode cadastrar outros gestores, que também terão acesso ao painel administrativo.</p>

                    <label for="foto">Foto do funcionário</label>
                    <input id="foto" name="foto" type="file" accept="image/jpeg,image/png,image/webp">
                    <p class="hint">Formatos aceitos: JPG, PNG e WEBP. Tamanho máximo: 3 MB. Ao editar, deixe vazio para manter a foto atual.</p>

                    <div class="actions">
                        <button type="submit">Salvar funcionário</button>
                        <button class="secondary" type="button" onclick="resetEmployeeForm()">Limpar</button>
                    </div>
                </form>
            </div>

            <div class="card">
                <h2>Usuários cadastrados (<?= count($employees) ?>)</h2>
                <?php if (!$employees): ?>
                    <div class="empty">Nenhum funcionário cadastrado ainda.</div>
                <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Cadastro</th><th>CPF e perfil</th><th>Ações</th></tr></thead>
                        <tbody>
                        <?php foreach ($employees as $employee): ?>
                            <tr>
                                <td>
                                    <div class="person">
                                        <span class="avatar">
                                            <?php if ($employee['Foto']): ?>
                                                <img src="<?= h(UPLOAD_URL . rawurlencode($employee['Foto'])) ?>" alt="Foto de <?= h($employee['Nome']) ?>">
                                            <?php else: ?>♡<?php endif; ?>
                                        </span>
                                        <span><strong><?= h($employee['Nome']) ?></strong><br><span class="hint"><?= h($employee['Email']) ?></span></span>
                                    </div>
                                </td>
                                <td><?= h($employee['CPF']) ?><br><span class="hint"><?= h($employee['perfil'] === 'gestor' ? 'Gestor' : 'Funcionário') ?></span></td>
                                <td>
                                    <div class="actions">
                                        <button type="button" class="secondary"
                                            onclick='editEmployee(<?= json_encode([
                                                'id' => $employee['id_funcionarios'],
                                                'nome' => $employee['Nome'],
                                                'cpf' => $employee['CPF'],
                                                'email' => $employee['Email'],
                                                'perfil' => $employee['perfil']
                                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>)'>Editar</button>
                                        <form class="small-form" method="post" onsubmit="return confirm('Tem certeza de que deseja excluir este funcionário?');">
                                            <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="delete_employee">
                                            <input type="hidden" name="id" value="<?= (int)$employee['id_funcionarios'] ?>">
                                            <button class="danger" type="submit">Excluir</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="panel" id="produtos">
        <div class="layout">
            <div class="card">
                <h2 id="product-title">Cadastrar produto</h2>
                <form method="post" enctype="multipart/form-data" id="product-form">
                    <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
                    <input type="hidden" name="action" value="save_product">
                    <input type="hidden" name="id" id="product-id" value="0">
                    <label for="nome-produto">Nome do produto *</label>
                    <input id="nome-produto" name="nome_produto" maxlength="120" required placeholder="Ex.: Bolo de chocolate">
                    <label for="preco-produto">Preço (R$) *</label>
                    <input id="preco-produto" name="preco_produto" type="number" min="0" step="0.01" required placeholder="Ex.: 35,00">
                    <label for="imagem-produto">Imagem do produto</label>
                    <input id="imagem-produto" name="imagem_produto" type="file" accept="image/jpeg,image/png,image/webp">
                    <p class="hint">JPG, PNG ou WEBP, até 5 MB. Ao editar, deixe vazio para manter a imagem atual.</p>
                    <div class="actions">
                        <button type="submit">Salvar produto</button>
                        <button class="secondary" type="button" onclick="resetProductForm()">Limpar</button>
                    </div>
                </form>
            </div>
            <div class="card">
                <h2>Produtos cadastrados (<?= count($products) ?>)</h2>
                <?php if (!$products): ?><div class="empty">Nenhum produto cadastrado.</div><?php else: ?>
                <div class="table-wrap"><table><thead><tr><th>Produto</th><th>Preço</th><th>Ações</th></tr></thead><tbody>
                <?php foreach ($products as $product): ?>
                    <tr>
                        <td><div class="person">
                            <?php if (!empty($product['imagem_url'])): ?><img class="product-thumb" src="<?= h($product['imagem_url']) ?>" alt="<?= h($product['nome']) ?>"><?php else: ?><span class="avatar">♡</span><?php endif; ?>
                            <strong><?= h($product['nome']) ?></strong>
                        </div></td>
                        <td>R$ <?= number_format((float)$product['preco'], 2, ',', '.') ?></td>
                        <td><div class="actions">
                            <button type="button" class="secondary" onclick='editProduct(<?= json_encode(['id'=>$product['id_produto'],'nome'=>$product['nome'],'preco'=>$product['preco']], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>)'>Editar</button>
                            <form class="small-form" method="post" onsubmit="return confirm('Excluir este produto? As receitas vinculadas precisam ser excluídas primeiro.');">
                                <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>"><input type="hidden" name="action" value="delete_product"><input type="hidden" name="id" value="<?= (int)$product['id_produto'] ?>"><button class="danger" type="submit">Excluir</button>
                            </form>
                        </div></td>
                    </tr>
                <?php endforeach; ?>
                </tbody></table></div><?php endif; ?>
                <p class="hint">Os produtos desta lista são os mesmos utilizados automaticamente pelo Cardapio_iFood.php.</p>
            </div>
        </div>
    </section>

    <section class="panel" id="receitas">
        <div class="layout">
            <div class="card">
                <h2 id="recipe-title">Cadastrar receita</h2>
                <form method="post" id="recipe-form">
                    <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>"><input type="hidden" name="action" value="save_recipe"><input type="hidden" name="id" id="recipe-id" value="0">
                    <label for="recipe-product">Produto relacionado *</label>
                    <select name="id_produto" id="recipe-product" required><option value="">Selecione o produto</option>
                        <?php foreach ($products as $product): ?><option value="<?= (int)$product['id_produto'] ?>"><?= h($product['nome']) ?></option><?php endforeach; ?>
                    </select>
                    <label for="recipe-name">Nome da receita *</label><input id="recipe-name" name="nome_receita" maxlength="100" required>
                    <label for="recipe-description">Descrição</label><input id="recipe-description" name="descricao_receita" maxlength="200">
                    <label for="recipe-method">Modo de preparo</label><textarea id="recipe-method" name="modo_preparo" maxlength="250" rows="3" style="width:100%;padding:11px 12px;border:1px solid #d9c8c8;border-radius:4px;font:15px Georgia,serif"></textarea>
                    <div class="actions"><button type="submit">Salvar receita</button><button class="secondary" type="button" onclick="resetRecipeForm()">Limpar</button></div>
                </form>
            </div>
            <div class="card">
                <h2>Receitas e ingredientes</h2>
                <?php if (!$recipes): ?><div class="empty">Nenhuma receita cadastrada.</div><?php else: ?>
                    <?php foreach ($recipes as $recipe): ?>
                        <article class="recipe-card">
                            <h3><?= h($recipe['nome_receita']) ?></h3>
                            <div class="recipe-meta"><strong>Produto:</strong> <?= h($recipe['produto_nome']) ?><br><?= h($recipe['descricao'] ?? '') ?><br><?= h($recipe['modo_preparo'] ?? '') ?></div>
                            <div class="actions">
                                <button type="button" class="secondary" onclick='editRecipe(<?= json_encode(['id'=>$recipe['id_receita'],'id_produto'=>$recipe['id_produto'],'nome'=>$recipe['nome_receita'],'descricao'=>$recipe['descricao'],'modo'=>$recipe['modo_preparo']], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>)'>Editar receita</button>
                                <form class="small-form" method="post" onsubmit="return confirm('Excluir esta receita e todos os ingredientes/unidades vinculados?');"><input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>"><input type="hidden" name="action" value="delete_recipe"><input type="hidden" name="id" value="<?= (int)$recipe['id_receita'] ?>"><button class="danger" type="submit">Excluir receita</button></form>
                            </div>
                            <strong>Ingredientes da receita</strong>
                            <ul class="unit-list">
                            <?php $foundUnit = false; foreach ($recipeUnits as $unit): if ((int)$unit['id_receita'] !== (int)$recipe['id_receita']) continue; $foundUnit = true; ?>
                                <li><?= h($unit['ingrediente_nome']) ?> — <?= h(rtrim(rtrim(number_format((float)$unit['quantidade'], 2, ',', '.'), '0'), ',')) ?> <?= h($unit['sigla_quantidade']) ?>
                                    <div class="actions">
                                        <button type="button" class="secondary" onclick='editUnit(<?= json_encode(['id'=>$unit['id_unidades'],'id_receita'=>$unit['id_receita'],'id_ingredientes'=>$unit['id_ingredientes'],'quantidade'=>$unit['quantidade'],'sigla'=>$unit['sigla_quantidade']], JSON_HEX_TAG|JSON_HEX_APOS|JSON_HEX_AMP|JSON_HEX_QUOT) ?>)'>Editar</button>
                                        <form class="small-form" method="post" onsubmit="return confirm('Remover este ingrediente da receita?');"><input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>"><input type="hidden" name="action" value="delete_unit"><input type="hidden" name="id" value="<?= (int)$unit['id_unidades'] ?>"><button class="danger" type="submit">Excluir</button></form>
                                    </div>
                                </li>
                            <?php endforeach; if (!$foundUnit): ?><li class="hint">Nenhum ingrediente adicionado.</li><?php endif; ?>
                            </ul>
                            <button type="button" onclick="newUnitForRecipe(<?= (int)$recipe['id_receita'] ?>)">Adicionar ingrediente a esta receita</button>
                        </article>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <div class="card" style="margin-top:22px;">
            <h2 id="unit-title">Adicionar ingrediente à receita</h2>
            <form method="post" id="unit-form">
                <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>"><input type="hidden" name="action" value="save_unit"><input type="hidden" name="id" id="unit-id" value="0">
                <div class="layout">
                    <div><label for="unit-recipe">Receita *</label><select id="unit-recipe" name="id_receita" required><option value="">Selecione a receita</option><?php foreach ($recipes as $recipe): ?><option value="<?= (int)$recipe['id_receita'] ?>"><?= h($recipe['produto_nome'] . ' — ' . $recipe['nome_receita']) ?></option><?php endforeach; ?></select></div>
                    <div><label for="unit-ingredient">Ingrediente *</label><select id="unit-ingredient" name="id_ingredientes" required><option value="">Selecione o ingrediente</option><?php foreach ($realIngredients as $ingredient): ?><option value="<?= (int)$ingredient['id_ingredientes'] ?>"><?= h($ingredient['nome']) ?></option><?php endforeach; ?></select></div>
                </div>
                <div class="layout">
                    <div><label for="unit-quantity">Quantidade *</label><input id="unit-quantity" name="quantidade_unidade" type="number" min="0.01" step="0.01" required></div>
                    <div>
                        <label for="unit-sigla">Unidade de medida *</label>
                        <select id="unit-sigla" name="sigla_quantidade" required>
                            <option value="">Selecione a unidade</option>
                            <option value="g">Grama (g)</option>
                            <option value="kg">Quilograma (kg)</option>
                            <option value="mg">Miligrama (mg)</option>
                            <option value="ml">Mililitro (ml)</option>
                            <option value="L">Litro (L)</option>
                            <option value="un">Unidade (un)</option>
                            <option value="pacote">Pacote</option>
                            <option value="caixa">Caixa</option>
                            <option value="colher_sopa">Colher de sopa</option>
                            <option value="colher_cha">Colher de chá</option>
                            <option value="xicara">Xícara</option>
                        </select>
                    </div>
                </div>
                <div class="actions"><button type="submit">Salvar ingrediente da receita</button><button type="button" class="secondary" onclick="resetUnitForm()">Limpar</button></div>
                <?php if (!$realIngredients): ?><p class="hint">Cadastre primeiro os ingredientes na tabela ingredientes para adicioná-los às receitas.</p><?php endif; ?>
            </form>
        </div>
    </section>

    <section class="panel" id="ingredientes">
        <div class="layout">
            <div class="card">
                <h2 id="ingredient-title">Adicionar ingrediente</h2>
                <form method="post">
                    <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
                    <input type="hidden" name="action" value="save_ingredient">
                    <input type="hidden" name="id" id="ingredient-id" value="0">

                    <label for="nome-ingrediente">Nome do ingrediente *</label>
                    <input id="nome-ingrediente" name="nome_ingrediente" maxlength="120" required placeholder="Ex.: Farinha de trigo">

                    <label for="quantidade">Quantidade em estoque *</label>
                    <input id="quantidade" name="quantidade" type="number" min="0" step="0.001" required placeholder="Ex.: 5">

                    <label for="unidade">Unidade de medida *</label>
                    <select id="unidade" name="unidade" required>
                        <option value="">Selecione</option>
                        <option value="kg">Quilograma (kg)</option>
                        <option value="g">Grama (g)</option>
                        <option value="L">Litro (L)</option>
                        <option value="ml">Mililitro (ml)</option>
                        <option value="unidade">Unidade</option>
                        <option value="pacote">Pacote</option>
                        <option value="caixa">Caixa</option>
                    </select>

                    <div class="actions">
                        <button type="submit">Salvar ingrediente</button>
                        <button class="secondary" type="button" onclick="resetIngredientForm()">Limpar</button>
                    </div>
                </form>
            </div>

            <div class="card">
                <h2>Ingredientes cadastrados (<?= count($ingredients) ?>)</h2>
                <?php if (!$ingredients): ?>
                    <div class="empty">Nenhum ingrediente cadastrado ainda.</div>
                <?php else: ?>
                <div class="table-wrap">
                    <table>
                        <thead><tr><th>Ingrediente</th><th>Estoque</th><th>Ações</th></tr></thead>
                        <tbody>
                        <?php foreach ($ingredients as $ingredient): ?>
                            <tr>
                                <td><strong><?= h($ingredient['nome']) ?></strong></td>
                                <td><?= h(rtrim(rtrim(number_format((float)$ingredient['quantidade'], 3, ',', '.'), '0'), ',')) ?> <?= h($ingredient['unidade']) ?></td>
                                <td>
                                    <div class="actions">
                                        <button type="button" class="secondary"
                                            onclick='editIngredient(<?= json_encode([
                                                'id' => $ingredient['id'],
                                                'nome' => $ingredient['nome'],
                                                'quantidade' => $ingredient['quantidade'],
                                                'unidade' => $ingredient['unidade']
                                            ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>)'>Editar</button>
                                        <form class="small-form" method="post" onsubmit="return confirm('Excluir este ingrediente? Verifique antes se ele é usado em alguma receita.');">
                                            <input type="hidden" name="csrf" value="<?= h(csrfToken()) ?>">
                                            <input type="hidden" name="action" value="delete_ingredient">
                                            <input type="hidden" name="id" value="<?= (int)$ingredient['id'] ?>">
                                            <button class="danger" type="submit">Excluir</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
        
    </section>
    <?php endif; ?>
</main>

<script>
document.querySelectorAll('.tab').forEach(function(tab) {
    tab.addEventListener('click', function() {
        document.querySelectorAll('.tab').forEach(function(item) { item.classList.remove('active'); });
        document.querySelectorAll('.panel').forEach(function(panel) { panel.classList.remove('active'); });
        tab.classList.add('active');
        document.getElementById(tab.dataset.tab).classList.add('active');
    });
});

function editEmployee(data) {
    document.querySelector('[data-tab="funcionarios"]').click();
    document.getElementById('employee-title').textContent = 'Editar funcionário';
    document.getElementById('employee-id').value = data.id;
    document.getElementById('nome').value = data.nome || '';
    document.getElementById('cpf-func').value = data.cpf || '';
    document.getElementById('email-func').value = data.email || '';
    document.getElementById('perfil-func').value = data.perfil || 'funcionarios';
    document.getElementById('senha-func').value = '';
    document.getElementById('senha-func').required = false;
    document.getElementById('senha-obrigatoria').textContent = '';
    document.getElementById('senha-ajuda').textContent = 'Deixe vazia para manter a senha atual.';
    document.getElementById('foto').value = '';
    window.scrollTo({top: 0, behavior: 'smooth'});
}
function resetEmployeeForm() {
    document.querySelector('#funcionarios form').reset();
    document.getElementById('employee-id').value = '0';
    document.getElementById('employee-title').textContent = 'Adicionar funcionário ou gestor';
    document.getElementById('senha-func').required = true;
    document.getElementById('senha-obrigatoria').textContent = '*';
    document.getElementById('senha-ajuda').textContent = 'Obrigatória para novos cadastros. Ao editar, deixe vazia para manter a senha atual.';
}
function editIngredient(data) {
    document.querySelector('[data-tab="ingredientes"]').click();
    document.getElementById('ingredient-title').textContent = 'Editar ingrediente';
    document.getElementById('ingredient-id').value = data.id;
    document.getElementById('nome-ingrediente').value = data.nome || '';
    document.getElementById('quantidade').value = data.quantidade;
    document.getElementById('unidade').value = data.unidade || '';
    window.scrollTo({top: 0, behavior: 'smooth'});
}
function resetIngredientForm() {
    document.querySelector('#ingredientes form').reset();
    document.getElementById('ingredient-id').value = '0';
    document.getElementById('ingredient-title').textContent = 'Adicionar ingrediente';
}

function resetProductForm() {
    document.getElementById('product-form').reset();
    document.getElementById('product-id').value = '0';
    document.getElementById('product-title').textContent = 'Cadastrar produto';
}
function editProduct(data) {
    document.querySelector('[data-tab="produtos"]').click();
    document.getElementById('product-title').textContent = 'Editar produto';
    document.getElementById('product-id').value = data.id;
    document.getElementById('nome-produto').value = data.nome || '';
    document.getElementById('preco-produto').value = data.preco || '';
    document.getElementById('imagem-produto').value = '';
    window.scrollTo({top:0,behavior:'smooth'});
}
function resetRecipeForm() {
    document.getElementById('recipe-form').reset();
    document.getElementById('recipe-id').value = '0';
    document.getElementById('recipe-title').textContent = 'Cadastrar receita';
}
function editRecipe(data) {
    document.querySelector('[data-tab="receitas"]').click();
    document.getElementById('recipe-title').textContent = 'Editar receita';
    document.getElementById('recipe-id').value = data.id;
    document.getElementById('recipe-product').value = data.id_produto;
    document.getElementById('recipe-name').value = data.nome || '';
    document.getElementById('recipe-description').value = data.descricao || '';
    document.getElementById('recipe-method').value = data.modo || '';
    window.scrollTo({top:0,behavior:'smooth'});
}
function resetUnitForm() {
    document.getElementById('unit-form').reset();
    document.getElementById('unit-id').value = '0';
    document.getElementById('unit-title').textContent = 'Adicionar ingrediente à receita';
}
function newUnitForRecipe(id) {
    document.querySelector('[data-tab="receitas"]').click();
    resetUnitForm();
    document.getElementById('unit-recipe').value = String(id);
    document.getElementById('unit-ingredient').focus();
    document.getElementById('unit-form').scrollIntoView({behavior:'smooth',block:'center'});
}
function editUnit(data) {
    document.querySelector('[data-tab="receitas"]').click();
    document.getElementById('unit-title').textContent = 'Editar ingrediente da receita';
    document.getElementById('unit-id').value = data.id;
    document.getElementById('unit-recipe').value = data.id_receita;
    document.getElementById('unit-ingredient').value = data.id_ingredientes;
    document.getElementById('unit-quantity').value = data.quantidade;
    document.getElementById('unit-sigla').value = data.sigla || '';
    document.getElementById('unit-form').scrollIntoView({behavior:'smooth',block:'center'});
}
</script>
</body>
</html>
