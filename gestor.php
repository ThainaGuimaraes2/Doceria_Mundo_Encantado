<?php

// ======================================
// 1. CONFIGURAÇÕES INICIAIS
// ======================================

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Iniciar sessão
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Conectar ao banco de dados
require_once "./config/database.php";

// Verificar conexão
if (!isset($pdo)) {
    die("Erro: conexão com o banco não encontrada.");
}

// ======================================
// 2. VERIFICAR LOGIN
// ======================================

if (!isset($_SESSION['id'])) {

    die("
        <h2>Acesso não autorizado!</h2>
        <p>Faça login para acessar o painel.</p>
        <a href='login.php'>Voltar ao login</a>
    ");

}

// ======================================
// 3. VERIFICAR PERFIL DO FUNCIONÁRIO
// ======================================

$sql = "SELECT id_funcionarios, Nome, Email, perfil
        FROM funcionarios
        WHERE id_funcionarios = ?";

$stmt = $pdo->prepare($sql);

$stmt->execute([
    $_SESSION['id']
]);

$gestor = $stmt->fetch(PDO::FETCH_ASSOC);

// Conferir se o funcionário existe
if (!$gestor) {
    die("Funcionário não encontrado.");
}

// Conferir permissão de gestor
if (strtolower(trim($gestor['perfil'])) !== 'gestor') {

    die("
        <h2>Acesso negado!</h2>
        <p>Somente gestores podem acessar este painel.</p>
        <a href='login.php'>Voltar ao login</a>
    ");

}

// ======================================
// 4. FUNÇÃO PARA PROTEGER OS DADOS
// ======================================

function proteger($valor) {

    return htmlspecialchars(
        (string)$valor,
        ENT_QUOTES,
        'UTF-8'
    );

}

// ======================================
// 5. FUNÇÃO PARA EXIBIR MENSAGENS
// ======================================

function mensagem($texto, $tipo = 'sucesso') {

    $_SESSION['mensagem_gestor'] = $texto;

    $_SESSION['tipo_gestor'] = $tipo;

    header("Location: gestor.php");

    exit;

}

// ======================================
// 6. TOKEN DE SEGURANÇA
// ======================================

if (empty($_SESSION['token_gestor'])) {

    $_SESSION['token_gestor'] = bin2hex(
        random_bytes(32)
    );

}

$token = $_SESSION['token_gestor'];


// ======================================
// 6.1 IDENTIFICAR AS CHAVES PRIMÁRIAS
// ======================================

// Detecta automaticamente o nome da chave primária de cada tabela.
// Assim, o painel funciona mesmo que a coluna não se chame "id".
function obterChavePrimaria(PDO $pdo, string $tabela): string {

    // Os nomes das tabelas são fixos e definidos neste arquivo.
    $tabelasPermitidas = ['produto', 'ingredientes'];

    if (!in_array($tabela, $tabelasPermitidas, true)) {
        die("Tabela não permitida.");
    }

    $consulta = $pdo->query("SHOW KEYS FROM `{$tabela}` WHERE Key_name = 'PRIMARY'");
    $chave = $consulta->fetch(PDO::FETCH_ASSOC);

    if (!$chave || empty($chave['Column_name'])) {
        die("A tabela {$tabela} não possui uma chave primária definida.");
    }

    return $chave['Column_name'];
}

$chaveProduto = obterChavePrimaria($pdo, 'produto');
$chaveIngrediente = obterChavePrimaria($pdo, 'ingredientes');

// Identificar a coluna de senha da tabela funcionarios.
$colunasFuncionarios = $pdo->query("SHOW COLUMNS FROM funcionarios")->fetchAll(PDO::FETCH_COLUMN);
$colunaSenha = null;
foreach (['Senha', 'senha', 'senha_funcionario', 'Senha_funcionario'] as $possivel) {
    if (in_array($possivel, $colunasFuncionarios, true)) {
        $colunaSenha = $possivel;
        break;
    }
}
if ($colunaSenha === null) {
    die("Não encontrei a coluna de senha na tabela funcionarios. Verifique se ela se chama Senha ou senha.");
}

// ======================================
// 7. PROCESSAR AÇÕES DO GESTOR
// ======================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (
        !isset($_POST['token']) ||
        !hash_equals($token, $_POST['token'])
    ) {
        mensagem("Token de segurança inválido.", "erro");
    }

    $acao = $_POST['acao'] ?? '';

    // CADASTRAR FUNCIONARIO
    if ($acao === 'cadastrar_funcionario') {

        $nomeFuncionario = trim($_POST['nome_funcionario'] ?? '');
        $emailFuncionario = trim($_POST['email_funcionario'] ?? '');
        $senhaFuncionario = $_POST['senha_funcionario'] ?? '';
        $cpfFuncionario = trim($_POST['cpf_funcionario'] ?? '');

        if ($nomeFuncionario === '' || !filter_var($emailFuncionario, FILTER_VALIDATE_EMAIL) || strlen($senhaFuncionario) < 6 || $cpfFuncionario === '') {
            mensagem("Informe nome, e-mail válido, CPF e senha com pelo menos 6 caracteres.", "erro");
        }

        // Impedir cadastro de e-mail já utilizado.
        $verificaEmail = $pdo->prepare("SELECT COUNT(*) FROM funcionarios WHERE Email = ?");
        $verificaEmail->execute([$emailFuncionario]);
        if ((int)$verificaEmail->fetchColumn() > 0) {
            mensagem("Já existe um funcionário cadastrado com esse e-mail.", "erro");
        }

        $senhaHash = password_hash($senhaFuncionario, PASSWORD_DEFAULT);
        $sqlCadastro = "INSERT INTO funcionarios (Nome, Email, `{$colunaSenha}`, CPF, perfil) VALUES (?, ?, ?, ?, ?)";
        $stmt = $pdo->prepare($sqlCadastro);
        $stmt->execute([$nomeFuncionario, $emailFuncionario, $senhaHash, $cpfFuncionario, 'funcionarios']);

        mensagem("Funcionário cadastrado com sucesso!");
    }

    // CADASTRAR PRODUTO
    if ($acao === 'cadastrar_produto') {

        $nome = trim($_POST['nome'] ?? '');
        $preco = $_POST['preco'] ?? 0;
        $imagemUrl = null;

        if ($nome === '' || !is_numeric($preco) || $preco < 0) {
            mensagem("Preencha os dados corretamente.", "erro");
        }

        // A imagem é opcional. Quando enviada, valida o tipo e o tamanho.
        if (isset($_FILES['imagem']) && $_FILES['imagem']['error'] !== UPLOAD_ERR_NO_FILE) {
            if ($_FILES['imagem']['error'] !== UPLOAD_ERR_OK) {
                mensagem("Não foi possível enviar a imagem.", "erro");
            }

            if ($_FILES['imagem']['size'] > 5 * 1024 * 1024) {
                mensagem("A imagem deve ter no máximo 5 MB.", "erro");
            }

            $informacoesImagem = @getimagesize($_FILES['imagem']['tmp_name']);
            $tiposPermitidos = [
                IMAGETYPE_JPEG => 'jpg',
                IMAGETYPE_PNG  => 'png',
                IMAGETYPE_WEBP => 'webp'
            ];

            if ($informacoesImagem === false || !isset($tiposPermitidos[$informacoesImagem[2]])) {
                mensagem("Envie uma imagem válida nos formatos JPG, PNG ou WEBP.", "erro");
            }

            $pastaImagens = __DIR__ . '/uploads/produtos';

            if (!is_dir($pastaImagens) && !mkdir($pastaImagens, 0755, true)) {
                mensagem("Não foi possível criar a pasta de imagens.", "erro");
            }

            $nomeArquivo = bin2hex(random_bytes(16)) . '.' . $tiposPermitidos[$informacoesImagem[2]];
            $destinoImagem = $pastaImagens . '/' . $nomeArquivo;

            if (!move_uploaded_file($_FILES['imagem']['tmp_name'], $destinoImagem)) {
                mensagem("Não foi possível salvar a imagem enviada.", "erro");
            }

            $imagemUrl = 'uploads/produtos/' . $nomeArquivo;
        }

        $stmt = $pdo->prepare(
            "INSERT INTO produto (nome, preco, imagem_url, id_funcionarios)
             VALUES (?, ?, ?, ?)"
        );

        $stmt->execute([$nome, $preco, $imagemUrl, $_SESSION['id']]);

        mensagem("Produto cadastrado com sucesso!");
    }

    // EXCLUIR PRODUTO
    if ($acao === 'excluir_produto') {

        $id = (int)($_POST['id'] ?? 0);

        // Recupera o caminho da imagem antes de excluir o produto.
        $buscaImagem = $pdo->prepare(
            "SELECT imagem_url FROM produto WHERE `{$chaveProduto}` = ?"
        );
        $buscaImagem->execute([$id]);
        $imagemAntiga = $buscaImagem->fetchColumn();

        $stmt = $pdo->prepare(
            "DELETE FROM produto WHERE `{$chaveProduto}` = ?"
        );
        $stmt->execute([$id]);

        // Remove somente arquivos armazenados na pasta de imagens dos produtos.
        if ($imagemAntiga && strpos($imagemAntiga, 'uploads/produtos/') === 0) {
            $arquivoImagem = __DIR__ . '/' . $imagemAntiga;
            if (is_file($arquivoImagem)) {
                unlink($arquivoImagem);
            }
        }

        mensagem("Produto excluído!");
    }

    // CADASTRAR INGREDIENTE
    if ($acao === 'cadastrar_ingrediente') {

        $nome = trim($_POST['nome'] ?? '');
        $dataValidade = $_POST['data_validade'] ?? '';

        if ($nome === '' || $dataValidade === '') {
            mensagem("Informe o nome e a data de validade do ingrediente.", "erro");
        }

        $stmt = $pdo->prepare(
            "INSERT INTO ingredientes (nome, data_validade)
             VALUES (?, ?)"
        );

        $stmt->execute([$nome, $dataValidade]);

        mensagem("Ingrediente cadastrado!");
    }

    // EXCLUIR INGREDIENTE
    if ($acao === 'excluir_ingrediente') {

        $id = (int)($_POST['id'] ?? 0);

        $stmt = $pdo->prepare(
            "DELETE FROM ingredientes WHERE `{$chaveIngrediente}` = ?"
        );

        $stmt->execute([$id]);

        mensagem("Ingrediente excluído!");
    }


    // CADASTRAR UNIDADE / INGREDIENTE DE RECEITA
    if ($acao === 'cadastrar_unidade') {
        $idReceita = (int)($_POST['id_receita'] ?? 0);
        $idIngrediente = (int)($_POST['id_ingredientes'] ?? 0);
        $quantidade = $_POST['quantidade'] ?? '';
        $sigla = trim($_POST['sigla_quantidade'] ?? '');

        if ($idReceita <= 0 || $idIngrediente <= 0 || !is_numeric($quantidade) || $quantidade <= 0 || $sigla === '') {
            mensagem("Preencha corretamente todos os dados da unidade.", "erro");
        }

        $stmt = $pdo->prepare(
            "INSERT INTO unidades (id_receita, id_ingredientes, quantidade, sigla_quantidade)
             VALUES (?, ?, ?, ?)"
        );
        $stmt->execute([$idReceita, $idIngrediente, $quantidade, $sigla]);

        mensagem("Unidade cadastrada com sucesso!");
    }

    // EXCLUIR UNIDADE
    if ($acao === 'excluir_unidade') {
        $id = (int)($_POST['id'] ?? 0);
        $stmt = $pdo->prepare("DELETE FROM unidades WHERE id_unidades = ?");
        $stmt->execute([$id]);
        mensagem("Unidade excluída!");
    }

}
// ======================================
// 8. BUSCAR PRODUTOS
// ======================================

