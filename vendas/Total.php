<?php
   require_once "../classes/cal.php";
   require_once "../classes/juros.php";


   $opcoes = $_POST['opcoes'] ;
   $quantidade = $_POST['quantidade'];

   $parcelas = $_POST['parcelas'];
   $opcoesPag = $_POST['opcoesPag'];

   $calculo=new Soma();
   $juros = new Juros();
   
    switch($opcoesPag){
       case '1':
           $resultado = $calculo->calcular($opcoes, $quantidade);
         break;
      case '2': 
          $resultado = $calculo->calcular($opcoes, $quantidade);
         break;
      case '3':
           $valorJuros = $juros->calcular($opcoes,$parcelas,$quantidade);
         break;

}
    










