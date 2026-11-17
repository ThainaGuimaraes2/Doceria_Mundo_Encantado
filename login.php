<?php
include './classes/cabecalho.php';
include './classes/NavBar.php';
?>

<style>
    body {
        background-color: #f7e1e7;
        font-family: Georgia, serif;
        color: #76545a;
    }

    .login-container {
        max-width: 400px;
        margin: 70px auto;
        padding: 35px;
        background-color: #fff;
        border-radius: 18px;
        box-shadow: 0 4px 15px rgba(118, 84, 90, 0.12);
    }

    .login-container h1 {
        text-align: center;
        color: #76545a;
        margin-bottom: 25px;
    }

    .login-container label {
        display: block;
        margin-bottom: 8px;
        font-weight: bold;
    }

    .login-container input[type="email"],
    .login-container input[type="password"] {
        width: 100%;
        padding: 12px;
        margin-bottom: 20px;
        border: 1px solid #ead3d8;
        border-radius: 9px;
        box-sizing: border-box;
        outline: none;
        font-size: 15px;
    }

    .login-container input:focus {
        border-color: #b77d8a;
        box-shadow: 0 0 5px rgba(183, 125, 138, 0.3);
    }

    .login-container input[type="submit"] {
        width: 100%;
        padding: 13px;
        background-color: #76545a;
        color: white;
        border: none;
        border-radius: 9px;
        font-size: 16px;
        cursor: pointer;
        transition: 0.3s;
    }

    .login-container input[type="submit"]:hover {
        background-color: #62434a;
    }
</style>

<div class="login-container">
    <h1>Entrar</h1>

    <form action="formulario.php" method="post">

        <label for="email">Email</label>
        <input type="email" name="email" id="email"
               placeholder="Digite um email" required>

        <label for="senha">Senha</label>
        <input type="password" name="senha" id="senha"
               placeholder="Digite uma senha" required>

        <input type="submit" value="Entrar">

    </form>
</div>

<?php
include './classes/rodape.php';
?>