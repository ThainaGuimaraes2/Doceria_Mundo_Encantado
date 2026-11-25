
<?php
session_start();
require_once "./config/database.php";

function protegerCardapio($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

// Busca os produtos cadastrados pelo gestor.
$stmt = $pdo->query(
    "SELECT id_produto, nome, preco, imagem_url
     FROM produto
     ORDER BY nome ASC"
);
$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Busca as receitas vinculadas aos produtos.
$stmtReceitas = $pdo->query(
    "SELECT id_produto, nome_receita, descricao
     FROM receita
     WHERE descricao IS NOT NULL
       AND TRIM(descricao) <> ''
     ORDER BY nome_receita ASC"
);

$receitasPorProduto = [];

foreach ($stmtReceitas->fetchAll(PDO::FETCH_ASSOC) as $receita) {
    $id = (int)$receita['id_produto'];
    $receitasPorProduto[$id][] = $receita;
}

$erroPedido = '';

// Processamento do pedido.
if ($_SERVER['REQUEST_METHOD'] === 'POST' &&
    isset($_POST['finalizar_pedido'])) {

    // Somente clientes logados podem comprar.
    if (
        !isset($_SESSION['tipo'], $_SESSION['id']) ||
        $_SESSION['tipo'] !== 'cliente'
    ) {
        $_SESSION['mensagem_login'] =
            'Entre na sua conta para finalizar o pedido.';

        header("Location: login.php");
        exit;
    }

    // Proteção contra envio de formulário antigo.
    if (
        !isset($_POST['token']) ||
        !hash_equals(
            $_SESSION['token_pedido'] ?? '',
            (string)$_POST['token']
        )
    ) {
        $erroPedido = 'Sessão expirada. Atualize a página.';
    } else {

        $idProduto = filter_input(
            INPUT_POST,
            'id_produto',
            FILTER_VALIDATE_INT
        );

        $quantidade = filter_input(
            INPUT_POST,
            'quantidade',
            FILTER_VALIDATE_INT
        );

        $forma = (string)($_POST['forma_pagamento'] ?? '');

        $formas = [
            'pix' => 'Pix',
            'dinheiro' => 'Dinheiro na retirada',
            'debito' => 'Cartão de débito na retirada',
            'credito' => 'Cartão de crédito na retirada'
        ];

        if (
            !$idProduto ||
            !$quantidade ||
            $quantidade < 1 ||
            $quantidade > 99 ||
            !isset($formas[$forma])
        ) {
            $erroPedido =
                'Confira o produto, a quantidade e o pagamento.';
        } else {

            // Consulta o produto diretamente no banco.
            $consulta = $pdo->prepare(
                "SELECT id_produto, nome, preco
                 FROM produto
                 WHERE id_produto = :id"
            );

            $consulta->execute([
                ':id' => $idProduto
            ]);

            $produtoSelecionado = $consulta->fetch(PDO::FETCH_ASSOC);

            if (!$produtoSelecionado) {
                $erroPedido = 'Produto não encontrado.';
            } else {

                $valorTotal =
                    (float)$produtoSelecionado['preco'] * $quantidade;

                try {
                    $pdo->beginTransaction();

                    // Registra o pedido.
                    $insertPedido = $pdo->prepare(
                        "INSERT INTO PEDIDOS
                        (
                            id_clientes,
                            id_produto,
                            data,
                            valor_total,
                            status,
                            quantidade_produto
                        )
                        VALUES
                        (
                            :cliente,
                            :produto,
                            CURDATE(),
                            :total,
                            'Pendente',
                            :quantidade
                        )"
                    );

                    $insertPedido->execute([
                        ':cliente' => $_SESSION['id'],
                        ':produto' => $idProduto,
                        ':total' => number_format(
                            $valorTotal,
                            2,
                            '.',
                            ''
                        ),
                        ':quantidade' => $quantidade
                    ]);

                    $idPedido = (int)$pdo->lastInsertId();

                    // Registra a forma de pagamento.
                    $insertPagamento = $pdo->prepare(
                        "INSERT INTO pagamento
                        (
                            id_pedidos,
                            forma_de_pagamento,
                            data,
                            codigo_pagamento
                        )
                        VALUES
                        (
                            :pedido,
                            :forma,
                            CURDATE(),
                            :codigo
                        )"
                    );

                    $insertPagamento->execute([
                        ':pedido' => $idPedido,
                        ':forma' => $formas[$forma],
                        ':codigo' => random_int(100000, 999999)
                    ]);

                    $pdo->commit();

                    header("Location: meus_pedidos.php?sucesso=1");
                    exit;

                } catch (Throwable $e) {

                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    error_log($e->getMessage());

                    $erroPedido =
                        'Não foi possível registrar o pedido.';
                }
            }
        }
    }
}

// Token de segurança.
if (empty($_SESSION['token_pedido'])) {
    $_SESSION['token_pedido'] = bin2hex(random_bytes(32));
}

include "./classes/cabecalho.php";
include "./classes/NavBar.php";
?>

<style>
:root {
    --rosa-fundo: #f7e1e7;
    --rosa-claro: #fbeef1;
    --marrom: #76545a;
    --marrom-escuro: #62434a;
    --branco: #fffdfc;
    --borda: #ead3d8;
}

body {
    margin: 0;
    background: var(--rosa-fundo);
    color: var(--marrom);
    font-family: Georgia, 'Times New Roman', serif;
}

.cardapio-pagina {
    min-height: 100vh;
    padding: 35px 16px 55px;
    background: var(--rosa-fundo);
}

.cardapio-container {
    width: min(94%, 900px);
    margin: auto;
}

.apresentacao-empresa {
    padding: 64px 18px 58px;
    background: linear-gradient(
        135deg,
        #fff8fa,
        #f7e1e7 55%,
        #f2d3dc
    );
    text-align: center;
    border-bottom: 1px solid var(--borda);
}

.apresentacao-conteudo {
    width: min(92%, 900px);
    margin: auto;
}

.apresentacao-selo {
    display: inline-block;
    margin-bottom: 15px;
    padding: 7px 15px;
    border: 1px solid #e4c3cc;
    border-radius: 30px;
    background: rgba(255,255,255,.7);
    color: #98777d;
    font-size: 13px;
    letter-spacing: 1px;
}

.apresentacao-titulo {
    margin: 0 0 16px;
    color: var(--marrom-escuro);
    font-size: clamp(34px, 6vw, 54px);
    font-weight: normal;
}

.apresentacao-texto {
    max-width: 690px;
    margin: auto auto 25px;
    font-size: 18px;
    line-height: 1.8;
}

.apresentacao-destaques {
    display: flex;
    justify-content: center;
    flex-wrap: wrap;
    gap: 12px;
    margin-bottom: 28px;
}

.apresentacao-destaques span {
    padding: 9px 14px;
    background: white;
    border: 1px solid var(--borda);
    border-radius: 30px;
}

.apresentacao-botao {
    display: inline-block;
    padding: 13px 25px;
    border-radius: 9px;
    background: var(--marrom);
    color: white;
    text-decoration: none;
    font-weight: bold;
}

.cardapio-topo {
    text-align: center;
    margin-bottom: 30px;
}

.cardapio-titulo {
    color: var(--marrom-escuro);
    font-size: 38px;
    font-weight: normal;
}

.cardapio-subtitulo {
    color: #98777d;
}

.cardapio-secao-titulo {
    margin: 28px 0 14px;
    color: var(--marrom-escuro);
    font-size: 22px;
    font-weight: normal;
}

.produtos-lista {
    display: grid;
    gap: 14px;
    margin-bottom: 32px;
}

.produto-card {
    display: flex;
    align-items: center;
    gap: 18px;
    padding: 12px;
    background: var(--branco);
    border: 1px solid var(--borda);
    border-radius: 12px;
    box-shadow: 0 3px 12px rgba(118,84,90,.07);
}

.produto-card img,
.produto-sem-imagem {
    flex: 0 0 125px;
    width: 125px;
    height: 116px;
    object-fit: cover;
    border-radius: 9px;
    background: var(--rosa-claro);
}

.produto-sem-imagem {
    display: grid;
    place-items: center;
}

.produto-info {
    flex: 1;
    min-width: 0;
}

.produto-info h3 {
    margin: 0 0 9px;
    color: var(--marrom-escuro);
    font-size: 19px;
    font-weight: normal;
}

.produto-preco {
    font-size: 18px;
    font-weight: bold;
}

.descricoes-receita {
    margin-top: 10px;
    padding-top: 9px;
    border-top: 1px solid var(--borda);
}

.descricao-receita {
    margin: 5px 0;
    color: #896a70;
    font-size: 14px;
    line-height: 1.5;
}

.descricao-receita strong {
    color: var(--marrom-escuro);
}

.produto-escolher {
    flex-shrink: 0;
    padding: 10px 17px;
    border: 1px solid var(--marrom);
    border-radius: 8px;
    background: white;
    color: var(--marrom);
    font-weight: bold;
    cursor: pointer;
}

.produto-escolher:hover {
    background: var(--marrom);
    color: white;
}

.cardapio-formulario {
    max-width: 700px;
    margin: 25px auto 0;
    padding: 28px;
    background: var(--branco);
    border: 1px solid var(--borda);
    border-radius: 12px;
    box-shadow: 0 3px 12px rgba(118,84,90,.06);
}

.cardapio-formulario h2 {
    margin-top: 0;
    color: var(--marrom-escuro);
    font-weight: normal;
}

.cardapio-instrucao {
    color: #98777d;
    line-height: 1.5;
}

.cardapio-formulario label {
    display: block;
    margin: 14px 0 7px;
}

.cardapio-formulario input,
.cardapio-formulario select {
    width: 100%;
    padding: 13px 14px;
    border: 1px solid #dfcdd1;
    border-radius: 8px;
    background: white;
    color: var(--marrom-escuro);
    font: 15px Georgia, serif;
}

.cardapio-formulario input:focus,
.cardapio-formulario select:focus {
    outline: none;
    border-color: #c58b9a;
}

.cardapio-botoes button {
    margin-top: 20px;
    padding: 13px 22px;
    border: 0;
    border-radius: 8px;
    background: var(--marrom);
    color: white;
    font: bold 15px Georgia, serif;
    cursor: pointer;
}

.cardapio-aviso {
    padding: 16px;
    margin: 20px auto;
    background: var(--branco);
    border: 1px solid var(--borda);
    border-radius: 10px;
}

.erro {
    color: #9b3030;
    background: #fceaea;
}

.aviso-pagamento {
    margin-top: 16px;
    padding: 14px;
    background: var(--rosa-claro);
    border: 1px solid var(--borda);
    border-radius: 9px;
    line-height: 1.6;
}

.aviso-pagamento small {
    display: block;
    margin-top: 10px;
    color: #896a70;
}

@media(max-width:560px) {
    .produto-card {
        gap: 10px;
        flex-wrap: wrap;
    }

    .produto-card img,
    .produto-sem-imagem {
        flex-basis: 85px;
        width: 85px;
        height: 85px;
    }

    .produto-escolher {
        margin-left: auto;
    }

    .cardapio-formulario {
        padding: 20px 16px;
    }
}
</style>

<!-- APRESENTAÇÃO -->
<section class="apresentacao-empresa">
    <div class="apresentacao-conteudo">

        <span class="apresentacao-selo">
            DOCERIA • AMOR EM CADA DETALHE
        </span>

        <h1 class="apresentacao-titulo">
            Doceria Mundo Encantado
        </h1>

        <p class="apresentacao-texto">
            Seja bem-vindo ao nosso cantinho mais doce!
            Preparamos delícias com carinho para transformar
            momentos simples em lembranças especiais.
        </p>

        <div class="apresentacao-destaques">
            <span>♡ Feito com carinho</span>
            <span>♡ Doces especiais</span>
            <span>♡ Momentos mais felizes</span>
        </div>

        <a class="apresentacao-botao" href="#cardapio">
            Conheça nosso cardápio
        </a>

    </div>
</section>

<!-- CARDÁPIO -->
<main class="cardapio-pagina" id="cardapio">

<div class="cardapio-container">

    <header class="cardapio-topo">
        <h1 class="cardapio-titulo">Nosso Cardápio</h1>
        <p class="cardapio-subtitulo">
            Escolha seus produtos favoritos!
        </p>
    </header>

    <?php if ($erroPedido !== ''): ?>
        <div class="cardapio-aviso erro">
            <?= protegerCardapio($erroPedido) ?>
        </div>
    <?php endif; ?>

    <?php if (empty($produtos)): ?>

        <div class="cardapio-aviso">
            Ainda não há produtos cadastrados.
        </div>

    <?php else: ?>

        <h2 class="cardapio-secao-titulo">Nossos produtos</h2>

        <section class="produtos-lista">

            <?php foreach ($produtos as $produto): ?>

                <article class="produto-card">

                    <?php if (!empty($produto['imagem_url'])): ?>

                        <img
                            src="<?= protegerCardapio($produto['imagem_url']) ?>"
                            alt="<?= protegerCardapio($produto['nome']) ?>"
                        >

                    <?php else: ?>

                        <div class="produto-sem-imagem">
                            Sem imagem
                        </div>

                    <?php endif; ?>

                    <div class="produto-info">

                        <h3><?= protegerCardapio($produto['nome']) ?></h3>

                        <p class="produto-preco">
                            R$ <?= number_format(
                                (float)$produto['preco'],
                                2,
                                ',',
                                '.'
                            ) ?>
                        </p>

                        <?php
                        $idAtual = (int)$produto['id_produto'];
                        $receitas = $receitasPorProduto[$idAtual] ?? [];
                        ?>

                        <?php if (!empty($receitas)): ?>

                            <div class="descricoes-receita">

                                <?php foreach ($receitas as $receita): ?>

                                    <p class="descricao-receita">

                                        <?php if (!empty($receita['nome_receita'])): ?>
                                            <strong>
                                                <?= protegerCardapio($receita['nome_receita']) ?>:
                                            </strong>
                                        <?php endif; ?>

                                        <?= nl2br(protegerCardapio($receita['descricao'])) ?>

                                    </p>

                                <?php endforeach; ?>

                            </div>

                        <?php endif; ?>

                    </div>

                    <button
                        type="button"
                        class="produto-escolher"
                        data-id="<?= (int)$produto['id_produto'] ?>"
                    >
                        Escolher
                    </button>

                </article>

            <?php endforeach; ?>

        </section>

        <!-- FORMULÁRIO DE PEDIDO -->

        <?php
        $clienteLogado =
            isset($_SESSION['tipo'], $_SESSION['id']) &&
            $_SESSION['tipo'] === 'cliente';
        ?>

        <?php if (!$clienteLogado): ?>

            <section class="cardapio-formulario">

                <h2>Finalize seu pedido</h2>

                <p class="cardapio-instrucao">
                    Para comprar, você precisa entrar na sua conta
                    ou criar um cadastro.
                </p>

                <a href="login.php" style="color:#76545a;font-weight:bold;">
                    Entrar / Fazer cadastro
                </a>

            </section>

        <?php else: ?>

            <form
                class="cardapio-formulario"
                method="POST"
                action=""
                id="formPedido"
            >

                <h2>Finalize seu pedido</h2>

                <p class="cardapio-instrucao">
                    Escolha o produto, a quantidade e a forma de pagamento.
                </p>

                <input
                    type="hidden"
                    name="token"
                    value="<?= protegerCardapio($_SESSION['token_pedido']) ?>"
                >

                <label for="id_produto">Produto escolhido:</label>

                <select name="id_produto" id="id_produto" required>

                    <option value="">Selecione um produto</option>

                    <?php foreach ($produtos as $produto): ?>

                        <option value="<?= (int)$produto['id_produto'] ?>">

                            <?= protegerCardapio($produto['nome']) ?>
                            — R$ <?= number_format(
                                (float)$produto['preco'],
                                2,
                                ',',
                                '.'
                            ) ?>

                        </option>

                    <?php endforeach; ?>

                </select>

                <label for="quantidade">Quantidade:</label>

                <input
                    type="number"
                    name="quantidade"
                    id="quantidade"
                    min="1"
                    max="99"
                    value="1"
                    required
                >

                <label for="forma_pagamento">Forma de pagamento:</label>

                <select
                    name="forma_pagamento"
                    id="forma_pagamento"
                    required
                >

                    <option value="">Selecione uma forma de pagamento</option>
                    <option value="pix">Pix</option>
                    <option value="dinheiro">Dinheiro na retirada</option>
                    <option value="debito">Cartão de débito na retirada</option>
                    <option value="credito">Cartão de crédito na retirada</option>

                </select>

                <div class="aviso-pagamento">

                    <strong>Informações sobre o pagamento</strong>

                    <ul>
                        <li>
                            <strong>Pix:</strong>
                            os dados serão informados após a confirmação do pedido.
                        </li>

                        <li>
                            <strong>Dinheiro:</strong>
                            pagamento realizado na retirada.
                        </li>

                        <li>
                            <strong>Cartão:</strong>
                            pagamento presencial na retirada.
                        </li>
                    </ul>

                    <small>
                        O site registra o pedido e a forma de pagamento,
                        mas ainda não realiza cobranças online.
                        Não informe dados de cartão neste formulário.
                    </small>

                </div>

                <div class="cardapio-botoes">

                    <button
                        type="submit"
                        name="finalizar_pedido"
                        value="1"
                    >
                        Confirmar pedido
                    </button>

                </div>

            </form>

        <?php endif; ?>

    <?php endif; ?>

</div>
</main>

<script>
document.querySelectorAll('.produto-escolher').forEach(function(botao) {

    botao.addEventListener('click', function() {

        const idProduto = this.dataset.id;
        const seletor = document.getElementById('id_produto');

        if (!seletor) {
            return;
        }

        // Seleciona o produto pelo ID.
        seletor.value = idProduto;

        // Destaca o botão selecionado.
        document.querySelectorAll('.produto-escolher').forEach(function(item) {

            item.classList.remove('selecionado');
            item.textContent = 'Escolher';

        });

        this.classList.add('selecionado');
        this.textContent = 'Selecionado';

        // Leva o cliente até o formulário.
        document.getElementById('formPedido').scrollIntoView({
            behavior: 'smooth',
            block: 'center'
        });

    });

});
</script>

<?php include "./classes/rodape.php"; ?>
