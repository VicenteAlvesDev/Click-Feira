<?php

session_start();

if (!isset($_SESSION["id_usuario"])) {
    die("Você precisa estar logado para acessar esta página.");
}

require_once "../config/database.php";

$user_id = $_SESSION["id_usuario"];

$sql = "SELECT name, email, phone, user_type
        FROM users
        WHERE id_user = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();

$resultado = $stmt->get_result();

if ($resultado->num_rows !== 1) {
    die("Usuário não encontrado.");
}

$usuario = $resultado->fetch_assoc();

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>Meu perfil - ClickFeira</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 700px;
            margin: 40px auto;
            padding: 0 20px;
        }

        .perfil {
            border: 1px solid #ddd;
            border-radius: 10px;
            padding: 25px;
        }

        .informacao {
            margin-bottom: 15px;
        }

        .informacao strong {
            display: block;
            margin-bottom: 5px;
        }

        a {
            display: inline-block;
            margin-top: 20px;
        }
    </style>
</head>

<body>

    <h1>Meu perfil</h1>

    <div class="perfil">

        <div class="informacao">
            <strong>Nome</strong>
            <?= $usuario["name"] ?>
        </div>

        <div class="informacao">
            <strong>E-mail</strong>
            <?= $usuario["email"] ?>
        </div>

        <div class="informacao">
            <strong>Telefone</strong>
            <?= $usuario["phone"] ?>
        </div>

        <div class="informacao">
            <strong>Tipo de conta</strong>
            <?= $usuario["user_type"] === "seller" ? "Vendedor" : "Cliente" ?>
        </div>

    </div>

    <a href="../index.php">Voltar para o início</a>

</body>

</html>

<?php

$stmt->close();

?>