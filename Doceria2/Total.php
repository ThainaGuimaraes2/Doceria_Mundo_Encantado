<?php
  
   $opcoes = $_POST['opcoes'];
   $quantidade = $_POST['quantidade'];
   $resultado;
   $resulBruto
   $parcelas = $_POST['parcelas'];
   $opcoesPag = $_POST['opcoesPag'];
   
    switch($opcoesPag){
       case '1':
          $resultado = $opcoes * $quantidade;
           echo "O total da sua compra : $resultado";
         break;
      case '2':
         # code...
         break;
    }







   if($opcoesPag == 1){
       
   } elseif($opcoesPag == 2 ){
        $resultado = $opcoes * $quantidade;
        echo "O total da sua compra : $resultado";
   }elseif($opcoesPag == 3 ){
        if($parcelas <= 2){
           $resulBruto = $opcoes * $quantidade * 0,0579;
           $resultado = $resulBruto + $opcoes*$quantidade;
           echo "O total da sua compra : $resultado";
        }else{
            $resulBruto = $opcoes * $quantidade * 0,1238;
           $resultado = $resulBruto + $opcoes*$quantidade;
           echo "O total da sua compra : $resultado";
        }
   }  







 
 

