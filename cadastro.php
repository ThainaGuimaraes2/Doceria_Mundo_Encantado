<?php
include "./classes/cabecalho.php" ;
include './classes/NavBar.php';
?>
    <form action="Action.php" method="post">
        <label for="">Nome:</label>
        <input type="text" name="name" id="">
         <BR>
        <label for="">Email:</label>
        <input type="email" name="email" id="">
          <BR>
        <label for="">Senha:</label>
        <input type="password" name="password" id="">
         <BR>
        <label for="">Confirme a senha:</label>
        <input type="password" name="password_confir" id="">
           <BR>
        <label for="">Telefone:</label>
        <input type="text" name="phoneNumber" id="">
          <BR>
        <label for="">Endereço:</label>
        <input type="text" name="endereco" id="">
           <BR>
        <label for="">CEP:</label>
        <input type="number" name="cep" id="">
            <BR>
        <label for="">Complemento:</label>
        <input type="text" name="complemento" id="">
                <BR>
        <label for="">Número:</label>
        <input type="number" name="number" id="">
          <BR>
            <input type="submit" value="Cadastrar">
            <input type="reset" value="Limpar">
<?php
include "./classes/rodape.php" ;
?>