$consultaProdutos = $pdo->query(
    "SELECT *, `{$chaveProduto}` AS id FROM produto ORDER BY `{$chaveProduto}` DESC"
);

$produtos = $consultaProdutos->fetchAll(PDO::FETCH_ASSOC);


// ======================================
// 9. BUSCAR INGREDIENTES
// ======================================

$consultaIngredientes = $pdo->query(
    "SELECT *, `{$chaveIngrediente}` AS id FROM ingredientes ORDER BY `{$chaveIngrediente}` DESC"
);

$ingredientes = $consultaIngredientes->fetchAll(PDO::FETCH_ASSOC);

 // ======================================
 // 9.1 BUSCAR UNIDADES
 // ======================================

$consultaUnidades = $pdo->query(
    "SELECT u.id_unidades, u.id_receita, u.quantidade, u.sigla_quantidade,
            i.nome AS ingrediente
     FROM unidades u
     INNER JOIN ingredientes i ON i.id_ingredientes = u.id_ingredientes
     ORDER BY u.id_unidades DESC"
);
$unidades = $consultaUnidades->fetchAll(PDO::FETCH_ASSOC);



// ======================================
// 10. BUSCAR FUNCIONARIOS
// ======================================

$consultaFuncionarios = $pdo->query(
    "SELECT id_funcionarios, Nome, Email, perfil FROM funcionarios ORDER BY id_funcionarios DESC"
);
$funcionarios = $consultaFuncionarios->fetchAll(PDO::FETCH_ASSOC);

