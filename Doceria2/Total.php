<?php
  
   $opcoes = $_POST['opcoes'];
   $quantidade = $_POST['quantidade'];
   $resultado;
   $resulBruto;
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
        echo "<br><br>"
        echo "<label for="">Parcelas</label>"
        echo"<br>"
        echo "<select name='parcelas' >
         <?php 
         for($i=1;$i<13;$i++){
            ?>
           <option value="<?php echo $i?>"> "<?php echo $i ?>"</option>
        
           <?php
         }   
         ?>
            
        </select>"
        echo "<br> <br>"
         break;
    }








 
 

