<?php
session_start();
// profile pic
function getAvatarColor($username) {
    $palette = [
        ['bg' => '#EEEDFE', 'text' => '#3C3489'],
        ['bg' => '#E1F5EE', 'text' => '#085041'],
        ['bg' => '#FAECE7', 'text' => '#712B13'],
        ['bg' => '#FBEAF0', 'text' => '#72243E'],
        ['bg' => '#E6F1FB', 'text' => '#0C447C'],
        ['bg' => '#EAF3DE', 'text' => '#27500A'],
        ['bg' => '#FAEEDA', 'text' => '#633806'],
        ['bg' => '#FCF0F0', 'text' => '#791F1F'],
    ];
    $hash = 0;
    foreach (str_split($username) as $char) {
        $hash = ord($char) + (($hash << 5) - $hash);
    }
    return $palette[abs($hash) % count($palette)];
}

$username   = $_SESSION['Username'] ?? 'Guest';
$avatarColor = getAvatarColor($username);
$avatarLetter = strtoupper(mb_substr($username, 0, 1));
require("db.php");

// AJAX handler: filter perfume cards by category
if (isset($_POST['category'])) {
    $category = $_POST['category'];
    $query    = "SELECT * FROM Perfume WHERE Perfume_Status = 'Available'";
    if (!empty($category)) {
        $safeCategory = mysqli_real_escape_string($conn, $category);
        $query .= " AND Perfume_Category = '$safeCategory'";
    }
    $result = mysqli_query($conn, $query);
    while ($perfume = mysqli_fetch_assoc($result)) {
        // echo '<div class="perfumeCard" onclick="window.location.href=\'?perfume_id=' . $perfume['Perfume_ID_PK'] . '\'">';
        echo '<div class="perfumeCard" onclick="loadPerfume(' . $perfume['Perfume_ID_PK'] . ')">';
        echo '<img src="' . (!empty($perfume['Perfume_Img']) ? htmlspecialchars($perfume['Perfume_Img']) : 'assets/perfumeBottle.png') . '">';
        echo '<div class="perfumeTitle">';
        echo '<p>' . htmlspecialchars($perfume['Inspired_Scent']) . '</p>';
        echo '</div>';
        echo '</div>';
    }
    exit;
}


// AJAX: fetch selected perfume details + reviews
if (isset($_POST['get_perfume'])) {
    $id = intval($_POST['get_perfume']);
    
    $p = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM Perfume WHERE Perfume_ID_PK = $id"));
    
    // $rq = mysqli_query($conn, "SELECT pr.Rating, pr.Description, ui.First_Name, ui.Last_Name
    //     FROM Perfume_Ratings pr
    //     JOIN User_Information ui ON pr.User_ID_FK = ui.User_ID_PK
    //     WHERE pr.Perfume_ID_FK = $id");
    
    $rq = mysqli_query($conn, "SELECT pr.Rating, pr.Description, pr.is_hidden, ui.First_Name, ui.Last_Name
    FROM Perfume_Ratings pr
    JOIN User_Information ui ON pr.User_ID_FK = ui.User_ID_PK
    WHERE pr.Perfume_ID_FK = $id");
    
    $rows = [];
    while ($r = mysqli_fetch_assoc($rq)) $rows[] = $r;
    
    echo json_encode(['perfume' => $p, 'reviews' => $rows]);
    exit;
}

// Load all available perfumes for the card grid
$perfumes1 = mysqli_query($conn, "SELECT * FROM Perfume WHERE Perfume_Status = 'Available'");
$perfumes  = mysqli_query($conn, "SELECT * FROM Perfume WHERE Perfume_Status = 'Available'");

// Determine which perfume is selected (default to the lowest ID)
$minRow          = mysqli_fetch_assoc(mysqli_query($conn, "SELECT MIN(Perfume_ID_PK) AS min_id FROM Perfume WHERE Perfume_Status = 'Available'"));
$perfumeSelected = isset($_GET['perfume_id']) ? intval($_GET['perfume_id']) : intval($minRow['min_id']);

// Fetch the full selected perfume row so we have Perfume_Color AND Perfume_Background
$selectedPerfumeData = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT * FROM Perfume WHERE Perfume_ID_PK = $perfumeSelected")
);

// Safe fallbacks so the page never breaks on empty values
$perfumeColor      = !empty($selectedPerfumeData['Perfume_Color'])      ? $selectedPerfumeData['Perfume_Color']      : '#ffffff';
$perfumeBackground = !empty($selectedPerfumeData['Perfume_Background']) ? $selectedPerfumeData['Perfume_Background'] : 'assets/perfumeBG.jpg';

