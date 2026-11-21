<?php

session_start();

require_once "../config/database.php";

function escapar($valor) {
    return htmlspecialchars((string) ($valor ?? ""), ENT_QUOTES, "UTF-8");
}

// Retorna o caminho da imagem somente se o arquivo existir.
function imagemUpload($arquivo, $diretorio) {

    if (empty($arquivo)) {
        return null;
    }

    $nome_arquivo = basename($arquivo);
    $caminho_fisico = __DIR__ . "/../uploads/" . $diretorio . "/" . $nome_arquivo;

    if (!is_file($caminho_fisico)) {
        return null;
    }

    return "../uploads/" . $diretorio . "/" . rawurlencode($nome_arquivo);
}

// Identifica o vendedor pela URL.
$vendedor_id = filter_input(INPUT_GET, "id", FILTER_VALIDATE_INT);

if (!$vendedor_id || $vendedor_id < 1) {
    http_response_code(404);
    die("Vendedor não encontrado.");
}

// Busca somente as informações públicas do vendedor.
$sql_vendedor = "SELECT id_user, name, profile_photo, stall_photo,
                        stall_location, user_type
                 FROM users
                 WHERE id_user = ? AND user_type = 'seller'
                 LIMIT 1";

$stmt_vendedor = $conn->prepare($sql_vendedor);
$stmt_vendedor->bind_param("i", $vendedor_id);
$stmt_vendedor->execute();

$vendedor = $stmt_vendedor->get_result()->fetch_assoc();

$stmt_vendedor->close();

if (!$vendedor) {
    http_response_code(404);
    die("Esta banca não foi encontrada.");
}

// Trata os dados do vendedor.
$nome_vendedor = trim((string) ($vendedor["name"] ?? ""));

if ($nome_vendedor === "") {
    $nome_vendedor = "Vendedor";
}

$localizacao_banca = trim((string) ($vendedor["stall_location"] ?? ""));

// Verifica as imagens do vendedor.
$foto_perfil_vendedor = imagemUpload(
    $vendedor["profile_photo"] ?? null,
    "profiles"
);

$foto_banca = imagemUpload(
    $vendedor["stall_photo"] ?? null,
    "stalls"
);

// Busca os produtos do vendedor.
$sql_produtos = "SELECT products.*,
                        categories.name AS category_name
                 FROM products
                 LEFT JOIN categories
                    ON products.category_id = categories.id_category
                 WHERE products.user_id = ?
                 ORDER BY products.id_product DESC";

$stmt_produtos = $conn->prepare($sql_produtos);
$stmt_produtos->bind_param("i", $vendedor_id);
$stmt_produtos->execute();

$resultado = $stmt_produtos->get_result();

// Busca a foto do usuário que está visualizando a página.
$foto_perfil_usuario = null;

if (isset($_SESSION["id_usuario"])) {

    $usuario_id = (int) $_SESSION["id_usuario"];

    $sql_foto = "SELECT profile_photo FROM users WHERE id_user = ?";
    $stmt_foto = $conn->prepare($sql_foto);
    $stmt_foto->bind_param("i", $usuario_id);
    $stmt_foto->execute();

    $dados_usuario = $stmt_foto->get_result()->fetch_assoc();

    $foto_perfil_usuario = imagemUpload(
        $dados_usuario["profile_photo"] ?? null,
        "profiles"
    );

    $stmt_foto->close();
}

$total_produtos = $resultado->num_rows;

