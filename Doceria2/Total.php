<?php
  
   $opcoes = $_POST['opcoes'];
   $quantidade = $_POST['quantidade'];

   $parcelas = $_POST['parcelas'];
   $opcoesPag = $_POST['opcoesPag'];
   
    switch($opcoesPag){
       case '1':
          $resultado = $opcoes * $quantidade;
           echo "O total da sua compra : $resultado";
         break;
      case '2':
          $resultado = $opcoes * $quantidade;
          echo "O total da sua compra : $resultado";
         break;
      case '3':
         break;
}
    










