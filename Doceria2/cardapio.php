<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document</title>
</head>
<body>
    <form action="Total.php" method="post">
       <h1>Cardapio</h1>
       <label for="">Opções</label>
          <br>
        <select name="opcoes" >
            <option value="15">Biscoito de Peixe</option>
            <option value="20">Bolo</option>
            <option value="18">Maça do Amor</option>
        </select>
         <br>
        <input type="number" name="quantidade" placeholder="Quantidade:">
        <br>
        <input type="submit" value="Enviar">
         <input type="reset" value="limpar">
    </form>
</body>
</html>