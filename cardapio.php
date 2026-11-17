<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once "./config/database.php";

if (!isset($pdo)) {
    die("Erro: conexão com o banco não encontrada.");
}

// Busca os produtos cadastrados no painel do gestor.
$stmt = $pdo->query(
    "SELECT id_produto, nome, preco, imagem_url
     FROM produto
     ORDER BY nome ASC"
);
$produtos = $stmt->fetchAll(PDO::FETCH_ASSOC);

function protegerCardapio($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

include "./classes/cabecalho.php";
include "./classes/NavBar.php";
?>

<style>
:root {
    --rosa-fundo: #f7e1e7; --rosa-claro: #fbeef1; --marrom: #76545a;
    --marrom-escuro: #62434a; --branco: #fffdfc; --borda: #ead3d8;
}
.cardapio-pagina { min-height:100vh; padding:35px 16px 55px; background:var(--rosa-fundo); color:var(--marrom); font-family:Georgia,'Times New Roman',serif; }
.cardapio-container { width:min(94%,900px); margin:0 auto; }
.cardapio-topo { text-align:center; margin-bottom:30px; }
.cardapio-titulo { margin:0 0 8px; color:var(--marrom-escuro); font-size:clamp(28px,4vw,38px); font-weight:normal; }
.cardapio-subtitulo { margin:0; color:#98777d; }
.cardapio-secao-titulo { margin:28px 0 14px; color:var(--marrom-escuro); font-size:22px; font-weight:normal; }
.produtos-lista { display:grid; grid-template-columns:1fr; gap:14px; margin-bottom:32px; }
.produto-card { display:flex; align-items:center; gap:18px; min-height:142px; padding:12px; background:var(--branco); border:1px solid var(--borda); border-radius:12px; box-shadow:0 3px 12px rgba(118,84,90,.07); transition:transform .2s,box-shadow .2s; }
.produto-card:hover { transform:translateY(-2px); box-shadow:0 6px 16px rgba(118,84,90,.12); }
.produto-card img,.produto-sem-imagem { flex:0 0 125px; width:125px; height:116px; object-fit:cover; border-radius:9px; background:var(--rosa-claro); }
.produto-sem-imagem { display:grid; place-items:center; color:#ad8991; font-size:13px; }
.produto-info { flex:1; min-width:0; }
.produto-info h3 { margin:0 0 9px; color:var(--marrom-escuro); font-size:19px; font-weight:normal; }
.produto-preco { margin:0; color:var(--marrom); font-size:18px; font-weight:bold; }
.produto-escolher { flex:0 0 auto; padding:10px 17px; border:1px solid var(--marrom); border-radius:8px; background:#fff; color:var(--marrom); font:bold 14px Georgia,'Times New Roman',serif; cursor:pointer; transition:.2s; }
.produto-escolher:hover,.produto-escolher.selecionado { background:var(--marrom); color:white; }
.cardapio-formulario { max-width:700px; margin:0 auto; padding:28px; background:var(--branco); border:1px solid var(--borda); border-radius:12px; box-shadow:0 3px 12px rgba(118,84,90,.06); }
.cardapio-formulario h2 { margin:0 0 8px; color:var(--marrom-escuro); font-size:23px; font-weight:normal; }
.cardapio-instrucao { margin:0 0 20px; color:#98777d; font-size:14px; }
.cardapio-formulario label { display:block; margin:14px 0 7px; color:var(--marrom-escuro); }
.cardapio-formulario input,.cardapio-formulario select { display:block; width:100%; padding:13px 14px; margin-bottom:14px; border:1px solid #dfcdd1; border-radius:8px; background:#fff; color:var(--marrom-escuro); font:14px Georgia,'Times New Roman',serif; box-sizing:border-box; }
.cardapio-formulario input:focus,.cardapio-formulario select:focus { outline:none; border-color:#c58b9a; box-shadow:0 0 0 3px rgba(244,201,213,.35); }
.cardapio-botoes { display:flex; flex-wrap:wrap; gap:10px; margin-top:8px; }
.cardapio-botoes button,.cardapio-botoes input { width:auto; margin:0; padding:12px 21px; border:1px solid var(--marrom); border-radius:8px; cursor:pointer; background:var(--marrom); color:#fffaf8; font:bold 14px Georgia,'Times New Roman',serif; }
.cardapio-botoes input[type="reset"] { background:#fff; color:var(--marrom); }
.cardapio-aviso { padding:18px; margin-bottom:24px; background:var(--branco); border:1px solid var(--borda); border-radius:10px; text-align:center; }
@media(max-width:560px) {
    .cardapio-container { width:100%; }
    .produto-card { gap:12px; min-height:112px; padding:9px; }
    .produto-card img,.produto-sem-imagem { flex-basis:92px; width:92px; height:92px; }
    .produto-info h3 { font-size:16px; }
    .produto-preco { font-size:16px; }
    .produto-escolher { padding:9px 11px; font-size:12px; }
    .cardapio-formulario { padding:21px 16px; }
}
</style>

<main class="cardapio-pagina">
    <div class="cardapio-container">
        <div class="cardapio-topo">
            <h1 class="cardapio-titulo">Nosso Cardápio</h1>
            <p class="cardapio-subtitulo">Escolha seus produtos favoritos!</p>
        </div>

        <?php if (empty($produtos)): ?>
            <div class="cardapio-aviso">Ainda não há produtos cadastrados. Volte mais tarde!</div>
        <?php else: ?>
            <h2 class="cardapio-secao-titulo">Nossos produtos</h2>
            <section class="produtos-lista" aria-label="Produtos disponíveis">
                <?php foreach ($produtos as $indice => $produto): ?>
                    <article class="produto-card">
                        <?php if (!empty($produto['imagem_url'])): ?>
                            <img src="<?= protegerCardapio($produto['imagem_url']) ?>" alt="<?= protegerCardapio($produto['nome']) ?>">
                        <?php else: ?>
                            <div class="produto-sem-imagem">Sem imagem</div>
                        <?php endif; ?>

                        <div class="produto-info">
                            <h3><?= protegerCardapio($produto['nome']) ?></h3>
                            <p class="produto-preco">R$ <?= number_format((float)$produto['preco'], 2, ',', '.') ?></p>
                        </div>

                        <button class="produto-escolher" type="button" data-indice="<?= (int)$indice ?>">Adicionar</button>
                    </article>
                <?php endforeach; ?>
            </section>

            <form class="cardapio-formulario" id="formPedido" action="Total.php" method="post">
                <h2>Finalize seu pedido</h2>
                <p class="cardapio-instrucao">Selecione os detalhes do seu pedido abaixo.</p>

                <label for="opcoes">Produto escolhido:</label>
                <select name="opcoes" id="opcoes" required>
                    <option value="">Selecione um produto</option>
                    <?php foreach ($produtos as $produto): ?>
                        <option value="<?= protegerCardapio($produto['preco']) ?>">
                            <?= protegerCardapio($produto['nome']) ?> — R$ <?= number_format((float)$produto['preco'], 2, ',', '.') ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="quantidade">Quantidade:</label>
                <input type="number" name="quantidade" id="quantidade" placeholder="Indique a quantidade" min="1" step="1" value="1" required>

                <label for="opcoesPag">Forma de pagamento:</label>
                <select name="opcoesPag" id="opcoesPag" required>
                    <option value="1">Pix / Dinheiro</option>
                    <option value="2">Débito</option>
                    <option value="3">Crédito</option>
                    <option value="4">Link de pagamento</option>
                </select>

                <label for="parcelas">Parcelas:</label>
                <select name="parcelas" id="parcelas" required>
                    <?php for ($i = 1; $i <= 12; $i++): ?>
                        <option value="<?= $i ?>"><?= $i ?>x</option>
                    <?php endfor; ?>
                </select>

                <div class="cardapio-botoes">
                    <button type="submit">Enviar pedido</button>
                    <input type="reset" value="Limpar">
                </div>
            </form>
        <?php endif; ?>
    </div>
</main>

<script>
document.querySelectorAll('.produto-escolher').forEach(function(botao) {
    botao.addEventListener('click', function() {
        const indice = Number(this.dataset.indice);
        const seletor = document.getElementById('opcoes');
        if (!seletor) return;

        // A primeira opção do select é apenas a instrução; os produtos começam no índice 1.
        seletor.selectedIndex = indice + 1;

        document.querySelectorAll('.produto-escolher').forEach(function(item) {
            item.classList.remove('selecionado');
            item.textContent = 'Adicionar';
        });

        this.classList.add('selecionado');
        this.textContent = 'Adicionado';
        document.getElementById('formPedido').scrollIntoView({behavior:'smooth', block:'center'});
    });
});
</script>

<?php include "./classes/rodape.php"; ?>
