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

if (
    !isset($_GET["id"]) ||
    !filter_var($_GET["id"], FILTER_VALIDATE_INT)
) {
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

// Verifica se o produto pertence ao vendedor.
$sql = "SELECT id_product, name
        FROM products
        WHERE id_product = ? AND user_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("ii", $product_id, $seller_id);
$stmt->execute();

$resultado = $stmt->get_result();
$produto = $resultado->fetch_assoc();

$stmt->close();

if (!$produto) {
    die("Produto não encontrado ou você não tem permissão.");
}

$mensagem = "";
$sucesso = false;

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    // Verifica se existem encomendas relacionadas ao produto.
    $sql = "SELECT id_item
            FROM order_items
            WHERE product_id = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("i", $product_id);
    $stmt->execute();

    $possui_encomendas = $stmt->get_result()->num_rows > 0;

    $stmt->close();

    if ($possui_encomendas) {

        $mensagem = "Este produto possui encomendas registradas e não pode ser excluído.";

    } else {

        $sql = "DELETE FROM products
                WHERE id_product = ? AND user_id = ?";

        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ii", $product_id, $seller_id);

        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $mensagem = "Produto excluído com sucesso.";
            $sucesso = true;
        } else {
            $mensagem = "Não foi possível excluir o produto.";
        }

        $stmt->close();
    }
}

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Excluir produto - ClickFeira Assaré</title>

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

        /* CARTÃO DE CONFIRMAÇÃO */

        .cartao-exclusao {
            max-width: 560px;
            margin: 0 auto;
            background: var(--branco);
            border: 1px solid var(--borda);
            border-radius: 13px;
            padding: 38px 32px;
            box-shadow: var(--sombra);
            text-align: center;
        }

        .icone-estado {
            width: 68px;
            height: 68px;
            margin: 0 auto 20px;
            border-radius: 50%;
            display: grid;
            place-items: center;
            font-size: 30px;
        }

        .icone-estado.perigo {
            background: #fff0ef;
            color: #b12c2c;
        }

        .icone-estado.sucesso {
            background: rgba(121, 191, 0, 0.15);
            color: var(--verde-escuro);
        }

        .icone-estado.aviso {
            background: rgba(242, 193, 78, 0.22);
            color: #8a6410;
        }

        .cartao-exclusao h2 {
            font-size: 23px;
            margin-bottom: 12px;
        }

        .cartao-exclusao p {
            color: var(--texto-suave);
            font-size: 14px;
            line-height: 1.6;
        }

        .nome-produto {
            display: inline-block;
            margin-top: 18px;
            padding: 11px 18px;
            border-radius: 9px;
            background: var(--creme);
            border: 1px solid #f0e0b8;
            color: #6b4f0e;
            font-size: 15px;
            font-weight: 600;
            overflow-wrap: anywhere;
            max-width: 100%;
        }

        .aviso-irreversivel {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            margin-top: 18px;
            font-size: 12px;
            color: #b12c2c;
            font-weight: 600;
        }

        .botoes {
            display: flex;
            justify-content: center;
            gap: 12px;
            margin-top: 30px;
            padding-top: 24px;
            border-top: 1px solid var(--borda);
        }

        .botoes form {
            margin: 0;
        }

        .botao {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            min-height: 43px;
            padding: 0 20px;
            border: none;
            border-radius: 7px;
            font-size: 13px;
            font-weight: bold;
            cursor: pointer;
            transition: 0.2s;
        }

        .botao-cancelar {
            background: #f0ede3;
            color: var(--texto-suave);
        }

        .botao-cancelar:hover {
            background: var(--creme);
            color: var(--texto);
        }

        .botao-excluir {
            background: #c0392b;
            color: white;
        }

        .botao-excluir:hover {
            background: #962d22;
        }

        .botao-voltar {
            background: var(--verde);
            color: white;
        }

        .botao-voltar:hover {
            background: var(--verde-escuro);
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
        }

        @media (max-width: 550px) {
            .container {
                margin-top: 25px;
            }

            .cabecalho-pagina h1 {
                font-size: 24px;
            }

            .cartao-exclusao {
                padding: 28px 20px;
            }

            .botoes {
                flex-direction: column-reverse;
            }

            .botoes form,
            .botao {
                width: 100%;
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

        <!-- TÍTULO -->

        <section class="cabecalho-pagina">

            <h1>Excluir produto</h1>

            <p>
                Confirme a exclusão do produto da sua banca.
            </p>

        </section>


        <section class="cartao-exclusao">

            <?php if ($mensagem !== ""): ?>

                <!-- RESULTADO -->

                <?php if ($sucesso): ?>

                    <div class="icone-estado sucesso">
                        <i class="fi fi-rr-check-circle"></i>
                    </div>

                    <h2>Tudo certo!</h2>

                <?php else: ?>

                    <div class="icone-estado aviso">
                        <i class="fi fi-rr-exclamation"></i>
                    </div>

                    <h2>Não foi possível excluir</h2>

                <?php endif; ?>

                <p><?= escapar($mensagem) ?></p>

                <div class="botoes">

                    <a href="my_products.php" class="botao botao-voltar">
                        <i class="fi fi-rr-arrow-left"></i>
                        Voltar para meus produtos
                    </a>

                </div>

            <?php else: ?>

                <!-- CONFIRMAÇÃO -->

                <div class="icone-estado perigo">
                    <i class="fi fi-rr-trash"></i>
                </div>

                <h2>Tem certeza que deseja excluir?</h2>

                <p>Você está prestes a excluir o produto:</p>

                <span class="nome-produto">
                    <?= escapar($produto["name"]) ?>
                </span>

                <p class="aviso-irreversivel">
                    <i class="fi fi-rr-info"></i>
                    Esta ação não poderá ser desfeita.
                </p>

                <div class="botoes">

                    <a href="my_products.php" class="botao botao-cancelar">
                        <i class="fi fi-rr-cross-small"></i>
                        Cancelar
                    </a>

                    <form method="POST">
                        <button type="submit" class="botao botao-excluir">
                            <i class="fi fi-rr-trash"></i>
                            Confirmar exclusão
                        </button>
                    </form>

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