// Gera a inicial do nome sem depender obrigatoriamente do mbstring.
if (function_exists("mb_substr") && function_exists("mb_strtoupper")) {
    $inicial_vendedor = mb_strtoupper(
        mb_substr($nome_vendedor, 0, 1, "UTF-8"),
        "UTF-8"
    );
} else {
    $inicial_vendedor = strtoupper(substr($nome_vendedor, 0, 1));
}

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title><?= escapar($nome_vendedor) ?> - ClickFeira Assaré</title>

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
            transition: 0.2s;
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


        /* APRESENTAÇÃO DA BANCA */

        .banca {
            background: var(--branco);
            border: 1px solid var(--borda);
            border-radius: 13px;
            overflow: hidden;
            box-shadow: var(--sombra);
            margin-bottom: 35px;
        }

        .capa-banca {
            height: 260px;
            background: linear-gradient(
                135deg,
                rgba(121, 191, 0, 0.28),
                rgba(23, 76, 50, 0.28)
            );
            overflow: hidden;
            position: relative;
        }

        .capa-banca img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
        }

        .capa-sem-foto {
            width: 100%;
            height: 100%;
            display: grid;
            place-items: center;
            color: rgba(23, 76, 50, 0.35);
            font-size: 65px;
        }

        .informacoes-banca {
            padding: 0 30px 27px;
            display: flex;
            align-items: flex-end;
            gap: 20px;
        }

        .foto-banca-perfil {
            width: 112px;
            height: 112px;
            min-width: 112px;
            border-radius: 50%;
            border: 5px solid var(--branco);
            background: var(--creme);
            object-fit: cover;
            margin-top: -55px;
            position: relative;
            z-index: 1;
        }

        .foto-perfil-placeholder {
            display: grid;
            place-items: center;
            color: var(--verde-escuro);
            font-size: 39px;
            font-weight: bold;
        }

        .dados-banca {
            flex: 1;
            min-width: 0;
            padding-top: 17px;
        }

        .dados-banca h1 {
            font-size: 28px;
            margin-bottom: 9px;
            overflow-wrap: anywhere;
        }

        .localizacao-banca {
            display: flex;
            align-items: center;
            gap: 7px;
            color: var(--texto-suave);
            font-size: 13px;
        }

        .localizacao-banca i {
            color: var(--verde);
        }

        .indicador-banca {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 12px;
            border-radius: 20px;
            background: rgba(121, 191, 0, 0.15);
            color: var(--verde-escuro);
            font-size: 11px;
            font-weight: bold;
            white-space: nowrap;
            margin-bottom: 4px;
        }


        /* PRODUTOS */

        .cabecalho-produtos {
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 15px;
            margin-bottom: 20px;
        }

        .cabecalho-produtos h2 {
            font-size: 21px;
        }

        .cabecalho-produtos p {
            margin-top: 5px;
            color: var(--texto-suave);
            font-size: 13px;
        }

        .quantidade {
            color: var(--texto-suave);
            font-size: 13px;
            white-space: nowrap;
        }

        .grade-produtos {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 22px;
        }

        .produto {
            min-width: 0;
            background: var(--branco);
            border: 1px solid var(--borda);
            border-radius: 13px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            transition: 0.22s;
        }

        .produto:hover {
            transform: translateY(-4px);
            box-shadow: var(--sombra);
            border-color: #d5dfd1;
        }

        .imagem-container {
            height: 215px;
            background: #f0f0eb;
            overflow: hidden;
            position: relative;
        }

        .foto-produto {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: transform 0.3s;
        }

        .produto:hover .foto-produto {
            transform: scale(1.045);
        }

        .sem-foto {
            width: 100%;
            height: 100%;
            display: grid;
            place-items: center;
            color: #a1a69b;
            font-size: 40px;
        }

        .selo-estoque {
            position: absolute;
            top: 12px;
            left: 12px;
            padding: 7px 10px;
            border-radius: 20px;
            background: var(--branco);
            color: var(--verde-escuro);
            font-size: 10px;
            font-weight: bold;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .selo-estoque.indisponivel {
            color: #a52a2a;
        }

        .produto-conteudo {
            padding: 16px;
            display: flex;
            flex-direction: column;
            flex: 1;
        }

        .categoria-produto {
            display: inline-block;
            align-self: flex-start;
            padding: 5px 9px;
            border-radius: 20px;
            background: rgba(121, 191, 0, 0.15);
            color: var(--verde-escuro);
            font-size: 10px;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .produto h3 {
            font-size: 16px;
            line-height: 1.4;
            margin-bottom: 7px;
            overflow-wrap: anywhere;
        }

        .descricao {
            color: var(--texto-suave);
            font-size: 12px;
            line-height: 1.5;
            margin-bottom: 12px;
            overflow-wrap: anywhere;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
            min-height: 36px;
        }

        .preco {
            color: var(--verde-escuro);
            font-size: 24px;
            font-weight: bold;
            margin-top: auto;
            margin-bottom: 5px;
        }

        .estoque {
            color: var(--texto-suave);
            font-size: 11px;
            margin-bottom: 10px;
        }

        .estoque.indisponivel {
            color: #b12c2c;
            font-weight: bold;
        }


        /* ENCOMENDA */

        .form-encomenda {
            margin-top: 6px;
            display: flex;
            flex-direction: column;
            gap: 9px;
        }

        .form-encomenda input {
            width: 100%;
            height: 40px;
            border: 1px solid var(--borda);
            border-radius: 7px;
            padding: 0 11px;
            outline: none;
            background: #fffdf8;
        }

        .form-encomenda input:focus {
            border-color: var(--verde);
        }

        .form-encomenda button {
            width: 100%;
            height: 42px;
            border: none;
            border-radius: 7px;
            background: var(--verde);
            color: white;
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.2s;
        }

        .form-encomenda button:hover {
            background: var(--verde-escuro);
        }

        .mensagem-login {
            font-size: 12px;
            color: var(--texto-suave);
            margin-top: 8px;
            line-height: 1.5;
        }

        .mensagem-login a {
            color: var(--verde-escuro);
            font-weight: bold;
        }


        /* ESTADO VAZIO */

        .sem-produtos {
            background: var(--branco);
            border: 1px solid var(--borda);
            border-radius: 13px;
            padding: 65px 20px;
            text-align: center;
        }

        .sem-produtos .icone {
            font-size: 43px;
            color: #a1a69b;
            margin-bottom: 17px;
        }

        .sem-produtos h3 {
            font-size: 20px;
            margin-bottom: 9px;
        }

        .sem-produtos p {
            color: var(--texto-suave);
            font-size: 13px;
            line-height: 1.5;
        }

        .botao-vazio {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 22px;
            padding: 12px 18px;
            border-radius: 7px;
            background: var(--amarelo);
            color: #49370d;
            font-size: 13px;
            font-weight: bold;
            transition: 0.2s;
        }

        .botao-vazio:hover {
            background: var(--creme);
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

        @media (max-width: 1150px) {

            .grade-produtos {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

        }

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

            .grade-produtos {
                grid-template-columns: repeat(2, minmax(0, 1fr));
            }

            .capa-banca {
                height: 220px;
            }

        }

        @media (max-width: 550px) {

            .container {
                margin-top: 25px;
            }

            .informacoes-banca {
                padding: 0 17px 20px;
                flex-wrap: wrap;
                gap: 12px;
            }

            .foto-banca-perfil {
                width: 88px;
                height: 88px;
                min-width: 88px;
                margin-top: -42px;
            }

            .dados-banca {
                padding-top: 12px;
            }

            .dados-banca h1 {
                font-size: 24px;
            }

            .indicador-banca {
                margin-left: 100px;
                margin-top: -5px;
            }

            .capa-banca {
                height: 175px;
            }

            .cabecalho-produtos {
                align-items: flex-start;
                flex-direction: column;
                gap: 7px;
            }

            .grade-produtos {
                grid-template-columns: repeat(2, minmax(0, 1fr));
                gap: 12px;
            }

            .imagem-container {
                height: 155px;
            }

            .produto-conteudo {
                padding: 12px;
            }

            .produto h3 {
                font-size: 14px;
            }

            .preco {
                font-size: 20px;
            }

            .descricao {
                font-size: 11px;
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

        @media (max-width: 360px) {

            .grade-produtos {
                grid-template-columns: 1fr;
            }

            .imagem-container {
                height: 210px;
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

                <?php if (isset($_SESSION["id_usuario"])): ?>

                    <a
                        href="profile.php"
                        class="foto-conta"
                        title="Meu perfil"
                        aria-label="Meu perfil"
                    >

                        <?php if ($foto_perfil_usuario): ?>

                            <img
                                src="<?= escapar($foto_perfil_usuario) ?>"
                                alt="Foto de perfil"
                            >

                        <?php else: ?>

                            <i class="fi fi-rr-user"></i>

                        <?php endif; ?>

                    </a>

                <?php else: ?>

                    <a href="login.php" class="link-conta">
                        Entrar
                    </a>

                    <a href="register.php" class="botao-conta">
                        Criar conta
                    </a>

                <?php endif; ?>

            </div>

        </div>

        <nav class="navegacao">

            <div class="navegacao-conteudo">

                <a href="../index.php">Início</a>

                <a href="../products/list.php">Todos os produtos</a>

                <?php if (isset($_SESSION["tipo_usuario"]) && $_SESSION["tipo_usuario"] === "customer"): ?>

                    <a href="../orders/my_orders.php">Minhas encomendas</a>

                <?php elseif (isset($_SESSION["tipo_usuario"]) && $_SESSION["tipo_usuario"] === "seller"): ?>

                    <a href="../products/my_products.php">Meus produtos</a>
                    <a href="../products/register.php">Cadastrar produto</a>
                    <a href="../orders/list.php">Encomendas recebidas</a>

                <?php endif; ?>

                <?php if (isset($_SESSION["id_usuario"])): ?>

                    <a href="logout.php" class="sair">Sair</a>

                <?php else: ?>

                    <a href="register.php">Criar conta</a>

                <?php endif; ?>

            </div>

        </nav>

    </header>


    <main class="container">

        <!-- APRESENTAÇÃO DA BANCA -->

        <section class="banca">

            <div class="capa-banca">

                <?php if ($foto_banca): ?>

                    <img
                        src="<?= escapar($foto_banca) ?>"
                        alt="Foto da banca de <?= escapar($nome_vendedor) ?>"
                    >

                <?php else: ?>

                    <div class="capa-sem-foto">
                        <i class="fi fi-rr-shop"></i>
                    </div>

                <?php endif; ?>

            </div>


            <div class="informacoes-banca">

                <?php if ($foto_perfil_vendedor): ?>

                    <img
                        class="foto-banca-perfil"
                        src="<?= escapar($foto_perfil_vendedor) ?>"
                        alt="Foto de <?= escapar($nome_vendedor) ?>"
                    >

                <?php else: ?>

                    <div class="foto-banca-perfil foto-perfil-placeholder">
                        <?= escapar($inicial_vendedor) ?>
                    </div>

                <?php endif; ?>


                <div class="dados-banca">

                    <h1><?= escapar($nome_vendedor) ?></h1>

                    <?php if ($localizacao_banca !== ""): ?>

                        <p class="localizacao-banca">
                            <i class="fi fi-rr-marker"></i>
                            <?= escapar($localizacao_banca) ?>
                        </p>

                    <?php endif; ?>

                </div>


                <span class="indicador-banca">
                    <i class="fi fi-rr-shop"></i>
                    Banca no ClickFeira
                </span>

            </div>

        </section>


        <!-- PRODUTOS DO VENDEDOR -->

        <section>

            <div class="cabecalho-produtos">

                <div>

                    <h2>Produtos da banca</h2>

                    <p>
                        Confira tudo o que <?= escapar($nome_vendedor) ?> tem disponível.
                    </p>

                </div>

                <span class="quantidade">
                    <?= $total_produtos ?>
                    <?= $total_produtos === 1 ? "produto" : "produtos" ?>
                </span>

            </div>


            <?php if ($total_produtos > 0): ?>

                <div class="grade-produtos">

                    <?php while ($produto = $resultado->fetch_assoc()): ?>

                        <?php

                        $foto_produto = imagemUpload(
                            $produto["product_photo"] ?? null,
                            "products"
                        );

                        $nome_produto = trim((string) ($produto["name"] ?? ""));

                        if ($nome_produto === "") {
                            $nome_produto = "Produto sem nome";
                        }

                        $descricao_produto = (string) ($produto["description"] ?? "");
                        $quantidade_produto = (int) ($produto["quantity"] ?? 0);
                        $preco_produto = (float) ($produto["price"] ?? 0);

                        ?>

                        <article class="produto">

                            <!-- IMAGEM DO PRODUTO -->

                            <div class="imagem-container">

                                <?php if ($foto_produto): ?>

                                    <img
                                        class="foto-produto"
                                        src="<?= escapar($foto_produto) ?>"
                                        alt="<?= escapar($nome_produto) ?>"
                                        loading="lazy"
                                    >

                                <?php else: ?>

                                    <div class="sem-foto">
                                        <i class="fi fi-rr-shopping-bag"></i>
                                    </div>

                                <?php endif; ?>


                                <?php if ($quantidade_produto > 0): ?>

                                    <span class="selo-estoque">
                                        <i class="fi fi-rr-check"></i>
                                        Disponível
                                    </span>

                                <?php else: ?>

                                    <span class="selo-estoque indisponivel">
                                        Indisponível
                                    </span>

                                <?php endif; ?>

                            </div>


                            <div class="produto-conteudo">

                                <span class="categoria-produto">
                                    <?= escapar($produto["category_name"] ?? "Sem categoria") ?>
                                </span>

                                <h3>
                                    <?= escapar($nome_produto) ?>
                                </h3>

                                <p class="descricao">
                                    <?= escapar($descricao_produto) ?>
                                </p>


                                <p class="preco">
                                    R$ <?= number_format($preco_produto, 2, ",", ".") ?>
                                </p>


                                <?php if ($quantidade_produto > 0): ?>

                                    <p class="estoque">
                                        <?= $quantidade_produto ?>
                                        <?= $quantidade_produto === 1 ? "unidade disponível" : "unidades disponíveis" ?>
                                    </p>

                                <?php else: ?>

                                    <p class="estoque indisponivel">
                                        Produto sem estoque
                                    </p>

                                <?php endif; ?>


                                <!-- ENCOMENDA -->

                                <?php if (
                                    isset($_SESSION["tipo_usuario"]) &&
                                    $_SESSION["tipo_usuario"] === "customer"
                                ): ?>

                                    <?php if ($quantidade_produto > 0): ?>

                                        <form
                                            class="form-encomenda"
                                            method="POST"
                                            action="../orders/create.php"
                                        >

                                            <input
                                                type="hidden"
                                                name="product_id"
                                                value="<?= (int) $produto["id_product"] ?>"
                                            >

                                            <input
                                                type="number"
                                                name="quantity"
                                                min="1"
                                                max="<?= $quantidade_produto ?>"
                                                placeholder="Quantidade desejada"
                                                required
                                            >

                                            <button type="submit">
                                                Encomendar produto
                                            </button>

                                        </form>

                                    <?php endif; ?>

                                <?php elseif (!isset($_SESSION["id_usuario"])): ?>

                                    <p class="mensagem-login">
                                        <a href="login.php">Entre na sua conta</a>
                                        para fazer uma encomenda.
                                    </p>

                                <?php endif; ?>

                            </div>

                        </article>

                    <?php endwhile; ?>

                </div>

            <?php else: ?>

                <!-- ESTADO VAZIO -->

                <div class="sem-produtos">

                    <div class="icone">
                        <i class="fi fi-rr-shopping-bag"></i>
                    </div>

                    <h3>Esta banca ainda não possui produtos</h3>

                    <p>
                        Volte mais tarde para conferir as novidades.
                    </p>

                    <a href="../products/list.php" class="botao-vazio">
                        <i class="fi fi-rr-search"></i>
                        Explorar outros produtos
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
$stmt_produtos->close();
?>