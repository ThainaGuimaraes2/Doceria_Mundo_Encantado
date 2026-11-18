-- Execute uma única vez no banco mtecpi5.
-- A coluna relaciona cada pedido a um produto (o cardápio atual escolhe um produto por pedido).
ALTER TABLE PEDIDOS
    ADD COLUMN id_produto INT NULL AFTER id_clientes,
    ADD CONSTRAINT fk_pedidos_produto
        FOREIGN KEY (id_produto) REFERENCES produto(id_produto);

-- Pedidos antigos podem continuar com id_produto NULL.
-- Os novos pedidos feitos pelo cardapio.php terão o produto associado.
