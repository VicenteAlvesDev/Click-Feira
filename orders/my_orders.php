<?php

session_start();

if (!isset($_SESSION["id_usuario"]) || $_SESSION["tipo_usuario"] !== "customer") {
    die("Acesso permitido apenas para clientes.");
}

require_once "../config/database.php";

$customer_id = $_SESSION["id_usuario"];

$sql = "SELECT
            o.id_order,
            u.name AS seller_name,
            p.name AS product_name,
            oi.quantity,
            oi.unit_price,
            o.order_date,
            o.status
        FROM orders o
        JOIN users u ON o.seller_id = u.id_user
        JOIN order_items oi ON o.id_order = oi.order_id
        JOIN products p ON oi.product_id = p.id_product
        WHERE o.customer_id = ?
        ORDER BY o.order_date DESC";

$stmt = $conn->prepare($sql);

$stmt->bind_param("i", $customer_id);

$stmt->execute();

$resultado = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Minhas encomendas - ClickFeira</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .encomenda {
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .encomenda h2 {
            margin-top: 0;
        }

        .status {
            font-weight: bold;
        }

        .cancelar {
            margin-top: 15px;
        }

        button {
            padding: 8px 12px;
            cursor: pointer;
        }
    </style>
</head>

<body>

    <h1>Minhas encomendas</h1>

    <?php if ($resultado->num_rows > 0): ?>

        <?php while ($encomenda = $resultado->fetch_assoc()): ?>

            <div class="encomenda">

                <h2>Encomenda #<?= $encomenda["id_order"] ?></h2>

                <p>
                    <strong>Vendedor:</strong>
                    <?= $encomenda["seller_name"] ?>
                </p>

                <p>
                    <strong>Produto:</strong>
                    <?= $encomenda["product_name"] ?>
                </p>

                <p>
                    <strong>Quantidade:</strong>
                    <?= $encomenda["quantity"] ?>
                </p>

                <p>
                    <strong>Preço unitário:</strong>
                    R$ <?= $encomenda["unit_price"] ?>
                </p>

                <p>
                    <strong>Data:</strong>
                    <?= $encomenda["order_date"] ?>
                </p>

                <p class="status">
                    <strong>Status:</strong>
                    <?= $encomenda["status"] ?>
                </p>

                <?php if ($encomenda["status"] === "PENDING"): ?>

                    <div class="cancelar">

                        <form method="POST" action="cancel.php">

                            <input
                                type="hidden"
                                name="order_id"
                                value="<?= $encomenda["id_order"] ?>"
                            >

                            <button type="submit">
                                Cancelar encomenda
                            </button>

                        </form>

                    </div>

                <?php endif; ?>

            </div>

        <?php endwhile; ?>

    <?php else: ?>

        <p>Você não possui encomendas.</p>

    <?php endif; ?>

    <a href="../index.php">Voltar para o início</a>

</body>

</html>

<?php

$stmt->close();

?>