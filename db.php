<?php 
    $host = "localhost";
    $username = "root";
    $password = "";
    $database = "jilz_perfume_bar";

    $conn = mysqli_connect($host, $username, $password, $database);

    if (!$conn) {
        die("Connection failed! <br>
             Error number: " . mysqli_connect_errno() . "<br>
             Error message: " . mysqli_connect_error()
        );
    }
?>
