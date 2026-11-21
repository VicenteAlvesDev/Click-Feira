<?php

session_start();

require_once "../config/database.php";

if (
    !isset($_SESSION["id_usuario"]) ||
    $_SESSION["tipo_usuario"] !== "seller"
) {
    die("Acesso permitido apenas para vendedores.");
}

$seller_id = (int) $_SESSION["id_usuario"];

// Busca a foto de perfil do vendedor.
$sql_foto = "SELECT profile_photo FROM users WHERE id_user = ?";
$stmt_foto = $conn->prepare($sql_foto);
$stmt_foto->bind_param("i", $seller_id);
$stmt_foto->execute();

$dados_usuario = $stmt_foto->get_result()->fetch_assoc();
$foto_perfil = $dados_usuario["profile_photo"] ?? null;

$stmt_foto->close();

$foto_perfil_url = $foto_perfil
    ? "../uploads/profiles/" . rawurlencode(basename($foto_perfil))
    : null;

// Busca as encomendas recebidas.
$sql = "SELECT
            o.id_order,
            u.name AS customer_name,
            p.name AS product_name,
            oi.quantity,
            oi.unit_price,
            o.order_date,
            o.status
        FROM orders o
        JOIN users u ON o.customer_id = u.id_user
        JOIN order_items oi ON o.id_order = oi.order_id
        JOIN products p ON oi.product_id = p.id_product
        WHERE o.seller_id = ?
        ORDER BY o.order_date DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $seller_id);
$stmt->execute();

$resultado = $stmt->get_result();

$total_encomendas = $resultado->num_rows;

function escapar($valor) {
    return htmlspecialchars((string) ($valor ?? ""), ENT_QUOTES, "UTF-8");
}

