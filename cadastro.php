 
<?php
include "./classes/cabecalho.php";
include './classes/NavBar.php';
?>
 
<style>
 
/* CONFIGURAÇÃO GERAL */
 
.cadastro-page {
    background-color: #f8e1e5;
    min-height: 100vh;
    padding: 60px 7%;
    font-family: Georgia, 'Times New Roman', serif;
    color: #704b50;
 
    display: grid;
    grid-template-columns: 1fr 1.3fr;
    gap: 60px;
    align-items: start;
    box-sizing: border-box;
}
 
/* INTRODUÇÃO */
 
.cadastro-intro {
    padding-top: 30px;
}
 
.cadastro-intro .subtitulo {
    font-size: 12px;
    font-weight: bold;
    letter-spacing: 3px;
    color: #79545a;
}
 
.cadastro-intro h1 {
    font-size: 38px;
    font-weight: normal;
    margin: 15px 0;
    color: #704b50;
}
 
.cadastro-intro p {
    font-size: 17px;
    line-height: 1.6;
    max-width: 420px;
}
 
/* FORMULÁRIO */
 
.cadastro-container {
    background-color: #fffdf9;
    padding: 35px;
    border-radius: 5px;
    width: 100%;
    box-sizing: border-box;
}
 
.cadastro-container h2 {
    font-size: 25px;
    font-weight: normal;
    margin-top: 0;
    margin-bottom: 25px;
    color: #704b50;
}
 
/* CAMPOS */
 
.campo {
    display: flex;
    flex-direction: column;
    margin-bottom: 18px;
}
 
.campo label {
    font-size: 16px;
    font-weight: bold;
    margin-bottom: 8px;
    color: #704b50;
}
 
.campo input {
    width: 100%;
    height: 43px;
    padding: 10px 13px;
    box-sizing: border-box;
 
    border: 1px solid #d9c8c8;
    border-radius: 3px;
 
    background-color: white;
    color: #704b50;
 
    font-family: Georgia, serif;
    font-size: 15px;
    outline: none;
    transition: 0.3s;
}
 
.campo input:focus {
    border-color: #a8757e;
    box-shadow: 0 0 4px rgba(168, 117, 126, 0.25);
}
 
.campo input::placeholder {
    color: #b5a5a5;
}
 
/* BOTÕES */
 
.botoes-cadastro {
    display: flex;
    gap: 12px;
    margin-top: 25px;
}
 
.botoes-cadastro input {
    padding: 14px 25px;
    border: none;
    border-radius: 3px;
 
    background-color: #80545d;
    color: white;
 
    font-family: Georgia, serif;
    font-size: 15px;
    font-weight: bold;
 
    cursor: pointer;
    transition: 0.3s;
}
 
.botoes-cadastro input:hover {
    background-color: #633e46;
}
 
.botoes-cadastro input[type="reset"] {
    background-color: transparent;
    border: 1px solid #80545d;
    color: #80545d;
}
 
.botoes-cadastro input[type="reset"]:hover {
    background-color: #f3dce1;
}
 
/* RESPONSIVIDADE */
 
@media (max-width: 850px) {
 
    .cadastro-page {
        grid-template-columns: 1fr;
        gap: 30px;
        padding: 35px 5%;
    }
 
    .cadastro-intro {
        padding-top: 0;
    }
 
    .cadastro-intro h1 {
        font-size: 32px;
    }
 
    .cadastro-container {
        padding: 25px;
    }
 
}
 
@media (max-width: 480px) {
 
    .cadastro-page {
        padding: 25px 4%;
    }
 
    .cadastro-container {
        padding: 20px;
    }
 
    .botoes-cadastro {
        flex-direction: column;
    }
 
    .botoes-cadastro input {
        width: 100%;
    }
 
}
 
</style>
 
<main class="cadastro-page">
 
    <section class="cadastro-intro">
 
        <span class="subtitulo">
            SEJA BEM-VINDO
        </span>
 
        <h1>Cadastro</h1>
 
        <p>
            Crie sua conta na Doceria Mundo Encantado.
            Preencha seus dados para aproveitar nossas delícias!
        </p>
 
    </section>
 
    <section class="cadastro-container">
 
        <h2>Faça seu cadastro.</h2>
 
        <form action="Action.php" method="post">
 
            <div class="campo">
                <label for="name">Nome:</label>
                <input type="text" name="name" id="name"
                placeholder="Digite seu nome" required>
            </div>
 
            <div class="campo">
                <label for="email">E-mail:</label>
                <input type="email" name="email" id="email"
                placeholder="voce@exemplo.com" required>
            </div>
 
            <div class="campo">
                <label for="password">Senha:</label>
                <input type="password" name="password" id="password"
                placeholder="Digite sua senha" required>
            </div>
 
            <div class="campo">
                <label for="password_confir">Confirme a senha:</label>
                <input type="password" name="password_confir"
                id="password_confir"
                placeholder="Confirme sua senha" required>
            </div>
 
            <div class="campo">
                <label for="phoneNumber">Telefone:</label>
                <input type="text" name="phoneNumber" id="phoneNumber"
                placeholder="(00) 00000-0000">
            </div>
 
            <div class="campo">
                <label for="endereco">Endereço:</label>
                <input type="text" name="endereco" id="endereco"
                placeholder="Digite seu endereço">
            </div>
 
            <div class="campo">
                <label for="cep">CEP:</label>
                <input type="text" name="cep" id="cep"
                placeholder="00000-000">
            </div>
 
            <div class="campo">
                <label for="complemento">Complemento:</label>
                <input type="text" name="complemento" id="complemento"
                placeholder="Apartamento, casa, etc.">
            </div>
 
            <div class="campo">
                <label for="number">Número:</label>
                <input type="number" name="number" id="number"
                placeholder="Número da residência">
            </div>
 
            <div class="botoes-cadastro">
 
                <input type="submit" value="Cadastrar">
 
                <input type="reset" value="Limpar">
 
            </div>
 
        </form>
 
    </section>
 
</main>
 
<?php
include "./classes/rodape.php";
?>