<?php

    $host = "localhost";
    $dbname = "mydb";
    $username = "root";
    $password = "root";

    $conn = new mysqli($host, $username, $password, $dbname);

    if ($conn->connect_error) {
        die("Erro na conexão: " . $conn->connect_error);
    }

    //echo "Conexão com o banco realizada com sucesso!";

?>