
<?php

session_start();

if (
    !isset($_SESSION["id_usuario"]) ||
    $_SESSION["tipo_usuario"] !== "customer"
) {
    die("Acesso permitido apenas para clientes.");
}

require_once "../config/database.php";

$customer_id = (int) $_SESSION["id_usuario"];

// Busca a foto de perfil do consumidor.
$sql_foto = "SELECT profile_photo FROM users WHERE id_user = ?";
$stmt_foto = $conn->prepare($sql_foto);
$stmt_foto->bind_param("i", $customer_id);
$stmt_foto->execute();

$dados_usuario = $stmt_foto->get_result()->fetch_assoc();
$foto_perfil = $dados_usuario["profile_photo"] ?? null;

$stmt_foto->close();

$foto_perfil_url = $foto_perfil
    ? "../uploads/profiles/" . rawurlencode(basename($foto_perfil))
    : null;


// Busca as encomendas do consumidor.
$sql = "SELECT
            o.id_order,
            o.order_date,
            o.status,
            u.name AS seller_name,
            p.name AS product_name,
            oi.quantity,
            oi.unit_price
        FROM orders o
        JOIN users u ON o.seller_id = u.id_user
        JOIN order_items oi ON o.id_order = oi.order_id
        JOIN products p ON oi.product_id = p.id_product
        WHERE o.customer_id = ?
        ORDER BY o.order_date DESC, o.id_order DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $customer_id);
$stmt->execute();

$resultado = $stmt->get_result();

$encomendas = [];

while ($item = $resultado->fetch_assoc()) {

    $id = $item["id_order"];

    if (!isset($encomendas[$id])) {

        $encomendas[$id] = [
            "id_order" => $id,
            "seller_name" => $item["seller_name"],
            "order_date" => $item["order_date"],
            "status" => $item["status"],
            "produtos" => [],
            "total" => 0
        ];
    }

    $subtotal = $item["quantity"] * $item["unit_price"];

    $encomendas[$id]["produtos"][] = [
        "name" => $item["product_name"],
        "quantity" => $item["quantity"],
        "unit_price" => $item["unit_price"],
        "subtotal" => $subtotal
    ];

    $encomendas[$id]["total"] += $subtotal;
}


function traduzirStatus($status) {

    $statusTraduzidos = [
        "PENDING" => "Aguardando confirmação",
        "ACCEPTED" => "Pedido aceito",
        "REJECTED" => "Pedido recusado",
        "COMPLETED" => "Concluído",
        "CANCELLED" => "Cancelado"
    ];

    return $statusTraduzidos[$status] ?? $status;
}


function escapar($valor) {
    return htmlspecialchars((string) ($valor ?? ""), ENT_QUOTES, "UTF-8");
}


function classeStatus($status) {

    $classes = [
        "PENDING" => "status-pendente",
        "ACCEPTED" => "status-aceito",
        "REJECTED" => "status-recusado",
        "COMPLETED" => "status-concluido",
        "CANCELLED" => "status-cancelado"
    ];

    return $classes[$status] ?? "";
}


$total_encomendas = count($encomendas);
$pendentes = 0;
$concluidas = 0;

