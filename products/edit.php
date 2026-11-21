<?php

session_start();

if (
    !isset($_SESSION["id_usuario"]) ||
    $_SESSION["tipo_usuario"] !== "seller"
) {
    die("Acesso permitido apenas para vendedores.");
}

require_once "../config/database.php";

function escapar($valor) {
    return htmlspecialchars((string) ($valor ?? ""), ENT_QUOTES, "UTF-8");
}

$seller_id = (int) $_SESSION["id_usuario"];

if (!isset($_GET["id"]) || !filter_var($_GET["id"], FILTER_VALIDATE_INT)) {
    die("Produto inválido.");
}

$product_id = (int) $_GET["id"];

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

// Busca o produto.
$sql = "SELECT id_product, name, description, quantity, price,
               product_photo, category_id
        FROM products
        WHERE id_product = ? AND user_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $product_id, $seller_id);
$stmt->execute();

$resultado = $stmt->get_result();
$produto = $resultado->fetch_assoc();

$stmt->close();

if (!$produto) {
    die("Produto não encontrado ou você não tem permissão para editá-lo.");
}

$erro = "";

$categorias = $conn->query(
    "SELECT id_category, name FROM categories ORDER BY name"
);

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $name = trim($_POST["name"] ?? "");
    $description = trim($_POST["description"] ?? "");

    $quantity = filter_var(
        $_POST["quantity"] ?? null,
        FILTER_VALIDATE_INT
    );

    $price = $_POST["price"] ?? "";

    $nova_foto = null;
    $foto_antiga = $produto["product_photo"];

    $category_id = filter_var(
        $_POST["category_id"] ?? null,
        FILTER_VALIDATE_INT
    );

    if (
        $name === "" ||
        $description === "" ||
        $quantity === false ||
        $quantity === null ||
        $quantity < 0 ||
        !is_numeric($price) ||
        (float) $price < 0 ||
        $category_id === false ||
        $category_id === null ||
        $category_id < 1
    ) {

        $erro = "Preencha os campos corretamente. Quantidade e preço não podem ser negativos.";

    } else {

        $price = (float) $price;

        // Processa a nova foto, caso tenha sido selecionada.
        if (
            isset($_FILES["product_photo"]) &&
            $_FILES["product_photo"]["error"] !== UPLOAD_ERR_NO_FILE
        ) {

            $arquivo = $_FILES["product_photo"];

            if ($arquivo["error"] !== UPLOAD_ERR_OK) {

                $erro = "Erro ao enviar a imagem.";

            } elseif ($arquivo["size"] > 2 * 1024 * 1024) {

                $erro = "A imagem deve ter no máximo 2 MB.";

            } else {

                $tipos_permitidos = [
                    "image/jpeg" => "jpg",
                    "image/png" => "png",
                    "image/webp" => "webp"
                ];

                $imagem = @getimagesize($arquivo["tmp_name"]);
                $tipo = $imagem["mime"] ?? "";

                if (!isset($tipos_permitidos[$tipo])) {

                    $erro = "Formato inválido. Use JPG, PNG ou WEBP.";

                } else {

                    $extensao = $tipos_permitidos[$tipo];

                    $nova_foto = bin2hex(random_bytes(16)) . "." . $extensao;

                    $destino = "../uploads/products/" . $nova_foto;

                    if (!move_uploaded_file($arquivo["tmp_name"], $destino)) {
                        $erro = "Não foi possível salvar a imagem.";
                        $nova_foto = null;
                    }
                }
            }
        }

        // Atualiza o produto se não houve erro.
        if ($erro === "") {

            $foto_final = $nova_foto ?? $foto_antiga;

            $sql = "UPDATE products
                    SET name = ?,
                        description = ?,
                        quantity = ?,
                        price = ?,
                        product_photo = ?,
                        category_id = ?
                    WHERE id_product = ? AND user_id = ?";

            $stmt = $conn->prepare($sql);

            $stmt->bind_param(
                "ssidsiii",
                $name,
                $description,
                $quantity,
                $price,
                $foto_final,
                $category_id,
                $product_id,
                $seller_id
            );

            if ($stmt->execute()) {

                $stmt->close();

                // Remove a foto anterior somente após a atualização.
                if ($nova_foto !== null && !empty($foto_antiga)) {

                    $arquivo_antigo = "../uploads/products/" .
                        basename($foto_antiga);

                    if (is_file($arquivo_antigo)) {
                        unlink($arquivo_antigo);
                    }
                }

                header("Location: my_products.php");
                exit;

            } else {

                // Remove a nova foto se o banco não atualizar.
                if ($nova_foto !== null) {
                    unlink("../uploads/products/" . $nova_foto);
                }

                $erro = "Não foi possível atualizar o produto.";
                $stmt->close();
            }
        }
    }

    // Mantém os valores enviados no formulário.
    $produto["name"] = $name;
    $produto["description"] = $description;
    $produto["quantity"] = $_POST["quantity"] ?? "";
    $produto["price"] = $price;
    $produto["category_id"] = $category_id;
}

