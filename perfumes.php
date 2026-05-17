<?php
session_start();
require("db.php"); // mysqli connection file

if (isset($_POST['category'])) {
    $category = $_POST['category'];

    $query = "SELECT * FROM perfume WHERE Perfume_Status = 'Available'";

    if (!empty($category)) {
        $query .= " AND Perfume_Category = '$category'";
    }

    $result = mysqli_query($conn, $query);

    while ($perfume = mysqli_fetch_assoc($result)) {
        echo '<div class="perfumeCard" onclick="window.location.href=\'?perfume_id=' . $perfume['Perfume_ID_PK'] . '\'">';
        echo '<img src="' . (!empty($perfume['Perfume_Img']) ? htmlspecialchars($perfume['Perfume_Img']) : 'assets/perfumeBottle.png') . '">';
        echo '<div class="perfumeTitle">';
        echo '<p>' . $perfume['Inspired_Scent'] . '</p>';
        echo '</div>';
        echo '</div>';
    }
    exit;
}
$perfumes1 = mysqli_query($conn, "SELECT * FROM perfume WHERE Perfume_Status = 'Available'");
$perfumes = mysqli_query($conn, "SELECT * FROM perfume WHERE Perfume_Status = 'Available'");

$minPerfumeID = mysqli_query($conn, "SELECT MIN(Perfume_ID_PK) AS min_id FROM perfume WHERE Perfume_Status = 'Available'");
$minPerfumeIDResult = mysqli_fetch_assoc($minPerfumeID);
$perfumeSelected = $_GET['perfume_id'] ?? $minPerfumeIDResult['min_id'];

