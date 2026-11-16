<?php
session_start();
 
require_once "./config/database.php";
 
// ======================================
// 1. PROTEÇÃO DO PAINEL
// ======================================
 
if (!isset($_SESSION['id']) || !isset($_SESSION['tipo'])) {
    header("Location: login.php");
    exit;
}
 
$perfil = strtolower(trim($_SESSION['tipo']));
 
if ($perfil !== 'gestor') {
    http_response_code(403);
    exit("Acesso negado. Somente gestores podem acessar este painel.");
}
 
// ID do gestor conectado
$idGestor = (int) $_SESSION['id'];
 
// ======================================
// 2. CONFIGURAÇÕES
// ======================================
 
$mensagem = "";
$tipoMensagem = "";
 
if (!isset($_SESSION['token_gestor'])) {
    $_SESSION['token_gestor'] = bin2hex(random_bytes(32));
}
 
$token = $_SESSION['token_gestor'];
 
function proteger($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}
 
function redirecionar($mensagem, $tipo = 'sucesso') {
    $_SESSION['mensagem_gestor'] = $mensagem;
    $_SESSION['tipo_mensagem_gestor'] = $tipo;
 
    header("Location: gestor.php");
    exit;
}
 
// ======================================
// 3. PROCESSAR FORMULÁRIOS
// ======================================
 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 
    if (
        !isset($_POST['token']) ||
        !hash_equals($token, $_POST['token'])
    ) {
        redirecionar("Solicitação inválida. Atualize a página.", "erro");
    }
 
    $acao = $_POST['acao'] ?? '';
 
    try {
 
        // ----------------------------------
        // CADASTRAR FUNCIONÁRIO
        // ----------------------------------
 
        if ($acao === 'cadastrar_funcionario') {
 
            $nome = trim($_POST['nome'] ?? '');
            $email = trim($_POST['email'] ?? '');
            $senha = $_POST['senha'] ?? '';
            $cpf = trim($_POST['cpf'] ?? '');
            $perfilNovo = $_POST['perfil'] ?? 'funcionario';
 
            if (
                $nome === '' ||
                !filter_var($email, FILTER_VALIDATE_EMAIL) ||
                strlen($email) > 40 ||
                strlen($nome) > 100 ||
                strlen($cpf) > 14 ||
                strlen($senha) < 8
            ) {
                redirecionar(
                    "Preencha os dados corretamente. A senha precisa ter pelo menos 8 caracteres.",
                    "erro"
                );
            }
 
            if (!in_array($perfilNovo, ['gestor', 'funcionario'], true)) {
                redirecionar("Perfil inválido.", "erro");
            }
 
            $senhaHash = password_hash($senha, PASSWORD_DEFAULT);
 
            $sql = "INSERT INTO funcionarios
                    (Nome, Email, Senha, CPF, perfil)
                    VALUES (?, ?, ?, ?, ?)";
 
            $stmt = $pdo->prepare($sql);
 
            $stmt->execute([
                $nome,
                $email,
                $senhaHash,
                $cpf,
                $perfilNovo
            ]);
 
            redirecionar("Funcionário cadastrado com sucesso!");
        }
 
        // ----------------------------------
        // EXCLUIR FUNCIONÁRIO
        // ----------------------------------
 
        elseif ($acao === 'excluir_funcionario') {
 
            $id = (int)($_POST['id'] ?? 0);
 
            if ($id <= 0) {
                redirecionar("Funcionário inválido.", "erro");
            }
 
            if ($id === $idGestor) {
                redirecionar(
                    "Você não pode excluir sua própria conta.",
                    "erro"
                );
            }
 
            $stmt = $pdo->prepare(
                "DELETE FROM funcionarios WHERE id_funcionarios = ?"
            );
 
            $stmt->execute([$id]);
 
            redirecionar("Funcionário excluído com sucesso!");
        }
 
        // ----------------------------------
        // CADASTRAR PRODUTO
        // ----------------------------------
 
        elseif ($acao === 'cadastrar_produto') {
 
            $nome = trim($_POST['nome'] ?? '');
            $preco = str_replace(',', '.', $_POST['preco'] ?? '');
            $imagem = trim($_POST['imagem_url'] ?? '');
 
            if (
                $nome === '' ||
                strlen($nome) > 100 ||
                !is_numeric($preco) ||
                (float)$preco < 0 ||
                strlen($imagem) > 255
            ) {
                redirecionar("Informe um nome e preço válidos.", "erro");
            }
 
            if ($imagem !== '' && !filter_var($imagem, FILTER_VALIDATE_URL)) {
                redirecionar("Informe uma URL de imagem válida.", "erro");
            }
 
            $stmt = $pdo->prepare(
                "INSERT INTO produto
                (nome, preco, imagem_url, id_funcionarios)
                VALUES (?, ?, ?, ?)"
            );
 
            $stmt->execute([
                $nome,
                $preco,
                $imagem !== '' ? $imagem : null,
                $idGestor
            ]);
 
            redirecionar("Produto cadastrado com sucesso!");
        }
 
        // ----------------------------------
        // EXCLUIR PRODUTO
        // ----------------------------------
 
        elseif ($acao === 'excluir_produto') {
 
            $id = (int)($_POST['id'] ?? 0);
 
            $stmt = $pdo->prepare(
                "DELETE FROM produto WHERE id_produto = ?"
            );
 
            $stmt->execute([$id]);
 
            redirecionar("Produto excluído com sucesso!");
        }
 
        // ----------------------------------
        // CADASTRAR INGREDIENTE
        // ----------------------------------
 
        elseif ($acao === 'cadastrar_ingrediente') {
 
            $nome = trim($_POST['nome'] ?? '');
            $validade = $_POST['data_validade'] ?? '';
 
            $data = DateTime::createFromFormat('!Y-m-d', $validade);
 
            if (
                $nome === '' ||
                strlen($nome) > 50 ||
                !$data ||
                $data->format('Y-m-d') !== $validade
            ) {
                redirecionar("Informe o ingrediente e uma validade válida.", "erro");
            }
 
            $stmt = $pdo->prepare(
                "INSERT INTO ingredientes (nome, data_validade)
                VALUES (?, ?)"
            );
 
            $stmt->execute([$nome, $validade]);
 
            redirecionar("Ingrediente cadastrado com sucesso!");
        }
 
        // ----------------------------------
        // EXCLUIR INGREDIENTE
        // ----------------------------------
 
        elseif ($acao === 'excluir_ingrediente') {
 
            $id = (int)($_POST['id'] ?? 0);
 
            $stmt = $pdo->prepare(
                "DELETE FROM ingredientes WHERE id_ingredientes = ?"
            );
 
            $stmt->execute([$id]);
 
            redirecionar("Ingrediente excluído com sucesso!");
        }
 
        else {
            redirecionar("Ação não reconhecida.", "erro");
        }
 
    } catch (PDOException $e) {
 
        // Erros de chave estrangeira
        if ($e->getCode() === '23000') {
            redirecionar(
                "Não foi possível excluir: existem registros vinculados a este item.",
                "erro"
            );
        }
 
        error_log($e->getMessage());
 
        redirecionar(
            "Ocorreu um erro no banco de dados. Verifique os dados e tente novamente.",
            "erro"
        );
    }
}
 
