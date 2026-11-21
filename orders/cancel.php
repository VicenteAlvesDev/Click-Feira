
<?php

session_start();

if (!isset($_SESSION["id_usuario"]) || $_SESSION["tipo_usuario"] !== "customer") {
    die("Acesso permitido apenas para clientes.");
}

require_once "../config/database.php";

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: my_orders.php");
    exit;
}

$customer_id = (int) $_SESSION["id_usuario"];
$order_id = filter_var($_POST["order_id"] ?? null, FILTER_VALIDATE_INT);

if (!$order_id || $order_id < 1) {
    die("ID de encomenda inválido.");
}

$conn->begin_transaction();

try {

    // Verifica se a encomenda pertence ao cliente e está pendente.
    $sql = "SELECT id_order
            FROM orders
            WHERE id_order = ?
            AND customer_id = ?
            AND status = 'PENDING'
            FOR UPDATE";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $order_id, $customer_id);
    $stmt->execute();

    $resultado = $stmt->get_result();

    if ($resultado->num_rows !== 1) {
        $stmt->close();
        throw new Exception("Encomenda não encontrada ou não pode ser cancelada.");
    }

    $stmt->close();

    // Recupera os produtos da encomenda.
    $sql = "SELECT product_id, quantity
            FROM order_items
            WHERE order_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $order_id);
    $stmt->execute();

    $itens = $stmt->get_result();

    // Devolve as quantidades ao estoque.
    $sqlUpdate = "UPDATE products
                  SET quantity = quantity + ?
                  WHERE id_product = ?";

    $stmtUpdate = $conn->prepare($sqlUpdate);

    while ($item = $itens->fetch_assoc()) {

        $quantidade = (int) $item["quantity"];
        $produto_id = (int) $item["product_id"];

        $stmtUpdate->bind_param(
            "ii",
            $quantidade,
            $produto_id
        );

        if (!$stmtUpdate->execute()) {
            throw new Exception("Erro ao devolver produto ao estoque.");
        }
    }

    $stmt->close();
    $stmtUpdate->close();

    // Atualiza o status da encomenda.
    $sql = "UPDATE orders
            SET status = 'CANCELLED'
            WHERE id_order = ?
            AND customer_id = ?
            AND status = 'PENDING'";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("ii", $order_id, $customer_id);

    if (!$stmt->execute() || $stmt->affected_rows !== 1) {
        $stmt->close();
        throw new Exception("Erro ao cancelar a encomenda.");
    }

    $stmt->close();

    $conn->commit();

    // Volta para a página de encomendas.
    header("Location: my_orders.php");
    exit;

} catch (Exception $e) {

    $conn->rollback();

    die($e->getMessage());
}

?>
