<?php

    session_start();

    if (!isset($_SESSION["id_usuario"]) || $_SESSION["tipo_usuario"] !== "seller") {
        die("Acesso permitido apenas para vendedores.");
    }

    require_once "../config/database.php";

    if ($_SERVER["REQUEST_METHOD"] === "POST") {

        $conn->begin_transaction();

        $order_id = $_POST["order_id"];
        $status = $_POST["status"];

        $status_permitidos = [
            "PENDING",
            "ACCEPTED",
            "REJECTED",
            "COMPLETED",
            "CANCELLED"
        ];

        if (!in_array($status, $status_permitidos)) {
            $conn->rollback();
            die("Status inválido.");
        }

        $seller_id = $_SESSION["id_usuario"];

        $sql = "SELECT status
        FROM orders
        WHERE id_order = ?
        AND seller_id = ?";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            "ii",
            $order_id,
            $seller_id
        );

        $stmt->execute();

        $resultado = $stmt->get_result();

        if ($resultado->num_rows !== 1) {
            $stmt->close();
            $conn->rollback();
            die("Encomenda não encontrada.");
        }

        $encomenda = $resultado->fetch_assoc();
        $status_atual = $encomenda["status"];

        $stmt->close();

        if ($status_atual === "PENDING") {

            $transicoes_permitidas = [
                "ACCEPTED",
                "REJECTED",
                "CANCELLED"
            ];

            if (!in_array($status, $transicoes_permitidas)) {
                $conn->rollback();
                die("Transição de status inválida.");
            }
        }

        if ($status_atual === "PENDING" && $status === "CANCELLED") {

            $sql = "SELECT product_id, quantity
                    FROM order_items
                    WHERE order_id = ?";

            $stmt = $conn->prepare($sql);
            $stmt->bind_param("i", $order_id);
            $stmt->execute();

            $itens = $stmt->get_result();
            $stmt->close();

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
        }

        if ($status_atual === "ACCEPTED") {

            if ($status !== "COMPLETED") {
                $conn->rollback();
                die("Transição de status inválida.");
            }
        }

        if ($status_atual === "REJECTED" || $status_atual === "CANCELLED") {
            $conn->rollback();
            die("Transição de status inválida.");
        }

        $sql = "UPDATE orders
                SET status = ?
                WHERE id_order = ?
                AND seller_id = ?
                AND status <> 'COMPLETED'";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            "sii",
            $status,
            $order_id,
            $seller_id
        );

        if ($stmt->execute()) {

            if ($stmt->affected_rows > 0) {

                $conn->commit();
                header("Location: list.php");
                exit;

            } else {

                $conn->rollback();
                echo "A encomenda não pode ter o status alterado.";
            }

        } else {

            $conn->rollback();
            echo "Erro ao atualizar status: " . $stmt->error;
        }

        $stmt->close();
    }

?>

<form method="POST">

    <input type="number" name="order_id" placeholder="ID da encomenda">

    <select name="status">
        <option value="PENDING">Pendente</option>
        <option value="ACCEPTED">Aceita</option>
        <option value="REJECTED">Recusada</option>
        <option value="COMPLETED">Concluída</option>
        <option value="CANCELLED">Cancelada</option>
    </select>

    <button type="submit">Atualizar status</button>

</form>