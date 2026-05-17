<?php

session_start();
require("db.php");

if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit();
}
/* Fix column types that commonly cause silent failures */
mysqli_query($conn, "ALTER TABLE Booking_Payment MODIFY COLUMN Gcash_Number VARCHAR(15) NOT NULL DEFAULT ''");
mysqli_query($conn, "ALTER TABLE Booking_Payment MODIFY COLUMN Gcash_Name VARCHAR(50) NOT NULL DEFAULT ''");
mysqli_query($conn, "ALTER TABLE Booking_Payment MODIFY COLUMN Refund_Receipt VARCHAR(250) NOT NULL DEFAULT ''");
mysqli_query($conn, "ALTER TABLE Booking MODIFY COLUMN Booking_Status ENUM('Pending','To Pay','Approved','Completed','To Refund','Cancelled') NOT NULL DEFAULT 'Pending'");

/* Fix legacy rows that used old label-style ENUM values */
mysqli_query($conn, "UPDATE Booking SET Booking_Status = 'Approved'  WHERE Booking_Status = 'Confirmed'");

/* ── HANDLE: Accept (Pending → To Pay) ───────────────────────── */
if (($_SERVER['REQUEST_METHOD'] ?? null) === 'POST' && isset($_POST['action_accept'])) {
    $id        = (int) $_POST['booking_id'];
    $gcashName = mysqli_real_escape_string($conn, trim($_POST['gcash_name'] ?? ''));
    $gcashNum  = mysqli_real_escape_string($conn, preg_replace('/[^0-9]/', '', trim($_POST['gcash_number'] ?? '')));
    $addFee    = (float) ($_POST['additional_fee'] ?? 0);
    $addReason = mysqli_real_escape_string($conn, trim($_POST['additional_fee_reason'] ?? ''));

    /* Fetch package price */
    $pkgResult = mysqli_query(
        $conn,
        "SELECT p.Price FROM Booking b
         INNER JOIN Packages p ON b.Package_ID_FK = p.Package_ID_PK
         WHERE b.Booking_ID_PK = $id"
    );
    $pkg   = mysqli_fetch_assoc($pkgResult);
    $total = $pkg ? ((float)$pkg['Price'] + $addFee) : $addFee;

    /* Handle QR Code Upload */
    $gcashCode = '';
    $uploadOk  = false;
    if (isset($_FILES['gcash_qr']) && $_FILES['gcash_qr']['error'] === UPLOAD_ERR_OK) {
        $targetDir = "uploads/gcash_qr/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        $fileExt      = strtolower(pathinfo($_FILES['gcash_qr']['name'], PATHINFO_EXTENSION));
        $newFilename  = "qr_" . $id . "_" . time() . "." . $fileExt;
        $targetFile   = $targetDir . $newFilename;
        $allowedTypes = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($fileExt, $allowedTypes) && $_FILES['gcash_qr']['size'] < 5000000) {
            if (move_uploaded_file($_FILES['gcash_qr']['tmp_name'], $targetFile)) {
                $gcashCode = $newFilename;
                $uploadOk  = true;
            }
        }
    }

    /* Check if payment record already exists */
    $existsResult = mysqli_query(
        $conn,
        "SELECT Booking_Payment_ID_PK, Gcash_Code FROM Booking_Payment WHERE Booking_ID_FK = $id"
    );
    $exists = mysqli_fetch_assoc($existsResult);

    /* Keep old QR code if no new file was uploaded */
    if (!$uploadOk && $exists && !empty($exists['Gcash_Code'])) {
        $gcashCode = $exists['Gcash_Code'];
    }
    $gcashCodeEsc = mysqli_real_escape_string($conn, $gcashCode);

    /* Run INSERT or UPDATE and capture result */
    if ($exists) {
        $payOk = mysqli_query(
            $conn,
            "UPDATE Booking_Payment SET
                Additional_Fee             = $addFee,
                Additional_Fee_Description = '$addReason',
                Gcash_Number               = '$gcashNum',
                Gcash_Name                 = '$gcashName',
                Gcash_Code                 = '$gcashCodeEsc',
                Total_Price                = $total
             WHERE Booking_ID_FK = $id"
        );
    } else {
        $payOk = mysqli_query(
            $conn,
            "INSERT INTO Booking_Payment
                (Booking_ID_FK, Additional_Fee, Additional_Fee_Description,
                 Gcash_Number, Gcash_Name, Gcash_Code, Total_Price)
             VALUES
                ($id, $addFee, '$addReason', '$gcashNum', '$gcashName', '$gcashCodeEsc', $total)"
        );
    }

    /* Only flip status when DB write succeeded */
    if ($payOk) {
        mysqli_query($conn, "UPDATE Booking SET Booking_Status = 'To Pay' WHERE Booking_ID_PK = $id");
        header("Location: bookingconfirmation.php");
        exit;
    }

    /* DB write failed — bounce back with error info visible in URL for debugging */
    header("Location: bookingconfirmation.php?db_err=" . urlencode(mysqli_error($conn)));
    exit;
}

/* ── HANDLE: Decline with note (Pending → Cancelled) ─────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_decline'])) {
    $id          = (int) $_POST['booking_id'];
    $declineNote = mysqli_real_escape_string($conn, trim($_POST['decline_note'] ?? ''));

    if ($declineNote !== '') {
        mysqli_query($conn, "UPDATE Booking SET Booking_Status = 'Cancelled' WHERE Booking_ID_PK = $id");

        /* Insert the decline reason into Booking_Cancelled */
        mysqli_query(
            $conn,
            "INSERT INTO Booking_Cancelled (Booking_ID_FK, Refund_Status, Note)
             VALUES ($id, 'Cancelled', '$declineNote')"
        );
    }

    header("Location: bookingconfirmation.php");
    exit;
}

