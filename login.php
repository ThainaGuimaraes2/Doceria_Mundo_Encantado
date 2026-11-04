
<?php
include './classes/cabecalho.php';
include './classes/NavBar.php';
?>

<form action="formulario.php" method="post">

    <label for="email">Email</label>
    <input type="email"  name="email" id="email" placeholder="Digite um email" required>
    <br>

    <label for="senha">Senha</label>
    <input  type="password"  name="senha" id="senha" placeholder="Digite uma senha" required >

    <br>

    <input type="submit" value="Entrar">

</form>

<?php
include './classes/rodape.php';
?>

