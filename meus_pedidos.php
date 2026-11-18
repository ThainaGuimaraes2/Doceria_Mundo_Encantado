<?php
session_start();
require_once "./config/database.php";

if (
    !isset($_SESSION['tipo'], $_SESSION['id']) ||
    $_SESSION['tipo'] !== 'cliente'
) {
    $_SESSION['mensagem_login'] = 'Entre na sua conta para consultar seus pedidos.';
    header("Location: login.php");
    exit;
}

function h($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

$sql = "SELECT p.id_pedidos, p.data, p.quantidade_produto, p.valor_total, p.status,
               pr.nome AS produto_nome, pg.forma_de_pagamento
        FROM PEDIDOS p
        LEFT JOIN produto pr ON pr.id_produto = p.id_produto
        LEFT JOIN pagamento pg ON pg.id_pedidos = p.id_pedidos
        WHERE p.id_clientes = :cliente
        ORDER BY p.id_pedidos DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute([':cliente' => $_SESSION['id']]);
$pedidos = $stmt->fetchAll(PDO::FETCH_ASSOC);

include "./classes/cabecalho.php";
include "./classes/NavBar.php";
?>
<style>
body{margin:0;background:#f7e1e7;color:#76545a;font-family:Georgia,'Times New Roman',serif}
.area{min-height:65vh;padding:35px 16px 55px;background:#f7e1e7}
.wrap{width:min(94%,900px);margin:auto}
h1{text-align:center;color:#62434a;font-weight:normal}
.sub{text-align:center;color:#98777d;margin-bottom:28px}
.pedido{background:#fffdfc;border:1px solid #ead3d8;border-radius:12px;padding:19px;margin:14px 0}
.pedido h2{font-size:19px;font-weight:normal;color:#62434a;margin:0 0 12px}
.pedido p{margin:7px 0;line-height:1.5}
.vazio{background:#fffdfc;border:1px solid #ead3d8;border-radius:12px;padding:25px;text-align:center}
.sucesso{background:#fbeef1;padding:13px;border-radius:8px;text-align:center}
</style>
<main class="area"><div class="wrap">
    <h1>Meus pedidos</h1>
    <p class="sub">Acompanhe suas compras na Doceria Mundo Encantado.</p>

    <?php if (isset($_GET['sucesso'])): ?>
        <p class="sucesso">Seu pedido foi registrado com sucesso!</p>
    <?php endif; ?>

    <?php if (!$pedidos): ?>
        <div class="vazio">Você ainda não realizou nenhum pedido.</div>
    <?php else: ?>
        <?php foreach ($pedidos as $pedido): ?>
            <article class="pedido">
                <h2>Pedido #<?= (int)$pedido['id_pedidos'] ?></h2>
                <p><strong>Produto:</strong> <?= h($pedido['produto_nome'] ?? 'Produto não identificado') ?></p>
                <p><strong>Data:</strong> <?= date('d/m/Y', strtotime($pedido['data'])) ?></p>
                <p><strong>Quantidade:</strong> <?= h($pedido['quantidade_produto']) ?></p>
                <p><strong>Valor total:</strong> R$ <?= number_format((float)$pedido['valor_total'], 2, ',', '.') ?></p>
                <p><strong>Pagamento:</strong> <?= h($pedido['forma_de_pagamento'] ?? 'Não informado') ?></p>
                <p><strong>Status:</strong> <?= h($pedido['status']) ?></p>
            </article>
        <?php endforeach; ?>
    <?php endif; ?>
</div></main>
<?php include "./classes/rodape.php"; ?>
