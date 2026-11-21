<?php

session_start();

if (!isset($_SESSION["id_usuario"])) {
    die("Você precisa estar logado para acessar esta página.");
}

require_once "../config/database.php";

$user_id = (int) $_SESSION["id_usuario"];
$user_type = $_SESSION["tipo_usuario"];

$mensagem = "";
$tipo_mensagem = "sucesso";


// PROCESSAMENTO AUTOMÁTICO DA LOCALIZAÇÃO.

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["salvar_localizacao_ajax"])
) {
    header("Content-Type: application/json; charset=UTF-8");

    if ($user_type !== "seller") {
        echo json_encode([
            "sucesso" => false,
            "mensagem" => "Apenas vendedores podem alterar a localização."
        ]);
        exit;
    }

    $localizacao = trim($_POST["stall_location"] ?? "");

    if ($localizacao === "") {
        echo json_encode([
            "sucesso" => false,
            "mensagem" => "A localização não pode ficar vazia."
        ]);
        exit;
    }

    if (strlen($localizacao) > 255) {
        echo json_encode([
            "sucesso" => false,
            "mensagem" => "A localização deve ter no máximo 255 caracteres."
        ]);
        exit;
    }

    $sql = "UPDATE users SET stall_location = ? WHERE id_user = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $localizacao, $user_id);

    if ($stmt->execute()) {
        echo json_encode([
            "sucesso" => true,
            "mensagem" => "Localização salva automaticamente."
        ]);
    } else {
        echo json_encode([
            "sucesso" => false,
            "mensagem" => "Erro ao salvar a localização."
        ]);
    }

    $stmt->close();
    exit;
}


// PROCESSAMENTO DO ENVIO DAS IMAGENS.

if (
    $_SERVER["REQUEST_METHOD"] === "POST" &&
    isset($_POST["tipo_foto"])
) {
    $campo = $_POST["tipo_foto"] ?? "";

    $configuracoes = [
        "perfil" => [
            "campo" => "profile_photo",
            "pasta" => "../uploads/profiles/",
            "mensagem" => "Foto de perfil"
        ],
        "banca" => [
            "campo" => "stall_photo",
            "pasta" => "../uploads/stalls/",
            "mensagem" => "Foto da banca"
        ]
    ];

    if (!isset($configuracoes[$campo])) {
        die("Tipo de imagem inválido.");
    }

    if ($campo === "banca" && $user_type !== "seller") {
        die("Apenas vendedores podem cadastrar foto da banca.");
    }

    $config = $configuracoes[$campo];
    $nome_coluna = $config["campo"];
    $pasta = $config["pasta"];

    if (
        !isset($_FILES["foto"]) ||
        $_FILES["foto"]["error"] !== UPLOAD_ERR_OK
    ) {
        $mensagem = "Selecione uma imagem válida.";
        $tipo_mensagem = "erro";
    } elseif ($_FILES["foto"]["size"] > 2 * 1024 * 1024) {
        $mensagem = "A imagem deve ter no máximo 2 MB.";
        $tipo_mensagem = "erro";
    } else {
        $arquivo = $_FILES["foto"];

        $tipos_permitidos = [
            "image/jpeg" => "jpg",
            "image/png" => "png",
            "image/webp" => "webp"
        ];

        $imagem = @getimagesize($arquivo["tmp_name"]);
        $tipo = $imagem["mime"] ?? "";

        if (!isset($tipos_permitidos[$tipo])) {
            $mensagem = "Formato inválido. Use JPG, PNG ou WEBP.";
            $tipo_mensagem = "erro";
        } else {
            $extensao = $tipos_permitidos[$tipo];

            $nome_arquivo = bin2hex(random_bytes(16)) . "." . $extensao;
            $caminho = $pasta . $nome_arquivo;

            if (move_uploaded_file($arquivo["tmp_name"], $caminho)) {
                $sql = "SELECT $nome_coluna FROM users WHERE id_user = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("i", $user_id);
                $stmt->execute();

                $anterior = $stmt->get_result()->fetch_assoc();
                $stmt->close();

                $sql = "UPDATE users SET $nome_coluna = ? WHERE id_user = ?";
                $stmt = $conn->prepare($sql);
                $stmt->bind_param("si", $nome_arquivo, $user_id);

                if ($stmt->execute()) {
                    if (!empty($anterior[$nome_coluna])) {
                        $arquivo_antigo = $pasta .
                            basename($anterior[$nome_coluna]);

                        if (is_file($arquivo_antigo)) {
                            unlink($arquivo_antigo);
                        }
                    }

                    $mensagem = $config["mensagem"] .
                        " atualizada com sucesso!";
                } else {
                    unlink($caminho);

                    $mensagem = "Erro ao atualizar a imagem.";
                    $tipo_mensagem = "erro";
                }

                $stmt->close();
            } else {
                $mensagem = "Não foi possível salvar a imagem.";
                $tipo_mensagem = "erro";
            }
        }
    }
}


