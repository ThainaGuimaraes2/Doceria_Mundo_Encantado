<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <form action="Total.php" method="post">
       <h1>Cardápio</h1>
       <label for="">Opções:</label>
          <br>
        <select name="opcoes" >
            <option value="15">Biscoito de Peixe: R$15,00</option>
            <option value="20">Tortinha de frutas: R$20,00</option>
            <option value="18">Maça do Amor: R$18,00</option>
            <option value="17">Donuts decorado: R$17,00</option>]
            <option value="18">Cupcake temático: R$18,00</option>
            <option value="16">Cookie decorados: R$16,00</option>
            <option value="19">Brownie decorado: R$19,00</option>
        </select>
        
         <br> <br>
         <label for="">Quantidade:</label>
         <br>
        <input type="number" name="quantidade" placeholder="Indique a quantidade">
        <br> <br>

        <label for="">Formas de Pagamento:</label>
        <br>
         <select name="opcoesPag" >
            <option value="1">Pix/Dinheiro</option>
            <option value="2">Débito</option>
            <option value="3">Crédito</option>
            <option value="4">Links de pagamento</option>
        </select>
        <br> <br>

        <label for="">Parcelas</label>
        <br>
         <select name="parcelas" >
         <?php 
         for($i=1;$i<13;$i++){
            ?>
           <option value="<?php echo $i?>"> <?php echo $i ?></option>
        
           <?php
         }   
         ?>
            
        </select>
        <br> <br>
      
        


        <input type="submit" value="Enviar">
         <input type="reset" value="Limpar">
    </form>
</body>
</html>