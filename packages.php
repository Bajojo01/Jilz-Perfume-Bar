<?php
session_start();
require("db.php");

$packages = mysqli_query($conn, "SELECT * FROM packages p JOIN bottle b ON p.Bottle_ID_FK = b.Bottle_ID_PK WHERE p.Package_Status = 'Available'");
$bottles = mysqli_query($conn, "SELECT * FROM bottle_variants bv JOIN bottle b ON bv.Bottle_ID_FK = b.Bottle_ID_PK");
$setup = mysqli_query($conn, "SELECT * FROM bar_setup");

$packageRatings = mysqli_query($conn, "SELECT 
    Package_ID_FK,
    ROUND(AVG(Rating), 1) AS avg_rating,
    COUNT(*) AS total_reviews
    FROM package_ratings
    GROUP BY Package_ID_FK");

$pkgRatingMap = [];
while ($pr = mysqli_fetch_assoc($packageRatings)) {
    $pkgRatingMap[$pr['Package_ID_FK']] = $pr;
}

$packageReviewsQuery = mysqli_query($conn, "SELECT 
    pr.Rating, pr.Description, pr.User_ID_FK, pr.Package_ID_FK,
    ui.First_Name, ui.Last_Name,
    p.Package_Name
    FROM package_ratings pr
    JOIN packages p ON pr.Package_ID_FK = p.Package_ID_PK
    JOIN user_information ui ON pr.User_ID_FK = ui.User_ID_PK
    ORDER BY pr.Package_Rating_ID_PK DESC");

$packageReviewsByPkg = [];
while ($row = mysqli_fetch_assoc($packageReviewsQuery)) {
    $packageReviewsByPkg[$row['Package_ID_FK']][] = $row;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jilz Perfume Bar | Packages</title>
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
            <li><a href="packages.php"><b>Packages</b></a></li>
            <li><a href="perfumes.php">Perfumes</a></li>
            <li><a href="booking.php">Booking</a></li>
            <li><a href="aboutUs.php">About</a></li>
        </ul>
    </div>

    <!-- HEADER -->
    <Header>
        <div class="logo">
            <img src="assets/Logo_Tentative.png">
        </div>
        <div id="nav" class="navs">
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="packages.php"><b>Packages</b></a></li>
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

    <!-- PACKAGE LISTS -->
    <h1 id="packageTitle">Perfume Bar Packages</h1>
    <section class="package">
        <?php while ($package = mysqli_fetch_assoc($packages)): ?>
            <div class="card">
                <div class="boxPackage">
                    <div class="description">
                        <div class="namep">
                            <h1><?php echo $package['Package_Name']; ?></h1>
                            <p style="font-size: 0.88rem; line-height: 1.8; color: #7A746C;">Starting Price:</p>
                            <p>₱<?php echo $package['Price']; ?></p>

                            <?php
                            $pkgID = $package['Package_ID_PK'];
                            $avg = isset($pkgRatingMap[$pkgID]) ? (float)$pkgRatingMap[$pkgID]['avg_rating'] : 0;
                            $total = isset($pkgRatingMap[$pkgID]) ? (int)$pkgRatingMap[$pkgID]['total_reviews'] : 0;
                            $rounded = round($avg * 2) / 2;
                            ?>
                            <div class="pkgRatingRow">
                                <a href="#packageReviews" class="pkgRatingAnchor"
                                    onclick="switchReviewTab('<?php echo $pkgID; ?>', null)">
                                    <div class="pkgStars">
                                        <?php for ($i = 1; $i <= 5; $i++): ?>
                                            <span class="pkgStar <?php echo $rounded >= $i ? 'full' : ($rounded >= $i - 0.5 ? 'half' : 'empty'); ?>">★</span>
                                        <?php endfor; ?>
                                    </div>
                                    <span class="pkgRatingText">
                                        <?php echo $total > 0 ? number_format($avg, 1) . ' (' . $total . ')' : 'No reviews'; ?>
                                    </span>
                                </a>
                            </div>
                            <br>
                            <hr>
                        </div>

                        <div class="packageDesc">
                            <div class="detailp">
                                <img src="assets/checked.png">
                                <p><?php echo $package['No_of_Bottles']; ?>pcs
                                    <?php echo $package['Bottle_Name']; ?> Glass Spray Bottles</p>
                            </div>
                            <div class="detailp">
                                <img src="assets/checked.png">
                                <p>Elegant Setup Based on Motif</p>
                            </div>
                            <div class="detailp">
                                <img src="assets/checked.png">
                                <p><?php echo $package['No_of_Scent']; ?> Scent of Choices</p>
                            </div>
                        </div>

                        <form class="buttonp" action="booking.php" method="GET">
                            <input type="hidden" name="package_id" value="<?php echo $package['Package_ID_PK']; ?>">
                            <button type="submit">Book</button>
                        </form>
                    </div>
                </div>
            </div>
        <?php endwhile; ?>
    </section>

    <!-- INCLUDED IN THE PACKAGE -->
    <section class="bar">
        <div class="perfumeBar">
            <h1 class="incl-heading">What's Included in the Package?</h1>
            <div class="incl-tabs" role="tablist">
                <button class="incl-tab active" role="tab" aria-selected="true" onclick="switchTab('perfumes', this)">Perfumes</button>
                <button class="incl-tab" role="tab" aria-selected="false" onclick="switchTab('bottles', this)">Bottles</button>
                <button class="incl-tab" role="tab" aria-selected="false" onclick="switchTab('setup', this)">Bar Setup</button>
                <button class="incl-tab" role="tab" aria-selected="false" onclick="switchTab('mirror', this)">Selfie Mirror</button>
                <button class="incl-tab" role="tab" aria-selected="false" onclick="switchTab('signage', this)">Signage</button>
            </div>

            <div id="tab-perfumes" class="incl-panel active" role="tabpanel">
                <div class="incl-layout">
                    <div class="incl-text">
                        <h3>1. Perfumes</h3>
                        <p>A curated selection of perfumes provided for guests to explore and choose
                            according to their own likings.</p>
                        <a href="perfumes.php" class="incl-link-btn">Go to Perfume Page &rarr;</a>
                    </div>
                    <div class="incl-visual">
                        <img src="assets/asset1.jpg" alt="Perfumes display" class="incl-single-img">
                    </div>
                </div>
            </div>

            <div id="tab-bottles" class="incl-panel" role="tabpanel">
                <div class="incl-layout">
                    <div class="incl-text">
                        <h3>2. Perfume Bottles</h3>
                        <p>High-quality glass bottles used to store the perfumes chosen by guests.</p>
                        <ul class="incl-list">
                            <li><img src="assets/checked.png" alt=""> Souvenir bag</li>
                            <li><img src="assets/checked.png" alt=""> Customized bottle sticker</li>
                            <li><img src="assets/checked.png" alt=""> Bottle ribbon / accessory</li>
                        </ul>
                    </div>
                    <div class="incl-gallery">
                        <?php if ($bottles): ?>
                            <?php while ($bottle = mysqli_fetch_assoc($bottles)): ?>
                                <div class="gallery-card" onclick="openOverlay('<?php echo htmlspecialchars($bottle['Bottle_Img']); ?>')">
                                    <div class="gallery-card__img">
                                        <img src="<?php echo htmlspecialchars($bottle['Bottle_Img']); ?>"
                                            alt="<?php echo htmlspecialchars($bottle['Bottle_Var_Name']); ?>">
                                    </div>
                                    <p class="gallery-card__cap"><?php echo htmlspecialchars($bottle['Bottle_Var_Name']); ?> <?php echo htmlspecialchars($bottle['Bottle_Name']); ?></p>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p class="incl-empty">No bottle variants found.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div id="tab-setup" class="incl-panel" role="tabpanel">
                <div class="incl-layout">
                    <div class="incl-text">
                        <h3>3. Perfume Bar Table</h3>
                        <p>A professionally styled table setup that enhances the overall aesthetic.</p>
                        <ul class="incl-list">
                            <li><img src="assets/checked.png" alt=""> Themed tablecloth &amp; florals</li>
                            <li><img src="assets/checked.png" alt=""> LED candles / fairy lights</li>
                            <li><img src="assets/checked.png" alt=""> Customized motif decor</li>
                        </ul>
                    </div>
                    <div class="incl-gallery">
                        <?php if ($setup): ?>
                            <?php while ($barSetups = mysqli_fetch_assoc($setup)): ?>
                                <div class="gallery-card" onclick="openOverlay('<?php echo htmlspecialchars($barSetups['Bar_Img'] ?? 'assets/barSetup.png'); ?>')">
                                    <div class="gallery-card__img">
                                        <img src="<?php echo htmlspecialchars($barSetups['Bar_Img'] ?? 'assets/barSetup.png'); ?>"
                                            alt="<?php echo htmlspecialchars($barSetups['Bar_Name']); ?>">
                                    </div>
                                    <p class="gallery-card__cap"><?php echo htmlspecialchars($barSetups['Bar_Name']); ?></p>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p class="incl-empty">No bar setups found.</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div id="tab-mirror" class="incl-panel" role="tabpanel">
                <div class="incl-layout">
                    <div class="incl-text">
                        <h3>4. Selfie Mirror</h3>
                        <p>A styled mirror display for photos and decoration.</p>
                        <ul class="incl-list">
                            <li><img src="assets/checked.png" alt=""> Text sticker</li>
                            <li><img src="assets/checked.png" alt=""> LED lights</li>
                            <li><img src="assets/checked.png" alt=""> Decorative accents</li>
                        </ul>
                    </div>
                    <div class="incl-visual">
                        <img src="assets/mirror.jpg" alt="Selfie Mirror" class="incl-single-img">
                    </div>
                </div>
            </div>

            <div id="tab-signage" class="incl-panel" role="tabpanel">
                <div class="incl-layout">
                    <div class="incl-text">
                        <h3>5. Signage</h3>
                        <p>Informational and decorative displays placed around the perfume bar.</p>
                        <ul class="incl-list">
                            <li><img src="assets/checked.png" alt=""> Welcome sign</li>
                            <li><img src="assets/checked.png" alt=""> Fragrance labels</li>
                            <li><img src="assets/checked.png" alt=""> Guest use instructions</li>
                            <li><img src="assets/checked.png" alt=""> Event details display</li>
                        </ul>
                    </div>
                    <div class="incl-visual">
                        <img src="assets/asset2.jpg" alt="Signage" class="incl-single-img">
                    </div>
                </div>
            </div>
        </div>
    </section>

    <div id="overlay" class="overlay" onclick="closeOverlay()">
        <button class="overlay__close" onclick="closeOverlay()" aria-label="Close">&times;</button>
        <img id="overlayImg" src="" alt="Preview">
    </div>

    <!-- PACKAGE CUSTOMER REVIEWS -->
    <section class="customerReviews" id="packageReviews">
        <h2 class="reviewsTitle">Customer Reviews</h2>
        <div class="reviewPkgDropdownWrapper">
            <select class="reviewPkgDropdown" onchange="switchReviewTab(this.value, null)">
                <?php
                $packagesForTabs = mysqli_query($conn, "SELECT Package_ID_PK, Package_Name FROM packages WHERE Package_Status = 'Available'");
                while ($pkgTab = mysqli_fetch_assoc($packagesForTabs)):
                ?>
                    <option value="<?php echo $pkgTab['Package_ID_PK']; ?>">
                        <?php echo htmlspecialchars($pkgTab['Package_Name']); ?>
                    </option>
                <?php endwhile; ?>
            </select>
        </div>

        <?php
        $packagesForReview = mysqli_query($conn, "SELECT * FROM packages p JOIN bottle b ON p.Bottle_ID_FK = b.Bottle_ID_PK WHERE p.Package_Status = 'Available'");
        $firstPanel = true;
        while ($pkg = mysqli_fetch_assoc($packagesForReview)):
            $pkgID = $pkg['Package_ID_PK'];
            $reviews = $packageReviewsByPkg[$pkgID] ?? [];
            $total = count($reviews);
            $avg = $total > 0 ? array_sum(array_column($reviews, 'Rating')) / $total : 0;
            $rounded = round($avg * 2) / 2;
            $starCounts = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
            foreach ($reviews as $r) $starCounts[(int)$r['Rating']]++;
        ?>
            <div class="pkgReviewBlock <?php echo $firstPanel ? 'active' : ''; ?>" id="pkgReview-<?php echo $pkgID; ?>">
                <?php if ($total === 0): ?>
                    <p class="noReviews">No reviews yet for this package.</p>
                <?php else: ?>
                    <div class="reviewsSummary">
                        <div class="reviewsLeft">
                            <p class="avgNumber"><?php echo number_format($avg, 1); ?></p>
                            <div class="avgStars">
                                <?php for ($i = 1; $i <= 5; $i++): ?>
                                    <span class="star <?php echo $rounded >= $i ? 'full' : ($rounded >= $i - 0.5 ? 'half' : 'empty'); ?>">★</span>
                                <?php endfor; ?>
                            </div>
                            <p class="reviewCount"><?php echo $total; ?> <?php echo $total == 1 ? 'review' : 'reviews'; ?></p>
                        </div>
                        <div class="reviewsRight">
                            <?php for ($s = 5; $s >= 1; $s--):
                                $count = $starCounts[$s];
                                $pct = ($count / $total) * 100;
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
                    <div class="reviewsList">
                        <?php foreach ($reviews as $rev): ?>
                            <div class="reviewCard">
                                <div class="reviewerInfo">
                                    <div class="reviewerAvatar"><?php echo strtoupper(substr($rev['First_Name'], 0, 1)); ?></div>
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
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php $firstPanel = false;
        endwhile; ?>
    </section>

    <!-- FOOTER -->
    <Footer>
        <div class="platforms">
            <img class="icons" onclick="window.open('https://web.facebook.com/profile.php?id=100083402345862', '_blank')" src="assets/facebook.png">
            <img class="icons" onclick="window.open('https://mail.google.com/mail/?view=cm&fs=1&to=jilzevangelista@gmail.com', '_blank')" src="assets/gmail.png">
            <img class="icons" onclick="window.open('https://web.facebook.com/profile.php?id=100083402345862', '_blank')" src="assets/contact.png">
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
        function switchTab(id, btn) {
            document.querySelectorAll('.incl-tab').forEach(function(t) {
                t.classList.remove('active');
                t.setAttribute('aria-selected', 'false');
            });
            document.querySelectorAll('.incl-panel').forEach(function(p) {
                p.classList.remove('active');
            });
            btn.classList.add('active');
            btn.setAttribute('aria-selected', 'true');
            var panel = document.getElementById('tab-' + id);
            if (panel) panel.classList.add('active');
        }

        function openOverlay(src) {
            var overlay = document.getElementById('overlay');
            var img = document.getElementById('overlayImg');
            if (!overlay || !img) return;
            img.src = src;
            overlay.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeOverlay() {
            var overlay = document.getElementById('overlay');
            if (!overlay) return;
            overlay.classList.remove('active');
            document.getElementById('overlayImg').src = '';
            document.body.style.overflow = '';
        }

        function switchReviewTab(pkgID, btn) {
            document.querySelectorAll('.pkgReviewBlock').forEach(function(p) {
                p.classList.remove('active');
            });
            var panel = document.getElementById('pkgReview-' + pkgID);
            if (panel) panel.classList.add('active');
            var dropdown = document.querySelector('.reviewPkgDropdown');
            if (dropdown) dropdown.value = pkgID;
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeOverlay();
        });
    </script>
    <script src="script.js"></script>
</body>

</html>