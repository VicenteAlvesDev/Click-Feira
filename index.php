<?php

session_start();

$logado = isset($_SESSION["id_usuario"]);
$tipo_usuario = $_SESSION["tipo_usuario"] ?? null;

$foto_perfil = null;

if ($logado) {

    require_once "config/database.php";

    $id_usuario = (int) $_SESSION["id_usuario"];

    $sql = "SELECT profile_photo FROM users WHERE id_user = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $id_usuario);
    $stmt->execute();

    $usuario = $stmt->get_result()->fetch_assoc();

    $foto_perfil = $usuario["profile_photo"] ?? null;

    $stmt->close();
}

$foto_perfil_url = $foto_perfil
    ? "uploads/profiles/" . rawurlencode(basename($foto_perfil))
    : null;

$indicadores = [
    "pendentes" => 0,
    "aceitas" => 0,
    "concluidas" => 0,
    "produtos" => 0
];

if ($logado && $tipo_usuario === "seller") {

    $seller_id = (int) $_SESSION["id_usuario"];

    $sql = "SELECT
                SUM(CASE WHEN status = 'PENDING' THEN 1 ELSE 0 END) AS pendentes,
                SUM(CASE WHEN status = 'ACCEPTED' THEN 1 ELSE 0 END) AS aceitas,
                SUM(CASE WHEN status = 'COMPLETED' THEN 1 ELSE 0 END) AS concluidas
            FROM orders
            WHERE seller_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $seller_id);
    $stmt->execute();

    $dados = $stmt->get_result()->fetch_assoc();

    $indicadores["pendentes"] = (int) ($dados["pendentes"] ?? 0);
    $indicadores["aceitas"] = (int) ($dados["aceitas"] ?? 0);
    $indicadores["concluidas"] = (int) ($dados["concluidas"] ?? 0);

    $stmt->close();

    $sql = "SELECT COUNT(*) AS total FROM products WHERE user_id = ?";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $seller_id);
    $stmt->execute();

    $dados = $stmt->get_result()->fetch_assoc();

    $indicadores["produtos"] = (int) $dados["total"];

    $stmt->close();
}

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <link rel="manifest" href="manifest.json">
    <meta name="theme-color" content="#174c32">

    <link rel="stylesheet" href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Kanit:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">

    <title>ClickFeira Assaré</title>

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
            text-decoration: none;
            color: inherit;
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
                url("assets/img/banner-principal.png") center 48% / cover no-repeat;
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

        .link-conta {
            font-size: 13px;
            color: white;
            font-weight: bold;
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

        .botao-conta {
            padding: 11px 17px;
            border-radius: 8px;
            background: var(--amarelo);
            color: #49370d;
            font-size: 13px;
            font-weight: bold;
            box-shadow: 0 3px 9px rgba(242, 193, 78, 0.25);
        }

        .botao-conta:hover {
            background: var(--creme);
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
        }

        .navegacao a.ativo {
            color: var(--verde);
        }

        .navegacao a:hover {
            color: var(--verde);
        }

        /* CONTEÚDO */

        .container {
            max-width: 1350px;
            width: 100%;
            flex: 1;
            margin: 35px auto;
            padding: 0 35px;
        }

        .banner {
            min-height: 280px;
            padding: 45px;
            border-radius: 16px;
            background: url("assets/img/banner-principal.png") center 48% / cover no-repeat;
            color: white;
            display: flex;
            align-items: center;
            justify-content: space-between;
            overflow: hidden;
            position: relative;
        }

        .banner-conteudo {
            max-width: 600px;
            position: relative;
            z-index: 1;
        }

        .banner-etiqueta {
            display: inline-block;
            background: rgba(204, 255, 102, 0.18);
            color: var(--verde-claro);
            border: 1px solid rgba(204, 255, 102, 0.28);
            padding: 7px 12px;
            border-radius: 20px;
            font-size: 12px;
            margin-bottom: 18px;
        }

        .banner h1 {
            font-size: 35px;
            line-height: 1.2;
            margin-bottom: 15px;
        }

        .banner p {
            color: rgba(255, 255, 255, 0.90);
            line-height: 1.6;
            font-size: 15px;
            margin-bottom: 25px;
        }

        .banner-botao {
            display: inline-block;
            padding: 13px 20px;
            border-radius: 8px;
            background: var(--amarelo);
            color: #49370d;
            font-weight: 700;
            font-size: 13px;
            transition: 0.2s;
        }

        .banner-botao:hover {
            background: var(--creme);
            transform: translateY(-2px);
        }

        .banner-decoracao {
            font-size: 115px;
            opacity: 0.9;
            padding-right: 45px;
        }

        /* SEÇÕES */

        .secao {
            margin-top: 38px;
        }

        .secao-cabecalho {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 18px;
            gap: 15px;
        }

        .secao-cabecalho h2 {
            font-size: 21px;
        }

        .secao-cabecalho p {
            margin-top: 5px;
            color: var(--texto-suave);
            font-size: 13px;
        }

        .ver-mais {
            color: var(--verde-escuro);
            font-size: 13px;
            font-weight: bold;
        }

        /* CATEGORIAS */

        .categorias {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
        }

        .categoria {
            background: white;
            border: 1px solid var(--borda);
            padding: 21px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            gap: 14px;
            transition: 0.2s;
        }

        .categoria:hover {
            transform: translateY(-3px);
            border-color: var(--verde);
            box-shadow: var(--sombra);
        }

        .categoria-icone {
            width: 45px;
            height: 45px;
            border-radius: 10px;
            background: rgba(121, 191, 0, 0.15);
            display: grid;
            place-items: center;
            font-size: 22px;
            color: var(--verde-escuro);
        }

        .categoria:nth-child(2) .categoria-icone {
            background: rgba(242, 193, 78, 0.22);
            color: #8a6410;
        }

        .categoria:nth-child(3) .categoria-icone {
            background: rgba(229, 139, 85, 0.18);
            color: #a65325;
        }

        .categoria:nth-child(4) .categoria-icone {
            background: rgba(79, 143, 131, 0.17);
            color: var(--azul-verde);
        }

        .categoria strong {
            font-size: 14px;
        }

        .categoria span {
            display: block;
            color: var(--texto-suave);
            font-size: 12px;
            margin-top: 4px;
        }

        /* ACESSO RÁPIDO */

        .atalhos {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 18px;
        }

        .atalho {
            padding: 25px;
            background: white;
            border: 1px solid var(--borda);
            border-radius: 12px;
            transition: 0.2s;
        }

        .atalho:hover {
            border-color: var(--verde);
            transform: translateY(-2px);
            box-shadow: var(--sombra);
        }

        .atalho-icone {
            font-size: 25px;
            color: var(--verde);
            margin-bottom: 15px;
        }

        .atalho:nth-child(2) .atalho-icone {
            color: #c18b16;
        }

        .atalho:nth-child(3) .atalho-icone {
            color: var(--laranja);
        }

        .atalho:nth-child(4) .atalho-icone {
            color: var(--azul-verde);
        }

        .atalho h3 {
            font-size: 16px;
            margin-bottom: 8px;
        }

        .atalho p {
            color: var(--texto-suave);
            font-size: 13px;
            line-height: 1.5;
        }

        /* PAINEL DO VENDEDOR */

        .indicadores {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 17px;
            margin-bottom: 28px;
        }

        .indicador {
            background: white;
            border: 1px solid var(--borda);
            border-radius: 12px;
            padding: 22px;
            box-shadow: var(--sombra);
        }

        .indicador h3 {
            font-size: 13px;
            color: var(--texto-suave);
            font-weight: normal;
            margin-bottom: 13px;
        }

        .indicador .numero {
            font-size: 31px;
            font-weight: bold;
            color: var(--verde-escuro);
        }

        .indicador:nth-child(2) .numero {
            color: #bd8610;
        }

        .indicador:nth-child(3) .numero {
            color: var(--azul-verde);
        }

        .indicador:nth-child(4) .numero {
            color: var(--laranja);
        }

        .painel-titulo {
            margin-bottom: 20px;
        }

        .painel-titulo h1 {
            font-size: 28px;
            margin-bottom: 7px;
        }

        .painel-titulo p {
            color: var(--texto-suave);
            font-size: 14px;
        }

        /* RODAPÉ */

        footer {
            margin-top: 0px;
            padding: 30px 20px;
            background: white;
            border-top: 1px solid var(--borda);
            text-align: center;
            color: var(--texto-suave);
            font-size: 12px;
        }

        .sair {
            color: #c0392b !important;
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

            .categorias {
                grid-template-columns: repeat(2, 1fr);
            }

            .indicadores {
                grid-template-columns: repeat(2, 1fr);
            }
        }

        @media (max-width: 550px) {
            .banner {
                padding: 30px 23px;
                min-height: 260px;
            }

            .banner h1 {
                font-size: 27px;
            }

            .banner-decoracao {
                display: none;
            }

            .categorias,
            .atalhos {
                grid-template-columns: 1fr;
            }

            .indicadores {
                gap: 10px;
            }

            .indicador {
                padding: 17px;
            }

            .indicador .numero {
                font-size: 26px;
            }

            .secao-cabecalho h2 {
                font-size: 18px;
            }

            .acoes-topo {
                gap: 10px;
            }

            .botao-conta {
                padding: 10px;
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

    <header class="topo">

        <div class="topo-principal">

            <a href="index.php" class="logo" aria-label="ClickFeira Assaré - Página inicial">
                <img src="assets/img/logo-clickfeira.png" alt="ClickFeira Assaré">
            </a>

            <form action="products/list.php" method="GET" class="pesquisa">
                <input
                    type="text"
                    name="pesquisa"
                    placeholder="O que você está procurando?"
                >

                <button type="submit">
                    <i class="fi fi-rr-search"></i>
                </button>
            </form>

            <div class="acoes-topo">

                <?php if ($logado): ?>

                    <a href="users/profile.php" class="foto-conta" title="Meu perfil">

                        <?php if ($foto_perfil_url): ?>

                            <img src="<?= htmlspecialchars($foto_perfil_url) ?>" alt="Foto de perfil">

                        <?php else: ?>

                            <i class="fi fi-rr-user"></i>

                        <?php endif; ?>

                    </a>

                <?php else: ?>

                    <a href="users/login.php" class="link-conta">
                        Entrar
                    </a>

                    <a href="users/register.php" class="botao-conta">
                        Criar conta
                    </a>

                <?php endif; ?>

            </div>

        </div>

        <nav class="navegacao">
            <div class="navegacao-conteudo">

                <a href="index.php" class="ativo">Início</a>
                <a href="products/list.php">Todos os produtos</a>

                <?php if (!$logado): ?>

                    <a href="users/register.php">Criar conta</a>

                <?php endif; ?>

                <?php if ($logado && $tipo_usuario === "customer"): ?>

                    <a href="orders/my_orders.php">Minhas encomendas</a>

                <?php elseif ($logado && $tipo_usuario === "seller"): ?>

                    <a href="products/my_products.php">Meus produtos</a>
                    <a href="products/register.php">Cadastrar produto</a>
                    <a href="orders/list.php">Encomendas recebidas</a>

                <?php endif; ?>

                <?php if ($logado): ?>

                    <a href="users/logout.php" class="sair">Sair</a>

                <?php endif; ?>

            </div>
        </nav>

    </header>

    <main class="container">

        <?php if (!$logado || $tipo_usuario === "customer"): ?>

            <section class="banner">

                <div class="banner-conteudo">

                    <span class="banner-etiqueta">
                        SUA FEIRA, AGORA MAIS PERTO
                    </span>

                    <h1>
                        Tudo o que você procura,
                        em um só lugar.
                    </h1>

                    <p>
                        Descubra produtos da feira de Assaré,
                        conheça os vendedores e encontre o que precisa.
                    </p>

                    <a href="products/list.php" class="banner-botao">
                        Explorar produtos →
                    </a>

                </div>

                <div class="banner-decoracao">
                    <i class="fi fi-rr-shopping-cart"></i>
                </div>

            </section>

            <section class="secao">

                <div class="secao-cabecalho">
                    <div>
                        <h2>Explore as categorias</h2>
                        <p>Encontre facilmente o que procura.</p>
                    </div>

                    <a href="products/list.php" class="ver-mais">
                        Ver produtos →
                    </a>
                </div>

                <div class="categorias">

                    <a href="products/list.php?categoria=1" class="categoria">
                        <div class="categoria-icone">
                            <i class="fi fi-rr-lemon"></i>
                        </div>
                        <div>
                            <strong>Frutas</strong>
                            <span>Produtos fresquinhos</span>
                        </div>
                    </a>

                    <a href="products/list.php?categoria=2" class="categoria">
                        <div class="categoria-icone">
                            <i class="fi fi-rr-leaf"></i>
                        </div>
                        <div>
                            <strong>Verduras</strong>
                            <span>Direto da feira</span>
                        </div>
                    </a>

                    <a href="products/list.php?categoria=3" class="categoria">
                        <div class="categoria-icone">
                            <i class="fi fi-rr-carrot"></i>
                        </div>
                        <div>
                            <strong>Legumes</strong>
                            <span>Variedade para você</span>
                        </div>
                    </a>

                    <a href="products/list.php" class="categoria">
                        <div class="categoria-icone">
                            <i class="fi fi-rr-shopping-basket"></i>
                        </div>
                        <div>
                            <strong>Outros produtos</strong>
                            <span>Descubra novidades</span>
                        </div>
                    </a>

                </div>

            </section>

            <section class="secao">

                <div class="secao-cabecalho">
                    <div>
                        <h2>
                            <?= $logado ? "Continue explorando" : "Comece agora" ?>
                        </h2>

                        <p>
                            Acesse os principais recursos do ClickFeira.
                        </p>
                    </div>
                </div>

                <div class="atalhos">

                    <a href="products/list.php" class="atalho">
                        <div class="atalho-icone">
                            <i class="fi fi-rr-shopping-bag"></i>
                        </div>
                        <h3>Encontrar produtos</h3>
                        <p>
                            Explore o catálogo e descubra os produtos
                            disponíveis na feira.
                        </p>
                    </a>

                    <?php if ($logado): ?>

                        <a href="orders/my_orders.php" class="atalho">
                            <div class="atalho-icone">
                                <i class="fi fi-rr-box"></i>
                            </div>
                            <h3>Minhas encomendas</h3>
                            <p>
                                Acompanhe suas compras e consulte
                                o andamento dos seus pedidos.
                            </p>
                        </a>

                    <?php else: ?>

                        <a href="users/register.php" class="atalho">
                            <div class="atalho-icone">
                                <i class="fi fi-rr-user-add"></i>
                            </div>
                            <h3>Faça seu cadastro</h3>
                            <p>
                                Crie sua conta e comece a utilizar
                                os recursos do ClickFeira.
                            </p>
                        </a>

                    <?php endif; ?>

                </div>

            </section>

        <?php elseif ($tipo_usuario === "seller"): ?>

            <section>

                <div class="painel-titulo">
                    <h1>Painel do vendedor</h1>
                    <p>Gerencie sua loja e acompanhe suas atividades.</p>
                </div>

                <div class="indicadores">

                    <div class="indicador">
                        <h3>Encomendas pendentes</h3>
                        <div class="numero">
                            <?= $indicadores["pendentes"] ?>
                        </div>
                    </div>

                    <div class="indicador">
                        <h3>Encomendas aceitas</h3>
                        <div class="numero">
                            <?= $indicadores["aceitas"] ?>
                        </div>
                    </div>

                    <div class="indicador">
                        <h3>Encomendas concluídas</h3>
                        <div class="numero">
                            <?= $indicadores["concluidas"] ?>
                        </div>
                    </div>

                    <div class="indicador">
                        <h3>Produtos cadastrados</h3>
                        <div class="numero">
                            <?= $indicadores["produtos"] ?>
                        </div>
                    </div>

                </div>

                <div class="secao-cabecalho">
                    <div>
                        <h2>Gerenciar minha loja</h2>
                        <p>Acesse as ferramentas do vendedor.</p>
                    </div>
                </div>

                <div class="atalhos">

                    <a href="products/register.php" class="atalho">
                        <div class="atalho-icone">
                            <i class="fi fi-rr-add"></i>
                        </div>
                        <h3>Cadastrar produto</h3>
                        <p>Adicione novos produtos ao catálogo.</p>
                    </a>

                    <a href="products/my_products.php" class="atalho">
                        <div class="atalho-icone">
                            <i class="fi fi-rr-shopping-cart"></i>
                        </div>
                        <h3>Meus produtos</h3>
                        <p>Consulte e gerencie seus produtos cadastrados.</p>
                    </a>

                    <a href="orders/list.php" class="atalho">
                        <div class="atalho-icone">
                            <i class="fi fi-rr-box"></i>
                        </div>
                        <h3>Encomendas recebidas</h3>
                        <p>Visualize e gerencie os pedidos dos clientes.</p>
                    </a>

                    <a href="users/profile.php" class="atalho">
                        <div class="atalho-icone">
                            <i class="fi fi-rr-user"></i>
                        </div>
                        <h3>Meu perfil</h3>
                        <p>Atualize suas informações e dados da banca.</p>
                    </a>

                </div>

            </section>

        <?php endif; ?>

    </main>

    <footer>
        <p>© <?= date("Y") ?> ClickFeira Assaré. Conectando vendedores e consumidores.</p>
    </footer>

    <script>
        if ("serviceWorker" in navigator) {
            window.addEventListener("load", () => {
                navigator.serviceWorker.register("./service-worker.js")
                    .then(() => {
                        console.log("Service Worker registrado com sucesso!");
                    })
                    .catch((erro) => {
                        console.error("Erro ao registrar Service Worker:", erro);
                    });
            });
        }
    </script>

</body>
</html>