<?php
session_start();
require("db.php");

$packages = mysqli_query($conn, "SELECT * FROM packages p JOIN bottle b ON p.Bottle_ID_FK = b.Bottle_ID_PK");
$perfumes = mysqli_query($conn, "SELECT * FROM perfume p");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jilz Perfume Bar</title>
    <link rel="shortcut icon" href="assets/Logo_Tentative.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="mobileStyle.css">
    <script>
        function openDrawer() {
            document.getElementById('mobileNavDrawer').classList.add('open');
            document.getElementById('mobileNavOverlay').classList.add('open');
            document.body.style.overflow = 'hidden';
        }

        function closeDrawer() {
            document.getElementById('mobileNavDrawer').classList.remove('open');
            document.getElementById('mobileNavOverlay').classList.remove('open');
            document.body.style.overflow = '';
        }
    </script>
</head>

<body>

    <!-- MOBILE NAV OVERLAY -->
    <div class="mobileNavOverlay" id="mobileNavOverlay" onclick="closeDrawer()"></div>

    <!-- MOBILE NAV DRAWER -->
    <div class="mobileNavDrawer" id="mobileNavDrawer">
        <div class="drawerHeader">
            <img class="drawerLogo" src="assets/Logo_Tentative.png" alt="Jilz">
            <button class="drawerClose" onclick="closeDrawer()">&#10005;</button>
        </div>

        <?php if (isset($_SESSION['UserID']) || isset($_SESSION['AdminID'])): ?>
            <div class="drawerUserProfile" onclick="window.location.href='profile.php'">
                <img src="assets/user.png" alt="Profile">
                <span>My Profile</span>
            </div>
        <?php else: ?>
            <div class="drawerAuthBtns">
                <button class="loginButton" onclick="location.href='login.php'">Log in</button>
                <button class="signupButton" onclick="location.href='signup.php'">Sign up</button>
            </div>
        <?php endif; ?>

        <ul>
            <li><a href="index.php"><b>Home</b></a></li>
            <li><a href="packages.php">Packages</a></li>
            <li><a href="perfumes.php">Perfumes</a></li>
            <li><a href="booking.php">Booking</a></li>
            <li><a href="aboutUs.php">About</a></li>
        </ul>
    </div>

    <!-- HEADER -->
    <Header>
        <div class="logo">
            <img src="assets/Logo_Tentative.png" alt="Jilz Logo">
        </div>

        <div id="nav" class="navs">
            <ul>
                <li><a href="index.php"><b>Home</b></a></li>
                <li><a href="packages.php">Packages</a></li>
                <li><a href="perfumes.php">Perfumes</a></li>
                <li><a href="booking.php">Booking</a></li>
                <li><a href="aboutUs.php">About</a></li>
                <li class="prof" style="display: none;"><a href="profile.php"><b>Profile</b></a></li>
            </ul>
        </div>

        <?php if (isset($_SESSION['UserID']) || isset($_SESSION['AdminID'])): ?>
            <div class="userProfile" style="display: flex;">
                <img onclick="window.location.href='profile.php'" src="assets/user.png" alt="User Profile">
            </div>
        <?php else: ?>
            <div class="loginOrSignup" style="display: flex;">
                <button class="loginButton" onclick="location.href='login.php';"><b>Log in</b></button>
                <button class="signupButton" onclick="location.href='signup.php';"><b>Sign up</b></button>
            </div>
        <?php endif; ?>

        <div class="burger" onclick="openDrawer()">
            <span class="material-icons">menu</span>
        </div>
    </Header>

    <!-- HERO SECTION -->
    <section class="heroSection">
        <div class="hero">

            <!-- LEFT: TEXT -->
            <div class="heroDescription">
                <span class="heroPill">PERFUME BAR &bull; EVENTS &bull; LUXURY SCENTS</span>

                <h1>
                    Inspired Luxury Scents<br>
                    Crafted for Weddings,<br>
                    Parties &amp; Unforgettable<br>
                    Events.
                </h1>

                <p>Jilz Perfume Bar brings a curated selection of luxury-inspired fragrances
                    to your special event — an immersive scent experience your guests will
                    never forget.</p>

                <div class="heroBtns">
                    <form action="booking.php" method="GET" style="display:inline;">
                        <button type="submit" class="heroButton heroButton--primary">Book Now</button>
                    </form>
                    <a href="packages.php" class="heroButton heroButton--outline">View Packages</a>
                </div>
            </div>

            <!-- RIGHT: AUTO-SCROLLING PHOTOS -->
            <div class="imgContainer">
                <div class="wrapper">
                    <img src="assets/asset4.jpg" alt="Event photo 1">
                    <img src="assets/asset5.jpg" alt="Event photo 2">
                    <img src="assets/asset12.jpg" alt="Event photo 3">
                    <img src="assets/asset8.jpg" alt="Event photo 4">
                    <img src="assets/asset10.jpg" alt="Event photo 5">
                    <img src="assets/asset19.jpg" alt="Event photo 6">
                    <img src="assets/asset11.jpg" alt="Event photo 7">
                    <img src="assets/asset13.jpg" alt="Event photo 8">
                </div>
            </div>

        </div>
    </section>

    <!-- PACKAGES SHORTCUT -->
    <div>
        <h1 class="sectionHeader">Jilz Packages</h1>
    </div>

    <section class="pckagesShortcut">
        <?php while ($package = mysqli_fetch_assoc($packages)): ?>
            <?php if ($package['Package_Status'] == 'Available'): ?>
                <div class="pckages">
                    <div class="pName">
                        <h1><?php echo $package['Package_Name']; ?></h1>
                        <p>&#8369;<?php echo $package['Price']; ?></p>
                        <br>
                        <hr>
                    </div>

                    <div class="pDetailsContainer">
                        <div class="pDetails">
                            <img src="assets/checked.png" alt="">
                            <p><?php echo $package['No_of_Bottles']; ?>pcs
                                <?php echo $package['Bottle_Name']; ?> Glass Spray Bottles</p>
                        </div>
                        <div class="pDetails">
                            <img src="assets/checked.png" alt="">
                            <p>Elegant Setup Based on Motif</p>
                        </div>
                        <div class="pDetails">
                            <img src="assets/checked.png" alt="">
                            <p><?php echo $package['No_of_Scent']; ?> Scent of Choices</p>
                        </div>
                    </div>

                    <form class="pButton" action="booking.php" method="GET">
                        <input type="hidden" name="package_id" value="<?php echo $package['Package_ID_PK']; ?>">
                        <button type="submit">Book</button>
                    </form>
                </div>
            <?php endif; ?>
        <?php endwhile; ?>
    </section>

    <div class="seeMore">
        <button class="seeMoreBtn" onclick="location.href='packages.php'">See more</button>
    </div>

    <!-- PERFUMES SHORTCUT -->
    <div>
        <h1 class="sectionHeader">Jilz Perfume Collection</h1>
    </div>

    <section class="pfumesShortcut">
        <?php while ($perfume = mysqli_fetch_assoc($perfumes)): ?>
            <?php if ($perfume['Perfume_Status'] == 'Available'): ?>
                <form action="perfumes.php" method="GET">
                    <div class="pfumes" onclick="this.parentNode.submit();">
                        <input type="hidden" name="perfume_id" value="<?php echo $perfume['Perfume_ID_PK']; ?>">
                        <img src="<?php echo !empty($perfume['Perfume_Img']) ? htmlspecialchars($perfume['Perfume_Img']) : 'assets/perfumeBottle.png'; ?>" alt="<?php echo htmlspecialchars($perfume['Inspired_Scent']); ?>">
                        <p><?php echo htmlspecialchars($perfume['Inspired_Scent']); ?></p>
                    </div>
                </form>
            <?php endif; ?>
        <?php endwhile; ?>
    </section>

    <div class="seeMore">
        <button class="seeMoreBtn" onclick="location.href='perfumes.php'">See more</button>
    </div>

    <!-- BOOKING PROCESS -->
    <div>
        <h1 class="sectionHeader">Booking Process</h1>
    </div>

    <section class="process">

        <div class="steps">
            <div class="divide">
                <span class="material-icons">inventory_2</span>
            </div>
            <div class="divide">
                <p>1. Choose a package.</p>
            </div>
        </div>

        <div class="steps">
            <div class="divide">
                <span class="material-icons">event_note</span>
            </div>
            <div class="divide">
                <p>2. Fill in your event details.</p>
            </div>
        </div>

        <div class="steps">
            <div class="divide">
                <span class="material-icons">send</span>
            </div>
            <div class="divide">
                <p>3. Submit your booking request.</p>
            </div>
        </div>

        <div class="steps">
            <div class="divide">
                <span class="material-icons">hourglass_top</span>
            </div>
            <div class="divide">
                <p>4. Wait for booking approval.</p>
            </div>
        </div>

        <div class="steps">
            <div class="divide">
                <span class="material-icons">payments</span>
            </div>
            <div class="divide">
                <p>5. Status changes to "To Pay."</p>
            </div>
        </div>

        <div class="steps">
            <div class="divide">
                <span class="material-icons">receipt_long</span>
            </div>
            <div class="divide">
                <p>6. Upload your GCash receipt in your profile.</p>
            </div>
        </div>

        <div class="steps">
            <div class="divide">
                <span class="material-icons">verified</span>
            </div>
            <div class="divide">
                <p>7. Wait for payment verification.</p>
            </div>
        </div>

        <div class="steps">
            <div class="divide">
                <span class="material-icons">check_circle</span>
            </div>
            <div class="divide">
                <p>8. Receive your booking confirmation.</p>
            </div>
        </div>

    </section>

    <!-- FOOTER -->
    <Footer>
        <div class="platforms">
            <img class="icons"
                onclick="window.open('https://web.facebook.com/profile.php?id=100083402345862', '_blank')"
                src="assets/facebook.png" alt="Facebook">
            <img class="icons"
                onclick="window.open('https://mail.google.com/mail/?view=cm&fs=1&to=jilzevangelista@gmail.com', '_blank')"
                src="assets/gmail.png" alt="Gmail">
            <img class="icons"
                onclick="window.open('https://web.facebook.com/profile.php?id=100083402345862', '_blank')"
                src="assets/contact.png" alt="Contact">
        </div>

        <div class="footNavs">
            <ul>
                <li><a href="#">Home</a></li>
                <li><a href="packages.php">Packages</a></li>
                <li><a href="perfumes.php">Perfumes</a></li>
                <li><a href="profile.php">Profile</a></li>
            </ul>
        </div>

        <div class="copyRight">
            <p>&copy; 2026 Jilz Perfume Bar</p>
        </div>
    </Footer>

</body>

</html>