$ratingQuery = mysqli_query($conn, "SELECT 
    pr.Rating, pr.Description, pr.User_ID_FK,
    ui.First_Name, ui.Last_Name,
    p.Perfume_ID_PK
    FROM perfume_ratings pr
    JOIN perfume p ON pr.Perfume_ID_FK = p.Perfume_ID_PK
    JOIN user_information ui ON pr.User_ID_FK = ui.User_ID_PK
    WHERE p.Perfume_ID_PK = " . intval($perfumeSelected));

$ratingRows = [];
while ($row = mysqli_fetch_assoc($ratingQuery)) {
    $ratingRows[] = $row;
}

$totalRatings = count($ratingRows);
$avgRating = $totalRatings > 0 ? array_sum(array_column($ratingRows, 'Rating')) / $totalRatings : 0;

$starCounts = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
foreach ($ratingRows as $r) {
    $starCounts[(int)$r['Rating']]++;
}

// PERFUME COLOR
$selectedPerfumeData = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT Perfume_Color FROM perfume WHERE Perfume_ID_PK = " . intval($perfumeSelected)
));
$perfumeColor = !empty($selectedPerfumeData['Perfume_Color']) ? $selectedPerfumeData['Perfume_Color'] : '#ffffff';
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jilz Perfume Bar | Perfumes</title>
    <link rel="shortcut icon" href="assets/Logo_Tentative.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans&display=swap" rel="stylesheet">
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
            <li><a href="index.php">Home</a></li>
            <li><a href="packages.php">Packages</a></li>
            <li><a href="perfumes.php"><b>Perfumes</b></a></li>
            <li><a href="booking.php">Booking</a></li>
            <li><a href="aboutUs.php">About</a></li>
        </ul>
    </div>

    <!-- HEADER -->
    <Header>
        <!-- THE LOGO -->
        <div class="logo">
            <img src="assets/Logo_Tentative.png">
        </div>
        <!-- NAVIGATIONS -->
        <div id="nav" class="navs">
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="packages.php">Packages</a></li>
                <li><a href="perfumes.php"><b>Perfumes</b></a></li>
                <li><a href="booking.php">Booking</a></li>
                <li><a href="aboutUs.php">About</a></li>
                <li class="prof" style="display: none;"><a href="profile.php"><b>Profile</b></a></li>
            </ul>
            <img id="close" class="close" style="display: none;" src="assets/close.png" alt="">
        </div>

        <!-- LOGIN AND SIGNUP BUTTON -->

        <?php if (isset($_SESSION['UserID']) || isset($_SESSION['AdminID'])): ?>

            <!-- USER PROFILE (SHOW WHEN LOGGED IN) -->
            <div class="userProfile" style="display: flex;">
                <img onclick="window.location.href='profile.php'" src="assets/user.png" alt="User Profile">
            </div>

        <?php else: ?>

            <!-- LOGIN AND SIGNUP (SHOW WHEN NOT LOGGED IN) -->
            <div class="loginOrSignup" style="display: flex;">
                <button class="loginButton" onclick="location.href='login.php';"><b>Log in</b></button>
                <button class="signupButton" onclick="location.href='signup.php';"><b>Sign up</b></button>
            </div>
        <?php endif; ?>

        <div class="burger" onclick="openDrawer()">
            <span class="material-icons">menu</span>
        </div>
    </Header>

    <!-- PERFUME DESCRIPTION -->
    <section class="perfumeDescription">
        <?php while ($perfume = mysqli_fetch_assoc($perfumes1)) { ?>
            <?php if ($perfume['Perfume_ID_PK'] == $perfumeSelected) { ?>

                <!-- HERO: bg image + bottle, clipped together -->
                <div class="perfumeHero">
                    <img src="assets/perfumeBG.jpg" id="perfumeBG">
                    <div class="perfumeImg">
                        <img src="<?php echo !empty($perfume['Perfume_Img']) ? htmlspecialchars($perfume['Perfume_Img']) : 'assets/perfumeBottle.png'; ?>">
                    </div>
                </div>

                <!-- DETAILS: separate block below the hero -->
                <div class="details">
                    <div class="perfumeDetails">
                        <p class="inspiredBy">INSPIRED BY</p>
                        <p class="perfumeName"><?php echo $perfume['Inspired_Scent']; ?></p>
                        <p class="desc"><?php echo $perfume['Perfume_Description']; ?></p>

                        <p class="ingredientTitle">Category: <?php echo $perfume['Perfume_Category']; ?></p>

                        <a href="#customerReviews" class="ratingAnchor">
                            <div class="ratingStarRow">
                                <?php
                                $roundedAvg = round($avgRating * 2) / 2;
                                for ($i = 1; $i <= 5; $i++):
                                    if ($roundedAvg >= $i):
                                ?>
                                        <span class="star full">★</span>
                                    <?php elseif ($roundedAvg >= $i - 0.5): ?>
                                        <span class="star half">★</span>
                                    <?php else: ?>
                                        <span class="star empty">★</span>
                                <?php endif;
                                endfor; ?>
                                <span class="ratingCount"><?php echo number_format($avgRating, 1); ?> (<?php echo $totalRatings; ?> <?php echo $totalRatings == 1 ? 'review' : 'reviews'; ?>)</span>
                            </div>
                        </a>
                    </div>
                </div>

            <?php } ?>
        <?php } ?>
        </section>

        <!-- PERFUME LISTS -->
        <h1 id="perfumeTitle">JILZ COLLECTION</h1>

        <select id="perfCat" name="perfCat" class="stat">
            <option value="" disabled selected>Category</option>
            <option value="">All Perfumes</option>
            <option value="Male">Male</option>
            <option value="Female">Female</option>
            <option value="Unisex">Unisex</option>
        </select>

        <section class="perfumeLists" id="perfumeContainer">
            <?php while ($perfume = mysqli_fetch_assoc($perfumes)) { ?>
                <div class="perfumeCard"
                    onclick="window.location.href='?perfume_id=<?php echo $perfume['Perfume_ID_PK']; ?>'">
                    <img src="<?php echo !empty($perfume['Perfume_Img']) ? htmlspecialchars($perfume['Perfume_Img']) : 'assets/perfumeBottle.png'; ?>">
                    <div class="perfumeTitle">
                        <p><?php echo $perfume['Inspired_Scent']; ?></p>
                    </div>
                </div>
            <?php } ?>
        </section>

        <!-- CUSTOMER REVIEWS SECTION -->
        <section class="customerReviews" id="customerReviews">
            <h2 class="reviewsTitle">Customer Reviews</h2>

            <div class="reviewsSummary">
                <!-- LEFT: avg + recommend -->
                <div class="reviewsLeft">
                    <p class="avgNumber"><?php echo number_format($avgRating, 1); ?></p>
                    <div class="avgStars">
                        <?php
                        $roundedAvg = round($avgRating * 2) / 2;
                        for ($i = 1; $i <= 5; $i++):
                            if ($roundedAvg >= $i): ?>
                                <span class="star full">★</span>
                            <?php elseif ($roundedAvg >= $i - 0.5): ?>
                                <span class="star half">★</span>
                            <?php else: ?>
                                <span class="star empty">★</span>
                        <?php endif;
                        endfor; ?>
                    </div>
                    <p class="reviewCount"><?php echo $totalRatings; ?> <?php echo $totalRatings == 1 ? 'review' : 'reviews'; ?></p>
                </div>

                <!-- RIGHT: bar breakdown -->
                <div class="reviewsRight">
                    <?php for ($s = 5; $s >= 1; $s--):
                        $count = $starCounts[$s];
                        $pct = $totalRatings > 0 ? ($count / $totalRatings) * 100 : 0;
                    ?>
                        <div class="starBarRow">
                            <span class="starBarLabel"><?php echo $s; ?> Star<?php echo $s > 1 ? 's' : ''; ?></span>
                            <div class="starBarTrack">
                                <div class="starBarFill" style="width: <?php echo $pct; ?>%"></div>
                            </div>
                            <span class="starBarPct"><?php echo number_format($pct, 1); ?>%</span>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- INDIVIDUAL REVIEWS -->
            <div class="reviewsList">
                <?php if (empty($ratingRows)): ?>
                    <p class="noReviews">No reviews yet for this perfume.</p>
                    <?php else: foreach ($ratingRows as $rev): ?>
                        <div class="reviewCard">
                            <div class="reviewerInfo">
                                <div class="reviewerAvatar">
                                    <?php echo strtoupper(substr($rev['First_Name'], 0, 1)); ?>
                                </div>
                                <div>
                                    <p class="reviewerName"><?php echo htmlspecialchars($rev['First_Name'] . ' ' . $rev['Last_Name']); ?></p>
                                    <div class="reviewStars">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <span class="star <?php echo $i <= $rev['Rating'] ? 'full' : 'empty'; ?>">★</span>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            </div>
                            <p class="reviewDesc"><?php echo htmlspecialchars($rev['Description']); ?></p>
                        </div>
                <?php endforeach;
                endif; ?>
            </div>
        </section>

        <!-- FOOTER -->
        <Footer>
            <div class="platforms">
                <img class="icons"
                    onclick="window.open('https://web.facebook.com/profile.php?id=100083402345862', '_blank')"
                    src="assets/facebook.png">
                <img class="icons"
                    onclick="window.open('https://mail.google.com/mail/?view=cm&fs=1&to=jilzevangelista@gmail.com', '_blank')"
                    src="assets/gmail.png">
                <img class="icons"
                    onclick="window.open('https://web.facebook.com/profile.php?id=100083402345862', '_blank')"
                    src="assets/contact.png">
            </div>

            <div class="footNavs">
                <ul>
                    <li><a href="index.php">Home</a></li>
                    <li><a href="#">Packages</a></li>
                    <li><a href="perfumes.php">Perfumes</a></li>
                    <li><a href="profile.php">Profile</a></li>
                </ul>
            </div>

            <div class="copyRight">
                <p>&copy; 2026 Jilz Perfume Bar</p>
            </div>
        </Footer>

        <script>
            document.getElementById("perfCat").addEventListener("change", function() {
                let category = this.value;

                fetch("perfumes.php", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/x-www-form-urlencoded"
                        },
                        body: "category=" + encodeURIComponent(category)
                    })
                    .then(res => res.text())
                    .then(data => {
                        document.getElementById("perfumeContainer").innerHTML = data;
                    });
            });
        </script>
        <script src="script.js"></script>
</body>

</html>