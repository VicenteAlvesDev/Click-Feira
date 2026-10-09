<?php

session_start();

require_once "../config/database.php";

$sql = "SELECT products.*, users.name AS seller_name
        FROM products
        JOIN users ON products.user_id = users.id_user";

$resultado = $conn->query($sql);

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Produtos - ClickFeira</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .produto {
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .produto h2 {
            margin-top: 0;
        }

        .preco {
            font-size: 20px;
            font-weight: bold;
        }
    </style>
</head>

<body>

    <h1>Produtos disponíveis</h1>

    <?php if ($resultado->num_rows > 0): ?>

        <?php while ($produto = $resultado->fetch_assoc()): ?>

            <div class="produto">

                <h2><?= $produto["name"] ?></h2>

                <p><?= $produto["description"] ?></p>

                <p>
                    <strong>Vendedor:</strong>
                    <?= $produto["seller_name"] ?>
                </p>

                <p>
                    <strong>Quantidade disponível:</strong>
                    <?= $produto["quantity"] ?>
                </p>

                <p class="preco">
                    R$ <?= $produto["price"] ?>
                </p>

                <?php if (isset($_SESSION["tipo_usuario"]) && $_SESSION["tipo_usuario"] === "customer"): ?>

                    <form method="POST" action="../orders/create.php">

                        <input
                            type="hidden"
                            name="product_id"
                            value="<?= $produto["id_product"] ?>"
                        >

                        <input
                            type="number"
                            name="quantity"
                            min="1"
                            max="<?= $produto["quantity"] ?>"
                            placeholder="Quantidade"
                            required
                        >

                        <button type="submit">
                            Encomendar
                        </button>

                    </form>

                <?php endif; ?>

                <hr>

            </div>

        <?php endwhile; ?>

    <?php else: ?>

        <p>Nenhum produto cadastrado.</p>

    <?php endif; ?>

    <a href="../index.php">Voltar para o início</a>

</body>

</html>