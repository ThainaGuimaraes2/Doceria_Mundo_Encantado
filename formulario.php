
<?php

require_once "./config/database.php";

// Receber os dados do formulário
$email = $_POST['email'] ?? '';
$senha = $_POST['senha'] ?? '';

// Verificar se os campos foram preenchidos
if (empty($email) || empty($senha)) {
    die("Preencha todos os campos!");
}

try {

    // Buscar cliente pelo email
    $sql = "SELECT * FROM clientes WHERE email = :email";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':email' => $email
    ]);

    $cliente = $stmt->fetch(PDO::FETCH_ASSOC);

    // Verificar se o cliente existe
    if ($cliente) {

        // Validar senha
        if (password_verify($senha, $cliente['senha'])) {

            echo "Login realizado com sucesso! Bem-vindo, "
                 . htmlspecialchars($cliente['nome']);

        } else {
            echo "Senha incorreta!";
        }

    } else {
        echo "E-mail não cadastrado!";
    }

} catch (PDOException $e) {
    echo "Erro ao realizar login.";
}