// ======================================
// 4. RECUPERAR MENSAGENS
// ======================================
 
if (isset($_SESSION['mensagem_gestor'])) {
    $mensagem = $_SESSION['mensagem_gestor'];
    $tipoMensagem = $_SESSION['tipo_mensagem_gestor'];
 
    unset(
        $_SESSION['mensagem_gestor'],
        $_SESSION['tipo_mensagem_gestor']
    );
}
 
// ======================================
// 5. CONSULTAR DADOS
// ======================================
 
$funcionarios = $pdo->query(
    "SELECT id_funcionarios, Nome, Email, CPF, perfil
     FROM funcionarios
     ORDER BY Nome"
)->fetchAll(PDO::FETCH_ASSOC);
 
$produtos = $pdo->query(
    "SELECT p.*, f.Nome AS responsavel
     FROM produto p
     INNER JOIN funcionarios f
     ON p.id_funcionarios = f.id_funcionarios
     ORDER BY p.nome"
)->fetchAll(PDO::FETCH_ASSOC);
 
$ingredientes = $pdo->query(
    "SELECT *,
    CASE
        WHEN data_validade < CURDATE() THEN 'Vencido'
        WHEN data_validade <= DATE_ADD(CURDATE(), INTERVAL 7 DAY)
            THEN 'Próximo do vencimento'
        ELSE 'Dentro da validade'
    END AS situacao
    FROM ingredientes
    ORDER BY data_validade ASC"
)->fetchAll(PDO::FETCH_ASSOC);
 
$totalFuncionarios = count($funcionarios);
$totalProdutos = count($produtos);
$totalIngredientes = count($ingredientes);
 
?>
 
<!DOCTYPE html>
<html lang="pt-br">
 
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
 
    <title>Painel do Gestor</title>
 
    <style>
 
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }
 
        body {
            background: #fff7f2;
            color: #49332f;
        }
 
        header {
            background: #7b263b;
            color: white;
            padding: 25px 7%;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }
 
        header h1 {
            font-size: 25px;
        }
 
        header a {
            color: white;
            text-decoration: none;
            background: #a94357;
            padding: 10px 18px;
            border-radius: 8px;
        }
 
        .container {
            width: 90%;
            max-width: 1200px;
            margin: 35px auto;
        }
 
        .boas-vindas {
            margin-bottom: 25px;
        }
 
        .boas-vindas h2 {
            color: #7b263b;
            margin-bottom: 8px;
        }
 
        .cards {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 35px;
        }
 
        .card {
            background: white;
            padding: 25px;
            border-radius: 12px;
            box-shadow: 0 3px 12px #00000010;
            border-left: 5px solid #a94357;
        }
 
        .card h3 {
            font-size: 15px;
            margin-bottom: 12px;
            color: #806c67;
        }
 
        .card strong {
            font-size: 30px;
            color: #7b263b;
        }
 
        .secao {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 3px 12px #00000010;
        }
 
        .secao h2 {
            color: #7b263b;
            margin-bottom: 20px;
        }
 
        .formulario {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
 
        input, select {
            width: 100%;
            padding: 12px;
            border: 1px solid #ddc9c5;
            border-radius: 7px;
            outline: none;
        }
 
        input:focus, select:focus {
            border-color: #a94357;
        }
 