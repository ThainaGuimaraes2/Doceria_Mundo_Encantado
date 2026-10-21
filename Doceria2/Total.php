<?php
  
   $opcoes = $_POST['opcoes'];
   $quantidade = $_POST['quantidade'];
   $resultado;
 
   $resultado = $opcoes * $quantidade;
   echo "O total da sua compra : $resultado";
