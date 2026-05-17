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
        $sqlUser = "SELECT User_ID_PK, Pass FROM user_information WHERE Username = ?";
        $stmtUser = mysqli_prepare($conn, $sqlUser);
        mysqli_stmt_bind_param($stmtUser, "s", $username);
        mysqli_stmt_execute($stmtUser);
        mysqli_stmt_bind_result($stmtUser, $UserID, $UserPass);
        mysqli_stmt_fetch($stmtUser);
        mysqli_stmt_close($stmtUser);

        $sqlAdmin = "SELECT Admin_ID_PK, Pass FROM admin_information WHERE Username = ?";
        $stmtAdmin = mysqli_prepare($conn, $sqlAdmin);
        mysqli_stmt_bind_param($stmtAdmin, "s", $username);
        mysqli_stmt_execute($stmtAdmin);
        mysqli_stmt_bind_result($stmtAdmin, $AdminID, $AdminPass);
        mysqli_stmt_fetch($stmtAdmin);
        mysqli_stmt_close($stmtAdmin);

        if ($UserPass) {
            if (password_verify($password, $UserPass)) {
                $_SESSION['Username'] = $username;
                $_SESSION['UserID'] = $UserID;
                header("Location: index.php");
                exit();
            } else {
                $errorMsg = 'Incorrect password. Please try again.';
            }
        } elseif ($AdminPass) {
            if (password_verify($password, $AdminPass)) {
                $_SESSION['AdminUsername'] = $username;
                $_SESSION['AdminID'] = $AdminID;
                header("Location: bookingconfirmation.php");
                exit();
            } else {
                $errorMsg = 'Incorrect password. Please try again.';
            }
        } else {
            $errorMsg = 'No account found with that username.';
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
        <a href="index.php">← Back to homepage</a>
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
            <input
                id="username"
                class="username <?php echo (!empty($errorMsg)) ? 'inputError' : ''; ?>"
                type="text"
                name="username"
                placeholder="Enter your username"
                value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>"
                required>

            <!-- PASSWORD -->
            <label for="password" class="formlabel">Password</label>
            <input
                id="password"
                class="password <?php echo (!empty($errorMsg)) ? 'inputError' : ''; ?>"
                type="password"
                name="password"
                placeholder="Enter your password"
                required>

            <!-- REMEMBER ME + FORGOT PASSWORD -->
            <div class="rememberme">
                <div class="left">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember">Remember me</label>
                </div>
                <a href="forgot_password.php">Forgot password?</a>
            </div>

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