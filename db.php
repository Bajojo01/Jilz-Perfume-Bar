<?php 
    $host = "autorack.proxy.rlwy.net";
    $username = "root";
    $password = "HbeDhrdaesrLUVfjlagZkPGKQNtKHtGz";
    $database = "railway";
    $port = 48427; // <-- Must define the port

    $conn = mysqli_connect($host, $username, $password, $database, $port); 

    if (!$conn) {
        die("Connection failed! <br>
             Error number: " . mysqli_connect_errno() . "<br>
             Error message: " . mysqli_connect_error()
        );
    }
?>
