<?php

try{
    
$dsn = "mysql:host=192.168.8.7;dbname=mtecpi5";
$username = "mtecpi5";
$password = "mtecpi5";
$pdo = new PDO($dsn, $username, $password);

}
catch(Exception $e){
    echo "erro ao conectar no banco de dados<br>entree como administrador";
}

/*$stmt = $pdo->query("SELECT * FROM funcionarios");
while ($funcionarios = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo $funcionarios['nome'] . " - " . $funcionarios['email'] . "<br>";
}*/