foreach ($encomendas as $encomenda) {

    if ($encomenda["status"] === "PENDING") {
        $pendentes++;
    }

    if ($encomenda["status"] === "COMPLETED") {
        $concluidas++;
    }
}

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Minhas encomendas - ClickFeira Assaré</title>

    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <style>

        :root {
            --verde: #79bf00;
            --verde-escuro: #174c32;
            --verde-claro: #ccff66;

            --amarelo: #f2c14e;
            --laranja: #e58b55;
            --azul-verde: #4f8f83;
            --creme: #fff3d9;

            --fundo: #f7f5ef;
            --branco: #ffffff;
            --texto: #29332d;
            --texto-suave: #747b72;
            --borda: #e8e4d9;
            --sombra: 0 4px 18px rgba(48, 55, 39, 0.07);
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: "Kanit", Arial, sans-serif;
            background: var(--fundo);
            color: var(--texto);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        button,
        input {
            font: inherit;
        }

        h1, h2, h3 {
            font-family: Impact, Haettenschweiler, "Arial Narrow Bold", sans-serif;
            font-weight: 400;
            letter-spacing: 0.35px;
        }

        /* CABEÇALHO */

        .topo {
            background: var(--branco);
            border-bottom: 1px solid var(--borda);
        }

        .topo-principal {
            max-width: none;
            margin: auto;
            padding: 18px max(35px, calc((100% - 1280px) / 2));
            display: flex;
            align-items: center;
            gap: 35px;

            background:
                linear-gradient(
                    90deg,
                    rgba(6, 92, 0, 0.35),
                    rgba(6, 92, 0, 0.15)
                ),
                url("../assets/img/banner-principal.png") center 48% / cover no-repeat;
        }

        .logo {
            display: flex;
            align-items: center;
            flex-shrink: 0;
        }

        .logo img {
            display: block;
            width: 205px;
            max-width: 100%;
            height: auto;
            object-fit: contain;
        }

        .pesquisa {
            flex: 1;
            display: flex;
            height: 46px;
            border: 1px solid var(--borda);
            border-radius: 9px;
            overflow: hidden;
            background: #fffdf8;
        }

        .pesquisa input {
            flex: 1;
            min-width: 0;
            border: none;
            outline: none;
            padding: 0 16px;
            background: transparent;
            font-family: Arial, sans-serif;
            font-size: 14px;
        }

        .pesquisa button {
            width: 55px;
            border: none;
            background: var(--verde);
            color: white;
            font-size: 19px;
            cursor: pointer;
            transition: 0.2s;
        }

        .pesquisa button:hover {
            background: var(--verde-escuro);
        }

        .acoes-topo {
            display: flex;
            align-items: center;
            gap: 20px;
            flex-shrink: 0;
        }

        .foto-conta {
            width: 43px;
            height: 43px;
            border-radius: 50%;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            background: var(--creme);
            border: 2px solid var(--borda);
            color: var(--verde-escuro);
            font-size: 19px;
            flex-shrink: 0;
            transition: 0.2s;
        }

        .foto-conta:hover {
            border-color: var(--verde);
            transform: scale(1.05);
        }

        .foto-conta img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        /* NAVEGAÇÃO */

        .navegacao {
            border-top: 1px solid var(--borda);
        }

        .navegacao-conteudo {
            max-width: 1350px;
            margin: auto;
            padding: 0 35px;
            min-height: 48px;
            display: flex;
            align-items: center;
            gap: 30px;
        }

        .navegacao a {
            font-size: 13px;
            color: var(--texto-suave);
            font-weight: 600;
            white-space: nowrap;
            transition: 0.2s;
        }

        .navegacao a:hover,
        .navegacao .ativo {
            color: var(--verde);
        }

        .sair {
            color: #c0392b !important;
        }

        /* CONTEÚDO */

        .container {
            max-width: 1350px;
            width: 100%;
            flex: 1;
            margin: 35px auto;
            padding: 0 35px;
        }

        .cabecalho-pagina {
            margin-bottom: 28px;
        }

        .cabecalho-pagina h1 {
            font-size: 28px;
            margin-bottom: 8px;
        }

        .cabecalho-pagina p {
            color: var(--texto-suave);
            font-size: 14px;
            line-height: 1.5;
        }

        /* RESUMO */

        .resumo-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 18px;
            margin-bottom: 32px;
        }

        .resumo-card {
            background: var(--branco);
            border: 1px solid var(--borda);
            border-radius: 12px;
            padding: 21px;
            display: flex;
            align-items: center;
            gap: 15px;
            box-shadow: var(--sombra);
            transition: 0.22s;
        }

        .resumo-card:hover {
            border-color: #d5dfd1;
        }

        .resumo-icone {
            width: 48px;
            height: 48px;
            min-width: 48px;
            border-radius: 12px;
            display: grid;
            place-items: center;
            font-size: 21px;
            background: rgba(121, 191, 0, 0.15);
            color: var(--verde-escuro);
        }

        .resumo-icone.amarelo {
            background: rgba(242, 193, 78, 0.22);
            color: #8a6410;
        }

        .resumo-icone.azul {
            background: rgba(79, 143, 131, 0.17);
            color: var(--azul-verde);
        }

        .resumo-numero {
            font-size: 27px;
            font-weight: bold;
            color: var(--verde-escuro);
            line-height: 1;
        }

        .resumo-legenda {
            font-size: 12px;
            color: var(--texto-suave);
            margin-top: 6px;
        }

        /* LISTAGEM */

        .secao-titulo {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .secao-titulo h2 {
            font-size: 21px;
        }

        .secao-titulo span {
            font-size: 13px;
            color: var(--texto-suave);
        }

        .lista-encomendas {
            display: flex;
            flex-direction: column;
            gap: 17px;
        }

        .encomenda {
            background: var(--branco);
            border: 1px solid var(--borda);
            border-radius: 13px;
            padding: 23px;
            box-shadow: var(--sombra);
            transition: 0.22s;
        }

        .encomenda:hover {
            border-color: #d5dfd1;
        }

        .encomenda-topo {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            padding-bottom: 17px;
            border-bottom: 1px solid var(--borda);
        }

        .numero {
            font-family: Impact, Haettenschweiler, "Arial Narrow Bold", sans-serif;
            font-size: 18px;
            letter-spacing: 0.35px;
        }

        .data {
            margin-top: 5px;
            font-size: 12px;
            color: var(--texto-suave);
        }

        /* STATUS */

        .status {
            display: inline-block;
            padding: 7px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: bold;
            white-space: nowrap;
        }

        .status-pendente {
            background: rgba(242, 193, 78, 0.22);
            color: #8a6410;
        }

        .status-aceito {
            background: rgba(79, 143, 131, 0.17);
            color: var(--azul-verde);
        }

        .status-recusado,
        .status-cancelado {
            background: #fff0ef;
            color: #b12c2c;
        }

        .status-concluido {
            background: rgba(121, 191, 0, 0.15);
            color: var(--verde-escuro);
        }

        /* VENDEDOR */

        .vendedor {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 18px 0;
        }

        .vendedor-icone {
            width: 39px;
            height: 39px;
            min-width: 39px;
            border-radius: 50%;
            background: rgba(121, 191, 0, 0.15);
            color: var(--verde-escuro);
            display: grid;
            place-items: center;
            font-size: 17px;
        }

        .vendedor-label {
            font-size: 11px;
            color: var(--texto-suave);
            margin-bottom: 4px;
        }

        .vendedor-nome {
            font-size: 14px;
            font-weight: 600;
            overflow-wrap: anywhere;
        }

        /* PRODUTOS */

        .produtos-titulo {
            font-size: 17px;
            margin-bottom: 5px;
        }

        .produto {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            padding: 15px 0;
            border-bottom: 1px solid var(--borda);
        }

        .produto:last-child {
            border-bottom: none;
        }

        .produto-nome {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 6px;
            overflow-wrap: anywhere;
        }

        .produto-detalhes {
            font-size: 12px;
            color: var(--texto-suave);
        }

        .produto-subtotal {
            font-size: 13px;
            font-weight: 600;
            white-space: nowrap;
        }

        /* TOTAL */

        .total-area {
            display: flex;
            justify-content: flex-end;
            align-items: center;
            gap: 20px;
            padding-top: 17px;
            margin-top: 5px;
            border-top: 1px solid var(--borda);
        }

        .total-label {
            font-size: 13px;
            color: var(--texto-suave);
        }

        .total-valor {
            color: var(--verde-escuro);
            font-size: 21px;
            font-weight: bold;
        }

        /* AÇÕES */

        .acoes {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
            margin-top: 19px;
            padding-top: 17px;
            border-top: 1px solid var(--borda);
        }

        .mensagem-status {
            font-size: 12px;
            color: var(--texto-suave);
        }

        .botao-cancelar {
            background: #fff0ef;
            color: #b12c2c;
            border: none;
            border-radius: 7px;
            padding: 11px 16px;
            font-size: 12px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.2s;
        }

        .botao-cancelar:hover {
            background: #ffe0de;
        }

        /* ESTADO VAZIO */

        .vazio {
            background: var(--branco);
            border: 1px solid var(--borda);
            border-radius: 13px;
            padding: 65px 20px;
            text-align: center;
            box-shadow: var(--sombra);
        }

        .vazio-icone {
            font-size: 43px;
            color: var(--verde);
            margin-bottom: 17px;
        }

        .vazio h3 {
            font-size: 20px;
            margin-bottom: 9px;
        }

        .vazio p {
            color: var(--texto-suave);
            font-size: 13px;
            line-height: 1.5;
        }

        .botao-catalogo {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 22px;
            padding: 12px 19px;
            background: var(--verde);
            color: white;
            border-radius: 7px;
            font-size: 13px;
            font-weight: bold;
            transition: 0.2s;
        }

        .botao-catalogo:hover {
            background: var(--verde-escuro);
        }

        .voltar {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 30px;
            color: var(--verde-escuro);
            font-size: 13px;
            font-weight: 600;
            transition: 0.2s;
        }

        .voltar:hover {
            color: var(--verde);
        }

        /* RODAPÉ */

        footer {
            margin-top: 0;
            padding: 30px 20px;
            background: var(--branco);
            border-top: 1px solid var(--borda);
            text-align: center;
            color: var(--texto-suave);
            font-size: 12px;
        }

        /* RESPONSIVIDADE */

        @media (max-width: 850px) {

            .topo-principal {
                flex-wrap: wrap;
                gap: 15px;
                padding: 17px 20px;
            }

            .pesquisa {
                order: 3;
                flex-basis: 100%;
            }

            .acoes-topo {
                margin-left: auto;
            }

            .navegacao-conteudo {
                padding: 15px 20px;
                flex-wrap: wrap;
                gap: 17px;
            }

            .container {
                padding: 0 20px;
            }

            .resumo-grid {
                gap: 10px;
            }

            .resumo-card {
                padding: 15px;
            }

        }

        @media (max-width: 550px) {

            .topo-principal {
                display: grid;
                grid-template-columns: minmax(0, 1fr) auto;
                grid-template-areas:
                    "logo acoes"
                    "pesquisa pesquisa";
                align-items: center;
                gap: 14px;
                padding: 14px 16px;
            }

            .logo {
                grid-area: logo;
                min-width: 0;
            }

            .logo img {
                width: 155px;
                max-width: 100%;
            }

            .pesquisa {
                grid-area: pesquisa;
                order: 0;
                flex-basis: auto;
                width: 100%;
                height: 44px;
            }

            .pesquisa input {
                font-size: 13px;
                padding: 0 12px;
            }

            .pesquisa button {
                width: 46px;
            }

            .acoes-topo {
                grid-area: acoes;
                margin-left: 0;
                gap: 9px;
                justify-self: end;
            }

            .foto-conta {
                width: 40px;
                height: 40px;
            }

            /* NAVEGAÇÃO MOBILE */

            .navegacao-conteudo {
                display: flex;
                flex-wrap: nowrap;
                overflow-x: auto;
                overflow-y: hidden;
                gap: 21px;
                min-height: 48px;
                padding: 13px 16px;
                white-space: nowrap;
                scrollbar-width: none;
                -webkit-overflow-scrolling: touch;
            }

            .navegacao-conteudo::-webkit-scrollbar {
                display: none;
            }

            .navegacao a {
                flex-shrink: 0;
                font-size: 12px;
            }

            .container {
                margin-top: 25px;
                padding: 0 16px;
            }

            .cabecalho-pagina h1 {
                font-size: 24px;
            }

            .cabecalho-pagina p {
                font-size: 13px;
            }

            .resumo-grid {
                grid-template-columns: 1fr;
                gap: 12px;
            }

            .resumo-card {
                padding: 17px;
            }

            .secao-titulo {
                align-items: flex-start;
                flex-direction: column;
                gap: 7px;
            }

            .secao-titulo h2 {
                font-size: 18px;
            }

            .encomenda {
                padding: 17px;
            }

            .encomenda-topo {
                align-items: flex-start;
                flex-direction: column;
                gap: 12px;
            }

            .produto {
                align-items: flex-start;
                gap: 10px;
            }

            .produto-subtotal {
                font-size: 12px;
            }

            .total-area {
                justify-content: space-between;
            }

            .acoes {
                align-items: stretch;
                flex-direction: column;
            }

            .acoes form {
                width: 100%;
            }

            .botao-cancelar {
                width: 100%;
            }

            .vazio {
                padding: 50px 17px;
            }

            .voltar {
                margin-top: 24px;
            }

        }

    </style>

</head>

<body>

    <!-- CABEÇALHO -->

    <header class="topo">

        <div class="topo-principal">

            <a href="../index.php" class="logo" aria-label="ClickFeira Assaré - Página inicial">

                <img
                    src="../assets/img/logo-clickfeira.png"
                    alt="ClickFeira Assaré"
                >

            </a>

            <form action="../products/list.php" method="GET" class="pesquisa">

                <input
                    type="text"
                    name="pesquisa"
                    placeholder="O que você está procurando?"
                >

                <button type="submit" aria-label="Pesquisar">
                    <i class="fi fi-rr-search"></i>
                </button>

            </form>

            <div class="acoes-topo">

                <a
                    href="../users/profile.php"
                    class="foto-conta"
                    title="Meu perfil"
                    aria-label="Meu perfil"
                >

                    <?php if ($foto_perfil_url): ?>

                        <img
                            src="<?= escapar($foto_perfil_url) ?>"
                            alt="Foto de perfil"
                        >

                    <?php else: ?>

                        <i class="fi fi-rr-user"></i>

                    <?php endif; ?>

                </a>

            </div>

        </div>

        <nav class="navegacao">

            <div class="navegacao-conteudo">

                <a href="../index.php">Início</a>

                <a href="../products/list.php">Todos os produtos</a>

                <a href="my_orders.php" class="ativo">Minhas encomendas</a>

                <a href="../users/logout.php" class="sair">Sair</a>

            </div>

        </nav>

    </header>


    <main class="container">

        <!-- TÍTULO -->

        <section class="cabecalho-pagina">

            <h1>Minhas encomendas</h1>

            <p>
                Acompanhe seus pedidos e veja atualizações dos vendedores.
            </p>

        </section>


        <!-- RESUMO -->

        <section class="resumo-grid">

            <div class="resumo-card">

                <div class="resumo-icone">
                    <i class="fi fi-rr-box"></i>
                </div>

                <div>

                    <div class="resumo-numero">
                        <?= $total_encomendas ?>
                    </div>

                    <div class="resumo-legenda">
                        Total de encomendas
                    </div>

                </div>

            </div>


            <div class="resumo-card">

                <div class="resumo-icone amarelo">
                    <i class="fi fi-rr-clock"></i>
                </div>

                <div>

                    <div class="resumo-numero">
                        <?= $pendentes ?>
                    </div>

                    <div class="resumo-legenda">
                        Aguardando confirmação
                    </div>

                </div>

            </div>


            <div class="resumo-card">

                <div class="resumo-icone azul">
                    <i class="fi fi-rr-check-circle"></i>
                </div>

                <div>

                    <div class="resumo-numero">
                        <?= $concluidas ?>
                    </div>

                    <div class="resumo-legenda">
                        Encomendas concluídas
                    </div>

                </div>

            </div>

        </section>


        <!-- LISTAGEM -->

        <section>

            <div class="secao-titulo">

                <h2>Histórico de pedidos</h2>

                <span>
                    <?= $total_encomendas ?>
                    <?= $total_encomendas === 1 ? "encomenda" : "encomendas" ?>
                </span>

            </div>


            <?php if (!empty($encomendas)): ?>

                <div class="lista-encomendas">

                    <?php foreach ($encomendas as $encomenda): ?>

                        <article class="encomenda">

                            <!-- CABEÇALHO -->

                            <div class="encomenda-topo">

                                <div>

                                    <div class="numero">
                                        Encomenda #<?= escapar($encomenda["id_order"]) ?>
                                    </div>

                                    <div class="data">
                                        Realizada em
                                        <?= date("d/m/Y às H:i", strtotime($encomenda["order_date"])) ?>
                                    </div>

                                </div>

                                <span class="status <?= escapar(classeStatus($encomenda["status"])) ?>">
                                    <?= escapar(traduzirStatus($encomenda["status"])) ?>
                                </span>

                            </div>


                            <!-- VENDEDOR -->

                            <div class="vendedor">

                                <div class="vendedor-icone">
                                    <i class="fi fi-rr-shop"></i>
                                </div>

                                <div>

                                    <div class="vendedor-label">
                                        Vendedor responsável
                                    </div>

                                    <div class="vendedor-nome">
                                        <?= escapar($encomenda["seller_name"]) ?>
                                    </div>

                                </div>

                            </div>


                            <!-- PRODUTOS -->

                            <h3 class="produtos-titulo">
                                Produtos da encomenda
                            </h3>

                            <?php foreach ($encomenda["produtos"] as $produto): ?>

                                <div class="produto">

                                    <div>

                                        <div class="produto-nome">
                                            <?= escapar($produto["name"]) ?>
                                        </div>

                                        <div class="produto-detalhes">

                                            <?= escapar($produto["quantity"]) ?> unidade(s)

                                            ×

                                            R$ <?= number_format($produto["unit_price"], 2, ",", ".") ?>

                                        </div>

                                    </div>

                                    <div class="produto-subtotal">
                                        R$ <?= number_format($produto["subtotal"], 2, ",", ".") ?>
                                    </div>

                                </div>

                            <?php endforeach; ?>


                            <!-- TOTAL -->

                            <div class="total-area">

                                <div class="total-label">
                                    Valor total
                                </div>

                                <div class="total-valor">
                                    R$ <?= number_format($encomenda["total"], 2, ",", ".") ?>
                                </div>

                            </div>


                            <!-- AÇÕES -->

                            <?php if ($encomenda["status"] === "PENDING"): ?>

                                <div class="acoes">

                                    <span class="mensagem-status">
                                        O vendedor ainda precisa confirmar seu pedido.
                                    </span>

                                    <form
                                        method="POST"
                                        action="cancel.php"
                                        onsubmit="return confirm('Deseja realmente cancelar esta encomenda?');"
                                    >

                                        <input
                                            type="hidden"
                                            name="order_id"
                                            value="<?= escapar($encomenda["id_order"]) ?>"
                                        >

                                        <button type="submit" class="botao-cancelar">
                                            <i class="fi fi-rr-cross-circle"></i>
                                            Cancelar encomenda
                                        </button>

                                    </form>

                                </div>

                            <?php elseif ($encomenda["status"] === "ACCEPTED"): ?>

                                <div class="acoes">

                                    <span class="mensagem-status">
                                        O vendedor aceitou seu pedido!
                                    </span>

                                </div>

                            <?php elseif ($encomenda["status"] === "COMPLETED"): ?>

                                <div class="acoes">

                                    <span class="mensagem-status">
                                        Sua encomenda foi concluída.
                                    </span>

                                </div>

                            <?php elseif ($encomenda["status"] === "REJECTED"): ?>

                                <div class="acoes">

                                    <span class="mensagem-status">
                                        O vendedor recusou esta encomenda.
                                    </span>

                                </div>

                            <?php elseif ($encomenda["status"] === "CANCELLED"): ?>

                                <div class="acoes">

                                    <span class="mensagem-status">
                                        Esta encomenda foi cancelada.
                                    </span>

                                </div>

                            <?php endif; ?>

                        </article>

                    <?php endforeach; ?>

                </div>

            <?php else: ?>

                <div class="vazio">

                    <div class="vazio-icone">
                        <i class="fi fi-rr-shopping-bag"></i>
                    </div>

                    <h3>Você ainda não fez nenhuma encomenda</h3>

                    <p>
                        Explore os produtos da feira e encontre algo que você goste.
                    </p>

                    <a href="../products/list.php" class="botao-catalogo">
                        <i class="fi fi-rr-shopping-bag"></i>
                        Explorar produtos
                    </a>

                </div>

            <?php endif; ?>

        </section>

    </main>


    <footer>

        <p>
            © <?= date("Y") ?> ClickFeira Assaré.
            Conectando vendedores e consumidores.
        </p>

    </footer>

</body>

</html>

<?php
$stmt->close();
?>
