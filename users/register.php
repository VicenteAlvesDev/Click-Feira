<?php

require_once "../config/database.php";

$erro = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $nome = $_POST["name"];
    $email = $_POST["email"];
    $senha = password_hash($_POST["password"], PASSWORD_DEFAULT);
    $telefone = $_POST["phone"];
    $tipo = $_POST["user_type"];

    // Verificar se o tipo de usuário é válido
    if ($tipo !== "customer" && $tipo !== "seller") {

        $erro = "Tipo de usuário inválido.";

    } else {

        $sql = "INSERT INTO users (name, email, password, phone, user_type)
                VALUES (?, ?, ?, ?, ?)";

        $stmt = $conn->prepare($sql);

        $stmt->bind_param(
            "sssss",
            $nome,
            $email,
            $senha,
            $telefone,
            $tipo
        );

        if ($stmt->execute()) {

            header("Location: login.php");
            exit;

        } else {

            $erro = "Erro ao cadastrar usuário: " . $stmt->error;

        }

        $stmt->close();
    }
}

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Criar conta - ClickFeira</title>

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
        .campo select {
            width: 100%;
            box-sizing: border-box;
            padding: 10px;
            border: 1px solid #ccc;
            border-radius: 6px;
        }

        button {
            padding: 10px 15px;
            cursor: pointer;
        }

        .erro {
            margin-bottom: 15px;
        }

        .links {
            margin-top: 20px;
        }

        .links a {
            display: inline-block;
            margin-right: 15px;
        }

    </style>

</head>

<body>

    <h1>Criar conta</h1>

    <div class="formulario">

        <?php if ($erro !== ""): ?>

            <p class="erro">
                <?= $erro ?>
            </p>

        <?php endif; ?>

        <form method="POST">

            <div class="campo">

                <label for="name">
                    Nome
                </label>

                <input
                    type="text"
                    id="name"
                    name="name"
                    placeholder="Nome"
                    required
                >

            </div>

            <div class="campo">

                <label for="email">
                    E-mail
                </label>

                <input
                    type="email"
                    id="email"
                    name="email"
                    placeholder="E-mail"
                    required
                >

            </div>

            <div class="campo">

                <label for="password">
                    Senha
                </label>

                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Senha"
                    required
                >

            </div>

            <div class="campo">

                <label for="phone">
                    Telefone
                </label>

                <input
                    type="text"
                    id="phone"
                    name="phone"
                    placeholder="Telefone"
                    required
                >

            </div>

            <div class="campo">

                <label for="user_type">
                    Tipo de conta
                </label>

                <select
                    id="user_type"
                    name="user_type"
                    required
                >

                    <option value="customer">
                        Cliente
                    </option>

                    <option value="seller">
                        Vendedor
                    </option>

                </select>

            </div>

            <button type="submit">
                Cadastrar
            </button>

        </form>

    </div>

    <div class="links">

        <a href="login.php">
            Já tenho uma conta
        </a>

        <a href="../index.php">
            Voltar para o início
        </a>

    </div>

</body>

</html>