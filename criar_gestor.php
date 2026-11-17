<?php
session_start();
 
require_once "./config/database.php";
 
// Verificar se já existe algum funcionário
$stmt = $pdo->query("SELECT COUNT(*) FROM funcionarios");
$total = (int) $stmt->fetchColumn();
 
if ($total > 0) {
    exit("O cadastro inicial já foi realizado. Por segurança, exclua este arquivo.");
}
 
$mensagem = "";
$tipo = "";
 
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
 
    $nome = trim($_POST['nome'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $cpf = trim($_POST['cpf'] ?? '');
    $senha = $_POST['senha'] ?? '';
    $confirmar = $_POST['confirmar_senha'] ?? '';
 
    if (
        $nome === '' ||
        $email === '' ||
        $cpf === '' ||
        $senha === '' ||
        $confirmar === ''
    ) {
        $mensagem = "Preencha todos os campos.";
        $tipo = "erro";
 
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $mensagem = "Digite um e-mail válido.";
        $tipo = "erro";
 
    } elseif (strlen($senha) < 8) {
        $mensagem = "A senha precisa ter pelo menos 8 caracteres.";
        $tipo = "erro";
 
    } elseif ($senha !== $confirmar) {
        $mensagem = "As senhas não são iguais.";
        $tipo = "erro";
 
    } elseif (
        mb_strlen($nome) > 100 ||
        mb_strlen($email) > 40 ||
        mb_strlen($cpf) > 14
    ) {
        $mensagem = "Um dos campos ultrapassou o limite permitido.";
        $tipo = "erro";
 
    } else {
 
        try {
 
            // Conferir novamente se ninguém cadastrou antes
            $stmt = $pdo->query(
                "SELECT COUNT(*) FROM funcionarios"
            );
 
            if ((int)$stmt->fetchColumn() > 0) {
                exit("O cadastro inicial já foi realizado.");
            }
 
            // Proteger a senha
            $senhaHash = password_hash(
                $senha,
                PASSWORD_DEFAULT
            );
 
            // Criar o primeiro gestor
            $sql = "INSERT INTO funcionarios
                    (Nome, Email, Senha, CPF, perfil)
                    VALUES (?, ?, ?, ?, 'gestor')";
 
            $stmt = $pdo->prepare($sql);
 
            $stmt->execute([
                $nome,
                $email,
                $senhaHash,
                $cpf
            ]);
 
            // Impedir novo cadastro inicial nesta sessão
            session_regenerate_id(true);
 
            $mensagem = "Primeiro gestor cadastrado com sucesso! Agora você pode fazer login.";
            $tipo = "sucesso";
 
        } catch (PDOException $e) {
 
            error_log($e->getMessage());
 
            $mensagem = "Erro ao cadastrar. Verifique os dados e tente novamente.";
            $tipo = "erro";
        }
    }
}
?>
 
<!DOCTYPE html>
<html lang="pt-BR">
 
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
 
    <title>Cadastrar primeiro gestor</title>
 
    <style>
 
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: Arial, sans-serif;
        }
 
        body {
            min-height: 100vh;
            background: #fff7f2;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
 
        .container {
            background: white;
            width: 100%;
            max-width: 450px;
            padding: 35px;
            border-radius: 15px;
            box-shadow: 0 5px 25px #00000012;
        }
 
        h1 {
            text-align: center;
            color: #7b263b;
            margin-bottom: 10px;
        }
 
        .subtitulo {
            text-align: center;
            color: #806c67;
            margin-bottom: 25px;
            font-size: 14px;
        }
 
        .campo {
            margin-bottom: 17px;
        }
 
        label {
            display: block;
            margin-bottom: 7px;
            color: #49332f;
            font-weight: bold;
            font-size: 14px;
        }
 
        input {
            width: 100%;
            padding: 13px;
            border: 1px solid #ddc9c5;
            border-radius: 8px;
            outline: none;
        }
 
        input:focus {
            border-color: #a94357;
        }
 
        button {
            width: 100%;
            padding: 14px;
            border: none;
            border-radius: 8px;
            background: #7b263b;
            color: white;
            font-weight: bold;
            font-size: 15px;
            cursor: pointer;
        }
 
        button:hover {
            background: #a94357;
        }
 
        .mensagem {
            padding: 13px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 14px;
        }
 
        .erro {
            background: #ffe0e0;
            color: #a32222;
        }
 
        .sucesso {
            background: #d9f4e1;
            color: #176534;
        }
 
        .aviso {
            margin-top: 20px;
            padding: 12px;
            background: #fff1ed;
            border-radius: 8px;
            color: #7b263b;
            font-size: 12px;
            text-align: center;
        }
 
    </style>
 
</head>
 
<body>
 
<div class="container">
 
    <h1>🍰 Primeiro Gestor</h1>
 
    <p class="subtitulo">
        Cadastre a conta administrativa da doceria.
    </p>
 
    <?php if ($mensagem): ?>
 
        <div class="mensagem <?= htmlspecialchars($tipo) ?>">
            <?= htmlspecialchars($mensagem) ?>
        </div>
 
    <?php endif; ?>
 
    <?php if ($tipo !== 'sucesso'): ?>
 
    <form method="POST">
 
        <div class="campo">
            <label>Nome completo</label>
            <input
                type="text"
                name="nome"
                maxlength="100"
                required
            >
        </div>
 
        <div class="campo">
            <label>E-mail</label>
            <input
                type="email"
                name="email"
                maxlength="40"
                required
            >
        </div>
 
        <div class="campo">
            <label>CPF</label>
            <input
                type="text"
                name="cpf"
                maxlength="14"
                placeholder="000.000.000-00"
                required
            >
        </div>
 
        <div class="campo">
            <label>Senha</label>
            <input
                type="password"
                name="senha"
                minlength="8"
                required
            >
        </div>
 
        <div class="campo">
            <label>Confirmar senha</label>
            <input
                type="password"
                name="confirmar_senha"
                minlength="8"
                required
            >
        </div>
 
        <button type="submit">
            Cadastrar primeiro gestor
        </button>
 
    </form>
 
    <?php endif; ?>
 
    <div class="aviso">
        Esta página serve exclusivamente para criar a primeira conta administrativa.
    </div>
 
</div>
 
</body>
</html>