<?php

session_start();

if (!isset($_SESSION["id_usuario"]) || $_SESSION["tipo_usuario"] !== "seller") {
    die("Acesso permitido apenas para vendedores.");
}

require_once "../config/database.php";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nome = $_POST["name"];
    $descricao = $_POST["description"];
    $preco = $_POST["price"];
    $quantidade = $_POST["quantity"];

    $user_id = $_SESSION["id_usuario"];

    $sql = "INSERT INTO products (user_id, name, description, quantity, price)
            VALUES (?, ?, ?, ?, ?)";

    $stmt = $conn->prepare($sql);

    $stmt->bind_param(
        "issid",
        $user_id,
        $nome,
        $descricao,
        $quantidade,
        $preco
    );

    if ($stmt->execute()) {

        header("Location: list.php");
        exit;

    } else {

        echo "Erro ao cadastrar produto: " . $stmt->error;
    }

    $stmt->close();
}

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Cadastrar produto - ClickFeira</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 600px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .formulario {
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 25px;
        }

        .campo {
            margin-bottom: 15px;
        }

        .campo label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }

        .campo input,
        .campo textarea {
            width: 100%;
            box-sizing: border-box;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
        }

        .campo textarea {
            height: 100px;
            resize: vertical;
        }

        button {
            padding: 10px 15px;
            cursor: pointer;
        }

        a {
            display: inline-block;
            margin-top: 20px;
        }
    </style>
</head>

<body>

    <h1>Cadastrar produto</h1>

    <div class="formulario">

        <form method="POST">

            <div class="campo">
                <label for="name">Nome do produto</label>
                <input
                    type="text"
                    id="name"
                    name="name"
                    required
                >
            </div>

            <div class="campo">
                <label for="description">Descrição</label>
                <textarea
                    id="description"
                    name="description"
                    required
                ></textarea>
            </div>

            <div class="campo">
                <label for="price">Preço</label>
                <input
                    type="number"
                    id="price"
                    name="price"
                    step="0.01"
                    min="0"
                    required
                >
            </div>

            <div class="campo">
                <label for="quantity">Quantidade</label>
                <input
                    type="number"
                    id="quantity"
                    name="quantity"
                    min="1"
                    required
                >
            </div>

            <button type="submit">
                Cadastrar produto
            </button>

        </form>

    </div>

    <a href="../index.php">Voltar para o início</a>

</body>

</html>