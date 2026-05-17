<?php
session_start();
require("db.php");

if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit();
}

if (!isset($_SESSION['UserID'])) {
    header("Location: login.php");
    exit();
}

$editMode = isset($_GET['edit']) && $_GET['edit'] == 1;

$getUserInfo = mysqli_query($conn, "SELECT * FROM user_information WHERE User_ID_PK = " . $_SESSION['UserID']);

if ($getUserInfo && mysqli_num_rows($getUserInfo) > 0) {
    $userInfo = mysqli_fetch_assoc($getUserInfo);
    $_SESSION['First_Name'] = $userInfo['First_Name'];
    $_SESSION['Last_Name'] = $userInfo['Last_Name'];
    $_SESSION['Email'] = $userInfo['Email'];
    $_SESSION['Gender'] = $userInfo['Gender'];
    $_SESSION['DOB'] = $userInfo['Birthday'];
    $_SESSION['Phone'] = $userInfo['Phone_No'];
}

if (($_SERVER['REQUEST_METHOD'] ?? null) === 'POST') {

    $userID = $_SESSION['UserID'];

    $firstName = $_POST['firstName'] ?? '';
    $lastName = $_POST['lastName'] ?? '';
    $email = $_POST['email'] ?? '';
    $gender = $_POST['gender'] ?? '';
    $dob = $_POST['dob'] ?? '';
    $phone = $_POST['phone'] ?? '';

    $sql = "UPDATE user_information 
            SET First_Name = ?, Last_Name = ?, Email = ?, Gender = ?, Birthday = ?, Phone_No = ?
            WHERE User_ID_PK = ?";

    $stmt = mysqli_prepare($conn, $sql);

    mysqli_stmt_bind_param(
        $stmt,
        "ssssssi",
        $firstName,
        $lastName,
        $email,
        $gender,
        $dob,
        $phone,
        $userID
    );

    if (mysqli_stmt_execute($stmt)) {
        $_SESSION['First_Name'] = $firstName;
        $_SESSION['Last_Name']  = $lastName;
        $_SESSION['Email']      = $email;
        $_SESSION['Gender']    = $gender;
        $_SESSION['DOB']        = $dob;
        $_SESSION['Phone']      = $phone;

        echo "<script>
            alert('Profile updated successfully!');
            window.location.href='profile.php';
        </script>";
        exit();
    } else {
        echo "<script>alert('Update failed');</script>";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jilz | Profile</title>
    <link rel="shortcut icon" href="assets/Logo_Tentative.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="mobileStyle.css">
</head>

<body class="profileBG">

    <!-- Mobile nav overlay -->
    <div class="profileNavOverlay" id="profileNavOverlay" onclick="closeProfileDrawer()"></div>

    <!-- Mobile nav drawer -->
    <div class="profileNavDrawer" id="profileNavDrawer">
        <div class="profileDrawerHeader">
            <img class="profileDrawerLogo" src="assets/Logo_Tentative.png" alt="Jilz">
            <button class="profileDrawerClose" onclick="closeProfileDrawer()">&#10005;</button>
        </div>

        <div class="profileDrawerUser">
            <img src="assets/user.png" alt="Profile">
            <span><?php echo isset($_SESSION['Username']) ? htmlspecialchars($_SESSION['Username']) : 'Guest'; ?></span>
        </div>

        <ul>
            <li><a href="index.php">Home</a></li>
            <li><a href="#"><b>Account Information</b></a></li>
            <li><a href="mybookings.php">My Bookings</a></li>
            <li><a href="myhistory.php">History</a></li>
            <li><a onclick="closeProfileDrawer(); document.getElementById('logoutPopup').style.display='flex';">Log out</a></li>
        </ul>
    </div>

    <!-- Mobile burger button (top-right) -->
    <button class="profileBurger" onclick="openProfileDrawer()">&#9776;</button>

    <!-- Sidebar (desktop) -->
    <div class="psidebar">
        <h1>Profile</h1>
        <div class="roww">
            <img src="assets/Logo_Tentative.png" alt="" class="profilepic">
            <div class="usernameemail">
                <h3><?php echo isset($_SESSION['Username']) ? $_SESSION['Username'] : 'Guest'; ?></h3>
            </div>
        </div>
        <hr>
        <div class="plinks">
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="#"><b>Account Information</b></a></li>
                <li><a href="mybookings.php">My Bookings</a></li>
                <li><a href="myhistory.php">History</a></li>
                <li>
                    <a onclick="document.getElementById('logoutPopup').style.display='flex'">Log out</a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Logout popup -->
    <section class="popUp" id="logoutPopup" style="display: none;">
        <div class="notif">
            <div class="icon">
                <img src="assets/warning.png" alt="warning">
                <p>Are you sure?</p>
            </div>
            <p class="errorMsg" style="text-align: center;">You're about to log out. Do you <br> want to continue?</p>
            <div class="uSureBtn">
                <button class="yes" type="button" onclick="window.location.href='profile.php?logout=true'">Yes</button>
                <button class="no" type="button" onclick="document.getElementById('logoutPopup').style.display='none'">No</button>
            </div>
        </div>
    </section>

    <!-- Account information -->
    <div class="infocontainer">
        <h1>Account Information</h1>

        <form action="profile.php<?= $editMode ? '?edit=1' : '' ?>" method="POST">
            <div class="infocon">

                <div class="prow">
                    <div class="pcolumn">
                        <label>First Name</label>
                        <input name="firstName" type="text"
                            value="<?= $_SESSION['First_Name'] ?? '' ?>"
                            <?= !$editMode ? 'readonly' : '' ?> required>
                    </div>
                    <div class="pcolumn">
                        <label>Last Name</label>
                        <input name="lastName" type="text"
                            value="<?= $_SESSION['Last_Name'] ?? '' ?>"
                            <?= !$editMode ? 'readonly' : '' ?> required>
                    </div>
                </div>

                <div class="prow">
                    <div class="pcolumn">
                        <label>Email</label>
                        <input name="email" type="email"
                            value="<?= $_SESSION['Email'] ?? '' ?>"
                            <?= !$editMode ? 'readonly' : '' ?> required>
                    </div>
                    <div class="pcolumn">
                        <label>Gender</label>
                        <input name="gender" type="text"
                            value="<?= $_SESSION['Gender'] ?? '' ?>"
                            <?= !$editMode ? 'readonly' : '' ?>>
                    </div>
                </div>

                <div class="prow">
                    <div class="pcolumn">
                        <label>Date of Birth</label>
                        <input name="dob" type="date"
                            value="<?= $_SESSION['DOB'] ?? '' ?>"
                            <?= !$editMode ? 'readonly' : '' ?>>
                    </div>
                    <div class="pcolumn">
                        <label>Phone</label>
                        <input name="phone" type="tel"
                            value="<?= $_SESSION['Phone'] ?? '' ?>"
                            <?= !$editMode ? 'readonly' : '' ?> required>
                    </div>
                </div>

                <div class="prow">
                    <?php if (!$editMode) { ?>
                        <div class="profBtn">
                            <button class="edit" type="button"
                                onclick="window.location.href='profile.php?edit=1'">
                                Edit Profile
                            </button>
                        </div>
                    <?php } else { ?>
                        <div class="editProf">
                            <button class="edit" type="submit">Save</button>
                            <button class="edit" type="button"
                                onclick="window.location.href='profile.php'">
                                Cancel
                            </button>
                        </div>
                    <?php } ?>
                </div>

            </div>
        </form>
    </div>

    <script>
        function openProfileDrawer() {
            document.getElementById('profileNavDrawer').classList.add('open');
            document.getElementById('profileNavOverlay').classList.add('open');
            document.body.style.overflow = 'hidden';
        }

        function closeProfileDrawer() {
            document.getElementById('profileNavDrawer').classList.remove('open');
            document.getElementById('profileNavOverlay').classList.remove('open');
            document.body.style.overflow = '';
        }
    </script>

</body>

</html>