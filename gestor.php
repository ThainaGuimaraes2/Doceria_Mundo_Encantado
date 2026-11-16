<?php
session_start();
 
// Somente gestores podem acessar
if (
    !isset($_SESSION['tipo']) ||
    $_SESSION['tipo'] !== 'gestor'
) {
    http_response_code(403);
    exit("Acesso negado! Somente gestores podem acessar esta página.");
}
 
include "classes/cabecalho.php";
include "classes/NavBar.php";
?>