/* ── HANDLE: Confirm Payment (To Pay → Approved) ─────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_confirm_payment'])) {
    $id = (int) $_POST['booking_id'];
    $receipt = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT Customer_Receipt FROM Booking_Payment WHERE Booking_ID_FK = $id"
    ));
    if ($receipt && !empty($receipt['Customer_Receipt'])) {
        mysqli_query($conn, "UPDATE Booking SET Booking_Status = 'Approved' WHERE Booking_ID_PK = $id");
    }
    header("Location: bookingconfirmation.php");
    exit;
}

/* ── HANDLE: Complete (Approved → Completed) ─────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_complete'])) {
    $id = (int) $_POST['booking_id'];
    $evt = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT Event_Date FROM Booking WHERE Booking_ID_PK = $id"
    ));
    $today     = new DateTime(date('Y-m-d'));
    $eventDate = new DateTime($evt['Event_Date']);
    if ($today >= $eventDate) {
        mysqli_query($conn, "UPDATE Booking SET Booking_Status = 'Completed' WHERE Booking_ID_PK = $id");
    }
    header("Location: bookingconfirmation.php");
    exit;
}

/* ── HANDLE: Cancel with note (Approved → To Refund) ─────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_cancel'])) {
    $id          = (int) $_POST['booking_id'];
    $cancelNote  = mysqli_real_escape_string($conn, trim($_POST['cancel_note'] ?? ''));

    if ($cancelNote !== '') {
        mysqli_query($conn, "UPDATE Booking SET Booking_Status = 'To Refund' WHERE Booking_ID_PK = $id");

        /* Insert the cancel reason into Booking_Cancelled */
        mysqli_query(
            $conn,
            "INSERT INTO Booking_Cancelled (Booking_ID_FK, Refund_Status, Note)
             VALUES ($id, 'To Refund', '$cancelNote')"
        );
    }

    header("Location: bookingconfirmation.php");
    exit;
}

/* ── HANDLE: Upload Refund Receipt (To Refund → Cancelled) ──────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_refund_receipt'])) {
    $id         = (int) $_POST['booking_id'];
    $refundFile = "";
    if (isset($_FILES['refund_receipt']) && $_FILES['refund_receipt']['error'] === 0) {
        $targetDir = "uploads/refund_receipts/";
        if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
        $fileExt     = strtolower(pathinfo($_FILES['refund_receipt']['name'], PATHINFO_EXTENSION));
        $newFilename = "refund_" . $id . "_" . time() . "." . $fileExt;
        $targetFile  = $targetDir . $newFilename;
        $allowedTypes = ['jpg', 'jpeg', 'png', 'webp', 'pdf'];
        if (in_array($fileExt, $allowedTypes) && $_FILES['refund_receipt']['size'] < 5000000) {
            if (move_uploaded_file($_FILES['refund_receipt']['tmp_name'], $targetFile)) {
                $refundFile = $newFilename;
                $exists = mysqli_fetch_assoc(mysqli_query(
                    $conn,
                    "SELECT Booking_Payment_ID_PK FROM Booking_Payment WHERE Booking_ID_FK = $id"
                ));
                if ($exists) {
                    mysqli_query($conn, "UPDATE Booking_Payment SET Refund_Receipt = '$refundFile' WHERE Booking_ID_FK = $id");
                } else {
                    mysqli_query($conn, "INSERT INTO Booking_Payment (Booking_ID_FK, Refund_Receipt, Gcash_Number) VALUES ($id, '$refundFile', '0')");
                }
                mysqli_query($conn, "UPDATE Booking SET Booking_Status = 'Cancelled' WHERE Booking_ID_PK = $id");
            }
        }
    }
    header("Location: bookingconfirmation.php");
    exit;
}

/* ── HANDLE: Delete Cancelled Booking ───────────────────────── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action_delete'])) {
    $id    = (int) $_POST['booking_id'];
    $check = mysqli_fetch_assoc(mysqli_query(
        $conn,
        "SELECT Booking_Status FROM Booking WHERE Booking_ID_PK = $id"
    ));
    if ($check && $check['Booking_Status'] === 'Cancelled') {
        mysqli_query($conn, "DELETE FROM Booking_Cancelled WHERE Booking_ID_FK = $id");
        mysqli_query($conn, "DELETE FROM Booking_Payment WHERE Booking_ID_FK = $id");
        mysqli_query($conn, "DELETE FROM Booking_Perfume WHERE Booking_ID_FK = $id");
        mysqli_query($conn, "DELETE FROM Booking WHERE Booking_ID_PK = $id");
    }
    header("Location: bookingconfirmation.php");
    exit;
}

/* ── Fetch all bookings ─────────────────────────────────────── */
$bookings = mysqli_query(
    $conn,
    "SELECT
        b.Booking_ID_PK,
        u.Username,
        CONCAT(u.First_Name, ' ', u.Last_Name) AS Full_Name,
        u.Email,
        u.Phone_No,
        b.Event_Address,
        b.Event_Time_Start,
        b.Event_Time_End,
        b.Event_Date,
        b.Event_Type,
        b.Event_Notes,
        b.Booking_Status,
        b.Created_At,
        p.Package_Name,
        p.Price AS Package_Price,
        bs.Bar_Name,
        bv.Bottle_Var_Name
    FROM Booking b
    INNER JOIN User_Information u  ON b.User_ID_FK       = u.User_ID_PK
    INNER JOIN Packages p          ON b.Package_ID_FK    = p.Package_ID_PK
    INNER JOIN Bar_Setup bs        ON b.Bar_Setup_ID_FK  = bs.Bar_Setup_ID_PK
    INNER JOIN Bottle_Variants bv  ON b.Bottle_Var_ID_FK = bv.Bottle_Var_ID_PK
    ORDER BY b.Booking_ID_PK DESC"
);

