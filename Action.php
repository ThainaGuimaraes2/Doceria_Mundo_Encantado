
<?php

require_once "./config/database.php";

// Receber os dados do formulário
$nome = $_POST['name'];
$email = $_POST['email'];
$senha = $_POST['password'];
$confirmaSenha = $_POST['password_confir'];
$telefone = $_POST['phoneNumber'];
$endereco = $_POST['endereco'];
$cep = $_POST['cep'];
$complemento = $_POST['complemento'];
$numero = $_POST['number'];

// Verificar se as senhas são iguais
if ($senha !== $confirmaSenha) {
    die("As senhas não coincidem!");
}

// Proteger a senha
$senhaHash = password_hash($senha, PASSWORD_DEFAULT);

try {

    // Preparar o comando SQL
    $sql = "INSERT INTO clientes
    (nome, email, senha, telefone, endereco, cep, complemento, numero)
    VALUES
    (:nome, :email, :senha, :telefone, :endereco, :cep, :complemento, :numero)";

    $stmt = $pdo->prepare($sql);

    // Enviar os dados
    $stmt->execute([
        ':nome' => $nome,
        ':email' => $email,
        ':senha' => $senhaHash,
        ':telefone' => $telefone,
        ':endereco' => $endereco,
        ':cep' => $cep,
        ':complemento' => $complemento,
        ':numero' => $numero
    ]);

    echo "Cliente cadastrado com sucesso!";

} catch (PDOException $e) {
    echo "Erro ao cadastrar cliente: " . $e->getMessage();
}

