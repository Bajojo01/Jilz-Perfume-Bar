<?php
session_start();
// profile pic
function getAvatarColor($username)
{
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

if (!isset($_SESSION['UserID'])) {
    header("Location: login.php");
    exit();
}

$packageID = isset($_GET['package_id']) ? (int)$_GET['package_id'] : 0;

$packages = mysqli_query($conn, "SELECT * FROM Packages");

$selectedPackage = mysqli_fetch_assoc(mysqli_query(
    $conn,
    "SELECT p.*, b.Bottle_ID_PK, b.Bottle_Name
     FROM Packages p
     JOIN Bottle b ON p.Bottle_ID_FK = b.Bottle_ID_PK
     WHERE p.Package_ID_PK = '$packageID'"
));

$bottleID = $selectedPackage['Bottle_ID_PK'] ?? null;
$bottles = null;
if ($bottleID) {
    $bottles = mysqli_query(
        $conn,
        "SELECT * FROM Bottle_Variants
         WHERE Bottle_ID_FK = '$bottleID' AND Bottle_Var_Status = 'Available'"
    );
}

$barSetup    = mysqli_query($conn, "SELECT * FROM Bar_Setup WHERE Bar_Status = 'Available'");
$selfMirrors = mysqli_query($conn, "SELECT * FROM Selfie_Mirror WHERE Mirror_Status = 'Available'");
$perfumes    = mysqli_query($conn, "SELECT * FROM Perfume");

if (($_SERVER['REQUEST_METHOD'] ?? null) === 'POST') {
    $userID       = $_SESSION['UserID'] ?? null;
    $date         = $_POST['selected_date'] ?? null;
    $bottleVar    = $_POST['bottle'] ?? null;
    $setupID      = $_POST['setup'] ?? null;
    $mirrorID     = $_POST['mirror'] ?? null;
    $timeStart    = $_POST['eventTimeFrom'] ?? null;
    $timeEnd      = $_POST['eventTimeTo'] ?? null;
    $eventType    = $_POST['eventType'] ?? null;
    $eventAddress = $_POST['eventAddress'] ?? null;
    $eventNotes   = $_POST['eventNotes'] ?? null;

    $selectedPerfumes = $_POST['perfumes'] ?? [];
    $max   = $selectedPackage['No_of_Scent'] ?? 0;
    $error = "";

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
            (User_ID_FK, Package_ID_FK, Bottle_Var_ID_FK, Bar_Setup_ID_FK, Selfie_Mirror_ID_FK,
             Event_Time_Start, Event_Time_End, Event_Type, Event_Address, Event_Date, Event_Notes)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";

        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param(
            $stmt,
            "iiiiissssss",
            $userID,
            $packageID,
            $bottleVar,
            $setupID,
            $mirrorID,
            $timeStart,
            $timeEnd,
            $eventType,
            $eventAddress,
            $date,
            $eventNotes
        );
        mysqli_stmt_execute($stmt);
        $bookingID = mysqli_insert_id($conn);

        foreach ($selectedPerfumes as $perfumeID) {
            $perfumeID = (int)$perfumeID;
            mysqli_query(
                $conn,
                "INSERT INTO Booking_Perfume (Booking_ID_FK, Perfume_ID_FK)
                 VALUES ($bookingID, $perfumeID)"
            );
        }

        echo "<script>alert('Booking successful!'); window.location.href='mybookings.php';</script>";
        exit();
    }
}

$bookedDates = [];
$result = mysqli_query($conn, "SELECT Event_Date FROM Booking WHERE Booking_Status = 'Approved'");
while ($row = mysqli_fetch_assoc($result)) {
    $bookedDates[] = $row['Event_Date'];
}

$minBookingDate = date('Y-m-d', strtotime('+14 days'));
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jilz Perfume Bar | Booking</title>
    <link rel="shortcut icon" href="assets/Logo_Tentative.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="style.css?v=8">
    <link rel="stylesheet" href="mobileStyle.css">
</head>

