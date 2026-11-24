<?php
session_start();

if (!isset($_SESSION['tipo']) || !in_array($_SESSION['tipo'], ['funcionario', 'funcionarios', 'gestor'], true)) {
    http_response_code(403);
    exit('Acesso negado!');
}

require_once './config/database.php';

function h($valor): string {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

if (empty($_SESSION['csrf_funcionario'])) {
    $_SESSION['csrf_funcionario'] = bin2hex(random_bytes(32));
}

$mensagem = '';
$tipoMensagem = 'sucesso';

// Opções de status: se a coluna for ENUM, utiliza os valores definidos no próprio banco.
$statusPermitidos = ['Pendente', 'Em preparo', 'Pronto', 'Saiu para entrega', 'Entregue', 'Concluído', 'Cancelado'];
try {
    $colunaStatus = $pdo->query("SHOW COLUMNS FROM PEDIDOS LIKE 'status'")->fetch(PDO::FETCH_ASSOC);
    if ($colunaStatus && preg_match('/^enum\((.*)\)$/i', $colunaStatus['Type'], $partes)) {
        preg_match_all("/'((?:\\\\'|[^'])*)'/", $partes[1], $valoresEnum);
        if (!empty($valoresEnum[1])) {
            $statusPermitidos = array_map(static function ($v) {
                return str_replace("\\'", "'", $v);
            }, $valoresEnum[1]);
        }
    }
} catch (Throwable $e) {
    error_log($e->getMessage());
}

// Processa as alterações antes de carregar os dados da página.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $tokenRecebido = (string)($_POST['csrf'] ?? '');
    if (!hash_equals($_SESSION['csrf_funcionario'], $tokenRecebido)) {
        $mensagem = 'Sua sessão expirou. Atualize a página e tente novamente.';
        $tipoMensagem = 'erro';
    } else {
        $acao = (string)($_POST['acao'] ?? '');

        try {
            if ($acao === 'alterar_status') {
                $idPedido = filter_input(INPUT_POST, 'id_pedido', FILTER_VALIDATE_INT);
                $novoStatus = trim((string)($_POST['status'] ?? ''));

                if (!$idPedido || !in_array($novoStatus, $statusPermitidos, true)) {
                    throw new RuntimeException('Selecione um pedido e um status válido.');
                }

                $stmt = $pdo->prepare('UPDATE PEDIDOS SET status = :status WHERE id_pedidos = :id');
                $stmt->execute([':status' => $novoStatus, ':id' => $idPedido]);
                $mensagem = $stmt->rowCount() ? 'Status do pedido atualizado com sucesso.' : 'O pedido já estava com esse status ou não foi encontrado.';

            } elseif ($acao === 'editar_ingrediente') {
                $idIngrediente = filter_input(INPUT_POST, 'id_ingredientes', FILTER_VALIDATE_INT);
                $nome = trim((string)($_POST['nome'] ?? ''));
                $validade = trim((string)($_POST['data_validade'] ?? ''));
                $dataValida = DateTime::createFromFormat('!Y-m-d', $validade);

                if (!$idIngrediente || $nome === '' || !$dataValida || $dataValida->format('Y-m-d') !== $validade) {
                    throw new RuntimeException('Informe um nome e uma data de validade válidos.');
                }

                $stmt = $pdo->prepare('UPDATE ingredientes SET nome = :nome, data_validade = :validade WHERE id_ingredientes = :id');
                $stmt->execute([':nome' => $nome, ':validade' => $validade, ':id' => $idIngrediente]);
                $mensagem = 'Ingrediente atualizado com sucesso.';

            } elseif ($acao === 'excluir_ingrediente') {
                $idIngrediente = filter_input(INPUT_POST, 'id_ingredientes', FILTER_VALIDATE_INT);
                if (!$idIngrediente) {
                    throw new RuntimeException('Ingrediente inválido.');
                }

                // Não permite apagar ingrediente que ainda faz parte de uma receita.
                $verifica = $pdo->prepare('SELECT COUNT(*) FROM unidades WHERE id_ingredientes = :id');
                $verifica->execute([':id' => $idIngrediente]);
                if ((int)$verifica->fetchColumn() > 0) {
                    throw new RuntimeException('Este ingrediente está vinculado a uma receita. Remova-o da receita antes de excluí-lo.');
                }

                $stmt = $pdo->prepare('DELETE FROM ingredientes WHERE id_ingredientes = :id');
                $stmt->execute([':id' => $idIngrediente]);
                $mensagem = $stmt->rowCount() ? 'Ingrediente excluído com sucesso.' : 'Ingrediente não encontrado.';
            }
        } catch (Throwable $e) {
            if ($e instanceof RuntimeException) {
                $mensagem = $e->getMessage();
            } else {
                error_log($e->getMessage());
                $mensagem = 'Não foi possível concluir a operação. Confira os dados e tente novamente.';
            }
            $tipoMensagem = 'erro';
        }
    }

    // Evita reenviar o formulário ao atualizar a página.
    $_SESSION['flash_funcionario'] = ['texto' => $mensagem, 'tipo' => $tipoMensagem];
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

if (!empty($_SESSION['flash_funcionario'])) {
    $mensagem = $_SESSION['flash_funcionario']['texto'];
    $tipoMensagem = $_SESSION['flash_funcionario']['tipo'];
    unset($_SESSION['flash_funcionario']);
}

$pedidos = $pdo->query(
    "SELECT p.id_pedidos, p.id_produto, p.data, p.quantidade_produto, p.valor_total, p.status,
            c.Nome AS cliente_nome, c.Telefone, c.Endereco, c.numero, c.Complemento, c.CEP,
            pr.nome AS produto_nome, pg.forma_de_pagamento
     FROM PEDIDOS p
     INNER JOIN clientes c ON c.id_clientes = p.id_clientes
     LEFT JOIN produto pr ON pr.id_produto = p.id_produto
     LEFT JOIN pagamento pg ON pg.id_pedidos = p.id_pedidos
     ORDER BY p.id_pedidos DESC"
)->fetchAll(PDO::FETCH_ASSOC);

/*
 * Busca as receitas ligadas aos produtos dos pedidos.
 * Cada receita pode ter vários ingredientes cadastrados em unidades.
 */
$receitasPorProduto = [];
try {
    $consultaReceitas = $pdo->query(
        "SELECT r.id_produto, r.id_receita, r.nome_receita, r.descricao, r.modo_preparo,
                u.id_unidades, u.quantidade, u.sigla_quantidade,
                i.nome AS ingrediente_nome
         FROM receita r
         LEFT JOIN unidades u ON u.id_receita = r.id_receita
         LEFT JOIN ingredientes i ON i.id_ingredientes = u.id_ingredientes
         ORDER BY r.id_produto, r.id_receita, i.nome"
    );

    foreach ($consultaReceitas->fetchAll(PDO::FETCH_ASSOC) as $linhaReceita) {
        $idProduto = (int)$linhaReceita['id_produto'];
        $idReceita = (int)$linhaReceita['id_receita'];

        if (!isset($receitasPorProduto[$idProduto][$idReceita])) {
            $receitasPorProduto[$idProduto][$idReceita] = [
                'nome' => $linhaReceita['nome_receita'],
                'descricao' => $linhaReceita['descricao'],
                'modo_preparo' => $linhaReceita['modo_preparo'],
                'ingredientes' => []
            ];
        }

        if ($linhaReceita['id_unidades'] !== null && $linhaReceita['ingrediente_nome'] !== null) {
            $receitasPorProduto[$idProduto][$idReceita]['ingredientes'][] = [
                'nome' => $linhaReceita['ingrediente_nome'],
                'quantidade' => $linhaReceita['quantidade'],
                'unidade' => $linhaReceita['sigla_quantidade']
            ];
        }
    }
} catch (Throwable $e) {
    error_log('Erro ao carregar receitas dos pedidos: ' . $e->getMessage());
    $receitasPorProduto = [];
}

$resumo = $pdo->query(
    "SELECT COUNT(*) AS total_pedidos,
            COALESCE(SUM(valor_total), 0) AS total_vendas,
            COALESCE(AVG(valor_total), 0) AS media_pedido
     FROM PEDIDOS"
)->fetch(PDO::FETCH_ASSOC);

$ingredientes = $pdo->query(
    "SELECT id_ingredientes, nome, data_validade
     FROM ingredientes
     ORDER BY data_validade ASC, nome ASC"
)->fetchAll(PDO::FETCH_ASSOC);

$hoje = new DateTimeImmutable('today');

include './classes/cabecalho.php';
include './classes/NavBar.php';
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
.secao{margin-top:30px}.pedido{margin:12px 0;line-height:1.55}.pedido h3{font-size:18px;font-weight:normal;color:#62434a;margin:0 0 8px}.pedido p{margin:5px 0}
.detalhes-receita{margin:12px 0;padding:13px 15px;background:#fff5f7;border:1px solid #ead3d8;border-radius:9px}
.detalhes-receita h4{margin:0 0 9px;color:#62434a;font-size:16px}
.receita-pedido{padding:9px 0;border-top:1px solid #ead3d8}
.receita-pedido:first-of-type{border-top:0}
.receita-pedido ul{margin:5px 0 8px 22px}
.texto-secundario{color:#98777d;font-size:13px}
.grid-ingredientes{display:grid;grid-template-columns:repeat(auto-fit,minmax(260px,1fr));gap:12px}
.ingrediente strong{display:block;color:#62434a;margin-bottom:7px;font-size:17px}
.vazio{padding:20px;background:#fffdfc;border:1px solid #ead3d8;border-radius:10px}
.alerta{padding:13px 16px;border-radius:9px;margin:0 0 18px}.alerta.sucesso{background:#e8f4e8;color:#285c32}.alerta.erro{background:#fbe8e8;color:#922f36}
.status-form,.editar-form{display:flex;gap:8px;align-items:center;flex-wrap:wrap;margin-top:12px;padding-top:12px;border-top:1px solid #f0dfe2}
select,input{padding:9px 10px;border:1px solid #dfcdd1;border-radius:7px;background:#fff;color:#62434a;font:14px Georgia,serif}
.status-form select{min-width:170px}.editar-form input[type=text]{flex:1;min-width:150px}.editar-form input[type=date]{min-width:145px}
button{padding:9px 13px;border:0;border-radius:7px;background:#76545a;color:#fff;font:bold 13px Georgia,serif;cursor:pointer}
button.excluir{background:#a94442}.validade{display:inline-block;margin:5px 0 8px;padding:5px 9px;border-radius:20px;font-size:13px;font-weight:bold}
.validade.ok{background:#e6f3e8;color:#28613a}.validade.atencao{background:#fff0c9;color:#815600}.validade.proxima{background:#ffe0bd;color:#914500}.validade.vencida{background:#f8d7da;color:#842029}
@media(max-width:600px){.status-form,.editar-form{align-items:stretch}.status-form select,.editar-form input{width:100%;box-sizing:border-box}.status-form button,.editar-form button{width:100%}}
</style>
<main class="area"><div class="wrap">
    <h1>Bem-vindo(a), <?= h($_SESSION['nome'] ?? 'Funcionário') ?>!</h1>
    <p class="intro">Área dos Funcionários — Doceria Mundo Encantado</p>

    <?php if ($mensagem !== ''): ?>
        <div class="alerta <?= $tipoMensagem === 'erro' ? 'erro' : 'sucesso' ?>"><?= h($mensagem) ?></div>
    <?php endif; ?>

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

                    <?php
                        $receitasDoProduto = $receitasPorProduto[(int)($p['id_produto'] ?? 0)] ?? [];
                    ?>
                    <?php if ($receitasDoProduto): ?>
                        <div class="detalhes-receita">
                            <h4>Receita do produto</h4>
                            <?php foreach ($receitasDoProduto as $receita): ?>
                                <div class="receita-pedido">
                                    <strong><?= h($receita['nome']) ?></strong>
                                    <?php if (!empty($receita['descricao'])): ?>
                                        <p><strong>Descrição:</strong> <?= nl2br(h($receita['descricao'])) ?></p>
                                    <?php endif; ?>

                                    <p><strong>Ingredientes:</strong></p>
                                    <?php if (!empty($receita['ingredientes'])): ?>
                                        <ul>
                                            <?php foreach ($receita['ingredientes'] as $ingrediente): ?>
                                                <li>
                                                    <?= h($ingrediente['nome']) ?> —
                                                    <?= h(rtrim(rtrim(number_format((float)$ingrediente['quantidade'], 2, ',', '.'), '0'), ',')) ?>
                                                    <?= h($ingrediente['unidade']) ?>
                                                </li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php else: ?>
                                        <p class="texto-secundario">Nenhum ingrediente vinculado a esta receita.</p>
                                    <?php endif; ?>

                                    <?php if (!empty($receita['modo_preparo'])): ?>
                                        <p><strong>Modo de preparo:</strong><br><?= nl2br(h($receita['modo_preparo'])) ?></p>
                                    <?php else: ?>
                                        <p class="texto-secundario">Modo de preparo não cadastrado.</p>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <p class="texto-secundario"><strong>Receita:</strong> ainda não há receita cadastrada para este produto.</p>
                    <?php endif; ?>

                    <p><strong>Quantidade:</strong> <?= h($p['quantidade_produto']) ?> |
                       <strong>Total:</strong> R$ <?= number_format((float)$p['valor_total'],2,',','.') ?></p>
                    <p><strong>Data:</strong> <?= $p['data'] ? date('d/m/Y',strtotime($p['data'])) : 'Não informada' ?> |
                       <strong>Status:</strong> <?= h($p['status']) ?></p>
                    <p><strong>Telefone:</strong> <?= h($p['Telefone']) ?></p>
                    <p><strong>Endereço:</strong> <?= h($p['Endereco']) ?>, <?= h($p['numero']) ?>
                       <?= !empty($p['Complemento']) ? ' - '.h($p['Complemento']) : '' ?> — CEP: <?= h($p['CEP']) ?></p>
                    <p><strong>Pagamento:</strong> <?= h($p['forma_de_pagamento'] ?? 'Não informado') ?></p>

                    <form class="status-form" method="post">
                        <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf_funcionario']) ?>">
                        <input type="hidden" name="acao" value="alterar_status">
                        <input type="hidden" name="id_pedido" value="<?= (int)$p['id_pedidos'] ?>">
                        <label for="status-<?= (int)$p['id_pedidos'] ?>"><strong>Alterar status:</strong></label>
                        <select name="status" id="status-<?= (int)$p['id_pedidos'] ?>" required>
                            <?php foreach ($statusPermitidos as $status): ?>
                                <option value="<?= h($status) ?>" <?= (string)$p['status'] === $status ? 'selected' : '' ?>><?= h($status) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button type="submit">Salvar status</button>
                    </form>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    </section>

    <section class="secao">
        <h2>Ingredientes e validade</h2>
        <p>Verde: dentro do prazo · Amarelo: vence em até 30 dias · Laranja: vence em até 7 dias · Vermelho: vencido.</p>
        <?php if (!$ingredientes): ?>
            <div class="vazio">Nenhum ingrediente cadastrado.</div>
        <?php else: ?>
            <div class="grid-ingredientes">
            <?php foreach ($ingredientes as $i): ?>
                <?php
                    try {
                        $dataValidade = new DateTimeImmutable($i['data_validade']);
                        $dias = (int)$hoje->diff($dataValidade)->format('%r%a');
                        if ($dias < 0) { $classeValidade = 'vencida'; $textoValidade = 'Vencido há ' . abs($dias) . ' dia(s)'; }
                        elseif ($dias <= 7) { $classeValidade = 'proxima'; $textoValidade = 'Vence em ' . $dias . ' dia(s)'; }
                        elseif ($dias <= 30) { $classeValidade = 'atencao'; $textoValidade = 'Atenção: vence em ' . $dias . ' dia(s)'; }
                        else { $classeValidade = 'ok'; $textoValidade = 'Dentro do prazo'; }
                    } catch (Throwable $e) {
                        $classeValidade = 'atencao'; $textoValidade = 'Data inválida';
                    }
                ?>
                <article class="ingrediente">
                    <strong><?= h($i['nome']) ?></strong>
                    <span class="validade <?= h($classeValidade) ?>"><?= h($textoValidade) ?></span><br>
                    <span>Validade: <?= $i['data_validade'] ? date('d/m/Y', strtotime($i['data_validade'])) : 'Não informada' ?></span>

                    <form class="editar-form" method="post">
                        <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf_funcionario']) ?>">
                        <input type="hidden" name="acao" value="editar_ingrediente">
                        <input type="hidden" name="id_ingredientes" value="<?= (int)$i['id_ingredientes'] ?>">
                        <input type="text" name="nome" value="<?= h($i['nome']) ?>" maxlength="120" aria-label="Nome do ingrediente" required>
                        <input type="date" name="data_validade" value="<?= h($i['data_validade']) ?>" aria-label="Data de validade" required>
                        <button type="submit">Salvar edição</button>
                    </form>
                    <form class="status-form" method="post" onsubmit="return confirm('Tem certeza de que deseja excluir este ingrediente?')">
                        <input type="hidden" name="csrf" value="<?= h($_SESSION['csrf_funcionario']) ?>">
                        <input type="hidden" name="acao" value="excluir_ingrediente">
                        <input type="hidden" name="id_ingredientes" value="<?= (int)$i['id_ingredientes'] ?>">
                        <button class="excluir" type="submit">Excluir ingrediente</button>
                    </form>
                </article>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div></main>
<?php include './classes/rodape.php'; ?>
