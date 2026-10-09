<?php

session_start();

if (!isset($_SESSION["id_usuario"]) || $_SESSION["tipo_usuario"] !== "customer") {
    die("Acesso permitido apenas para clientes.");
}

require_once "../config/database.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $conn->begin_transaction();

    $customer_id = $_SESSION["id_usuario"];
    $product_id = $_POST["product_id"];
    $quantity = $_POST["quantity"];

    if ($quantity <= 0) {
        $conn->rollback();
        echo "Quantidade inválida.";
        exit;
    }

    // 1. Buscar o preço atual do produto
    $sql = "SELECT price, user_id, quantity
            FROM products
            WHERE id_product = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();

    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 1) {

        $produto = $resultado->fetch_assoc();

        $unit_price = $produto["price"];
        $seller_id = $produto["user_id"];
        $estoque = $produto["quantity"];

        if ($quantity > $estoque) {
            $conn->rollback();
            echo "Quantidade solicitada maior que o estoque disponível.";
            exit;
        }

        $stmt->close();

        // 2. Criar a encomenda
        $sql = "INSERT INTO orders
                (customer_id, seller_id, order_date, status)
                VALUES (?, ?, NOW(), 'PENDING')";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $customer_id, $seller_id);

        if ($stmt->execute()) {

            $order_id = $conn->insert_id;

            $stmt->close();

            // 3. Adicionar o produto à encomenda
            $sql = "INSERT INTO order_items
                    (order_id, product_id, quantity, unit_price)
                    VALUES (?, ?, ?, ?)";

            $stmt = $conn->prepare($sql);

            $stmt->bind_param(
                "iiid",
                $order_id,
                $product_id,
                $quantity,
                $unit_price
            );

            if ($stmt->execute()) {

                // 4. Atualizar estoque
                $sql = "UPDATE products
                        SET quantity = quantity - ?
                        WHERE id_product = ?
                        AND quantity >= ?";

                $stmt = $conn->prepare($sql);

                $stmt->bind_param(
                    "iii",
                    $quantity,
                    $product_id,
                    $quantity
                );

                if ($stmt->execute()) {

                    $conn->commit();

                    header("Location: my_orders.php");
                    exit;

                } else {

                    $conn->rollback();
                    echo "Erro ao atualizar estoque: " . $stmt->error;
                }

            } else {

                $conn->rollback();
                echo "Erro ao adicionar produto: " . $stmt->error;
            }

        } else {

            $conn->rollback();
            echo "Erro ao criar encomenda: " . $stmt->error;
        }

        $stmt->close();

    } else {

        $stmt->close();
        $conn->rollback();

        echo "Produto não encontrado.";
    }
}

?>

<form method="POST">

    <input type="number" name="product_id" placeholder="ID do produto">

    <input type="number" name="quantity" placeholder="Quantidade">

    <button type="submit">Criar encomenda</button>

</form>