/* Status counts */
$statusCount = mysqli_query(
    $conn,
    "SELECT Booking_Status, COUNT(*) as total FROM Booking GROUP BY Booking_Status"
);
$counts = [];
while ($row = mysqli_fetch_assoc($statusCount)) {
    $counts[$row['Booking_Status']] = $row['total'];
}
function getCount($counts, $key)
{
    return $counts[$key] ?? 0;
}

/* Pre-load payment data */
$paymentQuery = mysqli_query(
    $conn,
    "SELECT Booking_ID_FK, Additional_Fee, Additional_Fee_Description,
            Gcash_Number, Gcash_Name, Gcash_Code, Customer_Receipt, Refund_Receipt, Total_Price
     FROM Booking_Payment"
);
$paymentData = [];
while ($row = mysqli_fetch_assoc($paymentQuery)) {
    $paymentData[$row['Booking_ID_FK']] = $row;
}

/* Pre-load cancel notes from Booking_Cancelled for display in modal */
$cancelledQuery = mysqli_query(
    $conn,
    "SELECT Booking_ID_FK, Refund_Status, Note, Created_At FROM Booking_Cancelled"
);
$cancelledNotes = [];
while ($row = mysqli_fetch_assoc($cancelledQuery)) {
    $cancelledNotes[$row['Booking_ID_FK']] = $row;
}
?>
<?php if (isset($_GET['db_err']) && $_GET['db_err']): ?>
    <div style="background:#c0392b;color:#fff;padding:12px 20px;font-family:sans-serif;font-size:13px;position:fixed;top:0;left:0;right:0;z-index:99999;">
        <strong>DB Error:</strong> <?= htmlspecialchars($_GET['db_err']); ?> — Please run: <code>ALTER TABLE Booking_Payment MODIFY COLUMN Gcash_Number VARCHAR(15) NOT NULL DEFAULT '';</code>
    </div>
<?php endif; ?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jilz | Admin – Bookings</title>
    <link rel="shortcut icon" href="assets/Logo_Tentative.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="mobileStyle.css">

</head>

