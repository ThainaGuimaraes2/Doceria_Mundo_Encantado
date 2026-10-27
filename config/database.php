<?php

$dsn = "mysql:host=192.168.8.7;dbname=mtecpi5";
$username = "mtecpi5";
$password = "mtecpi5";
$pdo = new PDO($dsn, $username, $password);

$stmt = $pdo->query("SELECT * FROM funcionarios");
while ($funcionarios = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $funcionarios['nome'] . " - " . $funcionarios['email'] . "<br>";
}
