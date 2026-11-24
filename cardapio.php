<?php
session_start();
require_once "./config/database.php";

function h($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

// O cardápio pode ser visualizado por qualquer pessoa.
$stmt = $pdo->query("SELECT id_produto, nome, preco, imagem_url FROM produto ORDER BY nome ASC");
$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Busca as descrições das receitas vinculadas a cada produto.
$stmtReceitas = $pdo->query(
    "SELECT id_produto, nome_receita, descricao
     FROM receita
     WHERE descricao IS NOT NULL AND TRIM(descricao) <> ''
     ORDER BY nome_receita ASC"
);
$receitasPorProduto = [];
foreach ($stmtReceitas->fetchAll(PDO::FETCH_ASSOC) as $receita) {
    $idProdutoReceita = (int)$receita['id_produto'];
    $receitasPorProduto[$idProdutoReceita][] = $receita;
}

$erro = '';
$pedidoCriado = false;

// A compra só é concluída por um cliente autenticado.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['finalizar_pedido'])) {
    if (
        !isset($_SESSION['tipo'], $_SESSION['id']) ||
        $_SESSION['tipo'] !== 'cliente'
    ) {
        $_SESSION['mensagem_login'] = 'Entre na sua conta ou cadastre-se para finalizar seu pedido.';
        header("Location: login.php");
        exit;
    }

    if (
        !isset($_POST['token']) ||
        !hash_equals($_SESSION['token_pedido'] ?? '', (string)$_POST['token'])
    ) {
        $erro = 'Sua sessão expirou. Atualize a página e tente novamente.';
    } else {
        $idProduto = filter_input(INPUT_POST, 'id_produto', FILTER_VALIDATE_INT);
        $quantidade = filter_input(INPUT_POST, 'quantidade', FILTER_VALIDATE_INT);
        $forma = (string)($_POST['forma_pagamento'] ?? '');

        // Opções compatíveis com o fluxo atual: o site registra o pedido,
        // mas não processa cobranças nem armazena dados de cartão.
        $formas = [
            'pix' => 'Pix (após confirmação do pedido)',
            'dinheiro_retirada' => 'Dinheiro na retirada',
            'debito_retirada' => 'Cartão de débito na retirada',
            'credito_retirada' => 'Cartão de crédito na retirada'
        ];

        if (
            !$idProduto || !$quantidade || $quantidade < 1 || $quantidade > 99 ||
            !isset($formas[$forma])
        ) {
            $erro = 'Confira o produto, a quantidade e a forma de pagamento.';
        } else {
            // O preço é consultado no banco. Não confiamos no preço enviado pelo navegador.
            $consulta = $pdo->prepare(
                "SELECT id_produto, nome, preco FROM produto WHERE id_produto = :id"
            );
            $consulta->execute([':id' => $idProduto]);
            $produto = $consulta->fetch(PDO::FETCH_ASSOC);

            if (!$produto) {
                $erro = 'O produto selecionado não foi encontrado.';
            } else {
                $valorUnitario = (float)$produto['preco'];
                $valorTotal = $valorUnitario * $quantidade;

                try {
                    $pdo->beginTransaction();

                    // O pedido fica associado ao cliente e ao produto.
                    $insertPedido = $pdo->prepare(
                        "INSERT INTO PEDIDOS
                         (id_clientes, id_produto, data, valor_total, status, quantidade_produto)
                         VALUES
                         (:cliente, :produto, CURDATE(), :total, 'Pendente', :quantidade)"
                    );
                    $insertPedido->execute([
                        ':cliente' => $_SESSION['id'],
                        ':produto' => $produto['id_produto'],
                        ':total' => number_format($valorTotal, 2, '.', ''),
                        ':quantidade' => (string)$quantidade
                    ]);

                    $idPedido = (int)$pdo->lastInsertId();
                    $codigoPagamento = random_int(100000, 999999);

                    $insertPagamento = $pdo->prepare(
                        "INSERT INTO pagamento
                         (id_pedidos, forma_de_pagamento, data, codigo_pagamento)
                         VALUES
                         (:pedido, :forma, CURDATE(), :codigo)"
                    );
                    $insertPagamento->execute([
                        ':pedido' => $idPedido,
                        ':forma' => $formas[$forma],
                        ':codigo' => $codigoPagamento
                    ]);

                    $pdo->commit();

                    header("Location: meus_pedidos.php?sucesso=1");
                    exit;
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    error_log($e->getMessage());
                    $erro = 'Não foi possível registrar seu pedido. Tente novamente.';
                }
            }
        }
    }
}

