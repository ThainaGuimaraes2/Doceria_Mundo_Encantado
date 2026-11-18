<?php
// Cabeçalho da navegação da Doceria Mundo Encantado
include_once __DIR__ . '/cabecalho.php';
?>

<style>
/* Navegação - Doceria Mundo Encantado */
.navbar-doceria {
    width: 100%;
    background: #fffaf9;
    border-bottom: 2px solid #ead3d8;
    box-shadow: 0 3px 12px rgba(118, 84, 90, 0.09);
    position: relative;
    z-index: 100;
    font-family: Georgia, 'Times New Roman', serif;
}

.navbar-doceria__container {
    width: min(1160px, 94%);
    min-height: 88px;
    margin: 0 auto;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 24px;
}

.navbar-doceria__marca {
    display: flex;
    align-items: center;
    gap: 13px;
    color: #76545a;
    text-decoration: none;
    min-width: 0;
}

.navbar-doceria__logo {
    width: 65px;
    height: 65px;
    object-fit: contain;
    border-radius: 50%;
    background: #fbeef1;
}

.navbar-doceria__nome {
    color: #62434a;
    font-size: clamp(18px, 2.4vw, 25px);
    font-weight: bold;
    line-height: 1.2;
}

.navbar-doceria__links {
    display: flex;
    align-items: center;
    gap: 8px;
    list-style: none;
    padding: 0;
    margin: 0;
}

.navbar-doceria__links a {
    display: block;
    padding: 11px 16px;
    border-radius: 22px;
    color: #76545a;
    text-decoration: none;
    font-size: 16px;
    transition: background-color .2s ease, color .2s ease, transform .2s ease;
}

.navbar-doceria__links a:hover,
.navbar-doceria__links a:focus-visible {
    background: #f7e1e7;
    color: #62434a;
    transform: translateY(-1px);
    outline: none;
}

.navbar-doceria__toggle {
    display: none;
    width: 43px;
    height: 40px;
    padding: 8px;
    border: 1px solid #ead3d8;
    border-radius: 9px;
    background: #fbeef1;
    cursor: pointer;
}

.navbar-doceria__toggle span {
    display: block;
    height: 2px;
    margin: 5px 0;
    background: #76545a;
    border-radius: 2px;
}

@media (max-width: 700px) {
    .navbar-doceria__container {
        min-height: 76px;
        flex-wrap: wrap;
        gap: 10px;
        padding: 9px 0;
    }

    .navbar-doceria__logo {
        width: 54px;
        height: 54px;
    }

    .navbar-doceria__toggle {
        display: block;
        margin-left: auto;
    }

    .navbar-doceria__links {
        display: none;
        width: 100%;
        flex-direction: column;
        align-items: stretch;
        gap: 3px;
        padding: 8px 0 5px;
    }

    .navbar-doceria__links.ativo {
        display: flex;
    }

    .navbar-doceria__links a {
        padding: 12px 14px;
        border-radius: 8px;
    }
}

</style>

<nav class="navbar-doceria">
    <div class="navbar-doceria__container">
        <a class="navbar-doceria__marca" href="./index.php" aria-label="Doceria Mundo Encantado - Página inicial">
            <img
                class="navbar-doceria__logo"
                src="./issues/img/logo.png"
                alt="Logo da Doceria Mundo Encantado"
            >
            <span class="navbar-doceria__nome">Doceria Mundo Encantado</span>
        </a>

        <button class="navbar-doceria__toggle" type="button" aria-label="Abrir menu" aria-expanded="false" aria-controls="menuDoceria">
            <span></span>
            <span></span>
            <span></span>
        </button>

        <ul class="navbar-doceria__links" id="menuDoceria">
            <li><a href="./index.php">Home</a></li>
            <li><a href="./cardapio.php">Cardápio</a></li>
            <li><a href="./cadastro.php">Cadastro</a></li>
            <li><a href="./login.php">Login</a></li>
        </ul>
    </div>
</nav>

<script>
(function () {
    const botao = document.querySelector('.navbar-doceria__toggle');
    const menu = document.querySelector('.navbar-doceria__links');

    if (botao && menu) {
        botao.addEventListener('click', function () {
            const aberto = menu.classList.toggle('ativo');
            botao.setAttribute('aria-expanded', aberto ? 'true' : 'false');
        });
    }
})();
</script>

<?php
// O rodapé continua sendo incluído no final da página principal,
// não dentro da barra de navegação.
?>
