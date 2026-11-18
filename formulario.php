<?php

session_start();
require_once "./config/database.php";

// Receber os dados do formulário
$email = trim($_POST['email'] ?? '');
$senha = $_POST['senha'] ?? '';

// Verificar preenchimento
if ($email === '' || $senha === '') {
    die("Preencha todos os campos!");
}

try {

    // 1. Verificar primeiro se é funcionário ou gestor
    $sql = "SELECT * FROM funcionarios WHERE Email = :email LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':email' => $email]);

    $funcionario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($funcionario) {

        // Verificar a senha
        $senhaBanco = $funcionario['Senha'];
        $senhaValida = password_verify($senha, $senhaBanco);

        // Compatibilidade temporária com senhas antigas em texto puro
        if (
            !$senhaValida &&
            password_get_info($senhaBanco)['algo'] === null &&
            hash_equals((string) $senhaBanco, (string) $senha)
        ) {
            $senhaValida = true;

            // Atualizar a senha antiga para um hash seguro
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

        // Padronizar o perfil para evitar diferenças entre singular/plural
        $perfil = strtolower(trim($funcionario['perfil'] ?? ''));

        if ($perfil === 'funcionarios') {
            $perfil = 'funcionario';
        }

        // Aceitar somente funcionário ou gestor
        if (!in_array($perfil, ['funcionario', 'gestor'], true)) {
            die("Perfil de acesso inválido. Verifique o perfil cadastrado no banco de dados.");
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

    // 2. Se não for funcionário, verificar se é cliente
    $sql = "SELECT * FROM clientes WHERE Email = :email LIMIT 1";
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

    // Registrar o erro no log, sem exibir detalhes do banco ao usuário
    error_log($e->getMessage());
    die("Erro ao realizar login. Tente novamente.");

}
