<?php
session_start();
require("db.php");

if (!isset($_SESSION['UserID'])) {
    header("Location: login.php");
    exit();
}

// GET PACKAGE ID
$packageID = isset($_GET['package_id']) ? (int)$_GET['package_id'] : 0;

$packages = mysqli_query($conn, "SELECT * FROM packages");

$selectedPackage = mysqli_query(
    $conn,
    "SELECT 
        p.*,
        b.Bottle_ID_PK,
        b.Bottle_Name
     FROM packages p
     JOIN bottle b 
     ON p.Bottle_ID_FK = b.Bottle_ID_PK
     WHERE p.Package_ID_PK = '$packageID'"
);

$selectedPackage = mysqli_fetch_assoc($selectedPackage);

//GET BOTTLES
$bottleID = $selectedPackage['Bottle_ID_PK'] ?? null;

$bottles = null;
if ($bottleID) {
    $bottles = mysqli_query(
        $conn,
        "SELECT * FROM bottle_variants 
         WHERE Bottle_ID_FK = '$bottleID' AND Bottle_Var_Status = 'Available'"
    );
}

// GET BAR SETUPS
$barSetup = mysqli_query($conn, "SELECT * FROM bar_setup WHERE Bar_Status = 'Available'");

// GET PERFUMES
$perfumes = mysqli_query($conn, "SELECT * FROM perfume");

// SUBMIT BOOKING
if (($_SERVER['REQUEST_METHOD'] ?? null) === 'POST') {

    $userID = $_SESSION['UserID'] ?? null;
    $date = $_POST['selected_date'] ?? null;
    $bottleVar = $_POST['bottle'] ?? null;
    $setupID = $_POST['setup'] ?? null;
    $timeStart = $_POST['eventTimeFrom'] ?? null;
    $timeEnd = $_POST['eventTimeTo'] ?? null;
    $eventType = $_POST['eventType'] ?? null;
    $eventAddress = $_POST['eventAddress'] ?? null;
    $eventNotes = $_POST['eventNotes'] ?? null;

    // GET SELECTED PERFUMES
    $selectedPerfumes = $_POST['perfumes'] ?? [];

    $max = $selectedPackage['No_of_Scent'] ?? 0;
    $error = "";

    // Two-week-ahead policy check
    $twoWeeksFromNow = new DateTime();
    $twoWeeksFromNow->modify('+14 days');
    $twoWeeksFromNow->setTime(0, 0, 0);

    if (!$userID) {
        $error = "User not found!";
    } elseif (!$timeStart || !$timeEnd) {
        $error = "Please select event time!";
    } elseif (!$date) {
        $error = "Please select a date!";
    } elseif (count($selectedPerfumes) != $max) {
        $error = "Selected Perfumes: " . count($selectedPerfumes) . " (Expected: $max)";
    } else {
        $selectedDateTime = new DateTime($date);
        $selectedDateTime->setTime(0, 0, 0);
        if ($selectedDateTime < $twoWeeksFromNow) {
            $error = "Bookings must be made at least 2 weeks in advance.";
        }
    }

    if ($error === '') {
        $sql = "INSERT INTO Booking 
    (User_ID_FK, Package_ID_FK, Bottle_Var_ID_FK, Bar_Setup_ID_FK, Event_Time_Start, Event_Time_End, Event_Type, Event_Address, Event_Date, Event_Notes)
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($conn, $sql);

        mysqli_stmt_bind_param(
            $stmt,
            "iiiissssss",
            $userID,
            $packageID,
            $bottleVar,
            $setupID,
            $timeStart,
            $timeEnd,
            $eventType,
            $eventAddress,
            $date,
            $eventNotes
        );
        mysqli_stmt_execute($stmt);

        // 2. GET BOOKING ID
        $bookingID = mysqli_insert_id($conn);

        // 3. INSERT PERFUMES
        foreach ($selectedPerfumes as $perfumeID) {
            $perfumeID = (int)$perfumeID;
            mysqli_query(
                $conn,
                "INSERT INTO booking_perfume (Booking_ID_FK, Perfume_ID_FK)
             VALUES ($bookingID, $perfumeID)"
            );
        }

        // Redirect to mybookings.php after successful booking
        echo "<script>alert('Booking successful!'); window.location.href='mybookings.php';</script>";
        exit();
    }
}

