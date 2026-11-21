
<?php

session_start();

require_once "../config/database.php";

function escapar($valor) {
    return htmlspecialchars((string) ($valor ?? ""), ENT_QUOTES, "UTF-8");
}

// Verifica se uma imagem existe antes de montar sua URL.
function imagemUpload($arquivo, $diretorio) {

    if (empty($arquivo)) {
        return null;
    }

    $nome = basename($arquivo);
    $caminho = __DIR__ . "/../uploads/" . $diretorio . "/" . $nome;

    if (!is_file($caminho)) {
        return null;
    }

    return "../uploads/" . $diretorio . "/" . rawurlencode($nome);
}

// Recebe os filtros.
$pesquisa = trim($_GET["pesquisa"] ?? "");

$categoria_id = filter_var(
    $_GET["categoria"] ?? 0,
    FILTER_VALIDATE_INT
);

if ($categoria_id === false || $categoria_id < 1) {
    $categoria_id = 0;
}

$disponibilidade = $_GET["disponibilidade"] ?? "todos";

if (!in_array($disponibilidade, ["todos", "disponiveis", "indisponiveis"], true)) {
    $disponibilidade = "todos";
}

$ordenacao = $_GET["ordenacao"] ?? "recentes";

$ordenacoes_validas = [
    "recentes" => "products.id_product DESC",
    "menor_preco" => "products.price ASC, products.id_product DESC",
    "maior_preco" => "products.price DESC, products.id_product DESC"
];

if (!isset($ordenacoes_validas[$ordenacao])) {
    $ordenacao = "recentes";
}

$ordem_sql = $ordenacoes_validas[$ordenacao];

// Paginação.
$por_pagina = 12;

$pagina = filter_var(
    $_GET["pagina"] ?? 1,
    FILTER_VALIDATE_INT
);

if ($pagina === false || $pagina < 1) {
    $pagina = 1;
}

// Busca foto do usuário logado.
$foto_perfil_url = null;

if (isset($_SESSION["id_usuario"])) {

    $id_usuario = (int) $_SESSION["id_usuario"];

    $sql_foto = "SELECT profile_photo
                 FROM users
                 WHERE id_user = ?";

    $stmt_foto = $conn->prepare($sql_foto);
    $stmt_foto->bind_param("i", $id_usuario);
    $stmt_foto->execute();

    $dados_usuario = $stmt_foto->get_result()->fetch_assoc();

    $foto_perfil_url = imagemUpload(
        $dados_usuario["profile_photo"] ?? null,
        "profiles"
    );

    $stmt_foto->close();
}

// Busca categorias.
$categorias = $conn->query(
    "SELECT id_category, name
     FROM categories
     ORDER BY name ASC"
);

// Monta as condições da consulta.
$condicoes = [
    "users.user_type = 'seller'"
];

$parametros = [];
$tipos = "";

if ($pesquisa !== "") {

    $condicoes[] = "(products.name LIKE ? OR users.name LIKE ?)";

    $termo = "%" . $pesquisa . "%";

    $parametros[] = $termo;
    $parametros[] = $termo;

    $tipos .= "ss";
}

if ($categoria_id > 0) {

    $condicoes[] = "products.category_id = ?";

    $parametros[] = $categoria_id;

    $tipos .= "i";
}

if ($disponibilidade === "disponiveis") {

    $condicoes[] = "products.quantity > 0";

} elseif ($disponibilidade === "indisponiveis") {

    $condicoes[] = "products.quantity <= 0";
}

$where_sql = implode(" AND ", $condicoes);

// Consulta base.
$from_sql = "FROM products
             JOIN users ON products.user_id = users.id_user
             LEFT JOIN categories
                ON products.category_id = categories.id_category
             WHERE $where_sql";

// Conta o total de resultados.
$sql_contagem = "SELECT COUNT(*) AS total $from_sql";

$stmt_contagem = $conn->prepare($sql_contagem);

if (!empty($parametros)) {
    $stmt_contagem->bind_param($tipos, ...$parametros);
}

$stmt_contagem->execute();

$total_produtos = (int) $stmt_contagem->get_result()->fetch_assoc()["total"];

$stmt_contagem->close();

// Calcula a paginação.
$total_paginas = max(1, (int) ceil($total_produtos / $por_pagina));

if ($pagina > $total_paginas) {
    $pagina = $total_paginas;
}

$offset = ($pagina - 1) * $por_pagina;

// Busca os produtos.
$sql = "SELECT products.*,
               users.id_user AS seller_id,
               users.name AS seller_name,
               users.profile_photo AS seller_photo,
               users.stall_photo,
               users.stall_location,
               categories.name AS category_name
        $from_sql
        ORDER BY $ordem_sql
        LIMIT ? OFFSET ?";

