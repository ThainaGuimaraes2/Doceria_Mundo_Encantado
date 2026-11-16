<?php
 
session_start();
require_once "./config/database.php";
 
// Receber os dados do formulário
$email = trim($_POST['email'] ?? '');
$senha = $_POST['senha'] ?? '';
 
// Verificar preenchimento
if (empty($email) || empty($senha)) {
    die("Preencha todos os campos!");
}
 
try {
 
    // 1. Verificar primeiro se é funcionário ou gestor
    $sql = "SELECT * FROM funcionarios WHERE Email = :email";
 
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':email' => $email]);
 
    $funcionario = $stmt->fetch(PDO::FETCH_ASSOC);
 
    if ($funcionario) {
 
        $senhaValida = password_verify(
            $senha,
            $funcionario['Senha']
        );
 
        // Compatibilidade temporária com senhas antigas em texto puro
        if (
            !$senhaValida &&
            password_get_info($funcionario['Senha'])['algo'] === null &&
            hash_equals($funcionario['Senha'], $senha)
        ) {
            $senhaValida = true;
 
            // Atualizar senha antiga para hash seguro
            $novaSenha = password_hash($senha, PASSWORD_DEFAULT);
 
            $update = $pdo->prepare(
                "UPDATE funcionarios
                 SET Senha = :senha
                 WHERE id_funcionarios = :id"
            );
 
            $update->execute([
                ':senha' => $novaSenha,
                ':id' => $funcionario['id_funcionarios']
            ]);
        }
 
        if (!$senhaValida) {
            die("Senha incorreta!");
        }
 
        // Verificar perfil
        $perfil = $funcionario['perfil'];
 
        if (!in_array($perfil, ['funcionario', 'gestor'], true)) {
            die("Perfil de acesso inválido.");
        }
 
        // Criar sessão
        session_regenerate_id(true);
 
        $_SESSION['id'] = $funcionario['id_funcionarios'];
        $_SESSION['nome'] = $funcionario['Nome'];
        $_SESSION['tipo'] = $perfil;
 
        // Redirecionar conforme o perfil
        if ($perfil === 'gestor') {
            header("Location: gestor.php");
            exit;
        }
 
        header("Location: funcionario.php");
        exit;
    }
 
    // 2. Se não for funcionário, verificar cliente
    $sql = "SELECT * FROM clientes WHERE Email = :email";
 
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':email' => $email]);
 
    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);
 
    if (!$cliente) {
        die("E-mail não cadastrado!");
    }
 
    if (!password_verify($senha, $cliente['Senha'])) {
        die("Senha incorreta!");
    }
 
    // Criar sessão do cliente
    session_regenerate_id(true);
 
    $_SESSION['id'] = $cliente['id_clientes'];
    $_SESSION['nome'] = $cliente['Nome'];
    $_SESSION['tipo'] = 'cliente';
 
    header("Location: cardapio.php");
    exit;
 
} catch (PDOException $e) {
 
    error_log($e->getMessage());
    die("Erro ao realizar login. Tente novamente.");
 
}
 