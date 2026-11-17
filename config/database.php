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

