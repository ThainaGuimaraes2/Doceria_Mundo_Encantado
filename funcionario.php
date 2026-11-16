
<?php
session_start();
 
if (
    !isset($_SESSION['tipo']) ||
    $_SESSION['tipo'] !== 'funcionario'
) {
    http_response_code(403);
    exit("Acesso negado!");
}
 
include "classes/cabecalho.php";
include "classes/NavBar.php";
?>
 
<h1>
    Bem-vindo(a), <?= htmlspecialchars($_SESSION['nome']) ?>!
</h1>
 
<h2>Área dos Funcionários</h2>
 
<p>Bem-vindo à Doceria Mundo Encantado!</p>
 
<?php
include "classes/rodape.php";
?>