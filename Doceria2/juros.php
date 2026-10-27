<?php

class Juros{
    function calcular($opcoes,$parcelas,$quantidade){
        $total= (float)$opcoes * (int)$quantidade;
      
            if($parcelas <= 2){
                 $taxa = 5.79 /100;

            }else{
                $taxa = 12.38/100;
            }
        
                 $totalJuros = $total * $taxa;
                 $valorJuros = $totalJuros + $total;

                 echo "O valor total de sua compra é R$ ". number_format($valorJuros,2,",",".");
    }
}