// Get only APPROVED booked dates (policy: only approved bookings block dates)
$bookedDates = [];
$result = mysqli_query($conn, "SELECT Event_Date FROM Booking WHERE Booking_Status = 'Approved'");

while ($row = mysqli_fetch_assoc($result)) {
    $bookedDates[] = $row['Event_Date'];
}

// Calculate 2-weeks-ahead minimum date for JS
$minBookingDate = date('Y-m-d', strtotime('+14 days'));
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jilz Perfume Bar | Booking</title>
    <link rel="shortcut icon" href="assets/Logo_Tentative.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="mobileStyle.css">
    <style>
        /* ── OVERFLOW FIX: prevent horizontal scroll on mobile ── */
        html,
        body {
            max-width: 100%;
            overflow-x: hidden;
        }

        /* ── DROPDOWN: open to the left on mobile ── */
        @media (max-width: 768px) {
            .packageList {
                left: auto !important;
                right: 0 !important;
                top: 2rem !important;
            }
        }

        /* ── PIC TITLE: larger text ── */
        .bookingContainer .infos .picCard .picTitle,
        .bookingContainer .infos .picCardSetup .picTitle {
            font-size: 0.95rem !important;
        }

        @media (max-width: 440px) {

            .bookingContainer .infos .picCard .picTitle,
            .bookingContainer .infos .picCardSetup .picTitle {
                font-size: 0.82rem !important;
            }

            /* Fix footer overflow */
            Footer {
                width: 100%;
                box-sizing: border-box;
                overflow: hidden;
            }

            Footer .footNavs ul {
                flex-wrap: wrap;
                justify-content: center;
                gap: 0.75rem;
                padding: 1rem 0.5rem;
            }

            /* Ensure booking container doesn't overflow */
            .bookingContainer {
                box-sizing: border-box;
                width: 100%;
                padding-left: 0.75rem;
                padding-right: 0.75rem;
            }

            .bookingContainer .packageBook {
                width: 100%;
                box-sizing: border-box;
            }

            .bookingContainer .packageBook .perfContainer {
                width: 100%;
                box-sizing: border-box;
            }

            .bookingContainer .packageBook .location #eventAddress {
                width: 100%;
                box-sizing: border-box;
            }

            .bookingContainer .infos {
                width: 100%;
                box-sizing: border-box;
            }

            .bookingContainer .infos .pics {
                width: 100%;
                box-sizing: border-box;
            }

            .bookingContainer .infos .notes textarea {
                width: 100%;
                box-sizing: border-box;
            }

            .bookingContainer .infos .submit {
                width: 100%;
                box-sizing: border-box;
            }

            .calendar {
                width: 100%;
                box-sizing: border-box;
            }

            /* Dropdown opens to left */
            .packageList {
                left: auto !important;
                right: 0 !important;
                top: 2rem !important;
                min-width: 180px;
            }
        }

        @media (max-width: 768px) and (min-width: 441px) {

            html,
            body {
                overflow-x: hidden;
            }

            .bookingContainer {
                box-sizing: border-box;
                width: 100%;
                padding-left: 1rem;
                padding-right: 1rem;
            }

            Footer {
                width: 100%;
                box-sizing: border-box;
                overflow: hidden;
            }
        }

        /* Disabled day style for past dates AND within-2-week dates */
        .day.disabled,
        .day.disabled:hover {
            color: #ccc !important;
            background: #f5f5f5 !important;
            pointer-events: none !important;
            cursor: default !important;
        }

        /* Today when it falls in the disabled window — keep black highlight */
        .day.today.disabled,
        .day.today.disabled:hover {
            background: black !important;
            color: white !important;
            pointer-events: none !important;
            cursor: default !important;
        }
    </style>
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
            <li><a href="booking.php"><b>Booking</b></a></li>
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
                <li><a href="perfumes.php">Perfumes</a></li>
                <li><a href="booking.php"><b>Booking</b></a></li>
                <li><a href="aboutUs.php">About</a></li>
                <li class="prof" style="display: none;"><a href="profile.php"><b>Profile</b></a></li>
            </ul>
            <img id="close" class="close" style="display: none;" src="assets/close.png" alt="">
        </div>

        <!-- LOGIN AND SIGNUP BUTTON -->
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

    <section>
        <form id="bookingForm" class="bookingContainer" action="" method="POST">
            <div class="packageBook">
                <div class="pckg">
                    <!-- Event details -->
                    <div>
                        <p>Event Details</p>
                    </div>
                    <!-- package selected -->
                    <div class="pckgSelected">
                        <h1><?php echo $selectedPackage['Package_Name'] ?? 'Package Name'; ?></h1>
                        <div class="packageDropdown">
                            <img src="assets/refresh.png" alt="" onclick="togglePackages()">

                            <div class="packageList" id="packageList">
                                <?php while ($pkg = mysqli_fetch_assoc($packages)) { ?>
                                    <a href="booking.php?package_id=<?php echo $pkg['Package_ID_PK']; ?>">
                                        <?php echo $pkg['Package_Name']; ?>
                                    </a>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                    <?php if ($packageID !== 0) { ?>
                        <p><?php echo $selectedPackage['No_of_Bottles'] ?? 0; ?>pcs <?php echo $selectedPackage['Bottle_Name'] ?? 'Bottle Name'; ?> Glass Spray Bottles,
                            Elegant Setup Based on Motif, <?php echo $selectedPackage['No_of_Scent'] ?? 0; ?> Scent of Choices</p>
                    <?php } ?>
                </div>
                <!-- perfumes -->
                <label for="">Perfumes</label>
                <div class="perfContainer">
                    <?php while ($perfume = mysqli_fetch_assoc($perfumes)) { ?>
                        <?php if ($perfume['Perfume_Status'] == 'Available') { ?>
                            <label class="perfCard">
                                <img src="assets/perfumeBottle.png" alt="">
                                <p><?php echo $perfume['Inspired_Scent']; ?></p>
                                <input type="checkbox"
                                    name="perfumes[]"
                                    value="<?php echo $perfume['Perfume_ID_PK']; ?>"
                                    <?php
                                    if (!empty($_POST['perfumes']) && in_array($perfume['Perfume_ID_PK'], $_POST['perfumes'])) {
                                        echo "checked";
                                    }
                                    ?>>
                            </label>
                        <?php } ?>
                    <?php } ?>
                </div>
                <!-- time -->
                <div class="timeType">
                    <div>
                        <label for="">Event Time</label>
                        <div class="timeRange">
                            <input name="eventTimeFrom" type="time" required
                                value="<?php echo $_POST['eventTimeFrom'] ?? ''; ?>">To <input name="eventTimeTo" type="time" required
                                value="<?php echo $_POST['eventTimeTo'] ?? ''; ?>">
                        </div>
                    </div>
                    <div>
                        <label for="">Event Type</label>
                        <input id="eventType" name="eventType" type="text" required
                            value="<?php echo htmlspecialchars($_POST['eventType'] ?? ''); ?>" placeholder="Weddings, Birthday, Christening etc...">
                    </div>
                </div>
                <!-- SETUPS -->
                <div class="aboutSetup">
                    <!-- choose bottle variation-->
                    <div>
                        <label for="bottle">Bottle Variation</label>
                        <select name="bottle" class="chooseBottle" id="bottle" required>
                            <option value="" disabled selected>Select Bottle</option>
                            <?php if ($bottles) {
                                while ($bottle = mysqli_fetch_assoc($bottles)) { ?>
                                    <option value="<?php echo $bottle['Bottle_Var_ID_PK'] ?>">
                                        <?php echo $selectedPackage['Bottle_Name'] ?> <?php echo $bottle['Bottle_Var_Name'] ?>
                                    </option>
                            <?php }
                            } ?>
                        </select>
                    </div>
                    <!-- choose setup -->
                    <div>
                        <label for="setup">Bar Setup</label>
                        <select name="setup" class="chooseSetup" id="setup" required>
                            <option value="" disabled selected>Select Setup</option>
                            <?php while ($barSetups = mysqli_fetch_assoc($barSetup)) { ?>
                                <option value="<?php echo $barSetups['Bar_Setup_ID_PK'] ?>">
                                    <?php echo $barSetups['Bar_Name'] ?>
                                </option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
                <!-- location/address -->
                <div class="location">
                    <label for="">Event Location / Address</label>
                    <input id="eventAddress" name="eventAddress" type="text" required
                        value="<?php echo htmlspecialchars($_POST['eventAddress'] ?? ''); ?>" placeholder="Unit Number, Street, Barangay, City/Municipality, Province, and Postal Code.">
                </div>
            </div>

            <div class="infos">
                <div class="pics">
                    <!-- BOTTLES -->
                    <?php if ($bottles) {
                        mysqli_data_seek($bottles, 0);
                        while ($bottle = mysqli_fetch_assoc($bottles)) { ?>
                            <div class="picCard">
                                <img src="<?php echo $bottle['Bottle_Img'] ?>" onclick="openOverlay(this.src)" alt="">
                                <p class="picTitle"><?php echo $selectedPackage['Bottle_Name'] ?> <?php echo $bottle['Bottle_Var_Name'] ?></p>
                            </div>
                    <?php }
                    } ?>
                    <!-- SETUPS -->
                    <?php {
                        mysqli_data_seek($barSetup, 0);
                        while ($barSetups = mysqli_fetch_assoc($barSetup)) { ?>
                            <div class="picCardSetup">
                                <img src="<?php echo htmlspecialchars($barSetups['Bar_Img']); ?>" onclick="openOverlay(this.src)" alt="">
                                <p class="picTitle"><?php echo $barSetups['Bar_Name'] ?></p>
                            </div>
                        <?php } ?>
                    <?php } ?>
                </div>

                <!-- calendar -->
                <div class="calendar">
                    <div class="header">
                        <button type="button" id="prev">&#10094;</button>
                        <h2 id="monthYear"></h2>
                        <button type="button" id="next">&#10095;</button>
                    </div>

                    <div class="days-header">
                        <div>Sun</div>
                        <div>Mon</div>
                        <div>Tue</div>
                        <div>Wed</div>
                        <div>Thu</div>
                        <div>Fri</div>
                        <div>Sat</div>
                    </div>

                    <div id="days"></div>
                    <div class="datePicked">
                        <input type="text" name="selected_date" id="selected_date" readonly required
                            value="<?php echo $_POST['selected_date'] ?? ''; ?>">
                    </div>
                </div>

                <!-- notes -->
                <div class="notes">
                    <label for="">Notes</label>
                    <textarea name="eventNotes" id="eventNotes" placeholder="Event Themes/Motif, Suggestions, Other Concerns" required><?php echo htmlspecialchars($_POST['eventNotes'] ?? ''); ?></textarea>
                </div>
                <button class="submit" type="button" onclick="openPopup()">Confirm Booking</button>
            </div>
        </form>
    </section>

    <!-- POP UP IF MISSING INPUT -->
    <section class="popUp" id="popup" style="display:none;">
        <div class="notif">
            <div class="icon">
                <img src="assets/warning.png" alt="warning">
                <p>Missing Input</p>
            </div>
            <p class="errorMsg"></p>
            <button onclick="document.getElementById('popup').style.display='none'">
                Ok
            </button>
        </div>
    </section>

    <!-- POP UP ARE YOU SURE -->
    <section class="popUp" id="popupRuSure" style="display: none;">
        <div class="notif">
            <div class="icon">
                <img src="assets/warning.png" alt="warning">
                <p>Are you sure?</p>
            </div>
            <p class="errorMsg">Please confirm your booking. You will <br> not be able to edit it after submission.</p>
            <div class="uSureBtn">
                <button class="yes" type="submit" name="submitBtn" form="bookingForm">
                    Yes
                </button>
                <button class="no" onclick="document.getElementById('popupRuSure').style.display='none'">
                    No
                </button>
            </div>
        </div>
    </section>

    <div id="imageOverlay" onclick="closeOverlay()" style="display:none;">
        <img id="overlayImg" src="" alt="">
    </div>

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
        // Only APPROVED dates are blocked
        let bookedDates = <?php echo json_encode($bookedDates); ?>;

        // Minimum booking date: 2 weeks from today
        let minBookingDate = '<?php echo $minBookingDate; ?>';
    </script>

    <script>
        window.maxPerfumes = <?php echo (int)($selectedPackage['No_of_Scent'] ?? 0); ?>;
    </script>

    <script>
        function togglePackages() {
            let list = document.getElementById("packageList");
            list.style.display = (list.style.display === "block") ? "none" : "block";
        }

        // close when clicked outside
        document.addEventListener("click", function(e) {
            let dropdown = document.querySelector(".packageDropdown");
            if (!dropdown.contains(e.target)) {
                document.getElementById("packageList").style.display = "none";
            }
        });
    </script>

    <script>
        function showErrorPopup(msg) {
            let popup = document.getElementById("popup");
            popup.querySelector(".errorMsg").innerText = msg;
            popup.style.display = "flex";
        }

        function openPopup() {
            // get inputs
            let date = document.getElementById("selected_date").value;
            let timeFrom = document.querySelector("input[name='eventTimeFrom']").value;
            let timeTo = document.querySelector("input[name='eventTimeTo']").value;
            let type = document.getElementById("eventType").value;
            let address = document.getElementById("eventAddress").value;
            let notes = document.getElementById("eventNotes").value;
            let bottle = document.getElementById("bottle").value;
            let setup = document.getElementById("setup").value;

            // check perfumes
            let perfumes = document.querySelectorAll("input[name='perfumes[]']:checked");
            let max = window.maxPerfumes;

            // VALIDATION
            if (!timeFrom || !timeTo || !type || !address || !notes || !bottle || !setup) {
                showErrorPopup("Please fill in all required fields.");
                return;
            }

            let start = new Date("1970-01-01T" + timeFrom);
            let end = new Date("1970-01-01T" + timeTo);

            if (end <= start) {
                end = new Date("1970-01-02T" + timeTo);
            }

            let diffMs = end - start;
            let diffHrs = diffMs / (1000 * 60 * 60);

            if (diffMs <= 0) {
                showErrorPopup("Event end time must be after start time.");
                return;
            }

            if (diffHrs > 4) {
                showErrorPopup("Event duration must not exceed 4 hours.");
                return;
            }

            if (!date) {
                showErrorPopup("Please select a date.");
                return;
            }

            // 2-week policy check (client-side)
            let selectedDateObj = new Date(date + "T00:00:00");
            let minDate = new Date(minBookingDate + "T00:00:00");
            if (selectedDateObj < minDate) {
                showErrorPopup("Bookings must be made at least 2 weeks in advance.");
                return;
            }

            if (perfumes.length != max) {
                showErrorPopup("Please select exactly " + max + " perfumes.");
                return;
            }

            // IF VALID → SHOW CONFIRM POPUP
            document.getElementById("popupRuSure").style.display = "flex";
        }

        function closePopup() {
            document.getElementById("popupRuSure").style.display = "none";
        }

        // FULL IMAGE VIEW
        function openOverlay(src) {
            document.getElementById("overlayImg").src = src;
            document.getElementById("imageOverlay").style.display = "flex";
        }

        function closeOverlay() {
            document.getElementById("imageOverlay").style.display = "none";
        }
    </script>
    <script src="perfume.js"></script>
    <script>
        // ── CALENDAR (inline — replaces calendar.js) ──────────────────────────
        (function() {
            const monthYear = document.getElementById("monthYear");
            const daysContainer = document.getElementById("days");
            const prevBtn = document.getElementById("prev");
            const nextBtn = document.getElementById("next");

            let currentDate = new Date();
            let selectedDate = null;

            const existingVal = document.getElementById("selected_date").value;
            if (existingVal) selectedDate = existingVal;

            const months = [
                "January", "February", "March", "April", "May", "June",
                "July", "August", "September", "October", "November", "December"
            ];

            function renderCalendar() {
                daysContainer.innerHTML = "";

                const year = currentDate.getFullYear();
                const month = currentDate.getMonth();
                monthYear.textContent = months[month] + " " + year;

                const firstDayIndex = new Date(year, month, 1).getDay();
                const lastDay = new Date(year, month + 1, 0).getDate();

                // Today — midnight, no time component
                const today = new Date();
                today.setHours(0, 0, 0, 0);

                // First selectable date = today + 14 days, then +1 more so +14 itself is also blocked
                // i.e. the soonest bookable day is today + 15
                const minDate = new Date(today);
                minDate.setDate(minDate.getDate() + 14);
                // minDate is now exactly +14 days at midnight
                // We will block: thisDate <= minDate  (so +14 is blocked, +15 is first allowed)

                // empty cells
                for (let i = 0; i < firstDayIndex; i++) {
                    daysContainer.innerHTML += "<div></div>";
                }

                for (let i = 1; i <= lastDay; i++) {
                    const fullDate = year + "-" +
                        String(month + 1).padStart(2, "0") + "-" +
                        String(i).padStart(2, "0");

                    const dayDiv = document.createElement("div");
                    dayDiv.classList.add("day");
                    dayDiv.textContent = i;

                    // Build this day at midnight using timestamp math to avoid DST issues
                    const thisDate = new Date(year, month, i, 0, 0, 0, 0);

                    const todayTs = today.getTime();
                    const thisTs = thisDate.getTime();
                    const minTs = minDate.getTime();

                    const isToday = thisTs === todayTs;
                    const isPast = thisTs < todayTs;
                    const isTooSoon = thisTs > todayTs && thisTs <= minTs;
                    const isBooked = bookedDates.includes(fullDate);

                    if (isToday) dayDiv.classList.add("today");

                    // Block: past, today, within 14 days, or approved-booked date
                    if (isPast || isToday || isTooSoon || isBooked) {
                        dayDiv.classList.add(isBooked ? "booked" : "disabled");
                        daysContainer.appendChild(dayDiv);
                        continue; // NO click listener
                    }

                    // Selectable
                    if (selectedDate === fullDate) dayDiv.classList.add("selected");

                    dayDiv.addEventListener("click", function() {
                        if (selectedDate === fullDate) {
                            selectedDate = null;
                            document.getElementById("selected_date").value = "";
                        } else {
                            selectedDate = fullDate;
                            document.getElementById("selected_date").value = fullDate;
                        }
                        renderCalendar();
                    });

                    daysContainer.appendChild(dayDiv);
                }
            }

            // Prev — don't go before current month
            prevBtn.addEventListener("click", function() {
                const now = new Date();
                if (
                    currentDate.getFullYear() > now.getFullYear() ||
                    currentDate.getMonth() > now.getMonth()
                ) {
                    currentDate.setMonth(currentDate.getMonth() - 1);
                    renderCalendar();
                }
            });

            nextBtn.addEventListener("click", function() {
                currentDate.setMonth(currentDate.getMonth() + 1);
                renderCalendar();
            });

            renderCalendar();
        })();
    </script>
</body>

</html>