// Fetch ratings for the selected perfume
// $ratingQuery = mysqli_query(
//     $conn,
//     "SELECT pr.Rating, pr.Description, pr.User_ID_FK,
//             ui.First_Name, ui.Last_Name, p.Perfume_ID_PK
//      FROM Perfume_Ratings pr
//      JOIN Perfume p          ON pr.Perfume_ID_FK  = p.Perfume_ID_PK
//      JOIN User_Information ui ON pr.User_ID_FK     = ui.User_ID_PK
//      WHERE p.Perfume_ID_PK = $perfumeSelected"
// );
$ratingQuery = mysqli_query($conn, "
    SELECT pr.Rating, pr.Description, pr.is_hidden, 
           ui.First_Name, ui.Last_Name
    FROM Perfume_Ratings pr
    JOIN User_Information ui ON pr.User_ID_FK = ui.User_ID_PK
    WHERE pr.Perfume_ID_FK = $perfumeSelected
");

$ratingRows   = [];
while ($row = mysqli_fetch_assoc($ratingQuery)) {
    $ratingRows[] = $row;
}

$totalRatings = count($ratingRows);
$avgRating    = $totalRatings > 0 ? array_sum(array_column($ratingRows, 'Rating')) / $totalRatings : 0;

$starCounts = [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
foreach ($ratingRows as $r) {
    $starCounts[(int)$r['Rating']]++;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jilz Perfume Bar | Perfumes</title>
    <link rel="shortcut icon" href="assets/Logo_Tentative.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=3">
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

    <!-- Mobile nav overlay -->
    <div class="mobileNavOverlay" id="mobileNavOverlay" onclick="closeDrawer()"></div>

    <!-- Mobile nav drawer -->
    <div class="mobileNavDrawer" id="mobileNavDrawer">
        <div class="drawerHeader">
            <img class="drawerLogo" src="assets/Logo_Tentative.png" alt="Jilz">
            <button class="drawerClose" onclick="closeDrawer()">&#10005;</button>
        </div>

        <?php if (isset($_SESSION['UserID']) || isset($_SESSION['AdminID'])): ?>
            <div class="drawerUserProfile" onclick="window.location.href='profile.php'">
                <div style="
    width: 44px; height: 44px; border-radius: 50%;
    background: <?= $avatarColor['bg'] ?>;
    color: <?= $avatarColor['text'] ?>;
    display: flex; align-items: center; justify-content: center;
    font-weight: 500; font-size: 18px; flex-shrink: 0;
"><?= htmlspecialchars($avatarLetter) ?></div>
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

    <!-- Header -->
    <Header>
        <div class="logo">
            <div class="logoBrand" onclick="window.location.href='index.php'">
                <img src="assets/Logo_Tentative.png" alt="Jilz Logo">
                <div class="logoDivider"></div>
                <div class="logoBrandText">
                    <span class="brandName">Jilz</span>
                    <span class="brandSub">perfume bar</span>
                </div>
            </div>
        </div>

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

        <?php if (isset($_SESSION['UserID']) || isset($_SESSION['AdminID'])): ?>
            <div class="userProfile" style="display: flex;">
                <div style="
    width: 44px; height: 44px; border-radius: 50%;
    background: <?= $avatarColor['bg'] ?>;
    color: <?= $avatarColor['text'] ?>;
    display: flex; align-items: center; justify-content: center;
    font-weight: 500; font-size: 18px; flex-shrink: 0; border:solid 1.5px;cursor: pointer;
"onclick="window.location.href='profile.php'" ><?= htmlspecialchars($avatarLetter) ?></div>
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

    <!-- Perfume description hero — background colour AND background image both from DB -->
    <section class="perfumeDescription"
        style="background-color: <?= htmlspecialchars($perfumeColor) ?>;">

        <?php while ($perfume = mysqli_fetch_assoc($perfumes1)): ?>
            <?php if ($perfume['Perfume_ID_PK'] == $perfumeSelected): ?>

                <!-- Hero: background image from Perfume_Background column + bottle image -->
                <div class="perfumeHero">
                    <img src="<?= !empty($perfume['Perfume_Background']) ? htmlspecialchars($perfume['Perfume_Background']) : 'assets/perfumeBG.jpg' ?>"
                        id="perfumeBG">
                    <div class="perfumeImg">
                        <img src="<?= !empty($perfume['Perfume_Img']) ? htmlspecialchars($perfume['Perfume_Img']) : 'assets/perfumeBottle.png' ?>">
                    </div>
                </div>

                <!-- Details block below the hero -->
                <div class="details">
                    <div class="perfumeDetails">
                        <p class="inspiredBy">INSPIRED BY</p>
                        <p class="perfumeName"><?= htmlspecialchars($perfume['Inspired_Scent']) ?></p>
                        <p class="desc"><?= htmlspecialchars($perfume['Perfume_Description']) ?></p>
                        <p class="ingredientTitle">Category: <?= htmlspecialchars($perfume['Perfume_Category']) ?></p>

                        <a href="#customerReviews" class="ratingAnchor">
                            <div class="ratingStarRow">
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
                                <span class="ratingCount">
                                    <?= number_format($avgRating, 1) ?>
                                    (<?= $totalRatings ?> <?= $totalRatings == 1 ? 'review' : 'reviews' ?>)
                                </span>
                            </div>
                        </a>
                    </div>
                </div>

            <?php endif; ?>
        <?php endwhile; ?>
    </section>

    <!-- Perfume collection grid -->
    <h1 id="perfumeTitle">JILZ COLLECTION</h1>

    <select id="perfCat" name="perfCat" class="stat">
        <option value="" disabled selected>Category</option>
        <option value="">All Perfumes</option>
        <option value="Male">Male</option>
        <option value="Female">Female</option>
        <option value="Unisex">Unisex</option>
    </select>

    <section class="perfumeLists" id="perfumeContainer">
        <?php while ($perfume = mysqli_fetch_assoc($perfumes)): ?>
            <div class="perfumeCard" onclick="loadPerfume(<?= $perfume['Perfume_ID_PK'] ?>)">
                
                <img src="<?= !empty($perfume['Perfume_Img']) ? htmlspecialchars($perfume['Perfume_Img']) : 'assets/perfumeBottle.png' ?>">
                <div class="perfumeTitle">
                    <p><?= htmlspecialchars($perfume['Inspired_Scent']) ?></p>
                </div>
            </div>
        <?php endwhile; ?>
    </section>

    <!-- Customer reviews -->
    <section class="customerReviews" id="customerReviews">
        <h2 class="reviewsTitle">Customer Reviews</h2>

        <div class="reviewsSummary">
            <!-- Average score + stars -->
            <div class="reviewsLeft">
                <p class="avgNumber"><?= number_format($avgRating, 1) ?></p>
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
                <p class="reviewCount"><?= $totalRatings ?> <?= $totalRatings == 1 ? 'review' : 'reviews' ?></p>
            </div>

            <!-- Star breakdown bars -->
            <div class="reviewsRight">
                <?php for ($s = 5; $s >= 1; $s--):
                    $count = $starCounts[$s];
                    $pct   = $totalRatings > 0 ? ($count / $totalRatings) * 100 : 0;
                ?>
                    <div class="starBarRow">
                        <span class="starBarLabel"><?= $s ?> Star<?= $s > 1 ? 's' : '' ?></span>
                        <div class="starBarTrack">
                            <div class="starBarFill" style="width: <?= $pct ?>%"></div>
                        </div>
                        <span class="starBarPct"><?= number_format($pct, 1) ?>%</span>
                    </div>
                <?php endfor; ?>
            </div>
        </div>

        <!-- Individual review cards -->
        <div class="reviewsList">
            <?php if (empty($ratingRows)): ?>
                <p class="noReviews">No reviews yet for this perfume.</p>
            <?php else: ?>
                <?php foreach ($ratingRows as $rev): 
    $isHidden = !empty($rev['is_hidden']);
?>
    <div class="reviewCard <?= $isHidden ? 'hidden-review' : '' ?>">
        <div class="reviewerInfo">
            <div class="reviewerAvatar">
                <?= strtoupper(substr($rev['First_Name'], 0, 1)) ?>
            </div>
            <div>
                <p class="reviewerName"><?= htmlspecialchars($rev['First_Name'] . ' ' . $rev['Last_Name']) ?></p>
                <div class="reviewStars">
                    <?php for ($i = 1; $i <= 5; $i++): ?>
                        <span class="star <?= $i <= $rev['Rating'] ? 'full' : 'empty' ?>">★</span>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
        
        <?php if ($isHidden): ?>
            <p class="reviewDesc hidden-desc"></p>
        <?php else: ?>
            <p class="reviewDesc"><?= htmlspecialchars($rev['Description']) ?></p>
        <?php endif; ?>
    </div>
<?php endforeach; ?>
            <?php endif; ?>
        </div>
    </section>

    <!-- Footer -->
    <Footer>
        <div class="platforms">
            <img class="icons"
                onclick="window.open('https://web.facebook.com/profile.php?id=100083402345862', '_blank')"
                src="assets/facebook.png" alt="Facebook">
            <img class="icons"
                onclick="window.open('https://mail.google.com/mail/?view=cm&fs=1&to=jenprado13@gmail.com', '_blank')"
                src="assets/gmail.png" alt="Gmail">
            <img class="icons"
                onclick="window.location.href='tel:+639615517623'"
                src="assets/contact.png"
                alt="Contact">
        </div>

        <div class="footNavs">
            <ul>
                <li><a href="#">Home</a></li>
                <li><a href="packages.php">Packages</a></li>
                <li><a href="perfumes.php">Perfumes</a></li>
                <li><a href="booking.php">Booking</a></li>
                <li><a href="aboutUs.php">About</a></li>
                <li><a href="profile.php">Profile</a></li>
            </ul>
        </div>

        <div class="copyRight">
            <p>&copy; 2026 Jilz Perfume Bar</p>
        </div>
    </Footer>

    <script>
        
        // Filter cards by category via AJAX without reloading
document.getElementById("perfCat").addEventListener("change", function() {
    let category = this.value;
    fetch("perfumes.php", {
        method: "POST",
        headers: { "Content-Type": "application/x-www-form-urlencoded" },
        body: "category=" + encodeURIComponent(category)
    })
    .then(res => res.text())
    .then(data => {
        document.getElementById("perfumeContainer").innerHTML = data;
    });
});

function loadPerfume(id) {
    fetch('perfumes.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'get_perfume=' + id
    })
    .then(res => res.json())
    .then(data => {
        const p = data.perfume;
        const reviews = data.reviews;

        // Update hero
        document.querySelector('.perfumeDescription').style.backgroundColor = p.Perfume_Color || '#ffffff';
        document.getElementById('perfumeBG').src = p.Perfume_Background || 'assets/perfumeBG.jpg';
        document.querySelector('.perfumeImg img').src = p.Perfume_Img || 'assets/perfumeBottle.png';

        // Update text
        document.querySelector('.perfumeName').textContent = p.Inspired_Scent;
        document.querySelector('.desc').textContent = p.Perfume_Description;
        document.querySelector('.ingredientTitle').textContent = 'Category: ' + p.Perfume_Category;

        // Update rating row sa hero
        const total = reviews.length;
        const avg = total > 0 ? reviews.reduce((s, r) => s + parseFloat(r.Rating), 0) / total : 0;
        document.querySelector('.ratingCount').textContent = avg.toFixed(1) + ' (' + total + (total == 1 ? ' review)' : ' reviews)');

        // Update reviews section
        updateReviews(reviews);

        // Scroll to top
        window.scrollTo({ top: 0, behavior: 'smooth' });
    });
}

function updateReviews(reviews) {
    const total = reviews.length;
    const avg = total > 0 ? reviews.reduce((s, r) => s + parseFloat(r.Rating), 0) / total : 0;

    document.querySelector('.avgNumber').textContent = avg.toFixed(1);
    document.querySelector('.reviewCount').textContent = total + (total == 1 ? ' review' : ' reviews');

    // Star bars
    const counts = {5:0, 4:0, 3:0, 2:0, 1:0};
    reviews.forEach(r => counts[parseInt(r.Rating)]++);
    document.querySelectorAll('.starBarRow').forEach((row, idx) => {
        const star = 5 - idx;
        const pct = total > 0 ? (counts[star] / total * 100) : 0;
        row.querySelector('.starBarFill').style.width = pct + '%';
        row.querySelector('.starBarPct').textContent = pct.toFixed(1) + '%';
    });

    // Review cards
    const list = document.querySelector('.reviewsList');
    if (total === 0) {
        list.innerHTML = '<p class="noReviews">No reviews yet for this perfume.</p>';
        return;
    }
    list.innerHTML = reviews.map(r => {
    const isHidden = r.is_hidden == 1;
    return `
        <div class="reviewCard ${isHidden ? 'hidden-review' : ''}">
            <div class="reviewerInfo">
                <div class="reviewerAvatar">${r.First_Name.charAt(0).toUpperCase()}</div>
                <div>
                    <p class="reviewerName">${r.First_Name} ${r.Last_Name}</p>
                    <div class="reviewStars">
                        ${[1,2,3,4,5].map(i => `<span class="star ${i <= r.Rating ? 'full' : 'empty'}">★</span>`).join('')}
                    </div>
                </div>
            </div>
            ${isHidden ? 
                `<p class="reviewDesc hidden-desc"></p>` : 
                `<p class="reviewDesc">${r.Description}</p>`
            }
        </div>
    `;
}).join('');
}
        // Filter cards by category via AJAX without reloading
        // document.getElementById("perfCat").addEventListener("change", function() {
        //     let category = this.value;
        //     fetch("perfumes.php", {
        //             method: "POST",
        //             headers: {
        //                 "Content-Type": "application/x-www-form-urlencoded"
        //             },
        //             body: "category=" + encodeURIComponent(category)
        //         })
        //         .then(res => res.text())
        //         .then(data => {
        //             document.getElementById("perfumeContainer").innerHTML = data;
        //         });
        // });
    </script>
    <script src="script.js"></script>
</body>

</html>