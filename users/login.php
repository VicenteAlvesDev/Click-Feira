<?php

session_start();

require_once "../config/database.php";

$erro = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = $_POST["email"];
    $senha = $_POST["password"];

    $sql = "SELECT * FROM users WHERE email = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();

    $resultado = $stmt->get_result();

    if ($resultado->num_rows === 1) {

        $usuario = $resultado->fetch_assoc();

        if (password_verify($senha, $usuario["password"])) {

            $_SESSION["id_usuario"] = $usuario["id_user"];
            $_SESSION["tipo_usuario"] = $usuario["user_type"];

            header("Location: ../index.php");
            exit;

        } else {

            $erro = "Senha incorreta!";

        }

    } else {

        $erro = "Usuário não encontrado!";

    }

    $stmt->close();
}

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Entrar - ClickFeira</title>

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

        .campo input {
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

    <h1>Entrar</h1>

    <div class="formulario">

        <?php if ($erro !== ""): ?>

            <p class="erro">
                <?= $erro ?>
            </p>

        <?php endif; ?>

        <form method="POST">

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

            <button type="submit">
                Entrar
            </button>

        </form>

    </div>

    <div class="links">

        <a href="register.php">
            Criar conta
        </a>

        <a href="../index.php">
            Voltar para o início
        </a>

    </div>

</body>

</html>