// BUSCA OS DADOS ATUALIZADOS DO USUÁRIO.

$sql = "SELECT name, email, phone, user_type,
               profile_photo, stall_photo, stall_location
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

$stmt->close();


function escapar($valor) {
    return htmlspecialchars((string) ($valor ?? ""), ENT_QUOTES, "UTF-8");
}

$is_seller = $usuario["user_type"] === "seller";

$foto_perfil_url = !empty($usuario["profile_photo"])
    ? "../uploads/profiles/" . rawurlencode(basename($usuario["profile_photo"]))
    : null;

$foto_banca_url = !empty($usuario["stall_photo"])
    ? "../uploads/stalls/" . rawurlencode(basename($usuario["stall_photo"]))
    : null;

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Meu perfil - ClickFeira Assaré</title>

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
            line-height: 1.6;
        }


        /* MENSAGENS */

        .mensagem {
            padding: 15px 18px;
            border-radius: 10px;
            margin-bottom: 22px;
            font-size: 13px;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .mensagem.sucesso {
            color: var(--verde-escuro);
            background: rgba(121, 191, 0, 0.12);
            border: 1px solid rgba(121, 191, 0, 0.35);
        }

        .mensagem.erro {
            color: #b00020;
            background: #fff0f0;
            border: 1px solid #f3cccc;
        }


        /* PERFIL PRINCIPAL */

        .perfil-destaque {
            background: var(--branco);
            border: 1px solid var(--borda);
            border-radius: 13px;
            overflow: hidden;
            box-shadow: var(--sombra);
            margin-bottom: 25px;
        }

        .capa-perfil {
            height: 155px;
            background:
                radial-gradient(circle at 85% 20%, rgba(255,255,255,0.16) 0 70px, transparent 71px),
                radial-gradient(circle at 72% 110%, rgba(255,255,255,0.12) 0 120px, transparent 121px),
                linear-gradient(115deg, #174c32, #3f8a2a 60%, #79bf00);
        }

        .perfil-conteudo {
            padding: 0 32px 27px;
            display: flex;
            align-items: flex-end;
            gap: 22px;
        }


        /* AVATAR CLICÁVEL */

        .form-avatar {
            margin-top: -58px;
            flex-shrink: 0;
        }

        .avatar {
            width: 125px;
            height: 125px;
            position: relative;
            border-radius: 50%;
            border: 5px solid var(--branco);
            background: var(--creme);
            display: grid;
            place-items: center;
            overflow: hidden;
            box-shadow: 0 3px 12px rgba(0,0,0,0.10);
            cursor: pointer;
        }

        .avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .avatar-placeholder {
            font-size: 43px;
            color: var(--verde-escuro);
        }

        .avatar-overlay {
            position: absolute;
            inset: 0;
            background: rgba(0, 0, 0, 0.45);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            opacity: 0;
            transition: 0.2s;
        }

        .avatar-overlay i {
            font-size: 27px;
        }

        .avatar:hover .avatar-overlay {
            opacity: 1;
        }

        .input-foto {
            display: none;
        }

        .dados-destaque {
            flex: 1;
            padding: 10px 0 3px;
            min-width: 0;
        }

        .dados-destaque h2 {
            font-size: 25px;
            margin-bottom: 6px;
            overflow-wrap: anywhere;
        }

        .dados-destaque p {
            color: var(--texto-suave);
            font-size: 13px;
            overflow-wrap: anywhere;
        }

        .tipo-conta {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(121, 191, 0, 0.15);
            color: var(--verde-escuro);
            border-radius: 20px;
            padding: 9px 14px;
            font-size: 12px;
            font-weight: 700;
            white-space: nowrap;
            margin-bottom: 5px;
        }


        /* CARTÕES */

        .grade-perfil {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 22px;
            align-items: start;
        }

        .cartao {
            background: var(--branco);
            border: 1px solid var(--borda);
            border-radius: 13px;
            padding: 25px;
            box-shadow: var(--sombra);
            min-width: 0;
        }

        .cartao-titulo {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 8px;
        }

        .icone-secao {
            width: 41px;
            height: 41px;
            display: grid;
            place-items: center;
            background: rgba(121, 191, 0, 0.15);
            color: var(--verde-escuro);
            border-radius: 11px;
            font-size: 18px;
            flex-shrink: 0;
        }

        .cartao-titulo h3 {
            font-size: 18px;
        }

        .cartao-descricao {
            color: var(--texto-suave);
            font-size: 12px;
            line-height: 1.6;
            margin-bottom: 22px;
        }


        /* INFORMAÇÕES */

        .informacao {
            padding: 15px 0;
            border-bottom: 1px solid var(--borda);
        }

        .informacao:first-of-type {
            padding-top: 5px;
        }

        .informacao:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }

        .informacao label {
            display: block;
            color: var(--texto-suave);
            font-size: 12px;
            margin-bottom: 7px;
        }

        .informacao strong {
            display: block;
            font-size: 14px;
            font-weight: 600;
            overflow-wrap: anywhere;
        }

        .tipo-usuario {
            display: inline-block;
            color: var(--verde-escuro);
            background: rgba(121, 191, 0, 0.15);
            padding: 6px 10px;
            border-radius: 7px;
            font-size: 12px;
        }


        /* BANCA */

        .banca-foto-container {
            position: relative;
            margin-bottom: 18px;
        }

        .banca-foto {
            width: 100%;
            height: 210px;
            border-radius: 11px;
            object-fit: cover;
            border: 1px solid var(--borda);
            display: block;
        }

        .banca-foto-placeholder {
            width: 100%;
            height: 210px;
            border-radius: 11px;
            background: #fffdf8;
            border: 2px dashed #d9d4c3;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            gap: 12px;
            color: var(--texto-suave);
        }

        .banca-foto-placeholder i {
            font-size: 38px;
            color: var(--verde);
        }

        .banca-foto-placeholder p {
            font-size: 12px;
        }

        .banca-foto-overlay {
            position: absolute;
            inset: 0;
            border-radius: 11px;
            background: rgba(0,0,0,0.45);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            font-size: 13px;
            font-weight: 700;
            opacity: 0;
            transition: 0.2s;
            cursor: pointer;
            z-index: 1;
        }

        .banca-foto-overlay i {
            font-size: 19px;
        }

        .banca-foto-container:hover .banca-foto-overlay {
            opacity: 1;
        }


        /* LOCALIZAÇÃO */

        .campo-localizacao {
            margin-top: 25px;
            padding-top: 22px;
            border-top: 1px solid var(--borda);
        }

        .campo-localizacao label {
            display: flex;
            align-items: center;
            gap: 9px;
            font-size: 12px;
            font-weight: bold;
            color: var(--texto-suave);
            margin-bottom: 10px;
        }

        .campo-localizacao label i {
            color: var(--verde);
            font-size: 17px;
        }

        .campo-localizacao input {
            width: 100%;
            padding: 13px 14px;
            border: 1px solid var(--borda);
            border-radius: 7px;
            background: #fffdf8;
            color: var(--texto);
            outline: none;
            font-size: 13px;
            transition: 0.2s;
        }

        .campo-localizacao input:focus {
            border-color: var(--verde);
            box-shadow: 0 0 0 3px rgba(121, 191, 0, 0.15);
        }

        .campo-localizacao input::placeholder {
            color: #a1a69b;
        }

        .status-localizacao {
            display: flex;
            align-items: center;
            gap: 7px;
            margin-top: 10px;
            min-height: 17px;
            font-size: 11px;
            color: var(--texto-suave);
        }

        .status-localizacao i {
            font-size: 13px;
        }

        .status-localizacao.salvo {
            color: var(--verde-escuro);
        }

        .status-localizacao.erro {
            color: #b00020;
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

            .grade-perfil {
                gap: 15px;
            }

            .cartao {
                padding: 20px;
            }

        }

        @media (max-width: 700px) {

            .grade-perfil {
                grid-template-columns: 1fr;
            }

            .perfil-conteudo {
                flex-wrap: wrap;
                align-items: center;
                padding: 0 22px 23px;
            }

        }

        @media (max-width: 550px) {

            .container {
                margin-top: 25px;
            }

            .titulo-pagina h1 {
                font-size: 24px;
            }

            .capa-perfil {
                height: 115px;
            }

            .perfil-conteudo {
                display: grid;
                grid-template-columns: 100px minmax(0, 1fr);
                grid-template-areas:
                    "avatar dados"
                    "tipo tipo";
                align-items: center;
                column-gap: 16px;
                row-gap: 12px;
                padding: 0 22px 23px;
            }

            .form-avatar {
                grid-area: avatar;
                margin-top: -45px;
            }

            .avatar {
                width: 100px;
                height: 100px;
            }

            .dados-destaque {
                grid-area: dados;
                min-width: 0;
                padding-top: 8px;
            }

            .dados-destaque h2 {
                font-size: 21px;
                overflow-wrap: break-word;
            }

            .dados-destaque p {
                overflow-wrap: anywhere;
            }

            .tipo-conta {
                grid-area: tipo;
                justify-self: start;
                margin-top: 0;
            }

            .cartao {
                padding: 19px;
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
                    href="profile.php"
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

                <?php if ($is_seller): ?>

                    <a href="../products/my_products.php">Meus produtos</a>
                    <a href="../products/register.php">Cadastrar produto</a>
                    <a href="../orders/list.php">Encomendas recebidas</a>

                <?php else: ?>

                    <a href="../orders/my_orders.php">Minhas encomendas</a>

                <?php endif; ?>

                <a href="logout.php" class="sair">Sair</a>

            </div>

        </nav>

    </header>


    <main class="container">

        <div class="titulo-pagina">

            <h1>Meu perfil</h1>

            <p>
                Gerencie suas informações e personalize sua presença no ClickFeira.
            </p>

        </div>


        <?php if ($mensagem !== ""): ?>

            <div class="mensagem <?= escapar($tipo_mensagem) ?>">

                <i class="fi <?= $tipo_mensagem === "sucesso" ? "fi-rr-check-circle" : "fi-rr-exclamation" ?>"></i>

                <?= escapar($mensagem) ?>

            </div>

        <?php endif; ?>


        <!-- PERFIL PRINCIPAL -->

        <section class="perfil-destaque">

            <div class="capa-perfil"></div>

            <div class="perfil-conteudo">

                <!-- FOTO DE PERFIL CLICÁVEL -->

                <form
                    class="form-avatar"
                    method="POST"
                    enctype="multipart/form-data"
                    id="form-perfil"
                >

                    <input type="hidden" name="tipo_foto" value="perfil">

                    <label
                        class="avatar"
                        for="foto-perfil"
                        title="Alterar foto de perfil"
                    >

                        <?php if ($foto_perfil_url): ?>

                            <img
                                src="<?= escapar($foto_perfil_url) ?>"
                                alt="Foto de perfil"
                            >

                        <?php else: ?>

                            <i class="fi fi-rr-user avatar-placeholder"></i>

                        <?php endif; ?>

                        <span class="avatar-overlay">
                            <i class="fi fi-rr-camera"></i>
                        </span>

                    </label>

                    <input
                        type="file"
                        class="input-foto"
                        id="foto-perfil"
                        name="foto"
                        accept="image/jpeg,image/png,image/webp"
                        required
                    >

                </form>


                <div class="dados-destaque">

                    <h2><?= escapar($usuario["name"]) ?></h2>

                    <p><?= escapar($usuario["email"]) ?></p>

                </div>


                <span class="tipo-conta">

                    <i class="fi <?= $is_seller ? "fi-rr-shop" : "fi-rr-shopping-bag" ?>"></i>

                    <?= $is_seller ? "Vendedor" : "Consumidor" ?>

                </span>

            </div>

        </section>


        <div class="grade-perfil">


            <!-- INFORMAÇÕES DA CONTA -->

            <section class="cartao">

                <div class="cartao-titulo">

                    <div class="icone-secao">
                        <i class="fi fi-rr-user"></i>
                    </div>

                    <h3>Informações da conta</h3>

                </div>

                <p class="cartao-descricao">
                    Seus dados cadastrados na plataforma.
                </p>


                <div class="informacao">

                    <label>Nome completo</label>

                    <strong>
                        <?= escapar($usuario["name"]) ?>
                    </strong>

                </div>


                <div class="informacao">

                    <label>E-mail</label>

                    <strong>
                        <?= escapar($usuario["email"]) ?>
                    </strong>

                </div>


                <div class="informacao">

                    <label>Telefone</label>

                    <strong>
                        <?= !empty($usuario["phone"]) ? escapar($usuario["phone"]) : "Não informado" ?>
                    </strong>

                </div>


                <div class="informacao">

                    <label>Tipo de conta</label>

                    <strong>

                        <span class="tipo-usuario">
                            <?= $is_seller ? "Vendedor" : "Consumidor" ?>
                        </span>

                    </strong>

                </div>

            </section>


            <?php if ($is_seller): ?>


                <!-- MINHA BANCA -->

                <section class="cartao">

                    <div class="cartao-titulo">

                        <div class="icone-secao">
                            <i class="fi fi-rr-shop"></i>
                        </div>

                        <h3>Minha banca</h3>

                    </div>

                    <p class="cartao-descricao">
                        Personalize a apresentação da sua banca para os consumidores.
                    </p>


                    <!-- FOTO DA BANCA CLICÁVEL -->

                    <form
                        method="POST"
                        enctype="multipart/form-data"
                        id="form-banca"
                    >

                        <input type="hidden" name="tipo_foto" value="banca">

                        <input
                            type="file"
                            class="input-foto"
                            id="foto-banca"
                            name="foto"
                            accept="image/jpeg,image/png,image/webp"
                            required
                        >

                        <div class="banca-foto-container">

                            <label
                                for="foto-banca"
                                class="banca-foto-overlay"
                            >

                                <i class="fi fi-rr-camera"></i>

                                Alterar foto da banca

                            </label>


                            <?php if ($foto_banca_url): ?>

                                <img
                                    src="<?= escapar($foto_banca_url) ?>"
                                    alt="Foto da banca"
                                    class="banca-foto"
                                >

                            <?php else: ?>

                                <div class="banca-foto-placeholder">

                                    <i class="fi fi-rr-shop"></i>

                                    <p>Adicione uma foto para sua banca</p>

                                </div>

                            <?php endif; ?>

                        </div>

                    </form>


                    <p class="cartao-descricao">
                        Clique na imagem para selecionar outra foto. JPG, PNG ou WEBP, até 2 MB.
                    </p>


                    <!-- LOCALIZAÇÃO AUTOMÁTICA -->

                    <div class="campo-localizacao">

                        <label for="stall_location">

                            <i class="fi fi-rr-marker"></i>

                            Localização da banca

                        </label>


                        <input
                            type="text"
                            id="stall_location"
                            maxlength="255"
                            value="<?= escapar($usuario["stall_location"] ?? "") ?>"
                            placeholder="Ex.: Corredor A, próximo à entrada"
                            autocomplete="off"
                        >


                        <div
                            class="status-localizacao"
                            id="status-localizacao"
                        >

                            <i class="fi fi-rr-info"></i>

                            As alterações serão salvas automaticamente.

                        </div>

                    </div>

                </section>

            <?php endif; ?>


        </div>

    </main>


    <footer>

        <p>
            © <?= date("Y") ?> ClickFeira Assaré.
            Conectando vendedores e consumidores.
        </p>

    </footer>


    <script>

        // ENVIO AUTOMÁTICO DAS FOTOS.

        const inputPerfil = document.getElementById("foto-perfil");
        const inputBanca = document.getElementById("foto-banca");


        function configurarUpload(input, formulario) {

            if (!input || !formulario) return;

            input.addEventListener("change", function () {

                const arquivo = this.files[0];

                if (!arquivo) return;

                const tiposPermitidos = [
                    "image/jpeg",
                    "image/png",
                    "image/webp"
                ];

                if (!tiposPermitidos.includes(arquivo.type)) {
                    alert("Selecione uma imagem JPG, PNG ou WEBP.");
                    this.value = "";
                    return;
                }

                if (arquivo.size > 2 * 1024 * 1024) {
                    alert("A imagem deve ter no máximo 2 MB.");
                    this.value = "";
                    return;
                }

                formulario.submit();

            });

        }


        configurarUpload(
            inputPerfil,
            document.getElementById("form-perfil")
        );

        configurarUpload(
            inputBanca,
            document.getElementById("form-banca")
        );


        // SALVAMENTO AUTOMÁTICO DA LOCALIZAÇÃO.

        const campoLocalizacao = document.getElementById("stall_location");
        const statusLocalizacao = document.getElementById("status-localizacao");

        let temporizadorLocalizacao;

        let ultimaLocalizacaoSalva = campoLocalizacao
            ? campoLocalizacao.value.trim()
            : "";

        let requisicaoLocalizacao = null;


        function atualizarStatusLocalizacao(mensagem, tipo = "") {

            if (!statusLocalizacao) return;

            statusLocalizacao.className = "status-localizacao";

            if (tipo) {
                statusLocalizacao.classList.add(tipo);
            }

            let icone = "fi-rr-info";

            if (tipo === "salvo") {
                icone = "fi-rr-check-circle";
            }

            if (tipo === "erro") {
                icone = "fi-rr-exclamation";
            }

            if (mensagem === "Salvando...") {
                icone = "fi-rr-time";
            }

            statusLocalizacao.innerHTML =
                `<i class="fi ${icone}"></i> ${mensagem}`;

        }


        async function salvarLocalizacao() {

            if (!campoLocalizacao) return;

            const localizacao = campoLocalizacao.value.trim();

            if (localizacao === ultimaLocalizacaoSalva) {
                return;
            }

            if (localizacao === "") {
                atualizarStatusLocalizacao(
                    "Informe uma localização para salvar.",
                    "erro"
                );
                return;
            }

            if (localizacao.length > 255) {
                atualizarStatusLocalizacao(
                    "A localização deve ter no máximo 255 caracteres.",
                    "erro"
                );
                return;
            }

            if (requisicaoLocalizacao) {
                requisicaoLocalizacao.abort();
            }

            requisicaoLocalizacao = new AbortController();

            atualizarStatusLocalizacao("Salvando...");

            const dados = new FormData();

            dados.append("salvar_localizacao_ajax", "1");
            dados.append("stall_location", localizacao);

            try {

                const resposta = await fetch(window.location.href, {
                    method: "POST",
                    body: dados,
                    signal: requisicaoLocalizacao.signal
                });

                const resultado = await resposta.json();

                if (resultado.sucesso) {

                    ultimaLocalizacaoSalva = localizacao;

                    atualizarStatusLocalizacao(
                        resultado.mensagem,
                        "salvo"
                    );

                } else {

                    atualizarStatusLocalizacao(
                        resultado.mensagem,
                        "erro"
                    );

                }

            } catch (erro) {

                if (erro.name !== "AbortError") {
                    atualizarStatusLocalizacao(
                        "Não foi possível salvar. Tente novamente.",
                        "erro"
                    );
                }

            }

        }


        if (campoLocalizacao) {

            campoLocalizacao.addEventListener("input", function () {

                clearTimeout(temporizadorLocalizacao);

                atualizarStatusLocalizacao("Aguardando alterações...");

                temporizadorLocalizacao = setTimeout(
                    salvarLocalizacao,
                    800
                );

            });


            campoLocalizacao.addEventListener("blur", function () {

                clearTimeout(temporizadorLocalizacao);

                salvarLocalizacao();

            });

        }

    </script>

</body>

</html>