<?php
session_start();
require("db.php");

$galleryQuery = mysqli_query($conn, "SELECT * FROM gallery_pictures");
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jilz Perfume Bar | About Us</title>
    <link rel="shortcut icon" href="assets/Logo_Tentative.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;600&display=swap" rel="stylesheet">
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
            <li><a href="perfumes.php">Perfumes</a></li>
            <li><a href="booking.php">Booking</a></li>
            <li><a href="aboutUs.php"><b>About</b></a></li>
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
                <li><a href="packages.php">Packages</a></li>
                <li><a href="perfumes.php">Perfumes</a></li>
                <li><a href="booking.php">Booking</a></li>
                <li><a href="aboutUs.php"><b>About</b></a></li>
                <li class="prof" style="display: none;"><a href="profile.php"><b>Profile</b></a></li>
            </ul>
            <img id="close" class="close" style="display: none;" src="assets/close.png" alt="">
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

    <!-- HERO -->
    <div class="aboutHero">
        <p>Jilz Perfume Bar</p>
        <h1>Crafting <em>Memories</em><br>Through Scent</h1>
    </div>

    <!-- ABOUT SECTION -->
    <div class="aboutSection">
        <p class="sectionLabel">About Us</p>
        <div class="aboutTextBlock">
            <h2>About Jilz Perfume Bar</h2>
            <p>At Jilz Perfume Bar, we believe that fragrance is more than just a scent. It is a personal expression, a memory, and a statement of style. Our mission is to make luxury-inspired perfumes accessible to everyone, offering high-quality fragrances that capture the essence of elegance without the high price tag. Each scent in our collection is carefully curated to suit different personalities, moods, and occasions, ensuring that there is something uniquely perfect for you.</p>
            <p>Beyond everyday wear, we specialize in creating unforgettable experiences for events. Our perfume bar setup allows guests to explore and choose scents that become connected to special moments, so every spritz brings them back to that celebration. At Jilz Perfume Bar, we do not just offer fragrances. We help turn your events into lasting memories that your visitors can carry with them long after the moment has passed.</p>
        </div>
    </div>

    <!-- GALLERY -->
    <div class="aboutSectionFull galleryBg">
        <div class="galleryInner">
            <p class="sectionLabel">Gallery</p>
            <h2 style="font-size: clamp(1.6rem, 3vw, 2.2rem); font-weight: 300; color: #1a1a1a; margin: 0 0 0.4rem;">Photo Gallery</h2>
            <p style="font-size: 0.88rem; color: #999; margin: 0;">A glimpse into our events and setups.</p>

            <div class="galleryGrid">
                <?php if (mysqli_num_rows($galleryQuery) > 0): ?>
                    <?php while ($pic = mysqli_fetch_assoc($galleryQuery)): ?>
                        <div class="galleryItem" onclick="openLightbox('<?php echo htmlspecialchars($pic['Img_URL']); ?>')">
                            <img src="<?php echo htmlspecialchars($pic['Img_URL']); ?>" alt="Gallery Photo">
                            <div class="galleryOverlay"></div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p style="color: #aaa; font-size: 0.9rem; grid-column: 1/-1;">No photos available yet.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- LIGHTBOX -->
    <div class="aboutLightbox" id="aboutLightbox" onclick="closeLightbox()">
        <button class="aboutLightboxClose" onclick="closeLightbox()">&#x2715;</button>
        <img id="lightboxImg" src="" alt="Gallery Preview">
    </div>

    <!-- FAQ -->
    <div class="aboutSection">
        <p class="sectionLabel">FAQ</p>
        <h2 style="font-size: clamp(1.6rem, 3vw, 2.2rem); font-weight: 300; color: #1a1a1a; margin: 0 0 0.4rem;">Frequently Asked Questions</h2>
        <p style="font-size: 0.88rem; color: #999; margin: 0 0 0.5rem;">Everything you need to know before booking.</p>

        <div class="faqList">

            <?php
            $faqs = [
                ["How far in advance should I reserve my preferred date?", "We recommend booking at least 14 days before the event to secure your preferred schedule."],
                ["Is a down payment required to confirm my booking?", "Yes. A 50% down payment is required to confirm and secure your reservation."],
                ["What payment methods do you accept?", "We currently accept GCash payments."],
                ["Can I modify my booking after confirmation?", "No. Once a booking is confirmed, modifications are not allowed. You may need to cancel and rebook instead."],
                ["Can I cancel my reservation?", "Yes. Cancellations are allowed; however, refund eligibility depends on the terms and timing of the cancellation."],
                ["How long does it take to receive booking confirmation?", "Booking confirmation is usually processed within the same day and does not take more than 24 hours."],
                ["Do you provide the full perfume bar setup?", "Yes. We provide a complete perfume bar setup for your event, including all necessary materials and styling."],
                ["How long does the perfume bar stay at the venue?", "The service is available for up to 4 hours during your event time."],
                ["Which locations do you cover?", "We currently cater to events within Luzon."],
                ["Is there an additional travel fee for distant venues?", "Yes. For locations outside Meycauayan and Marilao, an additional travel fee applies depending on the distance."],
                ["Are refunds available after cancellation?", "Yes. Refunds are available depending on the cancellation terms and conditions."],
            ];

            foreach ($faqs as $i => $faq): $num = $i + 1;
            ?>
                <div class="faqItem" id="faq-<?php echo $num; ?>">
                    <button class="faqQuestion" onclick="toggleFaq(<?php echo $num; ?>)">
                        <span class="faqNum"><?php echo str_pad($num, 2, '0', STR_PAD_LEFT); ?></span>
                        <span class="faqText"><?php echo $faq[0]; ?></span>
                        <span class="faqChevron">&#x25BE;</span>
                    </button>
                    <div class="faqAnswer">
                        <p><?php echo $faq[1]; ?></p>
                    </div>
                </div>
            <?php endforeach; ?>

        </div>
    </div>

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
                <li><a href="packages.php">Packages</a></li>
                <li><a href="perfumes.php">Perfumes</a></li>
                <li><a href="aboutUs.php">About</a></li>
            </ul>
        </div>
        <div class="copyRight">
            <p>&copy; 2026 Jilz Perfume Bar</p>
        </div>
    </Footer>

    <script src="script.js"></script>
    <script>
        function toggleFaq(num) {
            const item = document.getElementById('faq-' + num);
            const isOpen = item.classList.contains('open');

            // close all
            document.querySelectorAll('.faqItem').forEach(function(el) {
                el.classList.remove('open');
            });

            // open clicked if it was closed
            if (!isOpen) item.classList.add('open');
        }

        function openLightbox(src) {
            document.getElementById('lightboxImg').src = src;
            document.getElementById('aboutLightbox').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeLightbox() {
            document.getElementById('aboutLightbox').classList.remove('active');
            document.getElementById('lightboxImg').src = '';
            document.body.style.overflow = '';
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeLightbox();
        });
    </script>
</body>

</html>