<body class="adminBG">

    <!-- ── SIDEBAR ── -->
    <div class="asidebar">
        <h1>Admin</h1>
        <div class="roww">
            <img src="assets/Logo_Tentative.png" class="adminpic">
            <div id="adminnameemail">
                <h3>Username</h3>
                <p>Email</p>
            </div>
        </div>
        <hr>
        <ul>
            <li><a href="bookingconfirmation.php">Manage Bookings</a></li>
            <li><a href="manageProducts.php">Manage Offerings</a></li>
            <li><a href="manageGallery.php">Manage Gallery</a></li>
            <li><a href="addAdmin.php">Add Admin</a></li>
            <li><a href="#" onclick="openLogoutModal()">Logout</a></li>
        </ul>
    </div>

    <!-- ── MAIN CONTENT ── -->
    <div class="bookingPageWrap">
        <h1>Bookings</h1>

        <!-- Status Cards -->
        <div class="statusCardsRow">
            <div class="statusCard activeFilter" data-filter="All">
                <div class="cardLabel" style="justify-content:center;">ALL BOOKINGS</div>
                <div class="cardCount"><?= array_sum($counts); ?></div>
            </div>
            <div class="statusCard" data-filter="Pending" style="border-top: 3px solid #3b82f6;">
                <div class="cardLabel"><span class="cardDot" style="background:#3b82f6;"></span>PENDING</div>
                <div class="cardCount" style="color:#3b82f6;"><?= getCount($counts, 'Pending'); ?></div>
            </div>
            <div class="statusCard" data-filter="To Pay" style="border-top: 3px solid #0ea5e9;">
                <div class="cardLabel"><span class="cardDot" style="background:#0ea5e9;"></span>TO PAY</div>
                <div class="cardCount" style="color:#0ea5e9;"><?= getCount($counts, 'To Pay'); ?></div>
            </div>
            <div class="statusCard" data-filter="Approved" style="border-top: 3px solid #22c55e;">
                <div class="cardLabel"><span class="cardDot" style="background:#22c55e;"></span>CONFIRMED</div>
                <div class="cardCount" style="color:#22c55e;"><?= getCount($counts, 'Approved'); ?></div>
            </div>
            <div class="statusCard" data-filter="Completed" style="border-top: 3px solid #eab308;">
                <div class="cardLabel"><span class="cardDot" style="background:#eab308;"></span>COMPLETED</div>
                <div class="cardCount" style="color:#eab308;"><?= getCount($counts, 'Completed'); ?></div>
            </div>
            <div class="statusCard" data-filter="To Refund" style="border-top: 3px solid #ec4899;">
                <div class="cardLabel"><span class="cardDot" style="background:#ec4899;"></span>TO REFUND</div>
                <div class="cardCount" style="color:#ec4899;"><?= getCount($counts, 'To Refund'); ?></div>
            </div>
            <div class="statusCard" data-filter="Cancelled" style="border-top: 3px solid #ef4444;">
                <div class="cardLabel"><span class="cardDot" style="background:#ef4444;"></span>CANCELLED</div>
                <div class="cardCount" style="color:#ef4444;"><?= getCount($counts, 'Cancelled'); ?></div>
            </div>
        </div>

        <!-- Bookings Table -->
        <div class="bookingsTableWrap">
            <h2>Booking List</h2>
            <hr>
            <table class="bookingTable">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Address</th>
                        <th>Time</th>
                        <th>Date</th>
                        <th>Package</th>
                        <th>Date Issued</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = mysqli_fetch_assoc($bookings)):
                        /* Perfumes */
                        $pq = mysqli_query(
                            $conn,
                            "SELECT p.Inspired_Scent FROM Booking_Perfume bp
                         INNER JOIN Perfume p ON bp.Perfume_ID_FK = p.Perfume_ID_PK
                         WHERE bp.Booking_ID_FK = " . $row['Booking_ID_PK']
                        );
                        $pnames = [];
                        while ($p = mysqli_fetch_assoc($pq)) $pnames[] = $p['Inspired_Scent'];
                        $perfumesSelected = implode(', ', $pnames);

                        $pay        = $paymentData[$row['Booking_ID_PK']]   ?? [];
                        $cancelInfo = $cancelledNotes[$row['Booking_ID_PK']] ?? [];

                        /* Badge class map */
                        $badgeMap = [
                            'Pending'   => 'sPending',
                            'To Pay'    => 'sToPay',
                            'Approved'  => 'sApproved',
                            'Cancelled' => 'sCancelled',
                            'Completed' => 'sCompleted',
                            'To Refund' => 'sToRefund',
                        ];

                        /* Display label map */
                        $displayStatus = [
                            'Pending'   => 'Pending',
                            'To Pay'    => 'To Pay',
                            'Approved'  => 'Confirmed',
                            'Cancelled' => 'Cancelled',
                            'Completed' => 'Completed',
                            'To Refund' => 'To Refund',
                        ];

                        $badgeClass  = $badgeMap[$row['Booking_Status']] ?? '';
                        $statusLabel = $displayStatus[$row['Booking_Status']] ?? $row['Booking_Status'];
                    ?>
                        <tr data-row-status="<?= htmlspecialchars($row['Booking_Status']); ?>">
                            <td><?= $row['Booking_ID_PK']; ?></td>
                            <td><?= htmlspecialchars($row['Username']); ?></td>
                            <td><?= htmlspecialchars($row['Event_Address']); ?></td>
                            <td><?= date('h:i A', strtotime($row['Event_Time_Start'])); ?> – <?= date('h:i A', strtotime($row['Event_Time_End'])); ?></td>
                            <td><?= date('M d, Y', strtotime($row['Event_Date'])); ?></td>
                            <td><?= htmlspecialchars($row['Package_Name']); ?></td>
                            <td><?= date('M d, Y h:i A', strtotime($row['Created_At'])); ?></td>
                            <td><span class="tblStatusBadge <?= $badgeClass; ?>"><?= $statusLabel; ?></span></td>
                            <td>
                                <button class="viewBtn"
                                    data-id="<?= $row['Booking_ID_PK']; ?>"
                                    data-customername="<?= htmlspecialchars($row['Full_Name']); ?>"
                                    data-phone="<?= htmlspecialchars($row['Phone_No']); ?>"
                                    data-email="<?= htmlspecialchars($row['Email']); ?>"
                                    data-package="<?= htmlspecialchars($row['Package_Name']); ?>"
                                    data-price="<?= $row['Package_Price']; ?>"
                                    data-date="<?= date('F d, Y', strtotime($row['Event_Date'])); ?>"
                                    data-eventdateraw="<?= $row['Event_Date']; ?>"
                                    data-time="<?= date('h:i A', strtotime($row['Event_Time_Start'])) . ' – ' . date('h:i A', strtotime($row['Event_Time_End'])); ?>"
                                    data-address="<?= htmlspecialchars($row['Event_Address']); ?>"
                                    data-eventtype="<?= htmlspecialchars($row['Event_Type']); ?>"
                                    data-notes="<?= htmlspecialchars($row['Event_Notes']); ?>"
                                    data-barsetup="<?= htmlspecialchars($row['Bar_Name']); ?>"
                                    data-bottle="<?= htmlspecialchars($row['Bottle_Var_Name']); ?>"
                                    data-perfumes="<?= htmlspecialchars($perfumesSelected); ?>"
                                    data-status="<?= htmlspecialchars($row['Booking_Status']); ?>"
                                    data-gcash="<?= htmlspecialchars($pay['Gcash_Number'] ?? ''); ?>"
                                    data-gcashname="<?= htmlspecialchars($pay['Gcash_Name'] ?? ''); ?>"
                                    data-gcashcode="<?= htmlspecialchars($pay['Gcash_Code'] ?? ''); ?>"
                                    data-addfee="<?= $pay['Additional_Fee'] ?? '0'; ?>"
                                    data-addreason="<?= htmlspecialchars($pay['Additional_Fee_Description'] ?? ''); ?>"
                                    data-total="<?= $pay['Total_Price'] ?? $row['Package_Price']; ?>"
                                    data-receipt="<?= htmlspecialchars($pay['Customer_Receipt'] ?? ''); ?>"
                                    data-refundreceipt="<?= htmlspecialchars($pay['Refund_Receipt'] ?? ''); ?>"
                                    data-cancelnote="<?= htmlspecialchars($cancelInfo['Note'] ?? ''); ?>">
                                    View
                                </button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- ════════════════════════════════════════
         VIEW DETAILS MODAL
    ════════════════════════════════════════ -->
    <div class="modalOverlay" id="detailModal">
        <div class="modalBox">

            <!-- Header -->
            <div class="modalHeader">
                <p>Booking ID: <strong id="dId"></strong></p>
                <button class="modalCloseBtn" onclick="closeDetailModal()">&#10005;</button>
            </div>

            <!-- Customer -->
            <div class="modalSection">
                <h2 class="customerName" id="dCustomerName"></h2>
            </div>

            <!-- Quick info -->
            <div class="modalSection">
                <div class="infoGrid cols4">
                    <div class="infoItem">
                        <p class="lbl">Package</p>
                        <p class="val" id="dPackage"></p>
                    </div>
                    <div class="infoItem">
                        <p class="lbl">Date</p>
                        <p class="val" id="dDate"></p>
                    </div>
                    <div class="infoItem">
                        <p class="lbl">Time</p>
                        <p class="val" id="dTime"></p>
                    </div>
                    <div class="infoItem">
                        <p class="lbl">Address</p>
                        <p class="val" id="dAddress"></p>
                    </div>
                </div>
            </div>

            <!-- General info -->
            <div class="modalSection">
                <p class="sectionTitle">General Info</p>
                <div class="infoGrid">
                    <div class="infoItem">
                        <p class="lbl">Phone</p>
                        <p class="val" id="dPhone"></p>
                    </div>
                    <div class="infoItem">
                        <p class="lbl">Email</p>
                        <p class="val" id="dEmail"></p>
                    </div>
                    <div class="infoItem">
                        <p class="lbl">Event Type</p>
                        <p class="val" id="dEventType"></p>
                    </div>
                    <div class="infoItem">
                        <p class="lbl">Bar Setup</p>
                        <p class="val" id="dBarSetup"></p>
                    </div>
                    <div class="infoItem">
                        <p class="lbl">Bottle</p>
                        <p class="val" id="dBottle"></p>
                    </div>
                    <div class="infoItem">
                        <p class="lbl">Status</p>
                        <p class="val" id="dStatus"></p>
                    </div>
                    <div class="infoItem fullCol">
                        <p class="lbl">Perfumes Selected</p>
                        <p class="val" id="dPerfumes"></p>
                    </div>
                    <div class="infoItem fullCol">
                        <p class="lbl">Notes</p>
                        <p class="val" id="dNotes"></p>
                    </div>
                </div>
            </div>

            <!-- Action area (injected by JS based on booking status) -->
            <div class="actionArea" id="actionArea"></div>

        </div>
    </div>

    <!-- ════════════════════════════════════════
         MINI CONFIRM MODAL — Accept
    ════════════════════════════════════════ -->
    <div class="miniModal" id="miniAccept">
        <div class="miniModalBox">
            <h3>Accept Booking</h3>
            <p>Send this booking to payment? GCash details will be shared with the customer.</p>
            <div class="miniModalBtns">
                <button class="btnConfirm" onclick="document.getElementById('formAccept').submit()">Confirm</button>
                <button class="btnCancel" onclick="closeMini('miniAccept')">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Mini Confirm Modal — Confirm Payment -->
    <div class="miniModal" id="miniConfirmPay">
        <div class="miniModalBox">
            <h3>Confirm Payment</h3>
            <p>Confirm that the customer's payment has been received and verified?</p>
            <div class="miniModalBtns">
                <button class="btnConfirm" onclick="document.getElementById('formConfirmPay').submit()">Confirm</button>
                <button class="btnCancel" onclick="closeMini('miniConfirmPay')">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Mini Confirm Modal — Mark as Completed -->
    <div class="miniModal" id="miniComplete">
        <div class="miniModalBox">
            <h3>Mark as Completed</h3>
            <p>Mark this booking as completed? This means the event has already taken place.</p>
            <div class="miniModalBtns">
                <button class="btnConfirm" onclick="document.getElementById('formComplete').submit()">Confirm</button>
                <button class="btnCancel" onclick="closeMini('miniComplete')">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Mini Confirm Modal — Delete -->
    <div class="miniModal" id="miniDelete">
        <div class="miniModalBox">
            <h3>Delete Booking</h3>
            <p>Permanently delete this cancelled booking? This cannot be undone.</p>
            <div class="miniModalBtns">
                <button class="btnConfirm" style="background:#c0392b;" onclick="document.getElementById('formDelete').submit()">Delete</button>
                <button class="btnCancel" onclick="closeMini('miniDelete')">Cancel</button>
            </div>
        </div>
    </div>

    <!-- Logout Modal -->
    <!-- LOGOUT MODAL (uses admin.css classes — intentional) -->
    <div id="logoutModal" class="modal-overlay del-modal" style="display:none;">
        <div class="modal-box">
            <h3>Confirm Logout</h3>
            <p>Are you sure you want to logout?</p>
            <div class="del-btns">
                <button type="button" class="btn-confirm"
                    onclick="window.location.href='profile.php?logout=true'">Confirm</button>
                <button class="btn-cancel"
                    onclick="document.getElementById('logoutModal').style.display='none'">Cancel</button>
            </div>
        </div>
    </div>

    <!-- ════════════════════════════════════════
         JAVASCRIPT
    ════════════════════════════════════════ -->
    <script>
        /* ── Logout ── */
        function openLogoutModal() {
            document.getElementById('logoutModal').style.display = 'flex';
        }

        /* ── Mini modals ── */
        function openMini(id) {
            document.getElementById(id).classList.add('open');
        }

        function closeMini(id) {
            document.getElementById(id).classList.remove('open');
        }

        /* ── Detail modal ── */
        function closeDetailModal() {
            document.getElementById('detailModal').classList.remove('open');
        }

        document.getElementById('detailModal').addEventListener('click', function(e) {
            if (e.target === this) closeDetailModal();
        });

        /* ── HTML escape helper ── */
        function esc(str) {
            if (!str) return '';
            return str.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
        }

        /* ── Validate accept form before opening confirm mini modal ── */
        function validateAndAccept() {
            const name = document.querySelector('#formAccept input[name="gcash_name"]');
            const num = document.querySelector('#formAccept input[name="gcash_number"]');
            const qr = document.querySelector('#formAccept input[name="gcash_qr"]');
            const errors = [];

            if (!name || name.value.trim() === '') errors.push('GCash Name is required.');
            if (!num || num.value.trim() === '' || !/^\d{11}$/.test(num.value.trim())) errors.push('GCash Number must be exactly 11 digits.');
            if (!qr || qr.files.length === 0) errors.push('GCash QR Code image is required.');

            if (errors.length > 0) {
                alert('Please fix the following:\n\n' + errors.join('\n'));
                return;
            }
            openMini('miniAccept');
        }

        /* ── Show the inline cancel reason form and hide the trigger button row ── */
        function showCancelReasonForm(formId, btnRowId) {
            document.getElementById(formId).classList.add('visible');
            const btnRow = document.getElementById(btnRowId);
            if (btnRow) btnRow.style.display = 'none';
        }

        /* ── Hide the inline cancel reason form and restore the trigger button row ── */
        function hideCancelReasonForm(formId, btnRowId) {
            const form = document.getElementById(formId);
            if (form) {
                form.classList.remove('visible');
                /* Reset textarea and error state */
                const ta = form.querySelector('.cancelReasonTa');
                const err = form.querySelector('.cancelReasonErr');
                if (ta) ta.value = '';
                if (err) err.style.display = 'none';
            }
            const btnRow = document.getElementById(btnRowId);
            if (btnRow) btnRow.style.display = 'flex';
        }

        /* ── Validate and submit the inline cancel reason form ── */
        function submitCancelReason(formId, hiddenFormId, noteInputId) {
            const reasonForm = document.getElementById(formId);
            const ta = reasonForm.querySelector('.cancelReasonTa');
            const err = reasonForm.querySelector('.cancelReasonErr');

            /* Block submit if no reason was entered */
            if (!ta || ta.value.trim() === '') {
                if (err) err.style.display = 'block';
                return;
            }

            if (err) err.style.display = 'none';

            /* Copy the typed reason into the hidden form input and submit */
            document.getElementById(noteInputId).value = ta.value.trim();
            document.getElementById(hiddenFormId).submit();
        }

        /* ── Build action area based on booking status ── */
        function buildActionArea(d) {
            const area = document.getElementById('actionArea');
            area.innerHTML = '';

            const today = new Date().toISOString().split('T')[0];
            const canComplete = today >= d.eventDateRaw;
            const hasReceipt = d.receipt && d.receipt.trim() !== '';

            /* ── PENDING: Accept (with GCash form) + Decline (with reason) ── */
            if (d.status === 'Pending') {
                area.innerHTML = `
                    <p class="actionTitle">Actions</p>

                    <!-- Accept form -->
                    <form id="formAccept" class="actionForm" method="POST" action="bookingconfirmation.php" enctype="multipart/form-data">
                        <input type="hidden" name="booking_id" value="${d.id}">
                        <input type="hidden" name="action_accept" value="1">
                        <div class="fRow">
                            <div>
                                <label>GCash Name</label>
                                <input type="text" name="gcash_name" placeholder="e.g. Juan Dela Cruz" required>
                            </div>
                            <div>
                                <label>GCash Number</label>
                                <input type="text" name="gcash_number" placeholder="09XXXXXXXXX" minlength="11" maxlength="11" pattern="\\d{11}" required>
                            </div>
                        </div>
                        <div class="fRow">
                            <div>
                                <label>Additional Fee (PHP)</label>
                                <input type="number" name="additional_fee" value="0" min="0" step="0.01">
                            </div>
                            <div>
                                <label>Reason / Description</label>
                                <input type="text" name="additional_fee_reason" placeholder="e.g. Extra setup">
                            </div>
                        </div>
                        <div>
                            <label>GCash QR Code Image</label>
                            <input class="fileInput" type="file" id="qrUpload" name="gcash_qr" accept="image/jpeg,image/png,image/webp" required>
                            <div class="qrPreviewWrap" id="qrPreviewWrap">
                                <p>QR Preview:</p>
                                <img class="qrPreviewImg" id="qrPreviewImg" src="" alt="QR Preview">
                            </div>
                        </div>

                        <!-- Primary action buttons row -->
                        <div class="actionBtns" id="pendingBtnRow">
                            <button type="button" class="btn-accept" onclick="validateAndAccept()">Accept &amp; Send to Payment</button>
                            <button type="button" class="btn-decline" onclick="showCancelReasonForm('declineReasonForm', 'pendingBtnRow')">Decline</button>
                        </div>
                    </form>

                    <!-- Inline decline reason form — shown when Decline is clicked -->
                    <div class="cancelReasonForm" id="declineReasonForm">
                        <p class="cancelReasonLbl">Reason for Declining</p>
                        <textarea class="cancelReasonTa" maxlength="255" placeholder="Enter reason for declining this booking..."></textarea>
                        <p class="cancelReasonErr">Please enter a reason before confirming.</p>
                        <div class="cancelReasonActions">
                            <button type="button" class="btnCancelBack" onclick="hideCancelReasonForm('declineReasonForm', 'pendingBtnRow')">Go Back</button>
                            <button type="button" class="btnCancelSubmit" onclick="submitCancelReason('declineReasonForm', 'formDecline', 'declineNoteInput')">Confirm Decline</button>
                        </div>
                    </div>

                    <!-- Hidden form submitted when decline is confirmed -->
                    <form id="formDecline" method="POST" action="bookingconfirmation.php" style="display:none;">
                        <input type="hidden" name="booking_id"    value="${d.id}">
                        <input type="hidden" name="action_decline" value="1">
                        <input type="hidden" name="decline_note"  id="declineNoteInput" value="">
                    </form>`;

                /* QR live preview listener */
                setTimeout(() => {
                    const inp = document.getElementById('qrUpload');
                    const wrap = document.getElementById('qrPreviewWrap');
                    const img = document.getElementById('qrPreviewImg');
                    if (inp) {
                        inp.addEventListener('change', function() {
                            const file = this.files[0];
                            if (file) {
                                const reader = new FileReader();
                                reader.onload = e => {
                                    img.src = e.target.result;
                                    wrap.style.display = 'block';
                                };
                                reader.readAsDataURL(file);
                            }
                        });
                    }
                }, 200);
            }

            /* ── TO PAY: Show payment details and allow confirming payment ── */
            else if (d.status === 'To Pay') {
                const totalFmt = parseFloat(d.total).toLocaleString('en-PH', {
                    style: 'currency',
                    currency: 'PHP'
                });
                const receiptHtml = hasReceipt ?
                    `<img class="receiptImg" src="uploads/receipts/${esc(d.receipt)}" alt="Customer receipt">` :
                    `<p class="noReceipt">Waiting for customer to upload receipt.</p>`;

                area.innerHTML = `
                    <p class="actionTitle">Payment Details</p>
                    <div class="actionForm">
                        <div class="fRow">
                            <div>
                                <label>GCash Number (sent to customer)</label>
                                <input type="text" value="${esc(d.gcash)}" readonly>
                            </div>
                            <div>
                                <label>Total Amount</label>
                                <input type="text" value="${totalFmt}" readonly>
                            </div>
                        </div>
                        <div class="fRow">
                            <div>
                                <label>Additional Fee</label>
                                <input type="text" value="PHP ${parseFloat(d.addFee || 0).toFixed(2)}" readonly>
                            </div>
                            <div>
                                <label>Fee Description</label>
                                <input type="text" value="${esc(d.addReason) || '—'}" readonly>
                            </div>
                        </div>
                        <div>
                            <label>Customer Receipt</label>
                            <div>${receiptHtml}</div>
                        </div>
                        ${!hasReceipt ? `<div class="warningNote">Cannot confirm payment — customer has not yet uploaded their receipt.</div>` : ''}
                        <div class="actionBtns">
                            <button type="button" class="btn-confirm"
                                ${hasReceipt ? `onclick="openMini('miniConfirmPay')"` : 'disabled'}>
                                Confirm Payment
                            </button>
                        </div>
                    </div>
                    <form id="formConfirmPay" method="POST" action="bookingconfirmation.php" style="display:none;">
                        <input type="hidden" name="booking_id" value="${d.id}">
                        <input type="hidden" name="action_confirm_payment" value="1">
                    </form>`;
            }

            /* ── APPROVED: Complete or Cancel (with reason) ── */
            else if (d.status === 'Approved') {
                area.innerHTML = `
                    <p class="actionTitle">Actions</p>
                    ${!canComplete ? `<div class="warningNote">Mark as Completed is only available on or after the event date (${esc(d.date)}).</div>` : ''}

                    <!-- Primary action buttons row -->
                    <div class="actionBtns" id="approvedBtnRow">
                        <button type="button" class="btn-complete"
                            ${canComplete ? `onclick="openMini('miniComplete')"` : 'disabled'}>
                            Mark as Completed
                        </button>
                        <button type="button" class="btn-cancel" onclick="showCancelReasonForm('cancelReasonForm', 'approvedBtnRow')">
                            Cancel Booking
                        </button>
                    </div>

                    <!-- Inline cancel reason form — shown when Cancel Booking is clicked -->
                    <div class="cancelReasonForm" id="cancelReasonForm">
                        <p class="cancelReasonLbl">Reason for Cancellation</p>
                        <textarea class="cancelReasonTa" maxlength="255" placeholder="Enter reason for cancelling this booking..."></textarea>
                        <p class="cancelReasonErr">Please enter a reason before confirming.</p>
                        <div class="cancelReasonActions">
                            <button type="button" class="btnCancelBack" onclick="hideCancelReasonForm('cancelReasonForm', 'approvedBtnRow')">Go Back</button>
                            <button type="button" class="btnCancelSubmit" onclick="submitCancelReason('cancelReasonForm', 'formCancel', 'cancelNoteInput')">Confirm Cancel</button>
                        </div>
                    </div>

                    <!-- Hidden forms submitted on confirm -->
                    <form id="formComplete" method="POST" action="bookingconfirmation.php" style="display:none;">
                        <input type="hidden" name="booking_id" value="${d.id}">
                        <input type="hidden" name="action_complete" value="1">
                    </form>
                    <form id="formCancel" method="POST" action="bookingconfirmation.php" style="display:none;">
                        <input type="hidden" name="booking_id"   value="${d.id}">
                        <input type="hidden" name="action_cancel" value="1">
                        <input type="hidden" name="cancel_note"  id="cancelNoteInput" value="">
                    </form>`;
            }

            /* ── TO REFUND: Upload proof of refund then close the booking ── */
            else if (d.status === 'To Refund') {
                const hasRefund = d.refundReceipt && d.refundReceipt.trim() !== '';
                const refundHtml = hasRefund ?
                    `<img class="receiptImg" src="uploads/refund_receipts/${esc(d.refundReceipt)}" alt="Refund receipt">` :
                    '';

                /* Show the stored cancel note if one exists */
                const cancelNoteHtml = d.cancelNote && d.cancelNote.trim() !== '' ?
                    `<div class="cancelNoteBox">
                           <p class="cancelNoteLabel">Customer Cancellation Reason</p>
                           <p class="cancelNoteText">${esc(d.cancelNote)}</p>
                       </div>` :
                    '';

                area.innerHTML = `
                    <p class="actionTitle">Refund Actions</p>
                    ${cancelNoteHtml}
                    <p style="font-size:12px;color:rgba(255,255,255,0.5);margin-bottom:14px;">
                        Upload proof of refund and send it to the customer. Once uploaded, the booking will be marked as Cancelled.
                    </p>
                    <form id="formRefund" class="actionForm" method="POST" action="bookingconfirmation.php" enctype="multipart/form-data">
                        <input type="hidden" name="booking_id" value="${d.id}">
                        <input type="hidden" name="action_refund_receipt" value="1">
                        <div>
                            <label>Refund Receipt / Proof of Transfer</label>
                            <input class="fileInput" type="file" name="refund_receipt" accept="image/jpeg,image/png,image/webp,application/pdf" required>
                        </div>
                        <div class="actionBtns">
                            <button type="submit" class="btn-refund">Upload &amp; Mark as Cancelled</button>
                        </div>
                    </form>`;
            }

            /* ── CANCELLED: Show cancel note and allow deletion ── */
            else if (d.status === 'Cancelled') {
                /* Show the stored cancel note if one exists */
                const cancelNoteHtml = d.cancelNote && d.cancelNote.trim() !== '' ?
                    `<div class="cancelNoteBox">
                           <p class="cancelNoteLabel">Cancellation Reason</p>
                           <p class="cancelNoteText">${esc(d.cancelNote)}</p>
                       </div>` :
                    '';

                area.innerHTML = `
                    <p class="actionTitle">Actions</p>
                    ${cancelNoteHtml}
                    <p style="font-size:12px;color:rgba(255,255,255,0.5);margin-bottom:14px;">
                        This booking has been cancelled. You may delete it from the records.
                    </p>
                    <div class="actionBtns">
                        <button type="button" class="btn-delete" onclick="openMini('miniDelete')">Delete Booking</button>
                    </div>
                    <form id="formDelete" method="POST" action="bookingconfirmation.php" style="display:none;">
                        <input type="hidden" name="booking_id" value="${d.id}">
                        <input type="hidden" name="action_delete" value="1">
                    </form>`;
            }

            /* ── COMPLETED: No further actions ── */
            else {
                area.innerHTML = `<p style="font-size:12px;color:rgba(255,255,255,0.4);margin-top:4px;">No further actions available for this booking.</p>`;
            }
        }

        /* ── View button click handler ── */
        document.addEventListener('DOMContentLoaded', function() {
            document.querySelectorAll('.viewBtn').forEach(btn => {
                btn.addEventListener('click', function() {
                    const d = this.dataset;

                    document.getElementById('dId').textContent = d.id;
                    document.getElementById('dCustomerName').textContent = d.customername;
                    document.getElementById('dPackage').textContent = d.package;
                    document.getElementById('dDate').textContent = d.date;
                    document.getElementById('dTime').textContent = d.time;
                    document.getElementById('dAddress').textContent = d.address;
                    document.getElementById('dPhone').textContent = d.phone;
                    document.getElementById('dEmail').textContent = d.email;
                    document.getElementById('dEventType').textContent = d.eventtype;
                    document.getElementById('dBarSetup').textContent = d.barsetup;
                    document.getElementById('dBottle').textContent = d.bottle;
                    document.getElementById('dPerfumes').textContent = d.perfumes || 'None';
                    document.getElementById('dNotes').textContent = d.notes || 'No notes provided.';

                    /* Maps DB ENUM value to display label */
                    const statusLabels = {
                        'Pending': 'Pending',
                        'To Pay': 'To Pay',
                        'Approved': 'Confirmed',
                        'Cancelled': 'Cancelled',
                        'Completed': 'Completed',
                        'To Refund': 'To Refund',
                    };
                    document.getElementById('dStatus').textContent = statusLabels[d.status] || d.status;

                    buildActionArea({
                        id: d.id,
                        status: d.status,
                        date: d.date,
                        eventDateRaw: d.eventdateraw,
                        gcash: d.gcash,
                        gcashName: d.gcashname,
                        gcashCode: d.gcashcode,
                        addFee: d.addfee,
                        addReason: d.addreason,
                        total: d.total,
                        receipt: d.receipt,
                        refundReceipt: d.refundreceipt,
                        cancelNote: d.cancelnote,
                    });

                    document.getElementById('detailModal').classList.add('open');
                });
            });

            /* ── Status filter card click handler ── */
            document.querySelectorAll('.statusCard').forEach(card => {
                card.addEventListener('click', function() {
                    document.querySelectorAll('.statusCard').forEach(c => c.classList.remove('activeFilter'));
                    this.classList.add('activeFilter');
                    const sel = this.dataset.filter;
                    document.querySelectorAll('.bookingTable tbody tr').forEach(row => {
                        const rowStatus = row.dataset.rowStatus || '';
                        row.style.display = (sel === 'All' || rowStatus === sel) ? '' : 'none';
                    });
                });
            });
        });
    </script>

</body>

</html>