// ======================================
// 11. CONTADORES
// ======================================

$totalProdutos = count($produtos);
$totalIngredientes = count($ingredientes);
$totalFuncionarios = count($funcionarios);
$totalUnidades = count($unidades);

?>

<!DOCTYPE html>
<html lang="pt-br">

<head>

<meta charset="UTF-8">

<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>Painel do Gestor</title>

<style>
:root {
    --rosa-fundo: #f7e1e7;
    --rosa-claro: #fbeef1;
    --rosa-destaque: #f4c9d5;
    --pessego: #f6d1a5;
    --marrom: #76545a;
    --marrom-escuro: #62434a;
    --roxo: #9850dc;
    --branco: #fffdfc;
    --borda: #ead3d8;
}

* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    font-family: Georgia, 'Times New Roman', serif;
    background: var(--rosa-fundo);
    color: var(--marrom);
    line-height: 1.5;
    min-height: 100vh;
    border: 4px solid var(--roxo);
    outline: 1px solid #d9b7ee;
    outline-offset: -10px;
}

header {
    background: var(--rosa-fundo);
    color: var(--marrom-escuro);
    padding: 30px 20px;
    text-align: center;
    border-bottom: 1px solid var(--borda);
}

header h1 {
    font-size: clamp(26px, 4vw, 36px);
    font-weight: normal;
    letter-spacing: .3px;
    margin-bottom: 5px;
}

