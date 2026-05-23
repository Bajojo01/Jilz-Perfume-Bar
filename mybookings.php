<?php
// Profile pic setup
session_start();
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

$username    = $_SESSION['Username'] ?? 'Guest';
$avatarColor = getAvatarColor($username);
$avatarLetter = strtoupper(mb_substr($username, 0, 1));

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
        sm.Mirror_Name,
        sm.Mirror_Img,
        ui.First_Name,
        ui.Last_Name,
        ui.Phone_No,
        ui.Email,
        GROUP_CONCAT(p.Inspired_Scent SEPARATOR ', ') AS Perfumes
    FROM Booking b
    JOIN Packages pk ON b.Package_ID_FK = pk.Package_ID_PK
    JOIN Bottle_Variants bv ON b.Bottle_Var_ID_FK = bv.Bottle_Var_ID_PK
    JOIN Bottle bt ON bv.Bottle_ID_FK = bt.Bottle_ID_PK
    JOIN Bar_Setup bs ON b.Bar_Setup_ID_FK = bs.Bar_Setup_ID_PK
    JOIN Selfie_Mirror sm ON b.Selfie_Mirror_ID_FK = sm.Selfie_Mirror_ID_PK
    JOIN Booking_Perfume bp ON b.Booking_ID_PK = bp.Booking_ID_FK
    JOIN Perfume p ON bp.Perfume_ID_FK = p.Perfume_ID_PK
    JOIN User_Information ui ON b.User_ID_FK = ui.User_ID_PK
    WHERE b.User_ID_FK = " . intval($_SESSION['UserID']) . " AND b.Booking_Status IN ('Pending', 'Approved', 'To Pay', 'To Refund')
    GROUP BY 
        b.Booking_ID_PK, b.Event_Type, b.Event_Address, b.Event_Date,
        b.Event_Time_Start, b.Event_Time_End, b.Event_Notes, b.Booking_Status,
        b.Created_At, pk.Package_Name, pk.No_of_Bottles, pk.No_of_Scent,
        pk.Price, pk.Package_Img, bt.Bottle_Name, bt.Bottle_Size,
        bv.Bottle_Var_Name, bv.Bottle_Img, bs.Bar_Name, bs.Bar_Img,
        sm.Mirror_Name, sm.Mirror_Img,
        ui.First_Name, ui.Last_Name, ui.Phone_No, ui.Email
    ORDER BY b.Created_At DESC";

$myBookings  = mysqli_query($conn, $myBookingsQuery);
$bookingRows = [];
while ($row = mysqli_fetch_assoc($myBookings)) {
    $bookingRows[] = $row;
}

$statusCounts = ['Pending' => 0, 'To Pay' => 0, 'Approved' => 0, 'To Refund' => 0];
foreach ($bookingRows as $b) {
    if (isset($statusCounts[$b['Booking_Status']])) {
        $statusCounts[$b['Booking_Status']]++;
    }
}
$totalBookings = array_sum($statusCounts);

// Handle cancel/refund submission
if (($_SERVER['REQUEST_METHOD'] ?? null) === 'POST' && isset($_POST['cancelBookingSubmit'])) {
    $bookingId    = intval($_POST['cancelBookingId'] ?? 0);
    $cancelStatus = mysqli_real_escape_string($conn, $_POST['cancelStatus'] ?? '');
    $cancelNote   = mysqli_real_escape_string($conn, trim($_POST['cancelNote'] ?? ''));
    $allowedCancelStatuses = ['Cancelled', 'To Refund'];

    if ($bookingId > 0 && in_array($cancelStatus, $allowedCancelStatuses) && $cancelNote !== '') {
        $refundStatus = ($cancelStatus === 'To Refund') ? 'To Refund' : 'Cancelled';
        $updateBooking = "UPDATE Booking SET Booking_Status = '$cancelStatus'
                          WHERE Booking_ID_PK = $bookingId AND User_ID_FK = " . intval($_SESSION['UserID']);
        mysqli_query($conn, $updateBooking);
        $insertCancelled = "INSERT INTO Booking_Cancelled (Booking_ID_FK, Refund_Status, Note)
                            VALUES ($bookingId, '$refundStatus', '$cancelNote')";
        mysqli_query($conn, $insertCancelled);
        header("Location: " . ($_SERVER['PHP_SELF'] ?? null));
        exit();
    }
}

