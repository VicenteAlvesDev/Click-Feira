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

// Busca os produtos do vendedor.
$sql = "SELECT id_product, name, description, quantity, price, product_photo
        FROM products
        WHERE user_id = ?
        ORDER BY id_product DESC";

$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $seller_id);
$stmt->execute();

$resultado = $stmt->get_result();

$total_produtos = $resultado->num_rows;

?>

<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Meus produtos - ClickFeira Assaré</title>

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
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            margin-bottom: 28px;
        }

        .cabecalho-pagina h1 {
            font-size: 28px;
            margin-bottom: 8px;
        }

        .cabecalho-pagina p {
            color: var(--texto-suave);
            font-size: 14px;
        }

        .botao-novo {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 9px;
            padding: 13px 19px;
            border-radius: 8px;
            background: var(--amarelo);
            color: #49370d;
            font-size: 13px;
            font-weight: bold;
            box-shadow: 0 3px 9px rgba(242, 193, 78, 0.25);
            transition: 0.2s;
            white-space: nowrap;
        }

        .botao-novo:hover {
            background: var(--creme);
            transform: translateY(-2px);
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
            border-radius: 12px;
            background: rgba(121, 191, 0, 0.15);
            display: grid;
            place-items: center;
            font-size: 23px;
            color: var(--verde-escuro);
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
            margin-bottom: 20px;
            gap: 15px;
        }

        .secao-titulo h2 {
            font-size: 21px;
        }

        .secao-titulo span {
            color: var(--texto-suave);
            font-size: 13px;
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
            height: 190px;
            background: #f0f0eb;
            overflow: hidden;
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
            height: 100%;
            display: grid;
            place-items: center;
            font-size: 40px;
            color: #a1a69b;
        }

        .produto-conteudo {
            padding: 16px;
            display: flex;
            flex-direction: column;
            flex: 1;
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
            margin-bottom: 14px;
            overflow-wrap: anywhere;
        }

        .estoque {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 10px 0;
            border-top: 1px solid var(--borda);
            font-size: 12px;
            color: var(--texto-suave);
        }

        .estoque strong {
            color: var(--texto);
        }

        .estoque-baixo {
            color: #c47b19 !important;
        }

        .estoque-zero {
            color: #b12c2c !important;
        }

        .preco {
            color: var(--verde-escuro);
            font-size: 24px;
            font-weight: bold;
            margin: 10px 0 16px;
        }

        /* AÇÕES */

        .acoes {
            display: flex;
            gap: 9px;
            margin-top: auto;
        }

        .botao-editar,
        .botao-excluir {
            flex: 1;
            padding: 11px 8px;
            text-align: center;
            border-radius: 7px;
            font-size: 12px;
            font-weight: bold;
            transition: 0.2s;
        }

        .botao-editar {
            background: rgba(121, 191, 0, 0.15);
            color: var(--verde-escuro);
        }

        .botao-editar:hover {
            background: rgba(121, 191, 0, 0.28);
        }

        .botao-excluir {
            background: #fff0ef;
            color: #b12c2c;
        }

        .botao-excluir:hover {
            background: #ffe0de;
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
            margin-bottom: 22px;
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

            .cabecalho-pagina {
                align-items: flex-start;
                flex-direction: column;
            }

            .cabecalho-pagina h1 {
                font-size: 24px;
            }

            .botao-novo {
                width: 100%;
            }

            .secao-titulo h2 {
                font-size: 18px;
            }

            .grade-produtos {
                grid-template-columns: 1fr;
            }

            .imagem-container {
                height: 230px;
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
                <a href="list.php">Todos os produtos</a>
                <a href="my_products.php" class="ativo">Meus produtos</a>
                <a href="register.php">Cadastrar produto</a>
                <a href="../orders/list.php">Encomendas recebidas</a>
                <a href="../users/logout.php" class="sair">Sair</a>

            </div>

        </nav>

    </header>


    <main class="container">

        <!-- CABEÇALHO DA PÁGINA -->

        <section class="cabecalho-pagina">

            <div>

                <h1>Meus produtos</h1>

                <p>
                    Gerencie seus produtos, preços e estoque.
                </p>

            </div>

            <a href="register.php" class="botao-novo">
                <i class="fi fi-rr-add"></i>
                Cadastrar produto
            </a>

        </section>


        <!-- RESUMO -->

        <section class="resumo">

            <div class="resumo-icone">
                <i class="fi fi-rr-shopping-bag"></i>
            </div>

            <div>

                <div class="resumo-numero">
                    <?= $total_produtos ?>
                </div>

                <div class="resumo-legenda">
                    <?= $total_produtos === 1 ? "Produto cadastrado" : "Produtos cadastrados" ?>
                </div>

            </div>

        </section>


        <!-- PRODUTOS -->

        <section>

            <div class="secao-titulo">

                <h2>Seus produtos cadastrados</h2>

                <span>Mais recentes primeiro</span>

            </div>


            <?php if ($total_produtos > 0): ?>

                <div class="grade-produtos">

                    <?php while ($produto = $resultado->fetch_assoc()): ?>

                        <?php

                        $quantidade = (int) $produto["quantity"];

                        $classe_estoque = "";

                        if ($quantidade === 0) {
                            $classe_estoque = "estoque-zero";
                        } elseif ($quantidade <= 5) {
                            $classe_estoque = "estoque-baixo";
                        }

                        ?>

                        <article class="produto">

                            <div class="imagem-container">

                                <?php if (!empty($produto["product_photo"])): ?>

                                    <img
                                        class="foto-produto"
                                        src="../uploads/products/<?= escapar(rawurlencode(basename($produto["product_photo"]))) ?>"
                                        alt="<?= escapar($produto["name"]) ?>"
                                        loading="lazy"
                                    >

                                <?php else: ?>

                                    <div class="sem-foto">
                                        <i class="fi fi-rr-shopping-bag"></i>
                                    </div>

                                <?php endif; ?>

                            </div>


                            <div class="produto-conteudo">

                                <h3>
                                    <?= escapar($produto["name"]) ?>
                                </h3>

                                <p class="descricao">
                                    <?= nl2br(escapar($produto["description"])) ?>
                                </p>


                                <div class="estoque">

                                    <span>Estoque disponível</span>

                                    <strong class="<?= $classe_estoque ?>">
                                        <?= $quantidade ?>
                                        <?= $quantidade === 1 ? "unidade" : "unidades" ?>
                                    </strong>

                                </div>


                                <p class="preco">
                                    R$ <?= number_format((float) $produto["price"], 2, ",", ".") ?>
                                </p>


                                <div class="acoes">

                                    <a
                                        class="botao-editar"
                                        href="edit.php?id=<?= (int) $produto["id_product"] ?>"
                                    >
                                        Editar
                                    </a>

                                    <a
                                        class="botao-excluir"
                                        href="delete.php?id=<?= (int) $produto["id_product"] ?>"
                                        onclick="return confirm('Tem certeza que deseja excluir este produto?')"
                                    >
                                        Excluir
                                    </a>

                                </div>

                            </div>

                        </article>

                    <?php endwhile; ?>

                </div>

            <?php else: ?>

                <div class="sem-produtos">

                    <div class="icone">
                        <i class="fi fi-rr-shopping-cart"></i>
                    </div>

                    <h3>Você ainda não possui produtos</h3>

                    <p>
                        Comece cadastrando seu primeiro produto para disponibilizá-lo na feira.
                    </p>

                    <a href="register.php" class="botao-novo">
                        <i class="fi fi-rr-add"></i>
                        Cadastrar meu primeiro produto
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