$foto_produto_url = !empty($produto["product_photo"])
    ? "../uploads/products/" . rawurlencode(basename($produto["product_photo"]))
    : null;

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Editar produto - ClickFeira Assaré</title>

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
        input,
        textarea,
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
            transition: 0.2s;
            white-space: nowrap;
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
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 28px;
        }

        .cabecalho-texto {
            min-width: 0;
        }

        .cabecalho-pagina h1 {
            font-size: 28px;
            margin-bottom: 8px;
        }

        .subtitulo {
            color: var(--texto-suave);
            font-size: 14px;
            line-height: 1.6;
            max-width: 650px;
        }

        .etiqueta {
            display: inline-flex;
            align-items: center;
            gap: 7px;
            background: rgba(121, 191, 0, 0.15);
            color: var(--verde-escuro);
            padding: 8px 13px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.4px;
            white-space: nowrap;
            margin-top: 3px;
        }

        .etiqueta i {
            font-size: 12px;
        }

        /* FORMULÁRIO */

        .layout-formulario {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 310px;
            align-items: start;
            gap: 24px;
        }

        .formulario {
            background: var(--branco);
            border: 1px solid var(--borda);
            border-radius: 13px;
            padding: 26px;
            box-shadow: var(--sombra);
            min-width: 0;
        }

        .secao {
            margin-bottom: 28px;
        }

        .secao:last-child {
            margin-bottom: 0;
        }

        .secao-cabecalho {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 22px;
        }

        .secao-icone {
            width: 38px;
            height: 38px;
            min-width: 38px;
            display: grid;
            place-items: center;
            border-radius: 10px;
            background: rgba(121, 191, 0, 0.15);
            color: var(--verde-escuro);
            font-size: 17px;
        }

        .secao-titulo {
            font-size: 18px;
            margin-bottom: 5px;
        }

        .secao-descricao {
            color: var(--texto-suave);
            font-size: 12px;
            line-height: 1.5;
        }

        .grade-campos {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0 18px;
        }

        .campo {
            margin-bottom: 20px;
            min-width: 0;
        }

        .campo-completo {
            grid-column: 1 / -1;
        }

        .campo label {
            display: block;
            font-size: 12px;
            font-weight: bold;
            color: var(--texto-suave);
            margin-bottom: 8px;
        }

        .obrigatorio {
            color: #d04444;
        }

        .campo input,
        .campo textarea,
        .campo select {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid var(--borda);
            border-radius: 7px;
            background: #fffdf8;
            color: var(--texto);
            font-size: 13px;
            outline: none;
            transition: 0.2s;
        }

        .campo input:focus,
        .campo textarea:focus,
        .campo select:focus {
            border-color: var(--verde);
            box-shadow: 0 0 0 3px rgba(121, 191, 0, 0.15);
        }

        .campo input::placeholder,
        .campo textarea::placeholder {
            color: #a1a69b;
        }

        .campo textarea {
            min-height: 125px;
            resize: vertical;
            line-height: 1.6;
        }

        .separador {
            height: 1px;
            background: var(--borda);
            margin: 27px 0;
        }

        /* FOTO ATUAL */

        .foto-atual {
            display: flex;
            align-items: center;
            gap: 20px;
            padding: 16px;
            background: #fffdf8;
            border: 1px solid var(--borda);
            border-radius: 12px;
            margin-bottom: 18px;
        }

        .foto-atual img {
            width: 125px;
            height: 110px;
            object-fit: cover;
            border-radius: 9px;
            border: 1px solid var(--borda);
            flex-shrink: 0;
        }

        .foto-atual .sem-foto {
            width: 125px;
            height: 110px;
            display: grid;
            place-items: center;
            background: #f0f0eb;
            color: #a1a69b;
            border-radius: 9px;
            font-size: 30px;
            flex-shrink: 0;
        }

        .foto-info strong {
            display: block;
            font-size: 14px;
            margin-bottom: 7px;
        }

        .foto-info p {
            color: var(--texto-suave);
            font-size: 12px;
            line-height: 1.5;
        }

        /* UPLOAD */

        .upload-area {
            border: 2px dashed #d9d4c3;
            border-radius: 12px;
            padding: 25px 18px;
            background: #fffdf8;
            text-align: center;
            transition: 0.2s;
        }

        .upload-area:hover {
            border-color: var(--verde);
        }

        .upload-icone {
            width: 52px;
            height: 52px;
            margin: 0 auto 12px;
            display: grid;
            place-items: center;
            border-radius: 14px;
            background: rgba(121, 191, 0, 0.15);
            color: var(--verde-escuro);
            font-size: 23px;
        }

        .upload-area p {
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 7px;
        }

        .upload-area small {
            color: var(--texto-suave);
            font-size: 11px;
            line-height: 1.5;
            display: block;
        }

        .upload-area input {
            display: block;
            margin: 18px auto 0;
            max-width: 100%;
            font-size: 12px;
        }

        .preview-container {
            display: none;
            margin-top: 20px;
            text-align: center;
        }

        .preview-container img {
            max-width: 100%;
            max-height: 210px;
            object-fit: contain;
            border-radius: 10px;
            border: 1px solid var(--borda);
            background: white;
            padding: 5px;
        }

        .preview-legenda {
            display: block;
            margin-top: 9px;
            color: var(--texto-suave);
            font-size: 11px;
        }

        /* PAINEL LATERAL */

        .painel-lateral {
            display: flex;
            flex-direction: column;
            gap: 18px;
        }

        .card-lateral {
            background: var(--branco);
            border: 1px solid var(--borda);
            border-radius: 13px;
            padding: 22px;
            box-shadow: var(--sombra);
        }

        .card-lateral-titulo {
            display: flex;
            align-items: center;
            gap: 9px;
            font-size: 17px;
            margin-bottom: 17px;
        }

        .card-lateral-titulo i {
            color: var(--verde);
            font-size: 17px;
        }

        .dica {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            margin-bottom: 15px;
        }

        .dica:last-child {
            margin-bottom: 0;
        }

        .dica i {
            color: var(--verde);
            font-size: 14px;
            margin-top: 2px;
        }

        .dica p {
            color: var(--texto-suave);
            font-size: 12px;
            line-height: 1.5;
        }

        .dica strong {
            display: block;
            color: var(--texto);
            font-size: 12px;
            margin-bottom: 3px;
        }

        .aviso-lateral {
            background: var(--creme);
            border: 1px solid #f0e0b8;
            border-radius: 13px;
            padding: 17px;
        }

        .aviso-lateral i {
            color: #8a6410;
            font-size: 18px;
        }

        .aviso-lateral p {
            color: #6b4f0e;
            font-size: 12px;
            line-height: 1.6;
            margin-top: 9px;
        }

        /* MENSAGENS E BOTÕES */

        .mensagem {
            display: flex;
            align-items: flex-start;
            gap: 10px;
            padding: 13px 15px;
            border-radius: 9px;
            background: #fff0f0;
            color: #b00020;
            font-size: 13px;
            margin-bottom: 22px;
            border: 1px solid #f3cccc;
            line-height: 1.5;
        }

        .mensagem i {
            margin-top: 1px;
        }

        .acoes {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 15px;
            margin-top: 28px;
            padding-top: 22px;
            border-top: 1px solid var(--borda);
        }

        .botao-voltar {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--texto-suave);
            font-size: 13px;
            font-weight: bold;
            transition: 0.2s;
        }

        .botao-voltar:hover {
            color: var(--verde);
        }

        .botao-salvar {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            border: none;
            background: var(--verde);
            color: white;
            padding: 14px 22px;
            border-radius: 7px;
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.2s;
        }

        .botao-salvar:hover {
            background: var(--verde-escuro);
            transform: translateY(-1px);
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

        @media (max-width: 950px) {
            .layout-formulario {
                grid-template-columns: minmax(0, 1fr) 260px;
                gap: 17px;
            }

            .formulario {
                padding: 23px;
            }

            .card-lateral {
                padding: 18px;
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

            .layout-formulario {
                grid-template-columns: 1fr;
            }

            .painel-lateral {
                display: grid;
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 550px) {
            .container {
                margin-top: 25px;
            }

            .cabecalho-pagina {
                gap: 10px;
            }

            .cabecalho-pagina h1 {
                font-size: 24px;
            }

            .etiqueta {
                font-size: 9px;
                padding: 7px 9px;
            }

            .subtitulo {
                font-size: 13px;
            }

            .formulario {
                padding: 21px 17px;
            }

            .grade-campos {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .campo-completo {
                grid-column: auto;
            }

            .foto-atual {
                flex-direction: column;
                align-items: flex-start;
            }

            .painel-lateral {
                grid-template-columns: 1fr;
            }

            .acoes {
                flex-direction: column-reverse;
                align-items: stretch;
            }

            .botao-salvar {
                width: 100%;
            }

            .botao-voltar {
                justify-content: center;
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

            <form action="list.php" method="GET" class="pesquisa">

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
                <a href="list.php">Todos os produtos</a>
                <a href="my_products.php" class="ativo">Meus produtos</a>
                <a href="register.php">Cadastrar produto</a>
                <a href="../orders/list.php">Encomendas recebidas</a>
                <a href="../users/logout.php" class="sair">Sair</a>

            </div>

        </nav>

    </header>


    <main class="container">

        <div class="cabecalho-pagina">

            <div class="cabecalho-texto">

                <h1>Editar produto</h1>

                <p class="subtitulo">
                    Atualize as informações do seu produto e mantenha sua banca organizada.
                </p>

            </div>

            <span class="etiqueta">
                <i class="fi fi-rr-store"></i>
                ÁREA DO VENDEDOR
            </span>

        </div>

        <div class="layout-formulario">

            <section class="formulario">

                <?php if ($erro !== ""): ?>

                    <div class="mensagem">
                        <i class="fi fi-rr-exclamation"></i>
                        <span><?= escapar($erro) ?></span>
                    </div>

                <?php endif; ?>

                <form method="POST" enctype="multipart/form-data">

                    <div class="secao">

                        <div class="secao-cabecalho">

                            <div class="secao-icone">
                                <i class="fi fi-rr-edit"></i>
                            </div>

                            <div>
                                <h2 class="secao-titulo">Informações do produto</h2>
                                <p class="secao-descricao">
                                    Altere os dados que serão exibidos aos consumidores.
                                </p>
                            </div>

                        </div>

                        <div class="grade-campos">

                            <div class="campo campo-completo">
                                <label for="name">
                                    Nome do produto <span class="obrigatorio">*</span>
                                </label>

                                <input
                                    type="text"
                                    id="name"
                                    name="name"
                                    maxlength="255"
                                    placeholder="Ex.: Tomate italiano"
                                    value="<?= escapar($produto["name"]) ?>"
                                    required
                                >
                            </div>

                            <div class="campo campo-completo">
                                <label for="description">
                                    Descrição <span class="obrigatorio">*</span>
                                </label>

                                <textarea
                                    id="description"
                                    name="description"
                                    placeholder="Descreva seu produto, suas características e outras informações importantes..."
                                    required
                                ><?= escapar($produto["description"]) ?></textarea>
                            </div>

                            <div class="campo">
                                <label for="price">
                                    Preço (R$) <span class="obrigatorio">*</span>
                                </label>

                                <input
                                    type="number"
                                    id="price"
                                    name="price"
                                    min="0"
                                    step="0.01"
                                    placeholder="0,00"
                                    value="<?= escapar($produto["price"]) ?>"
                                    required
                                >
                            </div>

                            <div class="campo">
                                <label for="quantity">
                                    Quantidade disponível <span class="obrigatorio">*</span>
                                </label>

                                <input
                                    type="number"
                                    id="quantity"
                                    name="quantity"
                                    min="0"
                                    step="1"
                                    placeholder="Ex.: 20"
                                    value="<?= escapar($produto["quantity"]) ?>"
                                    required
                                >
                            </div>

                            <div class="campo campo-completo">
                                <label for="category_id">
                                    Categoria <span class="obrigatorio">*</span>
                                </label>

                                <select id="category_id" name="category_id" required>

                                    <option value="">Selecione uma categoria</option>

                                    <?php while ($categoria = $categorias->fetch_assoc()): ?>

                                        <option
                                            value="<?= (int) $categoria["id_category"] ?>"
                                            <?= (int) $produto["category_id"] === (int) $categoria["id_category"] ? "selected" : "" ?>
                                        >
                                            <?= escapar($categoria["name"]) ?>
                                        </option>

                                    <?php endwhile; ?>

                                </select>
                            </div>

                        </div>

                    </div>

                    <div class="separador"></div>

                    <div class="secao">

                        <div class="secao-cabecalho">

                            <div class="secao-icone">
                                <i class="fi fi-rr-camera"></i>
                            </div>

                            <div>
                                <h2 class="secao-titulo">Foto do produto</h2>
                                <p class="secao-descricao">
                                    Confira a imagem atual ou selecione uma nova para substituí-la.
                                </p>
                            </div>

                        </div>

                        <div class="foto-atual">

                            <?php if ($foto_produto_url): ?>

                                <img
                                    src="<?= escapar($foto_produto_url) ?>"
                                    alt="Foto atual do produto"
                                >

                            <?php else: ?>

                                <div class="sem-foto">
                                    <i class="fi fi-rr-shopping-bag"></i>
                                </div>

                            <?php endif; ?>

                            <div class="foto-info">

                                <strong>Foto atual</strong>

                                <p>
                                    <?php if ($foto_produto_url): ?>
                                        Esta é a imagem atualmente cadastrada para o produto.
                                    <?php else: ?>
                                        Este produto ainda não possui uma imagem cadastrada.
                                    <?php endif; ?>
                                </p>

                            </div>

                        </div>

                        <div class="upload-area">

                            <div class="upload-icone">
                                <i class="fi fi-rr-cloud-upload-alt"></i>
                            </div>

                            <p>Escolha uma nova imagem</p>

                            <small>
                                JPG, PNG ou WEBP. Tamanho máximo de 2 MB.<br>
                                Deixe vazio para manter a foto atual.
                            </small>

                            <input
                                type="file"
                                id="product_photo"
                                name="product_photo"
                                accept="image/jpeg,image/png,image/webp"
                            >

                            <div class="preview-container" id="preview-container">

                                <img
                                    id="preview-imagem"
                                    src=""
                                    alt="Pré-visualização da nova imagem"
                                >

                                <span class="preview-legenda">
                                    Pré-visualização da nova imagem
                                </span>

                            </div>

                        </div>

                    </div>

                    <div class="acoes">

                        <a href="my_products.php" class="botao-voltar">
                            <i class="fi fi-rr-cross-small"></i>
                            Cancelar
                        </a>

                        <button type="submit" class="botao-salvar">
                            <i class="fi fi-rr-disk"></i>
                            Salvar alterações
                        </button>

                    </div>

                </form>

            </section>

            <aside class="painel-lateral">

                <div class="card-lateral">

                    <h3 class="card-lateral-titulo">
                        <i class="fi fi-rr-lightbulb-on"></i>
                        Dicas para a edição
                    </h3>

                    <div class="dica">
                        <i class="fi fi-rr-check-circle"></i>
                        <p>
                            <strong>Estoque em dia</strong>
                            Atualize a quantidade sempre que vender ou repor o produto.
                        </p>
                    </div>

                    <div class="dica">
                        <i class="fi fi-rr-check-circle"></i>
                        <p>
                            <strong>Preço atualizado</strong>
                            Confira se o valor ainda está correto antes de salvar.
                        </p>
                    </div>

                    <div class="dica">
                        <i class="fi fi-rr-check-circle"></i>
                        <p>
                            <strong>Descrição completa</strong>
                            Informe características importantes para os consumidores.
                        </p>
                    </div>

                    <div class="dica">
                        <i class="fi fi-rr-check-circle"></i>
                        <p>
                            <strong>Foto do produto</strong>
                            Se enviar uma nova imagem, a anterior será substituída.
                        </p>
                    </div>

                </div>

                <div class="aviso-lateral">

                    <i class="fi fi-rr-info"></i>

                    <p>
                        As alterações ficam visíveis no catálogo do ClickFeira
                        assim que você salvar.
                    </p>

                </div>

            </aside>

        </div>

    </main>


    <footer>

        <p>
            © <?= date("Y") ?> ClickFeira Assaré.
            Conectando vendedores e consumidores.
        </p>

    </footer>


    <script>
        const campoFoto = document.getElementById("product_photo");
        const previewContainer = document.getElementById("preview-container");
        const previewImagem = document.getElementById("preview-imagem");

        campoFoto.addEventListener("change", function () {

            const arquivo = this.files[0];

            if (!arquivo) {
                previewContainer.style.display = "none";
                previewImagem.src = "";
                return;
            }

            const tiposPermitidos = [
                "image/jpeg",
                "image/png",
                "image/webp"
            ];

            if (!tiposPermitidos.includes(arquivo.type)) {
                alert("Selecione uma imagem JPG, PNG ou WEBP.");
                this.value = "";
                previewContainer.style.display = "none";
                previewImagem.src = "";
                return;
            }

            if (arquivo.size > 2 * 1024 * 1024) {
                alert("A imagem deve ter no máximo 2 MB.");
                this.value = "";
                previewContainer.style.display = "none";
                previewImagem.src = "";
                return;
            }

            const leitor = new FileReader();

            leitor.onload = function (evento) {
                previewImagem.src = evento.target.result;
                previewContainer.style.display = "block";
            };

            leitor.readAsDataURL(arquivo);
        });
    </script>

</body>

</html>