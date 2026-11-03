<?php

class Soma {
    public function calcular($opcoes,$quantidade){
          $resultado = (float)$opcoes * (int)$quantidade;
           echo "O total da sua compra : $resultado";

}
}