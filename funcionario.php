<?php
session_start();

if (!isset($_SESSION['tipo']) || !in_array($_SESSION['tipo'], ['funcionario', 'gestor'], true)) {
    http_response_code(403);
    exit("Acesso negado!");
}

require_once "./config/database.php";

function h($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

$pedidos = $pdo->query(
    "SELECT p.id_pedidos, p.data, p.quantidade_produto, p.valor_total, p.status,
            c.Nome AS cliente_nome, c.Telefone, c.Endereco, c.numero, c.Complemento, c.CEP,
            pr.nome AS produto_nome, pg.forma_de_pagamento
     FROM PEDIDOS p
     INNER JOIN clientes c ON c.id_clientes = p.id_clientes
     LEFT JOIN produto pr ON pr.id_produto = p.id_produto
     LEFT JOIN pagamento pg ON pg.id_pedidos = p.id_pedidos
     ORDER BY p.id_pedidos DESC"
)->fetchAll(PDO::FETCH_ASSOC);

$resumo = $pdo->query(
    "SELECT COUNT(*) AS total_pedidos, COALESCE(SUM(valor_total),0) AS total_vendas,
            COALESCE(AVG(valor_total),0) AS media_pedido
     FROM PEDIDOS"
)->fetch(PDO::FETCH_ASSOC);

$ingredientes = $pdo->query(
    "SELECT id_ingredientes, nome, data_validade
     FROM ingredientes
     ORDER BY data_validade ASC, nome ASC"
)->fetchAll(PDO::FETCH_ASSOC);

include "./classes/cabecalho.php";
include "./classes/NavBar.php";
?>
<style>
body{margin:0;background:#f7e1e7;color:#76545a;font-family:Georgia,'Times New Roman',serif}
.area{min-height:70vh;padding:32px 16px 55px;background:#f7e1e7}
.wrap{width:min(96%,1100px);margin:auto}
h1,h2{color:#62434a;font-weight:normal}
h1{text-align:center}.intro{text-align:center;color:#98777d;margin-bottom:25px}
.cards{display:grid;grid-template-columns:repeat(auto-fit,minmax(190px,1fr));gap:13px}
.card,.pedido,.ingrediente{background:#fffdfc;border:1px solid #ead3d8;border-radius:12px;padding:17px}
.card small{display:block;color:#98777d;margin-bottom:8px}.card strong{font-size:23px;color:#62434a}
.secao{margin-top:30px}
.pedido{margin:12px 0;line-height:1.55}
.pedido h3{font-size:18px;font-weight:normal;color:#62434a;margin:0 0 8px}
.pedido p{margin:5px 0}
.grid-ingredientes{display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:12px}
.ingrediente strong{display:block;color:#62434a;margin-bottom:7px}
.vazio{padding:20px;background:#fffdfc;border:1px solid #ead3d8;border-radius:10px}
</style>
<main class="area"><div class="wrap">
    <h1>Bem-vindo(a), <?= h($_SESSION['nome'] ?? 'Funcionário') ?>!</h1>
    <p class="intro">Área dos Funcionários — Doceria Mundo Encantado</p>

    <section class="cards">
        <div class="card"><small>Pedidos realizados</small><strong><?= (int)$resumo['total_pedidos'] ?></strong></div>
        <div class="card"><small>Valor total das vendas</small><strong>R$ <?= number_format((float)$resumo['total_vendas'],2,',','.') ?></strong></div>
        <div class="card"><small>Valor médio por pedido</small><strong>R$ <?= number_format((float)$resumo['media_pedido'],2,',','.') ?></strong></div>
    </section>

    <section class="secao">
        <h2>Pedidos dos clientes</h2>
        <?php if (!$pedidos): ?>
            <div class="vazio">Ainda não há pedidos registrados.</div>
        <?php else: ?>
            <?php foreach ($pedidos as $p): ?>
                <article class="pedido">
                    <h3>Pedido #<?= (int)$p['id_pedidos'] ?> — <?= h($p['cliente_nome']) ?></h3>
                    <p><strong>Produto:</strong> <?= h($p['produto_nome'] ?? 'Não informado') ?></p>
                    <p><strong>Quantidade:</strong> <?= h($p['quantidade_produto']) ?> |
                       <strong>Total:</strong> R$ <?= number_format((float)$p['valor_total'],2,',','.') ?></p>
                    <p><strong>Data:</strong> <?= date('d/m/Y',strtotime($p['data'])) ?> |
                       <strong>Status:</strong> <?= h($p['status']) ?></p>
                    <p><strong>Telefone:</strong> <?= h($p['Telefone']) ?></p>
                    <p><strong>Endereço:</strong> <?= h($p['Endereco']) ?>, <?= h($p['numero']) ?>
                       <?= $p['Complemento'] ? ' - '.h($p['Complemento']) : '' ?> — CEP: <?= h($p['CEP']) ?></p>
                    <p><strong>Pagamento:</strong> <?= h($p['forma_de_pagamento'] ?? 'Não informado') ?></p>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <section class="secao">
        <h2>Ingredientes e validade</h2>
        <?php if (!$ingredientes): ?>
            <div class="vazio">Nenhum ingrediente cadastrado.</div>
        <?php else: ?>
            <div class="grid-ingredientes">
            <?php foreach ($ingredientes as $i): ?>
                <article class="ingrediente">
                    <strong><?= h($i['nome']) ?></strong>
                    <span>Validade: <?= date('d/m/Y', strtotime($i['data_validade'])) ?></span>
                </article>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <p><small>As quantidades disponíveis em estoque não são exibidas porque o banco ainda não possui controle de estoque.</small></p>
    </section>
</div></main>
<?php include "./classes/rodape.php"; ?>
