<?php
session_start();
require("db.php");

$errorMsg = '';

if (isset($_POST['signup'])) {
    $username = trim($_POST['username']);
    $firstName = trim($_POST['first_name']);
    $lastName = trim($_POST['last_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $password = trim($_POST['password']);
    $conpassword = trim($_POST['conpassword']);

    if (empty($username) || empty($firstName) || empty($lastName) || empty($email) || empty($phone) || empty($password) || empty($conpassword)) {
        $errorMsg = 'All fields are required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errorMsg = 'Please enter a valid email address.';
    } elseif ($password !== $conpassword) {
        $errorMsg = 'Passwords do not match.';
    } elseif (strlen($password) < 6) {
        $errorMsg = 'Password must be at least 6 characters.';
    } else {
        $checkSql = "SELECT User_ID_PK FROM User_Information WHERE Username = ?";
        $checkStmt = mysqli_prepare($conn, $checkSql);
        mysqli_stmt_bind_param($checkStmt, "s", $username);
        mysqli_stmt_execute($checkStmt);
        mysqli_stmt_store_result($checkStmt);

        if (mysqli_stmt_num_rows($checkStmt) > 0) {
            $errorMsg = 'Username is already taken.';
        } else {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $insertSql = "INSERT INTO User_Information (Username, Pass, First_Name, Last_Name, Email, Phone_No) VALUES (?, ?, ?, ?, ?, ?)";
            $insertStmt = mysqli_prepare($conn, $insertSql);
            mysqli_stmt_bind_param($insertStmt, "ssssss", $username, $hashedPassword, $firstName, $lastName, $email, $phone);

            if (mysqli_stmt_execute($insertStmt)) {
                header("Location: login.php");
                exit();
            } else {
                $errorMsg = 'Something went wrong. Please try again.';
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
    <title>Jilz | Sign Up</title>
    <link rel="shortcut icon" href="assets/Logo_Tentative.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="mobileStyle.css">
</head>

<body>
    <div class="suPage">

        <!-- LEFT -->
        <div class="suLeft">
            <img class="suBg" src="assets/BGsignuplogin.jpg" alt="">
            <div class="suLeftContent">
                <div class="suLogoRow">
                    <img src="assets/Logo_Tentative.png" alt="Jilz">
                    <?php
                    $defaultBack = 'index.php';
                    $back = !empty($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : $defaultBack;
                    ?>
                    <a href="index.php">
                        ← Back to Home Page
                    </a>
                </div>
                <div class="suLeftTagline">
                    <h2>Join us and wear<br>your <em>signature scent.</em></h2>
                </div>
            </div>
        </div>

        <!-- RIGHT -->
        <div class="suRight">
            <div class="suCard">

                <div class="suHeading">
                    <h1>Create Account</h1>
                    <p>Fill in your details to get started.</p>
                </div>

                <?php if (!empty($errorMsg)): ?>
                    <div class="suError">
                        <div class="suErrorIcon">!</div>
                        <p><?php echo htmlspecialchars($errorMsg); ?></p>
                    </div>
                <?php endif; ?>

                <form action="" method="POST">

                    <div class="suSection">Personal Info</div>
                    <div class="suGrid">
                        <div class="suField">
                            <label for="first_name">First Name</label>
                            <input id="first_name" type="text" name="first_name" placeholder="Juan"
                                class="<?php echo !empty($errorMsg) ? 'suInputErr' : ''; ?>"
                                value="<?php echo isset($_POST['first_name']) ? htmlspecialchars($_POST['first_name']) : ''; ?>">
                        </div>
                        <div class="suField">
                            <label for="last_name">Last Name</label>
                            <input id="last_name" type="text" name="last_name" placeholder="dela Cruz"
                                class="<?php echo !empty($errorMsg) ? 'suInputErr' : ''; ?>"
                                value="<?php echo isset($_POST['last_name']) ? htmlspecialchars($_POST['last_name']) : ''; ?>">
                        </div>
                        <div class="suField">
                            <label for="email">Email</label>
                            <input id="email" type="email" name="email" placeholder="juan@email.com"
                                class="<?php echo !empty($errorMsg) ? 'suInputErr' : ''; ?>"
                                value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                        </div>
                        <div class="suField">
                            <label for="phone">Phone Number</label>
                            <input id="phone" type="tel" name="phone" placeholder="09XX XXX XXXX"
                                class="<?php echo !empty($errorMsg) ? 'suInputErr' : ''; ?>"
                                value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                        </div>
                    </div>

                    <div class="suSection">Account Details</div>
                    <div class="suGrid suFull">
                        <div class="suField">
                            <label for="username">Username</label>
                            <input id="username" type="text" name="username" placeholder="Choose a username"
                                class="<?php echo !empty($errorMsg) ? 'suInputErr' : ''; ?>"
                                value="<?php echo isset($_POST['username']) ? htmlspecialchars($_POST['username']) : ''; ?>">
                        </div>
                    </div>
                    <div class="suGrid" style="margin-top: 0.75rem;">
                        <div class="suField">
                            <label for="password">Password</label>
                            <input id="password" type="password" name="password" placeholder="Min. 6 characters"
                                class="<?php echo !empty($errorMsg) ? 'suInputErr' : ''; ?>">
                        </div>
                        <div class="suField">
                            <label for="conpassword">Confirm Password</label>
                            <input id="conpassword" type="password" name="conpassword" placeholder="Repeat password"
                                class="<?php echo !empty($errorMsg) ? 'suInputErr' : ''; ?>">
                        </div>
                    </div>

                    <button class="suSubmit" name="signup">Sign Up</button>

                    <p class="suLoginLink">
                        Already have an account? <a href="login.php">Log in here</a>
                    </p>

                </form>
            </div>
        </div>

    </div>
</body>

</html>