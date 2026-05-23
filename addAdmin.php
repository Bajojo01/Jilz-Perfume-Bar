<?php
session_start();
require("db.php");

// ======================secure======================
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

if (isset($_POST['signup'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    $conpassword = trim($_POST['conpassword']);

    if (empty($username) || empty($password) || empty($conpassword)) {
        echo "<script>alert('All fields are required!');</script>";
    } elseif ($password !== $conpassword) {
        echo "<script>alert('Passwords do not match!');</script>";
    } else {
        $checkSql = "SELECT Admin_ID_PK FROM Admin_Information WHERE Username = ?";
        $checkStmt = mysqli_prepare($conn, $checkSql);
        mysqli_stmt_bind_param($checkStmt, "s", $username);
        mysqli_stmt_execute($checkStmt);
        mysqli_stmt_store_result($checkStmt);

        if (mysqli_stmt_num_rows($checkStmt) > 0) {
            echo "<script>alert('Admin username already taken!');</script>";
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);

            $insertSql = "INSERT INTO Admin_Information (Username, Pass) VALUES (?, ?)";
            $insertStmt = mysqli_prepare($conn, $insertSql);
            mysqli_stmt_bind_param($insertStmt, "ss", $username, $hashedPassword);

            if (mysqli_stmt_execute($insertStmt)) {
                echo "<script>alert('Admin signup successful! Redirecting to login...'); window.location.href='login.php';</script>";
            } else {
                echo "<script>alert('Error during admin signup. Please try again.');</script>";
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jilz | Admin Sign Up</title>
    <link rel="shortcut icon" href="assets/Logo_Tentative.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="mobileStyle.css">
</head>

<body class="loginsignupBG">

    <div class="loginsignuplogo">
        <img src="assets/Logo_Tentative.png">
        <?php
        $defaultBack = 'index.php';
        $back = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : $defaultBack;
        ?>
        <a href="<?php echo htmlspecialchars($back); ?>"
            onclick="if(this.href === window.location.href) history.back(); return true;">
            ← Back
        </a>

    </div>

    <div class="loginsignupheader">
        <h1>Admin Registration Panel</h1>
    </div>

    <div class="loginsignup">
        <h1>Admin Sign Up</h1>

        <form action="" method="POST">

            <p class="usernamee">Username</p>
            <input class="username" type="text" name="username">

            <p class="passwordd">Password</p>
            <input class="password" type="password" name="password">

            <p class="conpasss">Confirm Password</p>
            <input class="confirmpassword" type="password" name="conpassword">

            <p></p>

            <button class="loginsignupbtn" name="signup">Sign Up</button>

            <div class="gosignup">
                <p>Already have an admin account? <a href="login.php">Log in here</a></p>
            </div>

        </form>

    </div>

</body>

</html>