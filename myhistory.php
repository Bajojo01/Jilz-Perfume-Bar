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

$myBookingsQuery = "SELECT 
        b.Booking_ID_PK,
        b.Event_Type,
        b.Event_Address,
        b.Event_Date,
        b.Event_Time_Start,
        b.Event_Time_End,
        b.Event_Notes,
        b.Booking_Status,
        b.Created_At,
        pk.Package_ID_PK,
        pk.Package_Name,
        pk.No_of_Bottles,
        pk.No_of_Scent,
        pk.Price,
        pk.Package_Img,
        bt.Bottle_Name,
        bt.Bottle_Size,
        bv.Bottle_Var_Name,
        bv.Bottle_Img,
        bs.Bar_Name,
        bs.Bar_Img,
        ui.First_Name,
        ui.Last_Name,
        ui.Phone_No,
        ui.Email,
        bp_pay.Refund_Receipt,
        bp_pay.Customer_Receipt,
        bp_pay.Gcash_Code,
        bp_pay.Gcash_Number,
        bp_pay.Gcash_Name,
        bp_pay.Total_Price,
        bp_pay.Additional_Fee,
        bp_pay.Additional_Fee_Description,
        bc.Note AS Cancellation_Reason,
        bc.Refund_Status,
        GROUP_CONCAT(DISTINCT CONCAT(p.Perfume_ID_PK, '|', p.Inspired_Scent) ORDER BY p.Perfume_ID_PK SEPARATOR ';;') AS PerfumeData
    FROM Booking b
    JOIN Packages pk ON b.Package_ID_FK = pk.Package_ID_PK
    JOIN Bottle_Variants bv ON b.Bottle_Var_ID_FK = bv.Bottle_Var_ID_PK
    JOIN Bottle bt ON bv.Bottle_ID_FK = bt.Bottle_ID_PK
    JOIN Bar_Setup bs ON b.Bar_Setup_ID_FK = bs.Bar_Setup_ID_PK
    JOIN Booking_Perfume bpf ON b.Booking_ID_PK = bpf.Booking_ID_FK
    JOIN Perfume p ON bpf.Perfume_ID_FK = p.Perfume_ID_PK
    JOIN User_Information ui ON b.User_ID_FK = ui.User_ID_PK
    LEFT JOIN Booking_Payment bp_pay ON b.Booking_ID_PK = bp_pay.Booking_ID_FK
    LEFT JOIN booking_cancelled bc ON b.Booking_ID_PK = bc.Booking_ID_FK AND bc.Refund_Status = 'Cancelled'
    WHERE b.User_ID_FK = " . intval($_SESSION['UserID']) . " AND b.Booking_Status IN ('Cancelled', 'To Refund', 'Completed')
    GROUP BY 
        b.Booking_ID_PK, b.Event_Type, b.Event_Address, b.Event_Date,
        b.Event_Time_Start, b.Event_Time_End, b.Event_Notes, b.Booking_Status,
        b.Created_At, pk.Package_ID_PK, pk.Package_Name, pk.No_of_Bottles, pk.No_of_Scent,
        pk.Price, pk.Package_Img, bt.Bottle_Name, bt.Bottle_Size,
        bv.Bottle_Var_Name, bv.Bottle_Img, bs.Bar_Name, bs.Bar_Img,
        ui.First_Name, ui.Last_Name, ui.Phone_No, ui.Email,
        bp_pay.Refund_Receipt, bp_pay.Customer_Receipt, bp_pay.Gcash_Code,
        bp_pay.Gcash_Number, bp_pay.Gcash_Name, bp_pay.Total_Price,
        bp_pay.Additional_Fee, bp_pay.Additional_Fee_Description,
        bc.Note, bc.Refund_Status
    ORDER BY b.Created_At DESC";

$myBookings = mysqli_query($conn, $myBookingsQuery);
$bookingRows = [];
while ($row = mysqli_fetch_assoc($myBookings)) {
    $perfumeList = [];
    if (!empty($row['PerfumeData'])) {
        foreach (explode(';;', $row['PerfumeData']) as $entry) {
            [$pid, $pname] = explode('|', $entry, 2);
            $perfumeList[] = ['id' => (int)$pid, 'name' => $pname];
        }
    }
    $row['PerfumeList'] = $perfumeList;
    $row['Perfumes'] = implode(', ', array_column($perfumeList, 'name'));
    unset($row['PerfumeData']);
    $bookingRows[] = $row;
}

$userId = intval($_SESSION['UserID']);

