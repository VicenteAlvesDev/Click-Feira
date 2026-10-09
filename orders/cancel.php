<?php

    session_start();

    if (!isset($_SESSION["id_usuario"]) || $_SESSION["tipo_usuario"] !== "customer") {
        die("Acesso permitido apenas para clientes.");
    }

    require_once "../config/database.php";

    if ($_SERVER["REQUEST_METHOD"] === "POST") {

        $customer_id = $_SESSION["id_usuario"];
        $order_id = $_POST["order_id"];

        $sql = "SELECT id_order
                FROM orders
                WHERE id_order = ?
                AND customer_id = ?
                AND status = 'PENDING'";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            "ii",
            $order_id,
            $customer_id
        );

        $stmt->execute();

        $resultado = $stmt->get_result();

        if ($resultado->num_rows !== 1) {
            $stmt->close();
            die("Encomenda não encontrada ou não pode ser cancelada.");
        }

        $stmt->close();

        $conn->begin_transaction();

        $sql = "SELECT product_id, quantity
            FROM order_items
            WHERE order_id = ?";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param("i", $order_id);

        $stmt->execute();

        $itens = $stmt->get_result();

        while ($item = $itens->fetch_assoc()) {

            $sql = "UPDATE products
                    SET quantity = quantity + ?
                    WHERE id_product = ?";

            $stmt = $conn->prepare($sql);

            $stmt->bind_param(
                "ii",
                $item["quantity"],
                $item["product_id"]
            );

            if (!$stmt->execute()) {
                $stmt->close();
                $conn->rollback();
                die("Erro ao devolver produto ao estoque.");
            }

            $stmt->close();
        }

        $sql = "UPDATE orders
        SET status = 'CANCELLED'
        WHERE id_order = ?";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param("i", $order_id);

        if (!$stmt->execute()) {
            $stmt->close();
            $conn->rollback();
            die("Erro ao cancelar a encomenda.");
        }

        $stmt->close();

        $conn->commit();

        echo "Encomenda cancelada com sucesso!";
    }
?>

<form method="POST">

    <input
        type="number"
        name="order_id"
        placeholder="ID da encomenda"
        required
    >

    <button type="submit">Cancelar encomenda</button>

</form>