header p {
    font-size: 14px;
    letter-spacing: .5px;
}

.container {
    width: min(90%, 1100px);
    margin: 38px auto;
}

.cards {
    display: flex;
    gap: 20px;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

.card {
    background: var(--branco);
    padding: 25px;
    border: 1px solid var(--borda);
    border-radius: 3px;
    flex: 1;
    min-width: 200px;
    box-shadow: 0 3px 12px rgba(118, 84, 90, .07);
}

.card h3 {
    color: #98777d;
    font-size: 15px;
    font-weight: normal;
    margin-bottom: 10px;
}

.card p {
    font-size: 30px;
    font-weight: bold;
    color: var(--marrom-escuro);
}

.secao {
    background: var(--branco);
    padding: 28px;
    border: 1px solid var(--borda);
    border-radius: 3px;
    margin-bottom: 26px;
    box-shadow: 0 3px 12px rgba(118, 84, 90, .06);
}

.secao h2 {
    margin-bottom: 20px;
    color: var(--marrom-escuro);
    font-size: 23px;
    font-weight: normal;
}

input, select {
    display: block;
    padding: 13px 14px;
    width: 100%;
    margin-bottom: 14px;
    border: 1px solid #dfcdd1;
    border-radius: 2px;
    background: #fff;
    color: var(--marrom-escuro);
    font: 14px Georgia, 'Times New Roman', serif;
    transition: border-color .2s, box-shadow .2s;
}

input::placeholder {
    color: #ad999d;
}

input:focus, select:focus {
    outline: none;
    border-color: #c58b9a;
    box-shadow: 0 0 0 3px rgba(244, 201, 213, .35);
}

button {
    padding: 12px 21px;
    border: 1px solid var(--marrom);
    border-radius: 2px;
    cursor: pointer;
    background: var(--marrom);
    color: #fffaf8;
    font: bold 14px Georgia, 'Times New Roman', serif;
    transition: background .2s, transform .2s;
}

button:hover {
    background: var(--marrom-escuro);
    transform: translateY(-1px);
}

.excluir {
    background: #b96d78;
    border-color: #b96d78;
}

.excluir:hover {
    background: #9f5662;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-top: 20px;
    background: #fff;
}

th, td {
    padding: 13px 14px;
    border-bottom: 1px solid #f0e2e5;
    text-align: left;
    vertical-align: middle;
}

th {
    background: var(--rosa-claro);
    color: var(--marrom-escuro);
    font-weight: bold;
}

td {
    color: #765e63;
}

td form {
    margin: 0;
}

.mensagem {
    padding: 15px 18px;
    background: #f1e8dc;
    color: #66523f;
    border: 1px solid #e4d2bd;
    border-radius: 3px;
    margin-bottom: 20px;
}

.erro {
    background: #f9e5e7;
    color: #8d414b;
    border-color: #e8c2c8;
}

@media(max-width: 700px) {
    body {
        border-width: 3px;
        outline-offset: -8px;
    }

    .container {
        width: 92%;
        margin: 25px auto;
    }

    .secao {
        padding: 20px 16px;
        overflow-x: auto;
    }

    .cards {
        gap: 12px;
    }

    .card {
        min-width: 145px;
        padding: 18px;
    }

    table {
        min-width: 480px;
        font-size: 13px;
    }

    th, td {
        padding: 9px;
    }
}
</style>

</head>

<body>

<header>

    <h1>Painel do Gestor</h1>

    <p>Gerenciamento do sistema</p>

</header>

<div class="container">

    <?php if (!empty($_SESSION['mensagem_gestor'])): ?>

        <div class="mensagem <?= $_SESSION['tipo_gestor'] === 'erro' ? 'erro' : '' ?>">

            <?= proteger($_SESSION['mensagem_gestor']) ?>

        </div>

        <?php
        unset($_SESSION['mensagem_gestor']);
        unset($_SESSION['tipo_gestor']);
        ?>

    <?php endif; ?>


    <div class="cards">

        <div class="card">

            <h3>Total de produtos</h3>

            <p><?= $totalProdutos ?></p>

        </div>


        <div class="card">

            <h3>Total de ingredientes</h3>

            <p><?= $totalIngredientes ?></p>

        </div>

        <div class="card">

            <h3>Total de funcionários</h3>

            <p><?= $totalFuncionarios ?></p>

        </div>

    </div>

    <div class="secao">

        <h2>Cadastrar produto</h2>

        <form method="POST" enctype="multipart/form-data">

            <input type="hidden" name="token"
                   value="<?= proteger($token) ?>">

            <input type="hidden" name="acao"
                   value="cadastrar_produto">

            <input type="text"
                   name="nome"
                   placeholder="Nome do produto"
                   required>

            <input type="number"
                   name="preco"
                   placeholder="Preço"
                   min="0"
                   step="0.01"
                   required>

            <label for="imagem_produto">Imagem do produto (JPG, PNG ou WEBP, até 5 MB):</label>
            <input type="file"
                   id="imagem_produto"
                   name="imagem"
                   accept="image/jpeg,image/png,image/webp">

            <button type="submit">
                Cadastrar produto
            </button>

        </form>


        <h2 style="margin-top:30px;">
            Produtos cadastrados
        </h2>

        <table>

            <tr>
                <th>ID</th>
                <th>Imagem</th>
                <th>Produto</th>
                <th>Preço</th>
                <th>Ação</th>
            </tr>

            <?php foreach ($produtos as $produto): ?>

                <tr>

                    <td><?= proteger($produto['id']) ?></td>

                    <td>
                        <?php if (!empty($produto['imagem_url'])): ?>
                            <img src="<?= proteger($produto['imagem_url']) ?>"
                                 alt="Imagem de <?= proteger($produto['nome']) ?>"
                                 style="width:70px;height:70px;object-fit:cover;border-radius:4px;">
                        <?php else: ?>
                            Sem imagem
                        <?php endif; ?>
                    </td>

                    <td><?= proteger($produto['nome']) ?></td>

                    <td>
                        R$ <?= number_format(
                            (float)$produto['preco'],
                            2,
                            ',',
                            '.'
                        ) ?>
                    </td>

                    <td>

                        <form method="POST"
                              onsubmit="return confirm('Deseja excluir este produto?')">

                            <input type="hidden" name="token"
                                   value="<?= proteger($token) ?>">

                            <input type="hidden" name="acao"
                                   value="excluir_produto">

                            <input type="hidden" name="id"
                                   value="<?= proteger($produto['id']) ?>">

                            <button class="excluir" type="submit">
                                Excluir
                            </button>

                        </form>

                    </td>

                </tr>

            <?php endforeach; ?>

        </table>

    </div>

    <div class="secao">

        <h2>Cadastrar ingrediente</h2>

        <form method="POST">

            <input type="hidden" name="token"
                   value="<?= proteger($token) ?>">

            <input type="hidden" name="acao"
                   value="cadastrar_ingrediente">

            <input type="text"
                   name="nome"
                   placeholder="Nome do ingrediente"
                   required>

            <label>Data de validade:</label>
            <input type="date" name="data_validade" required>

            <button type="submit">
                Cadastrar ingrediente
            </button>

        </form>


        <h2 style="margin-top:30px;">
            Ingredientes cadastrados
        </h2>

        <table>

            <tr>
                <th>ID</th>
                <th>Ingrediente</th>
                <th>Data de validade</th>
                <th>Ação</th>
            </tr>

            <?php foreach ($ingredientes as $ingrediente): ?>

                <tr>

                    <td><?= proteger($ingrediente['id']) ?></td>

                    <td><?= proteger($ingrediente['nome']) ?></td>
                    <td><?= date('d/m/Y', strtotime($ingrediente['data_validade'])) ?></td>

                    <td>

                        <form method="POST"
                              onsubmit="return confirm('Deseja excluir este ingrediente?')">

                            <input type="hidden" name="token"
                                   value="<?= proteger($token) ?>">

                            <input type="hidden" name="acao"
                                   value="excluir_ingrediente">

                            <input type="hidden" name="id"
                                   value="<?= proteger($ingrediente['id']) ?>">

                            <button class="excluir" type="submit">
                                Excluir
                            </button>

                        </form>

                    </td>

                </tr>

            <?php endforeach; ?>

        </table>

    </div>

    <div class="secao">
        <h2>Cadastrar unidade de receita</h2>
        <form method="POST">
            <input type="hidden" name="token" value="<?= proteger($token) ?>">
            <input type="hidden" name="acao" value="cadastrar_unidade">

            <input type="number" name="id_receita" placeholder="ID da receita" min="1" required>

            <select name="id_ingredientes" required>
                <option value="">Selecione o ingrediente</option>
                <?php foreach ($ingredientes as $ingrediente): ?>
                    <option value="<?= proteger($ingrediente['id_ingredientes']) ?>">
                        <?= proteger($ingrediente['nome']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <input type="number" name="quantidade" placeholder="Quantidade" min="0.01" step="0.01" required>
            <input type="text" name="sigla_quantidade" placeholder="Unidade (g, kg, ml...)" maxlength="4" required>

            <button type="submit">Cadastrar unidade</button>
        </form>

        <h2 style="margin-top:30px;">Unidades cadastradas</h2>
        <table>
            <tr>
                <th>ID</th>
                <th>ID da receita</th>
                <th>Ingrediente</th>
                <th>Quantidade</th>
                <th>Ação</th>
            </tr>
            <?php foreach ($unidades as $unidade): ?>
                <tr>
                    <td><?= proteger($unidade['id_unidades']) ?></td>
                    <td><?= proteger($unidade['id_receita']) ?></td>
                    <td><?= proteger($unidade['ingrediente']) ?></td>
                    <td><?= proteger($unidade['quantidade']) ?> <?= proteger($unidade['sigla_quantidade']) ?></td>
                    <td>
                        <form method="POST" onsubmit="return confirm('Deseja excluir esta unidade?')">
                            <input type="hidden" name="token" value="<?= proteger($token) ?>">
                            <input type="hidden" name="acao" value="excluir_unidade">
                            <input type="hidden" name="id" value="<?= proteger($unidade['id_unidades']) ?>">
                            <button class="excluir" type="submit">Excluir</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
        </table>
    </div>

    <div class="secao">

        <h2>Cadastrar funcionário</h2>

        <form method="POST">

            <input type="hidden" name="token" value="<?= proteger($token) ?>">
            <input type="hidden" name="acao" value="cadastrar_funcionario">

            <input type="text" name="nome_funcionario" placeholder="Nome completo" required>
            <input type="email" name="email_funcionario" placeholder="E-mail" required>
            <input type="text" name="cpf_funcionario" placeholder="CPF" maxlength="14" required>
            <input type="password" name="senha_funcionario" placeholder="Senha (mínimo 6 caracteres)" minlength="6" required>

            <button type="submit">Cadastrar funcionário</button>

        </form>

        <h2 style="margin-top:30px;">Funcionários cadastrados</h2>

        <table>
            <tr>
                <th>ID</th>
                <th>Nome</th>
                <th>E-mail</th>
                <th>Perfil</th>
            </tr>

            <?php foreach ($funcionarios as $funcionario): ?>
                <tr>
                    <td><?= proteger($funcionario['id_funcionarios']) ?></td>
                    <td><?= proteger($funcionario['Nome']) ?></td>
                    <td><?= proteger($funcionario['Email']) ?></td>
                    <td><?= proteger($funcionario['perfil']) ?></td>
                </tr>
            <?php endforeach; ?>
        </table>

    </div>

</div>

</body>
</html>