$perfumeRatingsMap = [];
$prResult = mysqli_query($conn, "SELECT Perfume_ID_FK, Rating FROM perfume_ratings WHERE User_ID_FK = $userId");
while ($pr = mysqli_fetch_assoc($prResult)) {
    $perfumeRatingsMap[$pr['Perfume_ID_FK']] = $pr['Rating'];
}

$packageRatingsMap = [];
$pkrResult = mysqli_query($conn, "SELECT Package_ID_FK, Rating FROM package_ratings WHERE User_ID_FK = $userId");
while ($pkr = mysqli_fetch_assoc($pkrResult)) {
    $packageRatingsMap[$pkr['Package_ID_FK']] = $pkr['Rating'];
}

if (($_SERVER['REQUEST_METHOD'] ?? null) === 'POST' && isset($_POST['ratingAction'])) {
    $action = $_POST['ratingAction'];
    if ($action === 'ratePerfume') {
        $perfumeId = intval($_POST['perfumeId']);
        $rating    = intval($_POST['rating']);
        $desc      = mysqli_real_escape_string($conn, $_POST['description'] ?? '');
        if ($rating >= 1 && $rating <= 5) {
            $check = mysqli_query($conn, "SELECT Perfume_Rating_ID_PK FROM perfume_ratings WHERE Perfume_ID_FK = $perfumeId AND User_ID_FK = $userId");
            if (mysqli_num_rows($check) > 0) {
                mysqli_query($conn, "UPDATE perfume_ratings SET Rating = $rating, Description = '$desc' WHERE Perfume_ID_FK = $perfumeId AND User_ID_FK = $userId");
            } else {
                mysqli_query($conn, "INSERT INTO perfume_ratings (Perfume_ID_FK, User_ID_FK, Rating, Description) VALUES ($perfumeId, $userId, $rating, '$desc')");
            }
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
    if ($action === 'ratePackage') {
        $packageId = intval($_POST['packageId']);
        $rating    = intval($_POST['rating']);
        $desc      = mysqli_real_escape_string($conn, $_POST['description'] ?? '');
        if ($rating >= 1 && $rating <= 5) {
            $check = mysqli_query($conn, "SELECT Package_Rating_ID_PK FROM package_ratings WHERE Package_ID_FK = $packageId AND User_ID_FK = $userId");
            if (mysqli_num_rows($check) > 0) {
                mysqli_query($conn, "UPDATE package_ratings SET Rating = $rating, Description = '$desc' WHERE Package_ID_FK = $packageId AND User_ID_FK = $userId");
            } else {
                mysqli_query($conn, "INSERT INTO package_ratings (Package_ID_FK, User_ID_FK, Rating, Description) VALUES ($packageId, $userId, $rating, '$desc')");
            }
        }
        header("Location: " . $_SERVER['PHP_SELF']);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jilz | My History</title>
    <link rel="shortcut icon" href="assets/Logo_Tentative.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="mobileStyle.css">
</head>

<body class="myprofileBG">

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
            <li><a href="profile.php">Account Information</a></li>
            <li><a href="mybookings.php">My Bookings</a></li>
            <li><a href="myhistory.php"><b>History</b></a></li>
            <li><a onclick="closeProfileDrawer(); document.getElementById('logoutPopup').style.display='flex';">Log out</a></li>
        </ul>
    </div>

    <!-- Mobile burger button (top-right) -->
    <button class="profileBurger" onclick="openProfileDrawer()">&#9776;</button>

    <!-- Sidebar (desktop) -->
    <div class="mypsidebar">
        <h1>Profile</h1>
        <div class="roww">
            <img src="assets/Logo_Tentative.png" alt="" class="profilepic">
            <div class="usernameemail">
                <h3>Username</h3>
            </div>
        </div>
        <hr>
        <div class="plinks">
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="profile.php">Account Information</a></li>
                <li><a href="mybookings.php">My Bookings</a></li>
                <li><a href="myhistory.php"><b>History</b></a></li>
                <li>
                    <a onclick="document.getElementById('logoutPopup').style.display='flex'">Log out</a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Bookings list -->
    <div class="mybookingscontainer">
        <h1>My History</h1>
        <div class="myinfocon">
            <?php if (empty($bookingRows)): ?>
                <div class="noInput">
                    <h1>No History</h1>
                </div>
            <?php else: ?>
                <?php foreach ($bookingRows as $booking): ?>
                    <div class="mybookingconfirmation">
                        <div class="mypcolumn">
                            <div class="myprow">
                                <p class="bookingid"><b>Booking ID: </b>0000<?php echo $booking['Booking_ID_PK']; ?></p>
                                <p class="PACKAGE"><b>Package: </b><?php echo htmlspecialchars($booking['Package_Name']); ?></p>
                            </div>
                            <div class="myprow">
                                <p class="EVENTTYPE"><b>Event Type: </b><?php echo htmlspecialchars($booking['Event_Type']); ?></p>
                                <p class="EVENTADDRESS"><b>Event Address: </b><?php echo htmlspecialchars($booking['Event_Address']); ?></p>
                            </div>
                            <div class="myprow">
                                <p class="EVENTDATE"><b>Event Date: </b><?php echo htmlspecialchars($booking['Event_Date']); ?></p>
                            </div>
                            <div class="myprow">
                                <p class="TIMESTART"><b>Time: </b><?php echo $booking['Event_Time_Start']; ?> - <?php echo $booking['Event_Time_End']; ?></p>
                            </div>
                            <div class="myprow">
                                <p class="PERFUMES"><b>Perfumes: </b><?php echo htmlspecialchars($booking['Perfumes']); ?></p>
                            </div>
                            <p class="NOTES"><b>Notes: </b><?php echo htmlspecialchars($booking['Event_Notes']); ?></p>

                            <?php if ($booking['Booking_Status'] === 'Cancelled' && !empty($booking['Cancellation_Reason'])): ?>
                                <div class="cancellationReasonWrap">
                                    <span class="cancellationReasonLabel">Cancellation Reason</span>
                                    <p class="cancellationReasonText"><?php echo htmlspecialchars($booking['Cancellation_Reason']); ?></p>
                                </div>
                            <?php endif; ?>

                            <?php if ($booking['Booking_Status'] === 'Completed'): ?>
                                <div class="rateSection">
                                    <p class="rateSectionLabel">Rate (optional)</p>
                                    <div class="rateBtnGroup">
                                        <?php
                                        $pkgId    = $booking['Package_ID_PK'];
                                        $pkgRated  = isset($packageRatingsMap[$pkgId]);
                                        $pkgRating = $pkgRated ? $packageRatingsMap[$pkgId] : 0;
                                        ?>
                                        <button
                                            class="rateChip <?php echo $pkgRated ? 'rated' : ''; ?>"
                                            onclick="openRatingModal('package', <?php echo $pkgId; ?>, '<?php echo htmlspecialchars(addslashes($booking['Package_Name'])); ?>', <?php echo $pkgRating; ?>)"
                                            type="button">
                                            <?php if ($pkgRated): ?>
                                                <span class="chipStar">&#9733;</span> <?php echo $pkgRating; ?>/5
                                            <?php else: ?>
                                                &#9734;
                                            <?php endif; ?>
                                            <?php echo htmlspecialchars($booking['Package_Name']); ?>
                                        </button>
                                        <?php foreach ($booking['PerfumeList'] as $pf): ?>
                                            <?php
                                            $pfRated  = isset($perfumeRatingsMap[$pf['id']]);
                                            $pfRating = $pfRated ? $perfumeRatingsMap[$pf['id']] : 0;
                                            ?>
                                            <button
                                                class="rateChip <?php echo $pfRated ? 'rated' : ''; ?>"
                                                onclick="openRatingModal('perfume', <?php echo $pf['id']; ?>, '<?php echo htmlspecialchars(addslashes($pf['name'])); ?>', <?php echo $pfRating; ?>)"
                                                type="button">
                                                <?php if ($pfRated): ?>
                                                    <span class="chipStar">&#9733;</span> <?php echo $pfRating; ?>/5
                                                <?php else: ?>
                                                    &#9734;
                                                <?php endif; ?>
                                                <?php echo htmlspecialchars($pf['name']); ?>
                                            </button>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="prow">
                            <div>
                                <p class="status"><b>Status: </b><?php echo htmlspecialchars($booking['Booking_Status']); ?></p>
                            </div>
                            <div class="profileButtons">
                                <button class="bookButton" onclick="showDetails(<?php echo $booking['Booking_ID_PK']; ?>)">View Details</button>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Booking details panel -->
    <div class="viewDetailSection" id="viewDetailSection">
        <div class="bookingDetails">
            <div class="bookingID">
                <p>Booking ID: <strong id="detailId"></strong></p>
                <button onclick="closeDetails()">&#10005;</button>
            </div>
            <div class="detailSection customerSection">
                <div class="name">
                    <p class="detailLabel">CUSTOMER NAME</p>
                    <h3 class="detailValue" id="detailCustomerName"></h3>
                </div>
            </div>
            <div class="detailSection bookingInfoGrid">
                <div class="infos">
                    <p class="detailLabel">PACKAGE</p>
                    <p class="detailValue" id="detailPackage"></p>
                </div>
                <div class="infos">
                    <p class="detailLabel">DATE</p>
                    <p class="detailValue" id="detailDate"></p>
                </div>
                <div class="infos">
                    <p class="detailLabel">TIME</p>
                    <p class="detailValue" id="detailTime"></p>
                </div>
                <div class="infos">
                    <p class="detailLabel">EVENT ADDRESS</p>
                    <p class="detailValue" id="detailAddress"></p>
                </div>
            </div>
            <div class="detailSection generalInfoSection">
                <h3 class="sectionTitle">General info</h3>
                <div class="generalInfoGrid">
                    <div class="infos">
                        <p class="detailLabel">FULL NAME</p>
                        <p class="detailValue" id="detailCustomerName2"></p>
                    </div>
                    <div class="infos">
                        <p class="detailLabel">PHONE NUMBER</p>
                        <p class="detailValue" id="detailPhone"></p>
                    </div>
                    <div class="infos">
                        <p class="detailLabel">EVENT TYPE</p>
                        <p class="detailValue" id="detailEventtype"></p>
                    </div>
                    <div class="infos">
                        <p class="detailLabel">EMAIL</p>
                        <p class="detailValue" id="detailEmail"></p>
                    </div>
                    <div class="infos">
                        <p class="detailLabel">BAR SETUP</p>
                        <p class="detailValue" id="detailBarsetup"></p>
                    </div>
                    <div class="infos">
                        <p class="detailLabel">BOTTLE</p>
                        <p class="detailValue" id="detailBottle"></p>
                    </div>
                    <div class="infos fullWidth">
                        <p class="detailLabel">PERFUMES SELECTED</p>
                        <p class="detailValue" id="detailPerfumes"></p>
                    </div>
                    <div class="infos fullWidth">
                        <p class="detailLabel">NOTES</p>
                        <p class="detailValue" id="detailNotes"></p>
                    </div>
                    <div class="infos fullWidth">
                        <p class="detailLabel">STATUS</p>
                        <p class="detailValue" id="detailStatus"></p>
                    </div>
                </div>
            </div>
            <div class="detailSection" id="detailCancellationSection" style="display:none;">
                <div class="cancelDetailWrap">
                    <span class="cancelDetailLabel">Cancellation Reason</span>
                    <p class="cancelDetailText" id="detailCancellationReason"></p>
                </div>
            </div>
            <div class="detailSection" id="detailRefundSection" style="display:none;">
                <div class="refundImgWrap">
                    <p class="refundImgLabel">Refund Receipt / Proof</p>
                    <img id="detailRefundImg" src="" alt="Refund image">
                    <p class="refundNote">This booking has been requested for a refund.</p>
                </div>
            </div>
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

    <!-- Rating modal -->
    <div class="ratingModal" id="ratingModal">
        <div class="ratingBox">
            <button class="btnCloseRating" onclick="closeRatingModal()">&#10005;</button>
            <h3 id="ratingModalTitle">Rate</h3>
            <p class="ratingSubject" id="ratingModalSubject"></p>
            <div class="starRow" id="starRow">
                <button class="starBtn" data-value="1" onclick="selectStar(1)" type="button">&#9733;</button>
                <button class="starBtn" data-value="2" onclick="selectStar(2)" type="button">&#9733;</button>
                <button class="starBtn" data-value="3" onclick="selectStar(3)" type="button">&#9733;</button>
                <button class="starBtn" data-value="4" onclick="selectStar(4)" type="button">&#9733;</button>
                <button class="starBtn" data-value="5" onclick="selectStar(5)" type="button">&#9733;</button>
            </div>
            <textarea class="ratingDescArea" id="ratingDesc" placeholder="Write a short review (optional)..."></textarea>
            <p class="ratingOptionalNote">All fields are optional. You can skip rating any item.</p>
            <div class="ratingActions">
                <button class="btnCancelRating" type="button" onclick="closeRatingModal()">Cancel</button>
                <button class="btnSubmitRating" type="button" onclick="submitRating()">Submit</button>
            </div>
            <form id="ratingForm" method="POST" action="" style="display:none;">
                <input type="hidden" name="ratingAction" id="ratingActionInput">
                <input type="hidden" name="perfumeId" id="ratingPerfumeIdInput">
                <input type="hidden" name="packageId" id="ratingPackageIdInput">
                <input type="hidden" name="rating" id="ratingValueInput">
                <input type="hidden" name="description" id="ratingDescInput">
            </form>
        </div>
    </div>

    <script>
        const bookings = <?php echo json_encode($bookingRows); ?>;

        function showDetails(id) {
            const b = bookings.find(x => x.Booking_ID_PK == id);
            if (!b) return;

            document.getElementById('detailId').textContent = '0000' + b.Booking_ID_PK;
            document.getElementById('detailCustomerName').textContent = b.First_Name + ' ' + b.Last_Name;
            document.getElementById('detailCustomerName2').textContent = b.First_Name + ' ' + b.Last_Name;
            document.getElementById('detailPhone').textContent = b.Phone_No || 'N/A';
            document.getElementById('detailEmail').textContent = b.Email || 'N/A';
            document.getElementById('detailPackage').textContent = b.Package_Name;
            document.getElementById('detailBarsetup').textContent = b.Bar_Name;
            document.getElementById('detailDate').textContent = b.Event_Date;
            document.getElementById('detailEventtype').textContent = b.Event_Type;
            document.getElementById('detailAddress').textContent = b.Event_Address;
            document.getElementById('detailBottle').textContent = b.Bottle_Var_Name + ' (' + b.Bottle_Name + ')';
            document.getElementById('detailPerfumes').textContent = b.Perfumes;
            document.getElementById('detailNotes').textContent = b.Event_Notes || 'None';
            document.getElementById('detailStatus').textContent = b.Booking_Status;

            function formatToAMPM(time24) {
                const [hours, minutes] = time24.split(':');
                let h = parseInt(hours);
                const ampm = h >= 12 ? 'PM' : 'AM';
                h = h % 12 || 12;
                return `${h}:${minutes} ${ampm}`;
            }
            document.getElementById('detailTime').textContent =
                formatToAMPM(b.Event_Time_Start) + ' - ' + formatToAMPM(b.Event_Time_End);

            const cancelSection = document.getElementById('detailCancellationSection');
            const cancelText = document.getElementById('detailCancellationReason');
            if (b.Booking_Status === 'Cancelled' && b.Cancellation_Reason) {
                cancelText.textContent = b.Cancellation_Reason;
                cancelSection.style.display = 'block';
            } else {
                cancelSection.style.display = 'none';
            }

            const refundSection = document.getElementById('detailRefundSection');
            if (b.Booking_Status === 'To Refund' && b.Refund_Receipt) {
                document.getElementById('detailRefundImg').src = b.Refund_Receipt;
                refundSection.style.display = 'block';
            } else {
                refundSection.style.display = 'none';
            }

            document.getElementById('viewDetailSection').style.display = 'flex';
        }

        function closeDetails() {
            document.getElementById('viewDetailSection').style.display = 'none';
        }

        let currentRatingType = null;
        let currentRatingTargetId = null;
        let currentRatingValue = 0;

        function openRatingModal(type, targetId, subjectName, existingRating) {
            currentRatingType = type;
            currentRatingTargetId = targetId;
            currentRatingValue = existingRating || 0;
            document.getElementById('ratingModalTitle').textContent = type === 'package' ? 'Rate Package' : 'Rate Perfume';
            document.getElementById('ratingModalSubject').textContent = subjectName;
            document.getElementById('ratingDesc').value = '';
            renderStars(currentRatingValue);
            document.getElementById('ratingModal').classList.add('active');
        }

        function closeRatingModal() {
            document.getElementById('ratingModal').classList.remove('active');
            currentRatingType = null;
            currentRatingTargetId = null;
            currentRatingValue = 0;
        }

        function renderStars(value) {
            document.querySelectorAll('.starBtn').forEach(btn => {
                btn.classList.toggle('filled', parseInt(btn.dataset.value) <= value);
            });
        }

        function selectStar(value) {
            currentRatingValue = value;
            renderStars(value);
        }

        function submitRating() {
            if (currentRatingValue === 0) {
                closeRatingModal();
                return;
            }
            document.getElementById('ratingActionInput').value = currentRatingType === 'package' ? 'ratePackage' : 'ratePerfume';
            document.getElementById('ratingPerfumeIdInput').value = currentRatingType === 'perfume' ? currentRatingTargetId : '';
            document.getElementById('ratingPackageIdInput').value = currentRatingType === 'package' ? currentRatingTargetId : '';
            document.getElementById('ratingValueInput').value = currentRatingValue;
            document.getElementById('ratingDescInput').value = document.getElementById('ratingDesc').value;
            document.getElementById('ratingForm').submit();
        }

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