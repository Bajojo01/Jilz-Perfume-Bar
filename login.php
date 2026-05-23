<?php
session_start();
require("db.php");

$errorMsg = '';

if (isset($_POST['login'])) {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    if (empty($username) || empty($password)) {
        $errorMsg = 'Please enter both username and password.';
    } else {
        $stmtAdmin = mysqli_prepare($conn, "SELECT Admin_ID_PK, Username, Pass FROM Admin_Information WHERE Username = ?");
        mysqli_stmt_bind_param($stmtAdmin, "s", $username);
        mysqli_stmt_execute($stmtAdmin);
        mysqli_stmt_bind_result($stmtAdmin, $AdminID, $AdminUsername, $AdminPass);
        $adminFound = mysqli_stmt_fetch($stmtAdmin);
        mysqli_stmt_close($stmtAdmin);

        if ($adminFound && password_verify($password, $AdminPass)) {
            $_SESSION['is_admin'] = true;
            $_SESSION['admin_id'] = $AdminID;
            $_SESSION['admin_user'] = $AdminUsername;

            header("Location: bookingconfirmation.php");
            exit();
        }

        $stmtUser = mysqli_prepare($conn, "SELECT User_ID_PK, Username, Pass FROM User_Information WHERE Username = ?");
        mysqli_stmt_bind_param($stmtUser, "s", $username);
        mysqli_stmt_execute($stmtUser);
        mysqli_stmt_bind_result($stmtUser, $UserID, $UserUsername, $UserPass);
        $userFound = mysqli_stmt_fetch($stmtUser);
        mysqli_stmt_close($stmtUser);

        if ($userFound && password_verify($password, $UserPass)) {
            $_SESSION['UserID'] = $UserID;
            $_SESSION['Username'] = $UserUsername;
            header("Location: index.php");
            exit();
        } else {
            $errorMsg = 'Incorrect username or password.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jilz | Login</title>
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
        <a href="index.php">
            ← Back to Home Page
        </a>
    </div>

    <div class="loginsignupheader">
        <h2>Log in to Book your Signature Perfume Experience.</h2>
    </div>

    <div class="loginsignup">
        <h1>Welcome Back!</h1>
        <form action="" method="post" id="loginForm">

            <!-- ERROR BANNER -->
            <?php if (!empty($errorMsg)): ?>
                <div class="loginErrorBanner">
                    <div class="loginErrorIconWrapper">
                        <span>!</span>
                    </div>
                    <p><?php echo htmlspecialchars($errorMsg); ?></p>
                </div>
            <?php endif; ?>

            <!-- USERNAME -->
            <label for="username" class="formlabel">Username</label>
            <input id="username" class="username <?php echo (!empty($errorMsg)) ? 'inputError' : ''; ?>" type="text"
                name="username" placeholder="Enter your username"
                value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>" required>

            <!-- PASSWORD -->
            <label for="password" class="formlabel">Password</label>
            <input id="password" class="password <?php echo (!empty($errorMsg)) ? 'inputError' : ''; ?>" type="password"
                name="password" placeholder="Enter your password" required>

            <!-- BUTTON -->
            <button type="submit" class="loginsignupbtn" name="login">Login</button>

            <!-- SIGNUP LINK -->
            <div class="gosignup">
                <p>Don't have an account? <a href="signup.php">Sign up here</a></p>
            </div>
        </form>
    </div>
</body>

</html>