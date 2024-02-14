<?php


function getVisitante($recordPage='index')
{
    $db_host = "localhost";
    $db_username = "userBD";
    $db_password = "passBD";
    $db_name = "admin_BD";
    $db_table = "conter";
    $counter_page = $recordPage;

    $db = mysqli_connect ($db_host, $db_username, $db_password, $db_name) or die("Host o base de datos no disponible");
    $sql_call = " UPDATE $db_table set num = num+1 where page='$counter_page' ";
    mysqli_query($db, $sql_call) or die("Error al introducir los datos");

    $sql_call = "SELECT num FROM ".$db_table." WHERE page='$counter_page' ";
    $sql_result = mysqli_query($db, $sql_call) or die("Error en la petición SQL");
    $row = mysqli_fetch_assoc($sql_result);
    $x = $row['num'];

    mysqli_close($db);
    return $x;
}

echo "-->".getVisitante('index');
