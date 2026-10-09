<?php
session_start();
?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <title>ClickFeira Assaré</title>

    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
            margin: 0 auto;
            padding: 40px 20px;
            background-color: #f5f5f5;
        }

        .cabecalho {
            text-align: center;
            margin-bottom: 40px;
        }

        .cabecalho h1 {
            margin-bottom: 10px;
        }

        .cabecalho p {
            color: #666;
        }

        .menu {
            display: grid;
            gap: 15px;
        }

        .opcao {
            display: block;
            padding: 20px;
            background-color: white;
            border: 1px solid #ddd;
            border-radius: 10px;
            text-decoration: none;
            color: #222;
        }

        .opcao:hover {
            border-color: #999;
        }

        .opcao h2 {
            margin: 0 0 8px;
            font-size: 20px;
        }

        .opcao p {
            margin: 0;
            color: #666;
        }

        .sair {
            display: inline-block;
            margin-top: 30px;
            color: #666;
        }
    </style>
</head>

<body>

    <div class="cabecalho">

        <h1>ClickFeira Assaré</h1>

        <p>Conectando vendedores e consumidores da feira.</p>

    </div>

    <?php if (!isset($_SESSION["id_usuario"])): ?>

        <div class="menu">

            <a class="opcao" href="users/login.php">
                <h2>Entrar</h2>
                <p>Acesse sua conta.</p>
            </a>

            <a class="opcao" href="users/register.php">
                <h2>Criar conta</h2>
                <p>Cadastre-se no ClickFeira.</p>
            </a>

            <a class="opcao" href="products/list.php">
                <h2>Ver produtos</h2>
                <p>Confira os produtos disponíveis.</p>
            </a>

        </div>

    <?php else: ?>

        <div class="menu">

            <?php if ($_SESSION["tipo_usuario"] === "customer"): ?>

                <a class="opcao" href="products/list.php">
                    <h2>Ver produtos</h2>
                    <p>Encontre produtos disponíveis na feira.</p>
                </a>

                <a class="opcao" href="orders/my_orders.php">
                    <h2>Minhas encomendas</h2>
                    <p>Acompanhe suas encomendas.</p>
                </a>

            <?php elseif ($_SESSION["tipo_usuario"] === "seller"): ?>

                <a class="opcao" href="products/register.php">
                    <h2>Cadastrar produto</h2>
                    <p>Adicione um novo produto à feira.</p>
                </a>

                <a class="opcao" href="products/list.php">
                    <h2>Ver produtos</h2>
                    <p>Confira os produtos cadastrados.</p>
                </a>

                <a class="opcao" href="orders/list.php">
                    <h2>Encomendas recebidas</h2>
                    <p>Veja e gerencie os pedidos dos clientes.</p>
                </a>

            <?php endif; ?>

            <a class="opcao" href="users/profile.php">
                <h2>Meu perfil</h2>
                <p>Veja suas informações.</p>
            </a>

        </div>

        <a class="sair" href="users/logout.php">Sair</a>

    <?php endif; ?>

</body>

</html>