$paymentQuery = "SELECT bp.Booking_ID_FK, bp.Additional_Fee, bp.Additional_Fee_Description, 
                        bp.Gcash_Code, bp.Gcash_Number, bp.Gcash_Name, bp.Total_Price,
                        bp.Customer_Receipt, bp.Resubmit_Requested
                 FROM Booking_Payment bp
                 WHERE bp.Booking_ID_FK IN (
                     SELECT Booking_ID_PK FROM Booking WHERE User_ID_FK = " . intval($_SESSION['UserID']) . "
                 )";
$paymentResult = mysqli_query($conn, $paymentQuery);
$paymentData   = [];
while ($row = mysqli_fetch_assoc($paymentResult)) {
    $paymentData[$row['Booking_ID_FK']] = $row;
}

// Handle mark as complete — only allowed after event end datetime has passed
if (isset($_POST['markComplete'])) {
    $bookingId = intval($_POST['markCompleteId'] ?? 0);
    if ($bookingId > 0) {
        // Fetch event date and end time to verify it has actually passed
        $checkQuery = mysqli_query(
            $conn,
            "SELECT Event_Date, Event_Time_End FROM Booking
             WHERE Booking_ID_PK = $bookingId
             AND User_ID_FK = " . intval($_SESSION['UserID']) . "
             AND Booking_Status = 'Approved'"
        );
        $checkRow = mysqli_fetch_assoc($checkQuery);
        if ($checkRow) {
            $eventEndDatetime = new DateTime($checkRow['Event_Date'] . ' ' . $checkRow['Event_Time_End']);
            $now = new DateTime();
            if ($now > $eventEndDatetime) {
                mysqli_query(
                    $conn,
                    "UPDATE Booking SET Booking_Status = 'Completed'
                     WHERE Booking_ID_PK = $bookingId
                     AND User_ID_FK = " . intval($_SESSION['UserID'])
                );
                header("Location: " . ($_SERVER['PHP_SELF'] ?? null));
                exit();
            }
        }
    }
}

