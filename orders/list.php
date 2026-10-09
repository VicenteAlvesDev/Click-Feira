<?php

session_start();

require_once "../config/database.php";

if (!isset($_SESSION["id_usuario"]) || $_SESSION["tipo_usuario"] !== "seller") {
    die("Acesso permitido apenas para vendedores.");
}

$seller_id = $_SESSION["id_usuario"];

$sql = "SELECT
            o.id_order,
            u.name AS customer_name,
            p.name AS product_name,
            oi.quantity,
            oi.unit_price,
            o.order_date,
            o.status
        FROM orders o
        JOIN users u ON o.customer_id = u.id_user
        JOIN order_items oi ON o.id_order = oi.order_id
        JOIN products p ON oi.product_id = p.id_product
        WHERE o.seller_id = ?";

$stmt = $conn->prepare($sql);

$stmt->bind_param("i", $seller_id);

$stmt->execute();

$resultado = $stmt->get_result();

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Encomendas - ClickFeira</title>

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

        .acoes {
            margin-top: 15px;
        }

        .acoes form {
            display: inline;
        }

        button {
            padding: 8px 12px;
            margin-right: 5px;
            cursor: pointer;
        }
    </style>
</head>

<body>

    <h1>Encomendas recebidas</h1>

    <?php if ($resultado->num_rows > 0): ?>

        <?php while ($encomenda = $resultado->fetch_assoc()): ?>

            <div class="encomenda">

                <h2>Encomenda #<?= $encomenda["id_order"] ?></h2>

                <p>
                    <strong>Cliente:</strong>
                    <?= $encomenda["customer_name"] ?>
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

                    <div class="acoes">

                        <form method="POST" action="update_status.php">

                            <input
                                type="hidden"
                                name="order_id"
                                value="<?= $encomenda["id_order"] ?>"
                            >

                            <input
                                type="hidden"
                                name="status"
                                value="ACCEPTED"
                            >

                            <button type="submit">
                                Aceitar
                            </button>

                        </form>

                        <form method="POST" action="update_status.php">

                            <input
                                type="hidden"
                                name="order_id"
                                value="<?= $encomenda["id_order"] ?>"
                            >

                            <input
                                type="hidden"
                                name="status"
                                value="REJECTED"
                            >

                            <button type="submit">
                                Recusar
                            </button>

                        </form>

                    </div>

                <?php elseif ($encomenda["status"] === "ACCEPTED"): ?>

                    <div class="acoes">

                        <form method="POST" action="update_status.php">

                            <input
                                type="hidden"
                                name="order_id"
                                value="<?= $encomenda["id_order"] ?>"
                            >

                            <input
                                type="hidden"
                                name="status"
                                value="COMPLETED"
                            >

                            <button type="submit">
                                Marcar como concluída
                            </button>

                        </form>

                    </div>

                <?php endif; ?>

            </div>

        <?php endwhile; ?>

    <?php else: ?>

        <p>Nenhuma encomenda encontrada.</p>

    <?php endif; ?>

    <a href="../index.php">Voltar para o início</a>

</body>

</html>

<?php

$stmt->close();

?>