<body>

    <div class="mobileNavOverlay" id="mobileNavOverlay" onclick="closeDrawer()"></div>
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
            <li><a href="perfumes.php">Perfumes</a></li>
            <li><a href="booking.php"><b>Booking</b></a></li>
            <li><a href="aboutUs.php">About</a></li>
        </ul>
    </div>

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
                <li><a href="perfumes.php">Perfumes</a></li>
                <li><a href="booking.php"><b>Booking</b></a></li>
                <li><a href="aboutUs.php">About</a></li>
                <li class="prof" style="display:none;"><a href="profile.php"><b>Profile</b></a></li>
            </ul>
        </div>
        <?php if (isset($_SESSION['UserID']) || isset($_SESSION['AdminID'])): ?>
            <div class="userProfile" style="display:flex;">
                <div style="
    width: 44px; height: 44px; border-radius: 50%;
    background: <?= $avatarColor['bg'] ?>;
    color: <?= $avatarColor['text'] ?>;
    display: flex; align-items: center; justify-content: center;
    font-weight: 500; font-size: 18px; flex-shrink: 0; border:solid 1.5px;cursor: pointer;
" onclick="window.location.href='profile.php'"><?= htmlspecialchars($avatarLetter) ?></div>
            </div>
        <?php else: ?>
            <div class="loginOrSignup" style="display:flex;">
                <button class="loginButton" onclick="location.href='login.php';"><b>Log in</b></button>
                <button class="signupButton" onclick="location.href='signup.php';"><b>Sign up</b></button>
            </div>
        <?php endif; ?>
        <div class="burger" onclick="openDrawer()">
            <span class="material-icons">menu</span>
        </div>
    </Header>

    <div class="bookingPage">

        <!-- Stepper -->
        <div class="stepperWrap">
            <div class="stepper">
                <div class="stepItem active" data-step="1" onclick="goToStep(1)">
                    <div class="stepNum">1</div>
                    <div class="stepLabel">Package</div>
                </div>
                <div class="stepItem" data-step="2" onclick="goToStep(2)">
                    <div class="stepNum">2</div>
                    <div class="stepLabel">Event Info</div>
                </div>
                <div class="stepItem" data-step="3" onclick="goToStep(3)">
                    <div class="stepNum">3</div>
                    <div class="stepLabel">Perfumes</div>
                </div>
                <div class="stepItem" data-step="4" onclick="goToStep(4)">
                    <div class="stepNum">4</div>
                    <div class="stepLabel">Setup</div>
                </div>
                <div class="stepItem" data-step="5" onclick="goToStep(5)">
                    <div class="stepNum">5</div>
                    <div class="stepLabel">Date</div>
                </div>
                <div class="stepItem" data-step="6" onclick="goToStep(6)">
                    <div class="stepNum">6</div>
                    <div class="stepLabel">Review</div>
                </div>
            </div>
        </div>

        <form id="bookingForm" action="" method="POST">
            <input type="hidden" name="bottle" id="hBottle" value="<?php echo htmlspecialchars($_POST['bottle'] ?? ''); ?>">
            <input type="hidden" name="setup" id="hSetup" value="<?php echo htmlspecialchars($_POST['setup'] ?? ''); ?>">
            <input type="hidden" name="mirror" id="hMirror" value="<?php echo htmlspecialchars($_POST['mirror'] ?? ''); ?>">
            <input type="hidden" name="selected_date" id="selectedDate" value="<?php echo htmlspecialchars($_POST['selected_date'] ?? ''); ?>">

            <div class="wizardBody">

                <!-- Step 1: Package -->
                <div class="stepPanel active" id="panel-1">
                    <div class="panelHeading">
                        <h2>Choose Your Package</h2>
                        <p>Select the package that best fits your event.</p>
                    </div>
                    <div class="stepError" id="err-1"></div>
                    <div class="packageGrid">
                        <?php
                        mysqli_data_seek($packages, 0);
                        while ($pkg = mysqli_fetch_assoc($packages)):
                            $isSelected = ($pkg['Package_ID_PK'] == $packageID);
                        ?>
                            <a href="booking.php?package_id=<?php echo $pkg['Package_ID_PK']; ?>"
                                class="pkgOption <?php echo $isSelected ? 'selected' : ''; ?>">
                                <div class="pkgCheck">&#10003;</div>
                                <h3><?php echo htmlspecialchars($pkg['Package_Name']); ?></h3>
                                <p><?php echo htmlspecialchars($pkg['No_of_Bottles'] ?? ''); ?> bottles &middot;
                                    <?php echo htmlspecialchars($pkg['No_of_Scent'] ?? ''); ?> scents</p>
                            </a>
                        <?php endwhile; ?>
                    </div>

                    <?php if ($packageID !== 0 && $selectedPackage): ?>
                        <div class="pkgSummary">
                            <div class="pkgSummaryLabel">Selected package includes</div>
                            <p>
                                <?php echo $selectedPackage['No_of_Bottles']; ?> pcs
                                <?php echo htmlspecialchars($selectedPackage['Bottle_Name']); ?> glass spray bottles &middot;
                                Elegant setup based on motif &middot;
                                <?php echo $selectedPackage['No_of_Scent']; ?> scent choices
                            </p>
                        </div>
                    <?php endif; ?>

                    <div class="navButtons">
                        <span></span>
                        <button type="button" class="btnNext" onclick="nextStep(1)">
                            <?php echo ($packageID !== 0) ? 'Continue' : 'Select a Package First'; ?>
                        </button>
                    </div>
                </div>

                <!-- Step 2: Event Info -->
                <div class="stepPanel" id="panel-2">
                    <div class="panelHeading">
                        <h2>Event Details</h2>
                        <p>Tell us about your event. All fields are required. Maximum event duration is 4 hours.</p>
                    </div>
                    <div class="stepError" id="err-2"></div>

                    <div class="formRow">
                        <div class="field">
                            <label>Event Time - From</label>
                            <input type="time" name="eventTimeFrom" id="eventTimeFrom"
                                value="<?php echo htmlspecialchars($_POST['eventTimeFrom'] ?? ''); ?>" required>
                        </div>
                        <div class="field">
                            <label>Event Time - To</label>
                            <input type="time" name="eventTimeTo" id="eventTimeTo"
                                value="<?php echo htmlspecialchars($_POST['eventTimeTo'] ?? ''); ?>" required>
                        </div>
                    </div>
                    <div class="formRow full">
                        <div class="field">
                            <label>Event Type</label>
                            <input type="text" name="eventType" id="eventType"
                                value="<?php echo htmlspecialchars($_POST['eventType'] ?? ''); ?>"
                                placeholder="e.g. Wedding, Birthday, Christening" required>
                        </div>
                    </div>
                    <div class="formRow full">
                        <div class="field">
                            <label>Event Location / Address</label>
                            <input type="text" name="eventAddress" id="eventAddress"
                                value="<?php echo htmlspecialchars($_POST['eventAddress'] ?? ''); ?>"
                                placeholder="Unit, Street, Barangay, City, Province, Postal Code" required>
                        </div>
                    </div>
                    <div class="navButtons">
                        <button type="button" class="btnBack" onclick="goToStep(1)">Back</button>
                        <button type="button" class="btnNext" onclick="nextStep(2)">Continue</button>
                    </div>
                </div>

                <!-- Step 3: Perfumes -->
                <div class="stepPanel" id="panel-3">
                    <div class="panelHeading">
                        <h2>Choose Your Scents</h2>
                        <p>Pick exactly the right number of perfumes for your package. You can deselect by clicking again.</p>
                    </div>
                    <div class="stepError" id="err-3"></div>

                    <div class="perfInfoBar">
                        <span>Select <strong id="maxScentsLabel"><?php echo (int)($selectedPackage['No_of_Scent'] ?? 0); ?></strong> perfumes</span>
                        <span class="perfCounter"><span id="perfCount">0</span> / <?php echo (int)($selectedPackage['No_of_Scent'] ?? 0); ?> selected</span>
                    </div>

                    <div class="perfumeGrid">
                        <?php
                        mysqli_data_seek($perfumes, 0);
                        while ($perfume = mysqli_fetch_assoc($perfumes)):
                            if ($perfume['Perfume_Status'] == 'Available'):
                        ?>
                                <div class="perfCard" data-pid="<?php echo $perfume['Perfume_ID_PK']; ?>">
                                    <div class="perfTick">&#10003;</div>
                                    <img src="assets/perfumeBottle.png" alt="">
                                    <p><?php echo htmlspecialchars($perfume['Inspired_Scent']); ?></p>
                                    <input type="checkbox" name="perfumes[]" value="<?php echo $perfume['Perfume_ID_PK']; ?>">
                                </div>
                        <?php endif;
                        endwhile; ?>
                    </div>

                    <div class="navButtons">
                        <button type="button" class="btnBack" onclick="goToStep(2)">Back</button>
                        <button type="button" class="btnNext" onclick="nextStep(3)">Continue</button>
                    </div>
                </div>

                <!-- Step 4: Bottle, Bar Setup & Selfie Mirror -->
                <div class="stepPanel" id="panel-4">
                    <div class="panelHeading">
                        <h2>Bottle, Bar Setup &amp; Mirror</h2>
                        <p>Choose your bottle variation, bar setup style, and selfie mirror. Tap an image to preview it.</p>
                    </div>
                    <div class="stepError" id="err-4"></div>

                    <!-- Bottle Variation -->
                    <div class="sectionSubLabel">Bottle Variation</div>
                    <div class="bottleGrid">
                        <?php if ($bottles):
                            mysqli_data_seek($bottles, 0);
                            while ($bottle = mysqli_fetch_assoc($bottles)):
                                $bSel = (!empty($_POST['bottle']) && $_POST['bottle'] == $bottle['Bottle_Var_ID_PK']);
                        ?>
                                <div class="bottleCard <?php echo $bSel ? 'selected' : ''; ?>"
                                    data-val="<?php echo $bottle['Bottle_Var_ID_PK']; ?>"
                                    onclick="selectBottle(this)">
                                    <img src="<?php echo htmlspecialchars($bottle['Bottle_Img']); ?>"
                                        onclick="event.stopPropagation(); openLightbox(this.src)" alt="">
                                    <div class="bottleCardBody">
                                        <p><?php echo htmlspecialchars($selectedPackage['Bottle_Name']); ?> <?php echo htmlspecialchars($bottle['Bottle_Var_Name']); ?></p>
                                    </div>
                                </div>
                        <?php endwhile;
                        endif; ?>
                    </div>

                    <!-- Bar Setup -->
                    <div class="sectionSubLabel" style="margin-top:1.25rem;">Bar Setup</div>
                    <div class="setupGrid">
                        <?php
                        mysqli_data_seek($barSetup, 0);
                        while ($s = mysqli_fetch_assoc($barSetup)):
                            $sSel = (!empty($_POST['setup']) && $_POST['setup'] == $s['Bar_Setup_ID_PK']);
                        ?>
                            <div class="setupCard <?php echo $sSel ? 'selected' : ''; ?>"
                                data-val="<?php echo $s['Bar_Setup_ID_PK']; ?>"
                                onclick="selectSetup(this)">
                                <img src="<?php echo htmlspecialchars($s['Bar_Img']); ?>"
                                    onclick="event.stopPropagation(); openLightbox(this.src)" alt="">
                                <div class="setupCardBody">
                                    <p><?php echo htmlspecialchars($s['Bar_Name']); ?></p>
                                    <small>Tap image to preview</small>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>

                    <!-- Selfie Mirror -->
                    <div class="sectionSubLabel" style="margin-top:1.25rem;">Selfie Mirror</div>
                    <div class="setupGrid">
                        <?php
                        mysqli_data_seek($selfMirrors, 0);
                        while ($m = mysqli_fetch_assoc($selfMirrors)):
                            $mSel = (!empty($_POST['mirror']) && $_POST['mirror'] == $m['Selfie_Mirror_ID_PK']);
                        ?>
                            <div class="mirrorCard <?php echo $mSel ? 'selected' : ''; ?>"
                                data-val="<?php echo $m['Selfie_Mirror_ID_PK']; ?>"
                                onclick="selectMirror(this)">
                                <img src="<?php echo htmlspecialchars($m['Mirror_Img']); ?>"
                                    onclick="event.stopPropagation(); openLightbox(this.src)" alt="">
                                <div class="setupCardBody">
                                    <p><?php echo htmlspecialchars($m['Mirror_Name']); ?></p>
                                    <small>Tap image to preview</small>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>

                    <div class="navButtons">
                        <button type="button" class="btnBack" onclick="goToStep(3)">Back</button>
                        <button type="button" class="btnNext" onclick="nextStep(4)">Continue</button>
                    </div>
                </div>

                <!-- Step 5: Date -->
                <div class="stepPanel" id="panel-5">
                    <div class="panelHeading">
                        <h2>Pick Your Event Date</h2>
                        <p>Bookings must be made at least 2 weeks in advance. Struck-through dates are already booked.</p>
                    </div>
                    <div class="stepError" id="err-5"></div>

                    <div class="calendarWrap">
                        <div class="calHeader">
                            <button type="button" class="calNav" id="calPrev">&#8249;</button>
                            <h3 id="calMonthYear"></h3>
                            <button type="button" class="calNav" id="calNext">&#8250;</button>
                        </div>
                        <div class="calDaysHdr">
                            <div>Su</div>
                            <div>Mo</div>
                            <div>Tu</div>
                            <div>We</div>
                            <div>Th</div>
                            <div>Fr</div>
                            <div>Sa</div>
                        </div>
                        <div class="calGrid" id="calDays"></div>
                        <div class="calLegend">
                            <div class="calLegendItem">
                                <div class="calLegendDot" style="background:var(--gold);"></div>Selected
                            </div>
                            <div class="calLegendItem">
                                <div class="calLegendDot" style="background:var(--dark);"></div>Today
                            </div>
                            <div class="calLegendItem">
                                <div class="calLegendDot" style="background:#e0e0e0; border:1px solid #ccc;"></div>Unavailable
                            </div>
                        </div>
                    </div>

                    <div class="selectedDateDisplay" id="dateDisplay">
                        <span id="dateDisplayText"></span>
                    </div>

                    <div class="navButtons">
                        <button type="button" class="btnBack" onclick="goToStep(4)">Back</button>
                        <button type="button" class="btnNext" onclick="nextStep(5)">Continue</button>
                    </div>
                </div>

                <!-- Step 6: Review -->
                <div class="stepPanel" id="panel-6">
                    <div class="panelHeading">
                        <h2>Review and Confirm</h2>
                        <p>Please verify all details before submitting your booking.</p>
                    </div>

                    <div class="summaryCard">
                        <div class="summarySection">
                            <div class="summarySectionTitle">Package</div>
                            <div class="summaryRow">
                                <span class="summaryKey">Package</span>
                                <span class="summaryVal"><?php echo htmlspecialchars($selectedPackage['Package_Name'] ?? '—'); ?></span>
                            </div>
                            <div class="summaryRow">
                                <span class="summaryKey">Includes</span>
                                <span class="summaryVal"><?php echo ($selectedPackage['No_of_Bottles'] ?? 0); ?> bottles &middot; <?php echo ($selectedPackage['No_of_Scent'] ?? 0); ?> scents</span>
                            </div>
                        </div>
                        <div class="summarySection">
                            <div class="summarySectionTitle">Event Info</div>
                            <div class="summaryRow">
                                <span class="summaryKey">Time</span>
                                <span class="summaryVal" id="revTime">—</span>
                            </div>
                            <div class="summaryRow">
                                <span class="summaryKey">Type</span>
                                <span class="summaryVal" id="revType">—</span>
                            </div>
                            <div class="summaryRow">
                                <span class="summaryKey">Address</span>
                                <span class="summaryVal" id="revAddress" style="max-width:60%;text-align:right;">—</span>
                            </div>
                        </div>
                        <div class="summarySection">
                            <div class="summarySectionTitle">Perfumes Selected</div>
                            <div class="perfTagList" id="revPerfumes"></div>
                        </div>
                        <div class="summarySection">
                            <div class="summarySectionTitle">Setup</div>
                            <div class="summaryRow">
                                <span class="summaryKey">Bottle</span>
                                <span class="summaryVal" id="revBottle">—</span>
                            </div>
                            <div class="summaryRow">
                                <span class="summaryKey">Bar Setup</span>
                                <span class="summaryVal" id="revSetup">—</span>
                            </div>
                            <div class="summaryRow">
                                <span class="summaryKey">Selfie Mirror</span>
                                <span class="summaryVal" id="revMirror">—</span>
                            </div>
                        </div>
                        <div class="summarySection">
                            <div class="summarySectionTitle">Date</div>
                            <div class="summaryRow">
                                <span class="summaryKey">Event Date</span>
                                <span class="summaryVal" id="revDate">—</span>
                            </div>
                        </div>
                        <div class="summarySection">
                            <div class="summarySectionTitle">Notes &amp; Suggestions</div>
                            <div class="formRow full" style="margin-top:0.5rem;">
                                <div class="field">
                                    <textarea name="eventNotes" id="eventNotes" required
                                        placeholder="e.g. Preferred color scheme, bar layout, decorations, event theme/motif, setup preferences, or any other requests..."><?php echo htmlspecialchars($_POST['eventNotes'] ?? ''); ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="navButtons">
                        <button type="button" class="btnBack" onclick="goToStep(5)">Back</button>
                        <button type="button" class="btnSubmit" onclick="openConfirm()">Confirm Booking</button>
                    </div>
                </div>

            </div>
        </form>
    </div>

    <!-- Confirm dialog -->
    <div class="confirmOverlay" id="confirmOverlay">
        <div class="confirmBox">
            <img src="assets/warning.png" alt="">
            <h3>Confirm Your Booking</h3>
            <p>You will not be able to edit your booking after submission. Are you sure everything is correct?</p>
            <div class="confirmBtns">
                <button type="button" class="confirmYes" onclick="submitBooking()">Yes, Submit</button>
                <button type="button" class="confirmNo" onclick="closeConfirm()">Review Again</button>
            </div>
        </div>
    </div>

    <!-- Image lightbox -->
    <div class="imgLightbox" id="imgLightbox" onclick="closeLightbox()">
        <img class="imgLightboxImg" id="imgLightboxImg" src="" alt="">
    </div>

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

        let currentStep = 1;
        const TOTAL_STEPS = 6;
        const MAX_PERFUMES = <?php echo (int)($selectedPackage['No_of_Scent'] ?? 0); ?>;
        const PACKAGE_ID = <?php echo $packageID; ?>;
        const bookedDates = <?php echo json_encode($bookedDates); ?>;
        const minBookDate = '<?php echo $minBookingDate; ?>';
        let maxReachedStep = 1;

        function goToStep(n) {
            if (n < 1 || n > TOTAL_STEPS) return;
            if (n > currentStep && n > maxReachedStep) return;

            document.querySelectorAll('.stepPanel').forEach(p => p.classList.remove('active'));
            document.getElementById('panel-' + n).classList.add('active');

            document.querySelectorAll('.stepItem').forEach(s => {
                const sn = parseInt(s.dataset.step);
                s.classList.remove('active', 'done');
                if (sn === n) s.classList.add('active');
                else if (sn < n) s.classList.add('done');
            });

            currentStep = n;
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
            if (n === 6) populateReview();
        }

        function showError(step, msg) {
            const el = document.getElementById('err-' + step);
            el.textContent = msg;
            el.classList.add('visible');
        }

        function clearError(step) {
            document.getElementById('err-' + step).classList.remove('visible');
        }

        function nextStep(step) {
            clearError(step);

            if (step === 1) {
                if (PACKAGE_ID === 0) {
                    showError(1, 'Please select a package before continuing.');
                    return;
                }
            }

            if (step === 2) {
                const from = document.getElementById('eventTimeFrom').value;
                const to = document.getElementById('eventTimeTo').value;
                const type = document.getElementById('eventType').value.trim();
                const address = document.getElementById('eventAddress').value.trim();

                if (!from || !to) {
                    showError(2, 'Please enter both start and end times.');
                    return;
                }
                if (!type) {
                    showError(2, 'Please enter the event type.');
                    return;
                }
                if (!address) {
                    showError(2, 'Please enter the event address.');
                    return;
                }

                const start = new Date('1970-01-01T' + from);
                let end = new Date('1970-01-01T' + to);
                if (end <= start) end = new Date('1970-01-02T' + to);
                const hrs = (end - start) / 3600000;
                if (hrs <= 0) {
                    showError(2, 'End time must be after start time.');
                    return;
                }
                if (hrs > 4) {
                    showError(2, 'Event duration cannot exceed 4 hours.');
                    return;
                }
            }

            if (step === 3) {
                const checked = document.querySelectorAll('.perfCard input[type=checkbox]:checked').length;
                if (checked !== MAX_PERFUMES) {
                    showError(3, 'Please select exactly ' + MAX_PERFUMES + ' perfume(s). You have ' + checked + ' selected.');
                    return;
                }
            }

            if (step === 4) {
                if (!document.getElementById('hBottle').value) {
                    showError(4, 'Please select a bottle variation.');
                    return;
                }
                if (!document.getElementById('hSetup').value) {
                    showError(4, 'Please select a bar setup.');
                    return;
                }
                if (!document.getElementById('hMirror').value) {
                    showError(4, 'Please select a selfie mirror.');
                    return;
                }
            }

            if (step === 5) {
                const d = document.getElementById('selectedDate').value;
                if (!d) {
                    showError(5, 'Please select an event date.');
                    return;
                }
                const sel = new Date(d + 'T00:00:00');
                const min = new Date(minBookDate + 'T00:00:00');
                if (sel < min) {
                    showError(5, 'Bookings must be made at least 2 weeks in advance.');
                    return;
                }
            }

            const nextStepNum = step + 1;
            if (nextStepNum > maxReachedStep) maxReachedStep = nextStepNum;
            goToStep(nextStepNum);
        }

        /* Perfume cards */
        function initPerfumeCards() {
            const cards = document.querySelectorAll('.perfCard');
            const counter = document.getElementById('perfCount');
            const selected = new Set();

            function updateUI() {
                counter.textContent = selected.size;
                const atMax = selected.size >= MAX_PERFUMES;
                cards.forEach(card => {
                    const pid = card.dataset.pid;
                    const cb = card.querySelector('input[type=checkbox]');
                    const isSel = selected.has(pid);
                    cb.checked = isSel;
                    card.classList.toggle('selected', isSel);
                    card.classList.toggle('maxed', atMax && !isSel);
                });
            }

            cards.forEach(card => {
                card.addEventListener('click', function() {
                    const pid = card.dataset.pid;
                    if (selected.has(pid)) {
                        selected.delete(pid);
                    } else {
                        if (selected.size >= MAX_PERFUMES) return;
                        selected.add(pid);
                    }
                    updateUI();
                });
            });

            updateUI();
        }

        /* Single-select helpers */
        function selectBottle(el) {
            document.querySelectorAll('.bottleCard').forEach(c => c.classList.remove('selected'));
            el.classList.add('selected');
            document.getElementById('hBottle').value = el.dataset.val;
        }

        function selectSetup(el) {
            document.querySelectorAll('.setupCard').forEach(c => c.classList.remove('selected'));
            el.classList.add('selected');
            document.getElementById('hSetup').value = el.dataset.val;
        }

        function selectMirror(el) {
            document.querySelectorAll('.mirrorCard').forEach(c => c.classList.remove('selected'));
            el.classList.add('selected');
            document.getElementById('hMirror').value = el.dataset.val;
        }

        /* Calendar */
        (function() {
            const MONTHS = ['January', 'February', 'March', 'April', 'May', 'June',
                'July', 'August', 'September', 'October', 'November', 'December'
            ];
            let cur = new Date();
            let selectedDate = document.getElementById('selectedDate').value || null;

            function render() {
                const year = cur.getFullYear();
                const month = cur.getMonth();
                document.getElementById('calMonthYear').textContent = MONTHS[month] + ' ' + year;

                const grid = document.getElementById('calDays');
                const firstDay = new Date(year, month, 1).getDay();
                const lastDay = new Date(year, month + 1, 0).getDate();
                const today = new Date();
                today.setHours(0, 0, 0, 0);
                const minDate = new Date(today);
                minDate.setDate(minDate.getDate() + 14);

                grid.innerHTML = '';
                for (let i = 0; i < firstDay; i++) grid.innerHTML += '<div></div>';

                for (let i = 1; i <= lastDay; i++) {
                    const fullDate = year + '-' + String(month + 1).padStart(2, '0') + '-' + String(i).padStart(2, '0');
                    const thisDate = new Date(year, month, i, 0, 0, 0, 0);
                    const div = document.createElement('div');
                    div.classList.add('calDay');
                    div.textContent = i;

                    const isToday = thisDate.getTime() === today.getTime();
                    const isPast = thisDate < today;
                    const isTooSoon = thisDate > today && thisDate <= minDate;
                    const isBooked = bookedDates.includes(fullDate);

                    if (isToday) div.classList.add('today');

                    if (isPast || isToday || isTooSoon || isBooked) {
                        div.classList.add(isBooked ? 'booked' : 'disabled');
                    } else {
                        if (selectedDate === fullDate) div.classList.add('selected');
                        div.addEventListener('click', function() {
                            selectedDate = (selectedDate === fullDate) ? null : fullDate;
                            document.getElementById('selectedDate').value = selectedDate || '';
                            updateDateDisplay();
                            render();
                        });
                    }
                    grid.appendChild(div);
                }
            }

            function updateDateDisplay() {
                const disp = document.getElementById('dateDisplay');
                const txt = document.getElementById('dateDisplayText');
                if (selectedDate) {
                    const d = new Date(selectedDate + 'T12:00:00');
                    txt.textContent = d.toLocaleDateString('en-PH', {
                        weekday: 'long',
                        year: 'numeric',
                        month: 'long',
                        day: 'numeric'
                    });
                    disp.classList.add('visible');
                } else {
                    disp.classList.remove('visible');
                }
            }

            document.getElementById('calPrev').addEventListener('click', function() {
                const now = new Date();
                if (cur.getFullYear() > now.getFullYear() || cur.getMonth() > now.getMonth()) {
                    cur.setMonth(cur.getMonth() - 1);
                    render();
                }
            });
            document.getElementById('calNext').addEventListener('click', function() {
                cur.setMonth(cur.getMonth() + 1);
                render();
            });

            render();
            updateDateDisplay();
        })();

        /* Review summary */
        function populateReview() {
            document.getElementById('revTime').textContent =
                (document.getElementById('eventTimeFrom').value || '—') + ' - ' +
                (document.getElementById('eventTimeTo').value || '—');
            document.getElementById('revType').textContent = document.getElementById('eventType').value || '—';
            document.getElementById('revAddress').textContent = document.getElementById('eventAddress').value || '—';

            const perfWrap = document.getElementById('revPerfumes');
            perfWrap.innerHTML = '';
            document.querySelectorAll('.perfCard input:checked').forEach(cb => {
                const label = cb.closest('.perfCard').querySelector('p').textContent;
                const tag = document.createElement('span');
                tag.className = 'perfTag';
                tag.textContent = label;
                perfWrap.appendChild(tag);
            });
            if (!perfWrap.children.length) {
                perfWrap.innerHTML = '<span style="color:#aaa;font-size:0.82rem;">None selected</span>';
            }

            const selBottle = document.querySelector('.bottleCard.selected');
            const selSetup = document.querySelector('.setupCard.selected');
            const selMirror = document.querySelector('.mirrorCard.selected');
            document.getElementById('revBottle').textContent = selBottle ? selBottle.querySelector('p').textContent : '—';
            document.getElementById('revSetup').textContent = selSetup ? selSetup.querySelector('p').textContent : '—';
            document.getElementById('revMirror').textContent = selMirror ? selMirror.querySelector('p').textContent : '—';

            const rawDate = document.getElementById('selectedDate').value;
            if (rawDate) {
                const d = new Date(rawDate + 'T12:00:00');
                document.getElementById('revDate').textContent = d.toLocaleDateString('en-PH', {
                    weekday: 'long',
                    year: 'numeric',
                    month: 'long',
                    day: 'numeric'
                });
            } else {
                document.getElementById('revDate').textContent = '—';
            }
        }

        /* Confirm dialog */
        function openConfirm() {
            const from = document.getElementById('eventTimeFrom').value;
            const to = document.getElementById('eventTimeTo').value;
            const type = document.getElementById('eventType').value.trim();
            const address = document.getElementById('eventAddress').value.trim();
            const notes = document.getElementById('eventNotes').value.trim();
            const checked = document.querySelectorAll('.perfCard input[type=checkbox]:checked').length;
            const bottle = document.getElementById('hBottle').value;
            const setup = document.getElementById('hSetup').value;
            const mirror = document.getElementById('hMirror').value;
            const date = document.getElementById('selectedDate').value;

            const missing = [];
            if (!from || !to) missing.push('event time');
            if (!type) missing.push('event type');
            if (!address) missing.push('event address');
            if (!notes) missing.push('notes & suggestions');
            if (checked !== MAX_PERFUMES) missing.push('perfume selection (' + checked + '/' + MAX_PERFUMES + ')');
            if (!bottle) missing.push('bottle variation');
            if (!setup) missing.push('bar setup');
            if (!mirror) missing.push('selfie mirror');
            if (!date) missing.push('event date');

            if (missing.length > 0) {
                alert('Please complete the following before submitting:\n• ' + missing.join('\n• '));
                return;
            }
            document.getElementById('confirmOverlay').classList.add('open');
        }

        function closeConfirm() {
            document.getElementById('confirmOverlay').classList.remove('open');
        }

        function submitBooking() {
            document.getElementById('bookingForm').submit();
        }

        /* Lightbox */
        function openLightbox(src) {
            document.getElementById('imgLightboxImg').src = src;
            document.getElementById('imgLightbox').classList.add('open');
        }

        function closeLightbox() {
            document.getElementById('imgLightbox').classList.remove('open');
        }

        /* Restore selections on POST back */
        (function() {
            const bVal = '<?php echo htmlspecialchars($_POST['bottle'] ?? ''); ?>';
            const sVal = '<?php echo htmlspecialchars($_POST['setup']  ?? ''); ?>';
            const mVal = '<?php echo htmlspecialchars($_POST['mirror'] ?? ''); ?>';
            if (bVal) {
                const el = document.querySelector('.bottleCard[data-val="' + bVal + '"]');
                if (el) el.classList.add('selected');
            }
            if (sVal) {
                const el = document.querySelector('.setupCard[data-val="' + sVal + '"]');
                if (el) el.classList.add('selected');
            }
            if (mVal) {
                const el = document.querySelector('.mirrorCard[data-val="' + mVal + '"]');
                if (el) el.classList.add('selected');
            }
        })();

        initPerfumeCards();
    </script>
</body>

</html>