// Handle receipt upload
if (isset($_POST['submitPayment'])) {
    $bookingId    = intval($_POST['paymentBookingId']);
    $allowedTypes = ['image/jpeg', 'image/png', 'image/jpg'];
    if (isset($_FILES['receiptImg']) && $_FILES['receiptImg']['error'] === 0) {
        if (in_array($_FILES['receiptImg']['type'], $allowedTypes)) {
            $uploadDir = 'uploads/receipts/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            $ext        = pathinfo($_FILES['receiptImg']['name'], PATHINFO_EXTENSION);
            $fileName   = 'receipt_' . $bookingId . '_' . time() . '.' . $ext;
            $targetPath = $uploadDir . $fileName;
            if (move_uploaded_file($_FILES['receiptImg']['tmp_name'], $targetPath)) {
                $escapedFileName = mysqli_real_escape_string($conn, $fileName);
                $updateQuery     = "UPDATE Booking_Payment SET Customer_Receipt = '$escapedFileName', Resubmit_Requested = 0 WHERE Booking_ID_FK = $bookingId";
                mysqli_query($conn, $updateQuery);
                header("Location: " . ($_SERVER['PHP_SELF'] ?? null));
                exit();
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
    <title>Jilz | My Bookings</title>
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
            <div style="
    width: 44px; height: 44px; border-radius: 50%;
    background: <?= $avatarColor['bg'] ?>;
    color: <?= $avatarColor['text'] ?>;
    display: flex; align-items: center; justify-content: center;
    font-weight: 500; font-size: 18px; flex-shrink: 0; padding-bottom: 10srem;
"><?= htmlspecialchars($avatarLetter) ?></div>

            <span><?php echo isset($_SESSION['Username']) ? htmlspecialchars($_SESSION['Username']) : 'Guest'; ?></span>
        </div>

        <ul>
            <li><a href="index.php">Home</a></li>
            <li><a href="profile.php">Account Information</a></li>
            <li><a href="mybookings.php"><b>My Bookings</b></a></li>
            <li><a href="myhistory.php">History</a></li>
            <li><a onclick="closeProfileDrawer(); document.getElementById('logoutPopup').style.display='flex';">Log out</a></li>
        </ul>
    </div>

    <!-- Mobile burger button -->
    <button class="profileBurger" onclick="openProfileDrawer()">&#9776;</button>

    <!-- Sidebar (desktop) -->
    <div class="mypsidebar">
        <h1>Profile</h1>
        <div class="roww">
            <div style="
    width: 70px; height: 70px; border-radius: 50%;
    background: <?= $avatarColor['bg'] ?>;
    color: <?= $avatarColor['text'] ?>;
    display: flex; align-items: center; justify-content: center;
    font-weight: 1000; font-size: 35px; flex-shrink: 0;
"><?= htmlspecialchars($avatarLetter) ?></div>
            <div class="usernameemail">
                <h3><?php echo isset($_SESSION['Username']) ? htmlspecialchars($_SESSION['Username']) : 'Guest'; ?></h3>
            </div>
        </div>
        <hr>
        <div class="plinks">
            <ul>
                <li><a href="index.php">Home</a></li>
                <li><a href="profile.php">Account Information</a></li>
                <li><a href="mybookings.php"><b>My Bookings</b></a></li>
                <li><a href="myhistory.php">History</a></li>
                <li>
                    <a onclick="document.getElementById('logoutPopup').style.display='flex'">Log out</a>
                </li>
            </ul>
        </div>
    </div>

    <!-- Main bookings content -->
    <div class="mybookingscontainer">
        <h1>My Bookings</h1>

        <!-- Status filter cards -->
        <div class="statusFilterRow">
            <div class="statusFilterCard cardAll active" data-filter="All" onclick="filterBookings('All', this)">
                <div class="cardLabel">All Bookings</div>
                <div class="cardCount"><?= $totalBookings; ?></div>
            </div>
            <div class="statusFilterCard" data-filter="Pending" onclick="filterBookings('Pending', this)" style="--cardColor: #3b82f6;">
                <div class="cardLabel"><span class="cardDot"></span>Pending</div>
                <div class="cardCount"><?= $statusCounts['Pending']; ?></div>
            </div>
            <div class="statusFilterCard" data-filter="To Pay" onclick="filterBookings('To Pay', this)" style="--cardColor: #0ea5e9;">
                <div class="cardLabel"><span class="cardDot"></span>To Pay</div>
                <div class="cardCount"><?= $statusCounts['To Pay']; ?></div>
            </div>
            <div class="statusFilterCard" data-filter="Approved" onclick="filterBookings('Approved', this)" style="--cardColor: #22c55e;">
                <div class="cardLabel"><span class="cardDot"></span>Confirmed</div>
                <div class="cardCount"><?= $statusCounts['Approved']; ?></div>
            </div>
            <div class="statusFilterCard" data-filter="To Refund" onclick="filterBookings('To Refund', this)" style="--cardColor: #f59e0b;">
                <div class="cardLabel"><span class="cardDot"></span>To Refund</div>
                <div class="cardCount"><?= $statusCounts['To Refund']; ?></div>
            </div>
        </div>

        <!-- Booking cards list -->
        <div class="myinfocon">
            <?php if (empty($bookingRows)): ?>
                <div class="noInput">
                    <h1>No Bookings.</h1>
                </div>
            <?php else: ?>
                <?php foreach ($bookingRows as $booking): ?>
                    <div class="mybookingconfirmation" data-status="<?= htmlspecialchars($booking['Booking_Status']); ?>">
                        <div class="mypcolumn">
                            <div class="myprow">
                                <p class="bookingid"><b>Booking ID: </b>0000<?= $booking['Booking_ID_PK']; ?></p>
                                <p class="PACKAGE"><b>Package: </b><?= htmlspecialchars($booking['Package_Name']); ?></p>
                            </div>
                            <div class="myprow">
                                <p class="EVENTTYPE"><b>Event Type: </b><?= htmlspecialchars($booking['Event_Type']); ?></p>
                                <p class="EVENTADDRESS"><b>Event Address: </b><?= htmlspecialchars($booking['Event_Address']); ?></p>
                            </div>
                            <div class="myprow">
                                <p class="EVENTDATE"><b>Event Date: </b><?= htmlspecialchars($booking['Event_Date']); ?></p>
                            </div>
                            <div class="myprow">
                                <p class="TIMESTART"><b>Time: </b><?= $booking['Event_Time_Start']; ?> - <?= $booking['Event_Time_End']; ?></p>
                            </div>
                            <div class="myprow">
                                <p class="PERFUMES"><b>Perfumes: </b><?= htmlspecialchars($booking['Perfumes']); ?></p>
                            </div>
                            <p class="NOTES"><b>Notes: </b><?= htmlspecialchars($booking['Event_Notes']); ?></p>

                            <?php if ($booking['Booking_Status'] === 'To Refund'): ?>
                                <div class="refundNotice">
                                    <strong>Refund in progress</strong>
                                    Please wait for the owner to process your refund. You will be notified once it has been sent.
                                </div>
                            <?php endif; ?>

                            <?php if (!empty($paymentData[$booking['Booking_ID_PK']]['Resubmit_Requested'])): ?>
                                <div style="
                                    background: #fff3cd;
                                    border-left: 4px solid #f59e0b;
                                    border-radius: 6px;
                                    padding: 10px 14px;
                                    margin-top: 10px;
                                    color: #78350f;
                                    font-size: 13px;
                                ">
                                    <strong>&#9888; Please reupload your receipt.</strong><br>
                                    The admin has flagged your receipt for resubmission. Please upload the correct image.
                                    <br>
                                    <button type="button" class="bookButton payBtn" style="margin-top:8px; background:none; color:red;"
                                        onclick="openPayModal(<?= $booking['Booking_ID_PK']; ?>)">
                                        Reupload Receipt
                                    </button>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="prow">
                            <div>
                                <p class="status"><b>Status: </b><?= htmlspecialchars($booking['Booking_Status']); ?></p>
                            </div>
                            <div class="profileButtons">
                                <?php if ($booking['Booking_Status'] === 'To Pay'): ?>
                                    <button type="button" class="bookButton payBtn"
                                        onclick="openPayModal(<?= $booking['Booking_ID_PK']; ?>)">
                                        Pay Now
                                    </button>
                                <?php endif; ?>
                                <button class="bookButton" onclick="showDetails(<?= $booking['Booking_ID_PK']; ?>)">View Details</button>
                                <?php if ($booking['Booking_Status'] !== 'To Refund'): ?>
                                    <button type="button" class="bookButton"
                                        onclick="openCancelModal(
                                            <?= $booking['Booking_ID_PK']; ?>,
                                            '<?= htmlspecialchars($booking['Booking_Status']); ?>',
                                            '<?= htmlspecialchars($booking['Event_Date']); ?>'
                                        )">
                                        Cancel
                                    </button>
                                <?php endif; ?>
                                <?php if ($booking['Booking_Status'] === 'Approved'): ?>
                                    <button type="button" class="bookButton"
                                        style="background: #22c55e; color: #fff;"
                                        onclick="openMarkCompleteModal(
                                            <?= $booking['Booking_ID_PK']; ?>,
                                            '<?= htmlspecialchars($booking['Event_Date']); ?>',
                                            '<?= htmlspecialchars($booking['Event_Time_End']); ?>'
                                        )">
                                        Mark as Complete
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Booking detail side panel -->
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
                        <p class="detailValue" id="detailEventType"></p>
                    </div>
                    <div class="infos">
                        <p class="detailLabel">EMAIL</p>
                        <p class="detailValue" id="detailEmail"></p>
                    </div>
                    <div class="infos">
                        <p class="detailLabel">BAR SETUP</p>
                        <p class="detailValue" id="detailBarSetup"></p>
                    </div>
                    <div class="infos">
                        <p class="detailLabel">BOTTLE</p>
                        <p class="detailValue" id="detailBottle"></p>
                    </div>
                    <div class="infos">
                        <p class="detailLabel">SELFIE MIRROR</p>
                        <p class="detailValue" id="detailMirror"></p>
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
        </div>
    </div>

    <!-- Cancel reason modal -->
    <div class="cancelReasonOverlay" id="cancelReasonOverlay">
        <div class="cancelReasonBox">
            <div class="cancelReasonHeader">
                <h3 id="cancelReasonTitle">Cancel Booking</h3>
                <button class="cancelReasonClose" onclick="closeCancelModal()">&#10005;</button>
            </div>
            <p class="cancelReasonSubtitle" id="cancelReasonSubtitle">Please provide a reason for cancelling this booking.</p>
            <textarea class="cancelReasonTextarea" id="cancelReasonTextarea" placeholder="Enter your reason here..." maxlength="255"></textarea>
            <p class="cancelReasonError" id="cancelReasonError">Please enter a reason before confirming.</p>
            <form id="cancelReasonForm" method="POST" action="">
                <input type="hidden" name="cancelBookingId" id="cancelBookingIdInput" value="">
                <input type="hidden" name="cancelStatus" id="cancelStatusInput" value="">
                <input type="hidden" name="cancelNote" id="cancelNoteInput" value="">
                <input type="hidden" name="cancelBookingSubmit" value="1">
            </form>
            <div class="cancelReasonFooter">
                <button class="cancelReasonBackBtn" onclick="closeCancelModal()">Go Back</button>
                <button class="cancelReasonConfirmBtn" onclick="submitCancelReason()">Confirm Cancel</button>
            </div>
        </div>
    </div>

    <!-- No-refund warning popup (shown when cancelling 6 days or less before event) -->
    <div class="cancelReasonOverlay" id="noRefundWarningOverlay" style="display:none; z-index: 9999;">
        <div class="cancelReasonBox" style="max-width: 420px;">
            <div class="cancelReasonHeader">
                <h3 style="color: #dc2626;">&#9888; No Refund Policy</h3>
                <button class="cancelReasonClose" onclick="closeNoRefundWarning()">&#10005;</button>
            </div>
            <div style="text-align: center; padding: 6px 0 10px;">
                <img src="assets/warning.png" alt="warning" style="width: 48px; margin-bottom: 8px;">
            </div>
            <p style="font-size: 14px; color: #374151; line-height: 1.6; margin-bottom: 10px;">
                Your event is <strong id="noRefundDaysLeft"></strong> away.
            </p>
            <p style="font-size: 13px; color: #6b7280; line-height: 1.6; margin-bottom: 14px;">
                Based on our cancellation policy, <strong>refunds are only available if the booking is cancelled at least 1 week (7 days) before the scheduled event date.</strong>
                Cancellations made <strong>6 days or less</strong> before the event are <strong style="color:#dc2626;">no longer eligible for a refund.</strong>
            </p>
            <p style="font-size: 13px; color: #374151; line-height: 1.6; margin-bottom: 18px;">
                If you proceed, your booking will be marked as <strong>Cancelled</strong> and no refund will be issued.
            </p>
            <div class="cancelReasonFooter">
                <button class="cancelReasonBackBtn" onclick="closeNoRefundWarning()">Go Back</button>
                <button class="cancelReasonConfirmBtn" style="background: #dc2626;" onclick="proceedToNoRefundCancel()">
                    I Understand, Cancel Anyway
                </button>
            </div>
        </div>
    </div>

    <!-- Payment modal -->
    <section class="popUp" id="paymentModal" style="display:none;">
        <div class="paymentBox">
            <div class="paymentHeader">
                <p>Booking ID: <strong id="payModalBookingId"></strong></p>
                <button class="closePayBtn" onclick="closePayModal()">&#10005;</button>
            </div>
            <div class="paymentBody">
                <div class="paymentLeft">
                    <p class="paySectionLabel">Scan to Pay via GCash</p>
                    <div style="margin-bottom:10px; text-align:center;">
                        <p id="gcashNameDisplay" style="font-weight:700; font-size:14px; margin:0;"></p>
                        <p id="gcashNumberDisplay" style="font-size:13px; color:#555; margin:2px 0 0;"></p>
                    </div>
                    <div class="gcashQrWrapper">
                        <img id="gcashQrImg" src="" alt="GCash QR Code" class="gcashQrImg"
                            onerror="this.src='assets/gcash_qr_placeholder.png'">
                    </div>
                    <div class="uploadSection">

                        <!-- Existing receipt display -->
                        <div id="existingReceiptSection" style="display:none; margin-bottom:12px;">
                            <p class="paySectionLabel" style="color:#22c55e;">Receipt Already Submitted</p>
                            <p id="receiptStatusMsg" style="font-size:12px; color:#888; margin:0 0 8px;"></p>
                            <img id="existingReceiptImg" src="" alt="Submitted Receipt"
                                style="width:100%; max-width:260px; border-radius:8px; border:1px solid #ddd;">
                            <p style="font-size:11px; color:#888; margin:6px 0 10px;">
                                You can re-upload below to replace your receipt.
                            </p>
                        </div>

                        <p class="paySectionLabel">Upload Receipt</p>
                        <form id="paymentForm" method="POST" action="" enctype="multipart/form-data">
                            <input type="hidden" name="paymentBookingId" id="paymentBookingId">
                            <input type="hidden" name="submitPayment" value="1">
                            <label class="uploadLabel" for="receiptUpload">
                                <span id="uploadLabelText">Choose image...</span>
                            </label>
                            <input type="file" id="receiptUpload" name="receiptImg"
                                accept="image/*" style="display:none;"
                                onchange="previewReceipt(event)">
                            <div id="receiptPreviewWrapper" style="display:none;">
                                <img id="receiptPreview" src="" alt="Receipt Preview" class="receiptPreview">
                            </div>
                            <button type="submit" class="submitPaymentBtn">Submit Receipt</button>
                        </form>
                    </div>
                </div>
                <div class="paymentRight">
                    <p class="paySectionLabel">Booking Summary</p>
                    <div class="summaryCard">
                        <div class="summaryRow">
                            <span class="summaryKey">Package</span>
                            <span class="summaryVal" id="payModalPackage"></span>
                        </div>
                        <div class="summaryDivider"></div>
                        <div class="summaryRow">
                            <span class="summaryKey">Starting Price</span>
                            <span class="summaryVal" id="payModalBasePrice"></span>
                        </div>
                        <div id="additionalFeesSection"></div>
                        <div class="summaryDivider"></div>
                        <div class="summaryRow">
                            <span class="summaryKey">Full Total</span>
                            <span class="summaryVal" id="payModalTotal"></span>
                        </div>
                        <div class="summaryDivider"></div>
                        <div class="summaryRow totalRow">
                            <span class="summaryKey">
                                Downpayment Due
                                <small style="font-weight:400; font-size:11px;">(50%)</small>
                            </span>
                            <span class="summaryVal totalVal" id="payModalDownpayment"></span>
                        </div>
                        <p style="font-size:11px; color:#888; margin:8px 0 0; line-height:1.4;">
                            A 50% downpayment is required to confirm your booking. The remaining balance is due on the event date.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Mark as Complete confirmation popup -->
    <section class="popUp" id="markCompletePopup" style="display:none;">
        <div class="notif" style="max-width: 400px; text-align: center;">
            <div class="icon">
                <img src="assets/warning.png" alt="warning">
                <p>Mark Event as Complete?</p>
            </div>
            <p class="errorMsg" style="text-align: center; margin-bottom: 6px;">
                Are you sure you want to mark this event as complete?
            </p>
            <p id="markCompleteNote" style="font-size: 12px; color: #888; margin-bottom: 14px;"></p>
            <form id="markCompleteForm" method="POST" action="">
                <input type="hidden" name="markCompleteId" id="markCompleteIdInput" value="">
                <input type="hidden" name="markComplete" value="1">
            </form>
            <div class="uSureBtn">
                <button class="yes" type="button" onclick="submitMarkComplete()">Yes, Complete</button>
                <button class="no" type="button" onclick="document.getElementById('markCompletePopup').style.display='none'">Go Back</button>
            </div>
        </div>
    </section>

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

    <script>
        const bookings = <?php echo json_encode($bookingRows); ?>;
        const payments = <?php echo json_encode($paymentData); ?>;

        // Tracks pending cancel info for the no-refund flow
        let pendingCancelId = null;
        let pendingCancelStatus = null;

        // Format number as Philippine peso using the actual peso sign
        function formatPHP(amount) {
            return '₱' + parseFloat(amount).toLocaleString('en-PH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function formatToAMPM(time24) {
            const [hours, minutes] = time24.split(':');
            let h = parseInt(hours);
            const ap = h >= 12 ? 'PM' : 'AM';
            h = h % 12 || 12;
            return `${h}:${minutes} ${ap}`;
        }

        function filterBookings(status, clickedCard) {
            document.querySelectorAll('.statusFilterCard').forEach(card => card.classList.remove('active'));
            clickedCard.classList.add('active');
            document.querySelectorAll('.mybookingconfirmation').forEach(card => {
                const cardStatus = card.dataset.status;
                card.style.display = (status === 'All' || cardStatus === status) ? '' : 'none';
            });
        }

        function showDetails(id) {
            const b = bookings.find(x => x.Booking_ID_PK == id);
            if (!b) return;
            document.getElementById('detailId').textContent = '0000' + b.Booking_ID_PK;
            document.getElementById('detailCustomerName').textContent = b.First_Name + ' ' + b.Last_Name;
            document.getElementById('detailCustomerName2').textContent = b.First_Name + ' ' + b.Last_Name;
            document.getElementById('detailPhone').textContent = b.Phone_No || 'N/A';
            document.getElementById('detailEmail').textContent = b.Email || 'N/A';
            document.getElementById('detailPackage').textContent = b.Package_Name;
            document.getElementById('detailBarSetup').textContent = b.Bar_Name;
            document.getElementById('detailDate').textContent = b.Event_Date;
            document.getElementById('detailTime').textContent =
                formatToAMPM(b.Event_Time_Start) + ' - ' + formatToAMPM(b.Event_Time_End);
            document.getElementById('detailEventType').textContent = b.Event_Type;
            document.getElementById('detailAddress').textContent = b.Event_Address;
            document.getElementById('detailBottle').textContent = b.Bottle_Var_Name + ' (' + b.Bottle_Name + ')';
            document.getElementById('detailMirror').textContent = b.Mirror_Name || 'N/A';
            document.getElementById('detailPerfumes').textContent = b.Perfumes;
            document.getElementById('detailNotes').textContent = b.Event_Notes || 'None';
            document.getElementById('detailStatus').textContent = b.Booking_Status;
            document.getElementById('viewDetailSection').style.display = 'flex';
        }

        function closeDetails() {
            document.getElementById('viewDetailSection').style.display = 'none';
        }

        // Check how many days until the event date (floor so 3 days stays as 3, not rounded up)
        function getDaysUntilEvent(eventDateStr) {
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            // Parse as local date by splitting manually to avoid timezone offset issues
            const parts = eventDateStr.split('-');
            const eventDate = new Date(parseInt(parts[0]), parseInt(parts[1]) - 1, parseInt(parts[2]));
            eventDate.setHours(0, 0, 0, 0);
            const diffMs = eventDate - today;
            return Math.floor(diffMs / (1000 * 60 * 60 * 24));
        }

        // Opens cancel modal — checks refund eligibility based on event date
        function openCancelModal(id, currentStatus, eventDate) {
            const daysLeft = getDaysUntilEvent(eventDate);

            // Approved + more than 6 days away = eligible for refund
            if (currentStatus === 'Approved' && daysLeft >= 7) {
                pendingCancelId = null;
                pendingCancelStatus = null;
                showCancelReasonModal(id, 'To Refund');
                return;
            }

            // Approved but 6 days or less = no refund, show warning first
            if (currentStatus === 'Approved' && daysLeft < 7) {
                pendingCancelId = id;
                pendingCancelStatus = 'Cancelled';
                showNoRefundWarning(daysLeft);
                return;
            }

            // Pending or To Pay = just cancel, no refund involved
            pendingCancelId = null;
            pendingCancelStatus = null;
            showCancelReasonModal(id, 'Cancelled');
        }

        // Show the standard cancel reason modal
        function showCancelReasonModal(id, newStatus) {
            const titleEl = document.getElementById('cancelReasonTitle');
            const subtitleEl = document.getElementById('cancelReasonSubtitle');

            if (newStatus === 'To Refund') {
                titleEl.textContent = 'Request Cancellation & Refund';
                subtitleEl.textContent = 'Your event is more than 1 week away. Cancelling will initiate a refund request. Please state your reason.';
            } else {
                titleEl.textContent = 'Cancel Booking';
                subtitleEl.textContent = 'Please provide a reason for cancelling this booking.';
            }

            document.getElementById('cancelBookingIdInput').value = id;
            document.getElementById('cancelStatusInput').value = newStatus;
            document.getElementById('cancelReasonTextarea').value = '';
            document.getElementById('cancelReasonError').style.display = 'none';
            document.getElementById('cancelReasonOverlay').style.display = 'flex';
        }

        // Show the no-refund policy warning popup
        function showNoRefundWarning(daysLeft) {
            const label = daysLeft <= 0 ?
                'today or has already passed' :
                daysLeft === 1 ?
                '1 day away' :
                daysLeft + ' days away';
            document.getElementById('noRefundDaysLeft').textContent = label;
            document.getElementById('noRefundWarningOverlay').style.display = 'flex';
        }

        function closeNoRefundWarning() {
            document.getElementById('noRefundWarningOverlay').style.display = 'none';
            pendingCancelId = null;
            pendingCancelStatus = null;
        }

        // Customer confirmed they understand no refund — proceed to reason modal
        function proceedToNoRefundCancel() {
            closeNoRefundWarning();
            if (pendingCancelId !== null) {
                showCancelReasonModal(pendingCancelId, 'Cancelled');
            }
        }

        function closeCancelModal() {
            document.getElementById('cancelReasonOverlay').style.display = 'none';
        }

        function submitCancelReason() {
            const noteValue = document.getElementById('cancelReasonTextarea').value.trim();
            const errorEl = document.getElementById('cancelReasonError');
            if (noteValue === '') {
                errorEl.style.display = 'block';
                return;
            }
            errorEl.style.display = 'none';
            document.getElementById('cancelNoteInput').value = noteValue;
            document.getElementById('cancelReasonForm').submit();
        }

        // Open mark as complete popup — only shows if event end time has already passed
        function openMarkCompleteModal(id, eventDate, eventTimeEnd) {
            const parts = eventDate.split('-');
            const timeParts = eventTimeEnd.split(':');
            const eventEnd = new Date(
                parseInt(parts[0]),
                parseInt(parts[1]) - 1,
                parseInt(parts[2]),
                parseInt(timeParts[0]),
                parseInt(timeParts[1]),
                0
            );
            const now = new Date();

            if (now <= eventEnd) {
                // Event hasn't ended yet — block it
                const diff = eventEnd - now;
                const hoursLeft = Math.floor(diff / (1000 * 60 * 60));
                const minsLeft = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                const timeMsg = hoursLeft > 0 ?
                    `${hoursLeft}h ${minsLeft}m` :
                    `${minsLeft} minute(s)`;
                alert(`You can only mark this as complete after the event ends.\n\nEvent ends in approximately: ${timeMsg}`);
                return;
            }

            document.getElementById('markCompleteIdInput').value = id;
            document.getElementById('markCompleteNote').textContent =
                'This action cannot be undone. The booking will be moved to your history.';
            document.getElementById('markCompletePopup').style.display = 'flex';
        }

        function submitMarkComplete() {
            document.getElementById('markCompleteForm').submit();
        }

        function openPayModal(id) {
            const b = bookings.find(x => x.Booking_ID_PK == id);
            const p = payments[id] || {};
            if (!b) return;
            document.getElementById('payModalBookingId').textContent = '0000' + b.Booking_ID_PK;
            document.getElementById('paymentBookingId').value = id;
            document.getElementById('payModalPackage').textContent = b.Package_Name;

            const totalPrice = parseFloat(p.Total_Price || b.Price || 0);
            const addFee = parseFloat(p.Additional_Fee || 0);
            const basePrice = totalPrice - addFee;
            const downpayment = totalPrice * 0.50;

            // Use actual peso sign
            document.getElementById('payModalBasePrice').textContent = formatPHP(basePrice);
            document.getElementById('payModalTotal').textContent = formatPHP(totalPrice);
            document.getElementById('payModalDownpayment').textContent = formatPHP(downpayment);

            const feesSection = document.getElementById('additionalFeesSection');
            feesSection.innerHTML = '';
            if (addFee > 0) {
                feesSection.innerHTML = `
                    <div class="additionalFeeRow">
                        <div class="summaryRow">
                            <span class="summaryKey">Additional Fee</span>
                            <span class="summaryVal">${formatPHP(addFee)}</span>
                        </div>
                        ${p.Additional_Fee_Description ? `<p class="feeDesc">${p.Additional_Fee_Description}</p>` : ''}
                    </div>`;
            }

            const gcashImg = document.getElementById('gcashQrImg');
            gcashImg.src = (p.Gcash_Code && p.Gcash_Code.trim() !== '') ?
                'uploads/gcash_qr/' + p.Gcash_Code.trim() :
                'assets/gcash_qr_placeholder.png';
            document.getElementById('gcashNameDisplay').textContent = p.Gcash_Name || '';
            document.getElementById('gcashNumberDisplay').textContent = p.Gcash_Number ? '0' + p.Gcash_Number : '';

            // Reset upload form
            document.getElementById('receiptUpload').value = '';
            document.getElementById('uploadLabelText').textContent = 'Choose image...';
            document.getElementById('receiptPreviewWrapper').style.display = 'none';
            document.getElementById('receiptPreview').src = '';

            // Show existing receipt if already uploaded
            const existingReceiptSection = document.getElementById('existingReceiptSection');
            if (p.Customer_Receipt && p.Customer_Receipt.trim() !== '') {
                existingReceiptSection.style.display = 'block';
                document.getElementById('existingReceiptImg').src = 'uploads/receipts/' + p.Customer_Receipt.trim();
                document.getElementById('receiptStatusMsg').textContent = 'Receipt submitted. Waiting for admin confirmation.';
            } else {
                existingReceiptSection.style.display = 'none';
                document.getElementById('receiptStatusMsg').textContent = '';
            }

            document.getElementById('paymentModal').style.display = 'flex';
        }

        function closePayModal() {
            document.getElementById('paymentModal').style.display = 'none';
        }

        function previewReceipt(event) {
            const file = event.target.files[0];
            if (!file) return;
            document.getElementById('uploadLabelText').textContent = file.name;
            const reader = new FileReader();
            reader.onload = e => {
                document.getElementById('receiptPreview').src = e.target.result;
                document.getElementById('receiptPreviewWrapper').style.display = 'block';
            };
            reader.readAsDataURL(file);
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