if (empty($_SESSION['token_pedido'])) {
    $_SESSION['token_pedido'] = bin2hex(random_bytes(32));
}

include "./classes/cabecalho.php";
include "./classes/NavBar.php";
?>
<style>
:root{--rosa:#f7e1e7;--rosa-claro:#fbeef1;--marrom:#76545a;--escuro:#62434a;--branco:#fffdfc;--borda:#ead3d8}
body{margin:0;background:var(--rosa);color:var(--marrom);font-family:Georgia,'Times New Roman',serif}
.pagina{min-height:70vh;padding:35px 16px 55px;background:var(--rosa)}
.container{width:min(94%,900px);margin:auto}
.topo{text-align:center;margin-bottom:28px}
h1{font-size:clamp(28px,4vw,38px);font-weight:normal;color:var(--escuro);margin:0 0 8px}
.sub{color:#98777d;margin:0}
h2{font-size:22px;font-weight:normal;color:var(--escuro);margin:25px 0 14px}
.lista{display:grid;gap:13px}
.produto{display:flex;align-items:center;gap:17px;background:var(--branco);border:1px solid var(--borda);border-radius:12px;padding:12px}
.produto img,.sem-imagem{width:112px;height:105px;object-fit:cover;border-radius:8px;background:var(--rosa-claro);flex-shrink:0}
.sem-imagem{display:grid;place-items:center;font-size:13px;color:#98777d}
.info{flex:1}.info h3{margin:0 0 8px;color:var(--escuro);font-size:19px;font-weight:normal}
.preco{font-weight:bold;font-size:18px;margin:0}
.descricoes-receita{margin-top:10px;padding-top:9px;border-top:1px solid var(--borda)}
.descricao-receita{margin:5px 0 0;font-size:14px;line-height:1.5;color:#896a70}
.descricao-receita strong{color:var(--escuro)}
.form,.aviso{max-width:700px;margin:25px auto 0;padding:25px;background:var(--branco);border:1px solid var(--borda);border-radius:12px}
label{display:block;margin:13px 0 6px;color:var(--escuro)}
select,input{width:100%;padding:12px;border:1px solid #dfcdd1;border-radius:8px;background:#fff;color:var(--escuro);font:15px Georgia,serif;box-sizing:border-box}
button{margin-top:18px;padding:12px 22px;border:0;border-radius:8px;background:var(--marrom);color:white;font:bold 15px Georgia,serif;cursor:pointer}
.aviso{text-align:center}.erro{color:#9b3030}.login-link{color:var(--escuro);font-weight:bold}
.orientacao-pagamento{line-height:1.5;color:#896a70}
.aviso-pagamento{margin-top:16px;padding:14px 16px;background:var(--rosa-claro);border:1px solid var(--borda);border-radius:9px;line-height:1.5}
.aviso-pagamento ul{margin:8px 0;padding-left:22px}
.aviso-pagamento small{display:block;color:#896a70}
@media(max-width:560px){.produto{gap:11px}.produto img,.sem-imagem{width:82px;height:82px}.info h3{font-size:16px}.form{padding:19px}}
</style>

<main class="pagina">
<div class="container">
    <header class="topo">
        <h1>Nosso Cardápio</h1>
        <p class="sub">Escolha seus produtos favoritos!</p>
    </header>

    <?php if ($erro !== ''): ?>
        <div class="aviso erro"><?= h($erro) ?></div>
    <?php endif; ?>

    <?php if (empty($produtos)): ?>
        <div class="aviso">Ainda não há produtos cadastrados. Volte mais tarde!</div>
    <?php else: ?>
        <h2>Nossos produtos</h2>
        <section class="lista">
        <?php foreach ($produtos as $produto): ?>
            <article class="produto">
                <?php if (!empty($produto['imagem_url'])): ?>
                    <img src="<?= h($produto['imagem_url']) ?>" alt="<?= h($produto['nome']) ?>">
                <?php else: ?>
                    <div class="sem-imagem">Sem imagem</div>
                <?php endif; ?>
                <div class="info">
                    <h3><?= h($produto['nome']) ?></h3>
                    <p class="preco">R$ <?= number_format((float)$produto['preco'], 2, ',', '.') ?></p>

                    <?php
                    $idProdutoAtual = (int)$produto['id_produto'];
                    $receitasDoProduto = $receitasPorProduto[$idProdutoAtual] ?? [];
                    ?>
                    <?php if (!empty($receitasDoProduto)): ?>
                        <div class="descricoes-receita">
                            <?php foreach ($receitasDoProduto as $receita): ?>
                                <p class="descricao-receita">
                                    <?php if (!empty($receita['nome_receita'])): ?>
                                        <strong><?= h($receita['nome_receita']) ?>:</strong>
                                    <?php endif; ?>
                                    <?= nl2br(h($receita['descricao'])) ?>
                                </p>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </article>
        <?php endforeach; ?>
        </section>

        <?php if (!isset($_SESSION['tipo'], $_SESSION['id']) || $_SESSION['tipo'] !== 'cliente'): ?>
            <section class="form">
                <h2 style="margin-top:0">Finalize seu pedido</h2>
                <p>Para fazer um pedido, você precisa ter uma conta de cliente e estar conectado.</p>
                <p><a class="login-link" href="login.php">Entrar / Fazer cadastro</a></p>
            </section>
        <?php else: ?>
            <form class="form" method="post" action="">
                <h2 style="margin-top:0">Finalize seu pedido</h2>
                <p class="orientacao-pagamento">Escolha o produto, a quantidade e como deseja pagar. O pedido será registrado como pendente até a confirmação.</p>

                <input type="hidden" name="token" value="<?= h($_SESSION['token_pedido']) ?>">

                <label for="id_produto">Produto:</label>
                <select name="id_produto" id="id_produto" required>
                    <option value="">Selecione um produto</option>
                    <?php foreach ($produtos as $produto): ?>
                        <option value="<?= (int)$produto['id_produto'] ?>">
                            <?= h($produto['nome']) ?> — R$ <?= number_format((float)$produto['preco'], 2, ',', '.') ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="quantidade">Quantidade:</label>
                <input type="number" name="quantidade" id="quantidade" min="1" max="99" value="1" required>

                <label for="forma_pagamento">Forma de pagamento:</label>
                <select name="forma_pagamento" id="forma_pagamento" required>
                    <option value="">Selecione como deseja pagar</option>
                    <option value="pix">Pix — após confirmação do pedido</option>
                    <option value="dinheiro_retirada">Dinheiro — na retirada</option>
                    <option value="debito_retirada">Cartão de débito — na retirada</option>
                    <option value="credito_retirada">Cartão de crédito — na retirada</option>
                </select>

                <div class="aviso-pagamento">
                    <strong>Como funciona o pagamento?</strong>
                    <ul>
                        <li><strong>Pix:</strong> os dados para transferência serão informados após a confirmação do pedido pela doceria.</li>
                        <li><strong>Dinheiro ou cartão:</strong> o pagamento será feito presencialmente no momento da retirada.</li>
                    </ul>
                    <small>Este site registra o pedido e a forma de pagamento escolhida. Ele ainda não processa pagamentos online. Não informe número de cartão, senha ou código de segurança neste formulário.</small>
                </div>

                <button type="submit" name="finalizar_pedido" value="1">Confirmar pedido</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</div>
</main>
<?php include "./classes/rodape.php"; ?>