function nomeStatus($status) {
    $nomes = [
        "PENDING" => "Pendente",
        "ACCEPTED" => "Aceita",
        "REJECTED" => "Recusada",
        "COMPLETED" => "Concluída"
    ];

    return $nomes[$status] ?? $status;
}

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Encomendas recebidas - ClickFeira Assaré</title>

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

        .resumo {
            background: var(--branco);
            border: 1px solid var(--borda);
            border-radius: 12px;
            padding: 22px;
            display: flex;
            align-items: center;
            gap: 17px;
            margin-bottom: 30px;
            box-shadow: var(--sombra);
        }

        .resumo-icone {
            width: 52px;
            height: 52px;
            min-width: 52px;
            border-radius: 12px;
            background: rgba(121, 191, 0, 0.15);
            display: grid;
            place-items: center;
            color: var(--verde-escuro);
            font-size: 23px;
        }

        .resumo-numero {
            font-size: 31px;
            font-weight: bold;
            color: var(--verde-escuro);
            line-height: 1;
        }

        .resumo-legenda {
            font-size: 13px;
            color: var(--texto-suave);
            margin-top: 6px;
        }

        /* LISTAGEM */

        .secao-titulo {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-bottom: 20px;
        }

        .secao-titulo h2 {
            font-size: 21px;
        }

        .secao-titulo span {
            color: var(--texto-suave);
            font-size: 13px;
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
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            padding-bottom: 17px;
            border-bottom: 1px solid var(--borda);
        }

        .numero-encomenda {
            font-family: Impact, Haettenschweiler, "Arial Narrow Bold", sans-serif;
            font-size: 18px;
            letter-spacing: 0.35px;
        }

        .data-encomenda {
            font-size: 12px;
            color: var(--texto-suave);
            margin-top: 5px;
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

        .status-aceita {
            background: rgba(79, 143, 131, 0.17);
            color: var(--azul-verde);
        }

        .status-recusada {
            background: #fff0ef;
            color: #b12c2c;
        }

        .status-concluida {
            background: rgba(121, 191, 0, 0.15);
            color: var(--verde-escuro);
        }

        /* INFORMAÇÕES */

        .informacoes {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 20px;
            padding: 20px 0;
        }

        .informacao-label {
            display: block;
            color: var(--texto-suave);
            font-size: 11px;
            margin-bottom: 7px;
        }

        .informacao-valor {
            font-size: 14px;
            font-weight: 600;
            overflow-wrap: anywhere;
        }

        .valor-total {
            color: var(--verde-escuro);
            font-size: 19px;
            font-weight: bold;
        }

        /* AÇÕES */

        .acoes {
            display: flex;
            align-items: center;
            gap: 10px;
            padding-top: 16px;
            border-top: 1px solid var(--borda);
            flex-wrap: wrap;
        }

        .acoes form {
            margin: 0;
        }

        .botao {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 7px;
            padding: 11px 17px;
            border: none;
            border-radius: 7px;
            font-size: 12px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.2s;
        }

        .botao-aceitar {
            background: var(--verde);
            color: white;
        }

        .botao-aceitar:hover {
            background: var(--verde-escuro);
        }

        .botao-recusar {
            background: #fff0ef;
            color: #b12c2c;
        }

        .botao-recusar:hover {
            background: #ffe0de;
        }

        .botao-concluir {
            background: rgba(79, 143, 131, 0.17);
            color: var(--azul-verde);
        }

        .botao-concluir:hover {
            background: rgba(79, 143, 131, 0.28);
        }

        .sem-acoes {
            font-size: 12px;
            color: var(--texto-suave);
        }

        /* ESTADO VAZIO */

        .sem-encomendas {
            background: var(--branco);
            border: 1px solid var(--borda);
            border-radius: 13px;
            padding: 65px 20px;
            text-align: center;
        }

        .sem-encomendas .icone {
            font-size: 43px;
            color: #a1a69b;
            margin-bottom: 17px;
        }

        .sem-encomendas h3 {
            font-size: 20px;
            margin-bottom: 9px;
        }

        .sem-encomendas p {
            color: var(--texto-suave);
            font-size: 13px;
            line-height: 1.5;
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

            .informacoes {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 550px) {
            .container {
                margin-top: 25px;
            }

            .cabecalho-pagina h1 {
                font-size: 24px;
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
            }

            .informacoes {
                gap: 17px;
            }

            .acoes {
                flex-direction: column;
                align-items: stretch;
            }

            .acoes form,
            .botao {
                width: 100%;
                text-align: center;
            }

            .navegacao-conteudo {
                gap: 13px;
            }

            .navegacao a {
                font-size: 12px;
            }

            .acoes-topo {
                gap: 10px;
            }

                                    /* HEADER MOBILE */
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

            .link-conta {
                font-size: 12px;
            }

            .botao-conta {
                padding: 9px 11px;
                font-size: 12px;
                white-space: nowrap;
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
        }
    </style>
</head>

<body>

    <!-- CABEÇALHO -->

    <header class="topo">

        <div class="topo-principal">

            <a href="../index.php" class="logo" aria-label="ClickFeira Assaré - Página inicial">
                <img src="../assets/img/logo-clickfeira.png" alt="ClickFeira Assaré">
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

                        <img src="<?= escapar($foto_perfil_url) ?>" alt="Foto de perfil">

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
                <a href="../products/my_products.php">Meus produtos</a>
                <a href="../products/register.php">Cadastrar produto</a>
                <a href="list.php" class="ativo">Encomendas recebidas</a>
                <a href="../users/logout.php" class="sair">Sair</a>

            </div>

        </nav>

    </header>


    <main class="container">

        <!-- TÍTULO -->

        <section class="cabecalho-pagina">

            <h1>Encomendas recebidas</h1>

            <p>
                Acompanhe os pedidos dos clientes e gerencie suas encomendas.
            </p>

        </section>


        <!-- RESUMO -->

        <section class="resumo">

            <div class="resumo-icone">
                <i class="fi fi-rr-box"></i>
            </div>

            <div>

                <div class="resumo-numero">
                    <?= $total_encomendas ?>
                </div>

                <div class="resumo-legenda">
                    <?= $total_encomendas === 1 ? "Item de encomenda encontrado" : "Itens de encomenda encontrados" ?>
                </div>

            </div>

        </section>


        <!-- LISTAGEM -->

        <section>

            <div class="secao-titulo">

                <h2>Pedidos dos clientes</h2>

                <span>Mais recentes primeiro</span>

            </div>


            <?php if ($total_encomendas > 0): ?>

                <div class="lista-encomendas">

                    <?php while ($encomenda = $resultado->fetch_assoc()): ?>

                        <?php

                        $status = $encomenda["status"];

                        $classe_status = [
                            "PENDING" => "status-pendente",
                            "ACCEPTED" => "status-aceita",
                            "REJECTED" => "status-recusada",
                            "COMPLETED" => "status-concluida"
                        ][$status] ?? "";

                        $quantidade = (int) $encomenda["quantity"];

                        $preco_unitario = (float) $encomenda["unit_price"];

                        $valor_total = $quantidade * $preco_unitario;

                        $data_formatada = date(
                            "d/m/Y H:i",
                            strtotime($encomenda["order_date"])
                        );

                        ?>

                        <article class="encomenda">

                            <!-- CABEÇALHO DO PEDIDO -->

                            <div class="encomenda-topo">

                                <div>

                                    <div class="numero-encomenda">
                                        Encomenda #<?= (int) $encomenda["id_order"] ?>
                                    </div>

                                    <div class="data-encomenda">
                                        Recebida em <?= escapar($data_formatada) ?>
                                    </div>

                                </div>

                                <span class="status <?= $classe_status ?>">
                                    <?= escapar(nomeStatus($status)) ?>
                                </span>

                            </div>


                            <!-- INFORMAÇÕES -->

                            <div class="informacoes">

                                <div>
                                    <span class="informacao-label">Cliente</span>
                                    <span class="informacao-valor">
                                        <?= escapar($encomenda["customer_name"]) ?>
                                    </span>
                                </div>

                                <div>
                                    <span class="informacao-label">Produto</span>
                                    <span class="informacao-valor">
                                        <?= escapar($encomenda["product_name"]) ?>
                                    </span>
                                </div>

                                <div>
                                    <span class="informacao-label">Quantidade</span>
                                    <span class="informacao-valor">
                                        <?= $quantidade ?>
                                        <?= $quantidade === 1 ? "unidade" : "unidades" ?>
                                    </span>
                                </div>

                                <div>
                                    <span class="informacao-label">Preço unitário</span>
                                    <span class="informacao-valor">
                                        R$ <?= number_format($preco_unitario, 2, ",", ".") ?>
                                    </span>
                                </div>

                                <div>
                                    <span class="informacao-label">Valor total do item</span>
                                    <span class="informacao-valor valor-total">
                                        R$ <?= number_format($valor_total, 2, ",", ".") ?>
                                    </span>
                                </div>

                            </div>


                            <!-- AÇÕES -->

                            <?php if ($status === "PENDING"): ?>

                                <div class="acoes">

                                    <form method="POST" action="update_status.php">

                                        <input
                                            type="hidden"
                                            name="order_id"
                                            value="<?= (int) $encomenda["id_order"] ?>"
                                        >

                                        <input type="hidden" name="status" value="ACCEPTED">

                                        <button type="submit" class="botao botao-aceitar">
                                            <i class="fi fi-rr-check"></i>
                                            Aceitar encomenda
                                        </button>

                                    </form>


                                    <form
                                        method="POST"
                                        action="update_status.php"
                                        onsubmit="return confirm('Tem certeza que deseja recusar esta encomenda?')"
                                    >

                                        <input
                                            type="hidden"
                                            name="order_id"
                                            value="<?= (int) $encomenda["id_order"] ?>"
                                        >

                                        <input type="hidden" name="status" value="REJECTED">

                                        <button type="submit" class="botao botao-recusar">
                                            <i class="fi fi-rr-cross-small"></i>
                                            Recusar encomenda
                                        </button>

                                    </form>

                                </div>

                            <?php elseif ($status === "ACCEPTED"): ?>

                                <div class="acoes">

                                    <form method="POST" action="update_status.php">

                                        <input
                                            type="hidden"
                                            name="order_id"
                                            value="<?= (int) $encomenda["id_order"] ?>"
                                        >

                                        <input type="hidden" name="status" value="COMPLETED">

                                        <button type="submit" class="botao botao-concluir">
                                            <i class="fi fi-rr-check-circle"></i>
                                            Marcar como concluída
                                        </button>

                                    </form>

                                </div>

                            <?php else: ?>

                                <div class="acoes">
                                    <p class="sem-acoes">
                                        Esta encomenda não possui ações disponíveis.
                                    </p>
                                </div>

                            <?php endif; ?>

                        </article>

                    <?php endwhile; ?>

                </div>

            <?php else: ?>

                <div class="sem-encomendas">

                    <div class="icone">
                        <i class="fi fi-rr-inbox"></i>
                    </div>

                    <h3>Nenhuma encomenda recebida</h3>

                    <p>
                        Quando um cliente fizer um pedido, ele aparecerá aqui.
                    </p>

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