$stmt = $conn->prepare($sql);

$parametros_produtos = $parametros;
$tipos_produtos = $tipos . "ii";

$parametros_produtos[] = $por_pagina;
$parametros_produtos[] = $offset;

$stmt->bind_param($tipos_produtos, ...$parametros_produtos);

$stmt->execute();

$resultado = $stmt->get_result();

// Monta URL preservando os filtros.
function urlPagina($pagina_destino) {

    $parametros = $_GET;
    $parametros["pagina"] = $pagina_destino;

    unset($parametros["vendedor"]);

    return "list.php?" . http_build_query($parametros);
}

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Produtos - ClickFeira Assaré</title>

    <link
        rel="stylesheet"
        href="https://cdn-uicons.flaticon.com/3.0.0/uicons-regular-rounded/css/uicons-regular-rounded.css"
    >

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
        input,
        select {
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

        .titulo-pagina {
            margin-bottom: 25px;
        }

        .titulo-pagina h1 {
            font-size: 28px;
            margin-bottom: 8px;
        }

        .titulo-pagina p {
            color: var(--texto-suave);
            font-size: 14px;
        }

        /* FILTROS */

        .filtros {
            background: var(--branco);
            border: 1px solid var(--borda);
            border-radius: 12px;
            padding: 22px;
            margin-bottom: 30px;
            box-shadow: var(--sombra);
        }

        .filtros-titulo {
            font-size: 17px;
            margin-bottom: 17px;
        }

        .filtros-form {
            display: grid;
            grid-template-columns: 1.7fr 1fr 1fr 1fr auto auto;
            align-items: end;
            gap: 13px;
        }

        .campo {
            display: flex;
            flex-direction: column;
            gap: 8px;
            min-width: 0;
        }

        .campo label {
            font-size: 12px;
            font-weight: bold;
            color: var(--texto-suave);
        }

        .campo input,
        .campo select {
            width: 100%;
            height: 43px;
            padding: 0 12px;
            border: 1px solid var(--borda);
            border-radius: 7px;
            background: #fffdf8;
            outline: none;
            font-size: 13px;
            color: var(--texto);
            min-width: 0;
        }

        .campo input:focus,
        .campo select:focus {
            border-color: var(--verde);
        }

        .botao-buscar {
            height: 43px;
            padding: 0 18px;
            border: none;
            border-radius: 7px;
            background: var(--verde);
            color: white;
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
            white-space: nowrap;
            transition: 0.2s;
        }

        .botao-buscar:hover {
            background: var(--verde-escuro);
        }

        .limpar {
            height: 43px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--verde-escuro);
            font-size: 12px;
            font-weight: bold;
            white-space: nowrap;
        }

        .limpar:hover {
            color: var(--verde);
        }

        /* RESULTADOS */

        .resultados-cabecalho {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 20px;
            gap: 15px;
        }

        .resultados-cabecalho h2 {
            font-size: 21px;
        }

        .quantidade-resultados {
            color: var(--texto-suave);
            font-size: 13px;
        }

        /* CARDS */

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
            box-shadow: 0 2px 8px rgba(0,0,0,0.08);
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
            margin-bottom: 15px;
        }

        .estoque.indisponivel {
            color: #b12c2c;
            font-weight: bold;
        }

        /* IDENTIDADE DO VENDEDOR */

        .vendedor {
            border-top: 1px solid var(--borda);
            padding-top: 13px;
            margin-top: 3px;
        }

        .perfil-vendedor {
            display: flex;
            align-items: center;
            gap: 10px;
            min-width: 0;
        }

        .link-perfil-vendedor {
            display: flex;
            align-items: center;
            flex-shrink: 0;
            border-radius: 50%;
        }

        .foto-vendedor,
        .foto-vendedor-placeholder {
            width: 39px;
            height: 39px;
            border-radius: 50%;
            object-fit: cover;
            background: var(--creme);
            flex-shrink: 0;
        }

        .foto-vendedor-placeholder {
            display: grid;
            place-items: center;
            color: var(--verde-escuro);
            font-weight: bold;
        }

        .link-perfil-vendedor:hover .foto-vendedor,
        .link-perfil-vendedor:hover .foto-vendedor-placeholder {
            outline: 2px solid var(--verde);
            outline-offset: 2px;
        }

        .dados-vendedor {
            min-width: 0;
        }

        .nome-vendedor {
            font-size: 12px;
            font-weight: bold;
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            max-width: 180px;
        }

        .link-nome-vendedor:hover {
            color: var(--verde);
        }

        .legenda-vendedor {
            font-size: 10px;
            color: var(--texto-suave);
            margin-top: 3px;
        }

        .localizacao {
            display: flex;
            align-items: flex-start;
            gap: 6px;
            font-size: 11px;
            color: var(--texto-suave);
            margin-top: 12px;
            line-height: 1.4;
        }

        .localizacao i {
            color: var(--verde);
            flex-shrink: 0;
        }

        /* ENCOMENDA */

        .form-encomenda {
            margin-top: 14px;
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
            margin-top: 14px;
            line-height: 1.5;
        }

        .mensagem-login a {
            color: var(--verde-escuro);
            font-weight: bold;
        }

        /* PAGINAÇÃO */

        .paginacao {
            display: flex;
            justify-content: center;
            align-items: center;
            flex-wrap: wrap;
            gap: 7px;
            margin-top: 35px;
        }

        .pagina-link {
            min-width: 38px;
            height: 38px;
            padding: 0 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid var(--borda);
            border-radius: 7px;
            background: var(--branco);
            color: var(--texto);
            font-size: 13px;
            font-weight: bold;
            transition: 0.2s;
        }

        .pagina-link:hover {
            border-color: var(--verde);
            color: var(--verde-escuro);
        }

        .pagina-link.ativo {
            background: var(--verde-escuro);
            border-color: var(--verde-escuro);
            color: white;
        }

        .pagina-link.desabilitado {
            opacity: 0.4;
            pointer-events: none;
        }

        /* ESTADO VAZIO */

        .sem-resultados {
            background: var(--branco);
            border: 1px solid var(--borda);
            border-radius: 13px;
            padding: 65px 20px;
            text-align: center;
        }

        .sem-resultados .icone {
            font-size: 43px;
            color: #a1a69b;
            margin-bottom: 17px;
        }

        .sem-resultados h3 {
            font-size: 20px;
            margin-bottom: 9px;
        }

        .sem-resultados p {
            color: var(--texto-suave);
            font-size: 13px;
            line-height: 1.5;
        }

        .botao-limpar-vazio {
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

        .botao-limpar-vazio:hover {
            background: var(--creme);
        }

        /* RODAPÉ */

        footer {
            padding: 30px 20px;
            background: var(--branco);
            border-top: 1px solid var(--borda);
            text-align: center;
            color: var(--texto-suave);
            font-size: 12px;
        }

        /* RESPONSIVIDADE */

        @media (max-width: 1150px) {

            .filtros-form {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }

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

        }

        @media (max-width: 550px) {

            .container {
                margin-top: 25px;
            }

            .titulo-pagina h1 {
                font-size: 24px;
            }

            .filtros {
                padding: 17px;
            }

            .filtros-form {
                grid-template-columns: 1fr;
            }

            .botao-buscar,
            .limpar {
                width: 100%;
            }

            .resultados-cabecalho {
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

            .nome-vendedor {
                max-width: 110px;
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

                <img
                    src="../assets/img/logo-clickfeira.png"
                    alt="ClickFeira Assaré"
                >

            </a>

            <form action="list.php" method="GET" class="pesquisa">

                <input
                    type="text"
                    name="pesquisa"
                    placeholder="O que você está procurando?"
                    value="<?= escapar($pesquisa) ?>"
                >

                <input type="hidden" name="categoria" value="<?= (int) $categoria_id ?>">
                <input type="hidden" name="disponibilidade" value="<?= escapar($disponibilidade) ?>">
                <input type="hidden" name="ordenacao" value="<?= escapar($ordenacao) ?>">

                <button type="submit" aria-label="Pesquisar">
                    <i class="fi fi-rr-search"></i>
                </button>

            </form>

            <div class="acoes-topo">

                <?php if (isset($_SESSION["id_usuario"])): ?>

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

                <?php else: ?>

                    <a href="../users/login.php" class="link-conta">
                        Entrar
                    </a>

                    <a href="../users/register.php" class="botao-conta">
                        Criar conta
                    </a>

                <?php endif; ?>

            </div>

        </div>

        <nav class="navegacao">

            <div class="navegacao-conteudo">

                <a href="../index.php">Início</a>

                <a href="list.php" class="ativo">Todos os produtos</a>

                <?php if (isset($_SESSION["tipo_usuario"]) && $_SESSION["tipo_usuario"] === "customer"): ?>

                    <a href="../orders/my_orders.php">Minhas encomendas</a>

                <?php elseif (isset($_SESSION["tipo_usuario"]) && $_SESSION["tipo_usuario"] === "seller"): ?>

                    <a href="my_products.php">Meus produtos</a>
                    <a href="register.php">Cadastrar produto</a>
                    <a href="../orders/list.php">Encomendas recebidas</a>

                <?php endif; ?>

                <?php if (isset($_SESSION["id_usuario"])): ?>

                    <a href="../users/logout.php" class="sair">Sair</a>

                <?php else: ?>

                    <a href="../users/register.php">Criar conta</a>

                <?php endif; ?>

            </div>

        </nav>

    </header>


    <main class="container">

        <!-- TÍTULO -->

        <section class="titulo-pagina">

            <h1>Produtos da feira</h1>

            <p>
                Descubra produtos e encontre vendedores de Assaré.
            </p>

        </section>


        <!-- FILTROS -->

        <section class="filtros">

            <h2 class="filtros-titulo">
                Encontre o que você procura
            </h2>

            <form method="GET" action="list.php" class="filtros-form">

                <div class="campo">

                    <label for="pesquisa">
                        Produto ou vendedor
                    </label>

                    <input
                        type="text"
                        id="pesquisa"
                        name="pesquisa"
                        placeholder="Ex.: Maçã ou João"
                        value="<?= escapar($pesquisa) ?>"
                    >

                </div>


                <div class="campo">

                    <label for="categoria">
                        Categoria
                    </label>

                    <select id="categoria" name="categoria">

                        <option value="0">Todas as categorias</option>

                        <?php while ($categoria = $categorias->fetch_assoc()): ?>

                            <option
                                value="<?= (int) $categoria["id_category"] ?>"
                                <?= $categoria_id == $categoria["id_category"] ? "selected" : "" ?>
                            >
                                <?= escapar($categoria["name"]) ?>
                            </option>

                        <?php endwhile; ?>

                    </select>

                </div>


                <div class="campo">

                    <label for="disponibilidade">
                        Disponibilidade
                    </label>

                    <select id="disponibilidade" name="disponibilidade">

                        <option value="todos" <?= $disponibilidade === "todos" ? "selected" : "" ?>>
                            Todos
                        </option>

                        <option value="disponiveis" <?= $disponibilidade === "disponiveis" ? "selected" : "" ?>>
                            Disponíveis
                        </option>

                        <option value="indisponiveis" <?= $disponibilidade === "indisponiveis" ? "selected" : "" ?>>
                            Indisponíveis
                        </option>

                    </select>

                </div>


                <div class="campo">

                    <label for="ordenacao">
                        Ordenar por
                    </label>

                    <select id="ordenacao" name="ordenacao">

                        <option value="recentes" <?= $ordenacao === "recentes" ? "selected" : "" ?>>
                            Mais recentes
                        </option>

                        <option value="menor_preco" <?= $ordenacao === "menor_preco" ? "selected" : "" ?>>
                            Menor preço
                        </option>

                        <option value="maior_preco" <?= $ordenacao === "maior_preco" ? "selected" : "" ?>>
                            Maior preço
                        </option>

                    </select>

                </div>


                <button type="submit" class="botao-buscar">
                    <i class="fi fi-rr-search"></i>
                    Buscar
                </button>


                <a href="list.php" class="limpar">
                    Limpar filtros
                </a>

            </form>

        </section>


        <!-- RESULTADOS -->

        <section>

            <div class="resultados-cabecalho">

                <h2>
                    <?= $pesquisa !== "" ||
                        $categoria_id > 0 ||
                        $disponibilidade !== "todos"
                        ? "Resultados da busca"
                        : "Todos os produtos" ?>
                </h2>

                <span class="quantidade-resultados">

                    <?= $total_produtos ?>
                    <?= $total_produtos === 1
                        ? "produto encontrado"
                        : "produtos encontrados" ?>

                </span>

            </div>


            <?php if ($total_produtos > 0): ?>

                <div class="grade-produtos">

                    <?php while ($produto = $resultado->fetch_assoc()): ?>

                        <?php

                        $nome_vendedor = trim((string) ($produto["seller_name"] ?? ""));

                        if ($nome_vendedor === "") {
                            $nome_vendedor = "Vendedor";
                        }

                        $nome_produto = trim((string) ($produto["name"] ?? ""));

                        if ($nome_produto === "") {
                            $nome_produto = "Produto sem nome";
                        }

                        $quantidade_produto = (int) ($produto["quantity"] ?? 0);

                        $foto_produto = imagemUpload(
                            $produto["product_photo"] ?? null,
                            "products"
                        );

                        $foto_vendedor = imagemUpload(
                            $produto["seller_photo"] ?? null,
                            "profiles"
                        );

                        $localizacao_vendedor = trim(
                            (string) ($produto["stall_location"] ?? "")
                        );

                        $inicial_vendedor = function_exists("mb_substr")
                            ? mb_strtoupper(mb_substr($nome_vendedor, 0, 1, "UTF-8"), "UTF-8")
                            : strtoupper(substr($nome_vendedor, 0, 1));

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

                                <!-- INFORMAÇÕES DO PRODUTO -->

                                <span class="categoria-produto">
                                    <?= escapar($produto["category_name"] ?? "Sem categoria") ?>
                                </span>

                                <h3>
                                    <?= escapar($nome_produto) ?>
                                </h3>

                                <p class="descricao">
                                    <?= escapar($produto["description"] ?? "") ?>
                                </p>


                                <p class="preco">
                                    R$ <?= number_format((float) ($produto["price"] ?? 0), 2, ",", ".") ?>
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


                                <!-- IDENTIDADE DO VENDEDOR -->

                                <div class="vendedor">

                                    <div class="perfil-vendedor">

                                        <a
                                            href="../users/store.php?id=<?= (int) $produto["seller_id"] ?>"
                                            class="link-perfil-vendedor"
                                            title="Visitar banca de <?= escapar($nome_vendedor) ?>"
                                        >

                                            <?php if ($foto_vendedor): ?>

                                                <img
                                                    class="foto-vendedor"
                                                    src="<?= escapar($foto_vendedor) ?>"
                                                    alt="Foto de <?= escapar($nome_vendedor) ?>"
                                                    loading="lazy"
                                                >

                                            <?php else: ?>

                                                <div class="foto-vendedor-placeholder">
                                                    <?= escapar($inicial_vendedor) ?>
                                                </div>

                                            <?php endif; ?>

                                        </a>


                                        <div class="dados-vendedor">

                                            <a
                                                href="../users/store.php?id=<?= (int) $produto["seller_id"] ?>"
                                                class="nome-vendedor link-nome-vendedor"
                                            >
                                                <?= escapar($nome_vendedor) ?>
                                            </a>

                                            <p class="legenda-vendedor">
                                                Vendedor do ClickFeira
                                            </p>

                                        </div>

                                    </div>


                                    <?php if ($localizacao_vendedor !== ""): ?>

                                        <p class="localizacao">
                                            <i class="fi fi-rr-marker"></i>
                                            <?= escapar($localizacao_vendedor) ?>
                                        </p>

                                    <?php endif; ?>

                                </div>


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
                                        <a href="../users/login.php">
                                            Entre na sua conta
                                        </a>
                                        para fazer uma encomenda.
                                    </p>

                                <?php endif; ?>

                            </div>

                        </article>

                    <?php endwhile; ?>

                </div>


                <!-- PAGINAÇÃO -->

                <?php if ($total_paginas > 1): ?>

                    <nav class="paginacao" aria-label="Paginação">

                        <a
                            href="<?= escapar(urlPagina(max(1, $pagina - 1))) ?>"
                            class="pagina-link <?= $pagina <= 1 ? "desabilitado" : "" ?>"
                        >
                            <i class="fi fi-rr-angle-left"></i>
                        </a>


                        <?php

                        $inicio = max(1, $pagina - 2);
                        $fim = min($total_paginas, $pagina + 2);

                        for ($i = $inicio; $i <= $fim; $i++):

                        ?>

                            <a
                                href="<?= escapar(urlPagina($i)) ?>"
                                class="pagina-link <?= $pagina === $i ? "ativo" : "" ?>"
                                <?= $pagina === $i ? 'aria-current="page"' : "" ?>
                            >
                                <?= $i ?>
                            </a>

                        <?php endfor; ?>


                        <a
                            href="<?= escapar(urlPagina(min($total_paginas, $pagina + 1))) ?>"
                            class="pagina-link <?= $pagina >= $total_paginas ? "desabilitado" : "" ?>"
                        >
                            <i class="fi fi-rr-angle-right"></i>
                        </a>

                    </nav>

                <?php endif; ?>


            <?php else: ?>

                <!-- ESTADO VAZIO -->

                <div class="sem-resultados">

                    <div class="icone">
                        <i class="fi fi-rr-search"></i>
                    </div>

                    <h3>Nenhum produto encontrado</h3>

                    <p>
                        Não encontramos produtos com esses critérios.
                        Experimente alterar sua pesquisa ou remover alguns filtros.
                    </p>

                    <a href="list.php" class="botao-limpar-vazio">
                        <i class="fi fi-rr-refresh"></i>
                        Ver todos os produtos
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
