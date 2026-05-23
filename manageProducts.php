<?php
session_start();
require("db.php");

if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

$adminUsername = 'Admin';
if (isset($_SESSION['admin_id'])) {
    $adminId = (int) $_SESSION['admin_id'];
    $adminResult = mysqli_query($conn, "SELECT Username FROM Admin_Information WHERE Admin_ID_PK = $adminId");
    if ($adminRow = mysqli_fetch_assoc($adminResult)) {
        $adminUsername = $adminRow['Username'];
    }
}

if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit();
}

function uploadImage($file)
{
    if (!isset($file['name']) || $file['name'] == "") return null;
    $targetDir = "uploads/";
    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);
    $filename   = time() . "_" . basename($file["name"]);
    $targetFile = $targetDir . $filename;
    if (move_uploaded_file($file["tmp_name"], $targetFile)) return $targetFile;
    return null;
}

$activeTab = $_GET['tab'] ?? 'perfumes';

/* ─── PERFUMES — SAVE ─── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_perfume') {
    $id   = $_POST['edit_id'] ?? null;
    $name = $_POST['perfumeName'];
    $desc = $_POST['perfumeDescription'];
    $cat  = $_POST['perfumeCategory'];
    $stat = $_POST['perfumeStatus'];
    $img1 = uploadImage($_FILES['image1'] ?? []);
    $img2 = uploadImage($_FILES['image2'] ?? []);
    $img3 = !empty($_POST['image3']) ? $_POST['image3'] : null;

    if ($id) {
        $old  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM Perfume WHERE Perfume_ID_PK=" . (int)$id));
        $img1 = $img1 ?: $old['Perfume_Img'];
        $img2 = $img2 ?: $old['Perfume_Background'];
        $img3 = $img3 ?: $old['Perfume_Color'];
        $sql  = "UPDATE Perfume SET Inspired_Scent=?, Perfume_Description=?, Perfume_Category=?,
                 Perfume_Img=?, Perfume_Status=?, Perfume_Background=?, Perfume_Color=?
                 WHERE Perfume_ID_PK=?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sssssssi", $name, $desc, $cat, $img1, $stat, $img2, $img3, $id);
    } else {
        $sql  = "INSERT INTO Perfume (Inspired_Scent, Perfume_Description, Perfume_Category,
                 Perfume_Img, Perfume_Status, Perfume_Background, Perfume_Color)
                 VALUES (?, ?, ?, ?, ?, ?, ?)";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "sssssss", $name, $desc, $cat, $img1, $stat, $img2, $img3);
    }
    mysqli_stmt_execute($stmt);
    header("Location: manageProducts.php?tab=perfumes");
    exit();
}

/* ─── PERFUMES — DELETE ─── */
if (isset($_GET['delete_perf'])) {
    $pid = intval($_GET['delete_perf']);
    $ref = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM Booking_Perfume WHERE Perfume_ID_FK=$pid"));
    if ($ref['cnt'] > 0) {
        header("Location: manageProducts.php?tab=perfumes&perf_ref_error=1&perf_ref_id=$pid");
        exit();
    }
    $s = mysqli_prepare($conn, "DELETE FROM Perfume_Ratings WHERE Perfume_ID_FK=?");
    mysqli_stmt_bind_param($s, "i", $pid);
    mysqli_stmt_execute($s);
    $s = mysqli_prepare($conn, "DELETE FROM Perfume WHERE Perfume_ID_PK=?");
    mysqli_stmt_bind_param($s, "i", $pid);
    mysqli_stmt_execute($s);
    header("Location: manageProducts.php?tab=perfumes");
    exit();
}

$editPerf = null;
if (isset($_GET['perf_edit_id'])) {
    $editPerf  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM Perfume WHERE Perfume_ID_PK=" . intval($_GET['perf_edit_id'])));
    $activeTab = 'perfumes';
}
$perfRefError = isset($_GET['perf_ref_error']) ? intval($_GET['perf_ref_id']) : null;

$perfSQL = "SELECT * FROM Perfume";
$pwhere  = [];
if (!empty($_GET['pcat'])) {
    $pwhere[] = "Perfume_Category='" . mysqli_real_escape_string($conn, $_GET['pcat']) . "'";
}
if (!empty($_GET['pstat'])) {
    $pwhere[] = "Perfume_Status='"   . mysqli_real_escape_string($conn, $_GET['pstat']) . "'";
}
if ($pwhere) $perfSQL .= " WHERE " . implode(" AND ", $pwhere);
$perfumes = mysqli_query($conn, $perfSQL);

/* ─── BOTTLES — ADD ─── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_bottle') {
    $name = $_POST['Bottle_Name'];
    $size = $_POST['Bottle_Size'];
    $stat = $_POST['Bottle_Status'];
    $s    = mysqli_prepare($conn, "INSERT INTO Bottle (Bottle_Name, Bottle_Size, Bottle_Status) VALUES (?, ?, ?)");
    mysqli_stmt_bind_param($s, "sis", $name, $size, $stat);
    mysqli_stmt_execute($s);
    header("Location: manageProducts.php?tab=bottles");
    exit();
}

/* ─── BOTTLES — EDIT ─── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_bottle') {
    $id   = $_POST['edit_id'];
    $name = $_POST['Bottle_Name'];
    $size = $_POST['Bottle_Size'];
    $stat = $_POST['Bottle_Status'];
    $s = mysqli_prepare($conn, "UPDATE Bottle SET Bottle_Name=?, Bottle_Size=?, Bottle_Status=? WHERE Bottle_ID_PK=?");
    mysqli_stmt_bind_param($s, "sisi", $name, $size, $stat, $id);
    mysqli_stmt_execute($s);
    if ($stat === 'Unavailable') {
        $sv = mysqli_prepare($conn, "UPDATE Bottle_Variants SET Bottle_Var_Status='Unavailable' WHERE Bottle_ID_FK=?");
        mysqli_stmt_bind_param($sv, "i", $id);
        mysqli_stmt_execute($sv);
    }
    header("Location: manageProducts.php?tab=bottles");
    exit();
}

/* ─── BOTTLES — DELETE ─── */
if (isset($_GET['delete_bottle'])) {
    $bid = intval($_GET['delete_bottle']);
    $r1  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM Booking b JOIN Bottle_Variants v ON b.Bottle_Var_ID_FK=v.Bottle_Var_ID_PK WHERE v.Bottle_ID_FK=$bid"));
    $r2  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM Packages WHERE Bottle_ID_FK=$bid"));
    if ($r1['cnt'] > 0 || $r2['cnt'] > 0) {
        header("Location: manageProducts.php?tab=bottles&bottle_ref_error=1&bottle_ref_id=$bid");
        exit();
    }
    $s = mysqli_prepare($conn, "DELETE FROM Bottle_Variants WHERE Bottle_ID_FK=?");
    mysqli_stmt_bind_param($s, "i", $bid);
    mysqli_stmt_execute($s);
    $s = mysqli_prepare($conn, "DELETE FROM Bottle WHERE Bottle_ID_PK=?");
    mysqli_stmt_bind_param($s, "i", $bid);
    mysqli_stmt_execute($s);
    header("Location: manageProducts.php?tab=bottles");
    exit();
}

$editBottle = null;
if (isset($_GET['bottle_edit_id'])) {
    $editBottle = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM Bottle WHERE Bottle_ID_PK=" . intval($_GET['bottle_edit_id'])));
    $activeTab  = 'bottles';
}
$bottleRefError = isset($_GET['bottle_ref_error']) ? intval($_GET['bottle_ref_id']) : null;

$bStat = !empty($_GET['bstat']) ? mysqli_real_escape_string($conn, $_GET['bstat']) : '';
if ($bStat) {
    $bstmt = mysqli_prepare($conn, "SELECT * FROM Bottle WHERE Bottle_Status = ?");
    mysqli_stmt_bind_param($bstmt, "s", $bStat);
    mysqli_stmt_execute($bstmt);
    $bottles = mysqli_stmt_get_result($bstmt);
} else {
    $bottles = mysqli_query($conn, "SELECT * FROM Bottle");
}

/* ─── BOTTLE VARIANTS — ADD ─── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'add_variant') {
    $bid  = $_POST['Bottle_ID_FK'];
    $vn   = $_POST['Bottle_Var_Name'];
    $vs   = $_POST['Bottle_Var_Status'];
    $vimg = '';
    if (isset($_FILES['Bottle_Img']) && $_FILES['Bottle_Img']['error'] === 0) {
        $vimg = "uploads/" . basename($_FILES['Bottle_Img']['name']);
        move_uploaded_file($_FILES['Bottle_Img']['tmp_name'], $vimg);
    }
    $s = mysqli_prepare($conn, "INSERT INTO Bottle_Variants (Bottle_ID_FK, Bottle_Var_Name, Bottle_Img, Bottle_Var_Status) VALUES (?, ?, ?, ?)");
    mysqli_stmt_bind_param($s, "isss", $bid, $vn, $vimg, $vs);
    mysqli_stmt_execute($s);
    header("Location: manageProducts.php?tab=bottles");
    exit();
}

/* ─── BOTTLE VARIANTS — EDIT ─── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'edit_variant') {
    $id     = $_POST['variant_edit_id'];
    $vn     = $_POST['Bottle_Var_Name'];
    $vs     = $_POST['Bottle_Var_Status'];
    $newImg = '';
    if (isset($_FILES['Bottle_Img']) && $_FILES['Bottle_Img']['error'] === 0 && $_FILES['Bottle_Img']['name'] != '') {
        $newImg = "uploads/" . basename($_FILES['Bottle_Img']['name']);
        move_uploaded_file($_FILES['Bottle_Img']['tmp_name'], $newImg);
    }
    if ($newImg) {
        $s = mysqli_prepare($conn, "UPDATE Bottle_Variants SET Bottle_Var_Name=?, Bottle_Img=?, Bottle_Var_Status=? WHERE Bottle_Var_ID_PK=?");
        mysqli_stmt_bind_param($s, "sssi", $vn, $newImg, $vs, $id);
    } else {
        $s = mysqli_prepare($conn, "UPDATE Bottle_Variants SET Bottle_Var_Name=?, Bottle_Var_Status=? WHERE Bottle_Var_ID_PK=?");
        mysqli_stmt_bind_param($s, "ssi", $vn, $vs, $id);
    }
    mysqli_stmt_execute($s);
    header("Location: manageProducts.php?tab=bottles");
    exit();
}

/* ─── BOTTLE VARIANTS — DELETE ─── */
if (isset($_GET['delete_variant'])) {
    $vid = intval($_GET['delete_variant']);
    $r   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM Booking WHERE Bottle_Var_ID_FK=$vid"));
    if ($r['cnt'] > 0) {
        header("Location: manageProducts.php?tab=bottles&variant_ref_error=1&variant_id=$vid");
        exit();
    }
    $s = mysqli_prepare($conn, "DELETE FROM Bottle_Variants WHERE Bottle_Var_ID_PK=?");
    mysqli_stmt_bind_param($s, "i", $vid);
    mysqli_stmt_execute($s);
    header("Location: manageProducts.php?tab=bottles");
    exit();
}

$editVariant = null;
if (isset($_GET['variant_edit_id'])) {
    $editVariant = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM Bottle_Variants WHERE Bottle_Var_ID_PK=" . intval($_GET['variant_edit_id'])));
    $activeTab   = 'bottles';
}
$variantRefError = isset($_GET['variant_ref_error']) ? intval($_GET['variant_id']) : null;

$vStat = !empty($_GET['vstat']) ? mysqli_real_escape_string($conn, $_GET['vstat']) : '';
if ($vStat) {
    $vstmt = mysqli_prepare($conn, "SELECT bv.*, b.Bottle_Size FROM Bottle_Variants bv JOIN Bottle b ON bv.Bottle_ID_FK=b.Bottle_ID_PK WHERE bv.Bottle_Var_Status=?");
    mysqli_stmt_bind_param($vstmt, "s", $vStat);
    mysqli_stmt_execute($vstmt);
    $variants = mysqli_stmt_get_result($vstmt);
} else {
    $variants = mysqli_query($conn, "SELECT bv.*, b.Bottle_Size FROM Bottle_Variants bv JOIN Bottle b ON bv.Bottle_ID_FK=b.Bottle_ID_PK");
}

/* ─── BAR SETUP — SAVE ─── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_setup') {
    $id    = $_POST['edit_id'] ?? null;
    $name  = $_POST['Bar_Name'];
    $stat  = $_POST['Bar_Status'] ?? 'Available';
    $image = uploadImage($_FILES['Bar_Img'] ?? []);
    if ($id) {
        $old   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM Bar_Setup WHERE Bar_Setup_ID_PK=$id"));
        $image = $image ?: $old['Bar_Img'];
        $s     = mysqli_prepare($conn, "UPDATE Bar_Setup SET Bar_Name=?, Bar_Img=?, Bar_Status=? WHERE Bar_Setup_ID_PK=?");
        mysqli_stmt_bind_param($s, "sssi", $name, $image, $stat, $id);
    } else {
        $s = mysqli_prepare($conn, "INSERT INTO Bar_Setup (Bar_Name, Bar_Img, Bar_Status) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($s, "sss", $name, $image, $stat);
    }
    mysqli_stmt_execute($s);
    header("Location: manageProducts.php?tab=setup");
    exit();
}

/* ─── BAR SETUP — DELETE ─── */
if (isset($_GET['delete_setup'])) {
    $sid = intval($_GET['delete_setup']);
    $r   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM Booking WHERE Bar_Setup_ID_FK=$sid"));
    if ($r['cnt'] > 0) {
        header("Location: manageProducts.php?tab=setup&setup_ref_error=1&setup_id=$sid");
        exit();
    }
    $s = mysqli_prepare($conn, "DELETE FROM Bar_Setup WHERE Bar_Setup_ID_PK=?");
    mysqli_stmt_bind_param($s, "i", $sid);
    mysqli_stmt_execute($s);
    header("Location: manageProducts.php?tab=setup");
    exit();
}

$editSetup = null;
if (isset($_GET['setup_edit_id'])) {
    $editSetup = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM Bar_Setup WHERE Bar_Setup_ID_PK=" . intval($_GET['setup_edit_id'])));
    $activeTab = 'setup';
}
$setupRefError = isset($_GET['setup_ref_error']) ? intval($_GET['setup_id']) : null;

$sStat    = !empty($_GET['sstat']) ? mysqli_real_escape_string($conn, $_GET['sstat']) : '';
$setupSQL = "SELECT * FROM Bar_Setup" . ($sStat ? " WHERE Bar_Status='$sStat'" : '');
$setups   = mysqli_query($conn, $setupSQL);

/* ─── MIRRORS — SAVE ─── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_mirror') {
    $id    = $_POST['edit_id'] ?? null;
    $name  = $_POST['Mirror_Name'];
    $stat  = $_POST['Mirror_Status'] ?? 'Available';
    $image = uploadImage($_FILES['Mirror_Img'] ?? []);
    if ($id) {
        $old   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM Selfie_Mirror WHERE Selfie_Mirror_ID_PK=$id"));
        $image = $image ?: $old['Mirror_Img'];
        $s     = mysqli_prepare($conn, "UPDATE Selfie_Mirror SET Mirror_Name=?, Mirror_Img=?, Mirror_Status=? WHERE Selfie_Mirror_ID_PK=?");
        mysqli_stmt_bind_param($s, "sssi", $name, $image, $stat, $id);
    } else {
        $s = mysqli_prepare($conn, "INSERT INTO Selfie_Mirror (Mirror_Name, Mirror_Img, Mirror_Status) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($s, "sss", $name, $image, $stat);
    }
    mysqli_stmt_execute($s);
    header("Location: manageProducts.php?tab=mirrors");
    exit();
}

/* ─── MIRRORS — DELETE ─── */
if (isset($_GET['delete_mirror'])) {
    $mid = intval($_GET['delete_mirror']);
    $r   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM Booking WHERE Selfie_Mirror_ID_FK=$mid"));
    if ($r['cnt'] > 0) {
        header("Location: manageProducts.php?tab=mirrors&mirror_ref_error=1&mirror_ref_id=$mid");
        exit();
    }
    $s = mysqli_prepare($conn, "DELETE FROM Selfie_Mirror WHERE Selfie_Mirror_ID_PK=?");
    mysqli_stmt_bind_param($s, "i", $mid);
    mysqli_stmt_execute($s);
    header("Location: manageProducts.php?tab=mirrors");
    exit();
}

$editMirror = null;
if (isset($_GET['mirror_edit_id'])) {
    $editMirror = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM Selfie_Mirror WHERE Selfie_Mirror_ID_PK=" . intval($_GET['mirror_edit_id'])));
    $activeTab  = 'mirrors';
}
$mirrorRefError = isset($_GET['mirror_ref_error']) ? intval($_GET['mirror_ref_id']) : null;

$mStat     = !empty($_GET['mstat']) ? mysqli_real_escape_string($conn, $_GET['mstat']) : '';
$mirrorSQL = "SELECT * FROM Selfie_Mirror" . ($mStat ? " WHERE Mirror_Status='$mStat'" : '');
$mirrors   = mysqli_query($conn, $mirrorSQL);

/* ─── PACKAGES — SAVE ─── */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_package') {
    $id    = $_POST['edit_id'] ?? null;
    $pname = $_POST['Package_Name'];
    $bid   = $_POST['Bottle_ID_FK'];
    $bots  = $_POST['No_of_Bottles'];
    $scts  = $_POST['No_of_Scent'];
    $price = $_POST['Price'];
    $stat  = $_POST['Package_Status'];
    $image = uploadImage($_FILES['Package_Img'] ?? []);
    if ($id) {
        $old   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM Packages WHERE Package_ID_PK=$id"));
        $image = $image ?: $old['Package_Img'];
        $s     = mysqli_prepare($conn, "UPDATE Packages SET Package_Name=?, Bottle_ID_FK=?, No_of_Bottles=?, No_of_Scent=?, Price=?, Package_Img=?, Package_Status=? WHERE Package_ID_PK=?");
        mysqli_stmt_bind_param($s, "siiidssi", $pname, $bid, $bots, $scts, $price, $image, $stat, $id);
    } else {
        $s = mysqli_prepare($conn, "INSERT INTO Packages (Package_Name, Bottle_ID_FK, No_of_Bottles, No_of_Scent, Price, Package_Img, Package_Status) VALUES (?, ?, ?, ?, ?, ?, ?)");
        mysqli_stmt_bind_param($s, "siiidss", $pname, $bid, $bots, $scts, $price, $image, $stat);
    }
    mysqli_stmt_execute($s);
    header("Location: manageProducts.php?tab=packages");
    exit();
}

/* ─── PACKAGES — DELETE ─── */
if (isset($_GET['delete_package'])) {
    $pkgid = intval($_GET['delete_package']);
    $r     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM Booking WHERE Package_ID_FK=$pkgid"));
    if ($r['cnt'] > 0) {
        header("Location: manageProducts.php?tab=packages&pkg_ref_error=1&pkg_ref_id=$pkgid");
        exit();
    }
    $s = mysqli_prepare($conn, "DELETE FROM Package_Ratings WHERE Package_ID_FK=?");
    mysqli_stmt_bind_param($s, "i", $pkgid);
    mysqli_stmt_execute($s);
    $s = mysqli_prepare($conn, "DELETE FROM Packages WHERE Package_ID_PK=?");
    mysqli_stmt_bind_param($s, "i", $pkgid);
    mysqli_stmt_execute($s);
    header("Location: manageProducts.php?tab=packages");
    exit();
}

$editPackage = null;
if (isset($_GET['pkg_edit_id'])) {
    $editPackage = mysqli_fetch_assoc(mysqli_query($conn, "SELECT * FROM Packages WHERE Package_ID_PK=" . intval($_GET['pkg_edit_id'])));
    $activeTab   = 'packages';
}
$pkgRefError = isset($_GET['pkg_ref_error']) ? intval($_GET['pkg_ref_id']) : null;

$pStat      = !empty($_GET['pkgstat']) ? mysqli_real_escape_string($conn, $_GET['pkgstat']) : '';
$pkgSQL     = "SELECT p.*, b.Bottle_Name FROM Packages p JOIN Bottle b ON p.Bottle_ID_FK=b.Bottle_ID_PK" . ($pStat ? " WHERE p.Package_Status='$pStat'" : '');
$packages   = mysqli_query($conn, $pkgSQL);
$bottleList  = mysqli_query($conn, "SELECT * FROM Bottle");
$bottleList2 = mysqli_query($conn, "SELECT * FROM Bottle");

function limitWords($t, $l = 15)
{
    $w = explode(" ", $t);
    return count($w) <= $l ? $t : implode(" ", array_slice($w, 0, $l)) . "...";
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jilz | Manage Products</title>
    <link rel="shortcut icon" href="assets/Logo_Tentative.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="adminProductsMobile.css">
</head>

<body class="adminBG">

    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <div class="mobile-topbar">
        <div class="mobile-topbar-brand">
            <img src="assets/Logo_Tentative.png" alt="Jilz Logo">
            <span>Admin</span>
        </div>
        <button class="burger-btn" id="burgerBtn" aria-label="Open menu" aria-expanded="false">
            <span class="burger-line"></span>
            <span class="burger-line"></span>
            <span class="burger-line"></span>
        </button>
    </div>

    <div class="asidebar" id="adminSidebar">
        <h1 style="margin-bottom:8vw;">Admin</h1>
        <div class="roww">
            <div id="adminnameemail">
                <h3><?= htmlspecialchars($adminUsername); ?></h3>
            </div>
        </div>
        <hr>
        <ul>
            <li><a href="bookingconfirmation.php">Manage Bookings</a></li>
            <li><a href="eventCalendar.php">Event Calendar</a></li>
            <li><a href="manageProducts.php">Manage Offerings</a></li>
            <li><a href="manageGallery.php">Manage Gallery</a></li>
            <li><a href="ratingsfilter.php">Manage Reviews</a></li>
            <li><a href="addAdmin.php">Add Admin</a></li>
            <li><a href="#" onclick="openLogoutModal()">Logout</a></li>
        </ul>
    </div>

    <div class="ainfocontainer">
        <h1 style="font-size:2rem; margin-bottom:1.5rem;">Manage Offerings</h1>

        <nav class="prod-nav">
            <button class="prod-tab <?= $activeTab === 'perfumes' ? 'active' : '' ?>" onclick="switchTab('perfumes')">
                <span class="material-icons">spa</span> Perfumes
            </button>
            <button class="prod-tab <?= $activeTab === 'bottles' ? 'active' : '' ?>" onclick="switchTab('bottles')">
                <span class="material-icons">science</span> Bottles
            </button>
            <button class="prod-tab <?= $activeTab === 'setup' ? 'active' : '' ?>" onclick="switchTab('setup')">
                <span class="material-icons">table_restaurant</span> Bar Setup
            </button>
            <button class="prod-tab <?= $activeTab === 'mirrors' ? 'active' : '' ?>" onclick="switchTab('mirrors')">
                <span class="material-icons">face_retouching_natural</span> Mirrors
            </button>
            <button class="prod-tab <?= $activeTab === 'packages' ? 'active' : '' ?>" onclick="switchTab('packages')">
                <span class="material-icons">inventory_2</span> Packages
            </button>
        </nav>

        <!-- ══ PERFUMES ══ -->
        <div id="panel-perfumes" class="prod-panel <?= $activeTab === 'perfumes' ? 'active' : '' ?>">
            <div class="section-header">
                <h2>Perfumes</h2>
                <div class="section-actions">
                    <div class="filter-wrap">
                        <button class="filter-btn"><span class="material-icons" style="font-size:0.9rem">tune</span> Category</button>
                        <div class="filter-menu">
                            <a href="?tab=perfumes">All</a>
                            <a href="?tab=perfumes&pcat=Male">Male</a>
                            <a href="?tab=perfumes&pcat=Female">Female</a>
                            <a href="?tab=perfumes&pcat=Unisex">Unisex</a>
                        </div>
                    </div>
                    <div class="filter-wrap">
                        <button class="filter-btn"><span class="material-icons" style="font-size:0.9rem">filter_list</span> Status</button>
                        <div class="filter-menu">
                            <a href="?tab=perfumes">All</a>
                            <a href="?tab=perfumes&pstat=Available">Available</a>
                            <a href="?tab=perfumes&pstat=Unavailable">Unavailable</a>
                        </div>
                    </div>
                    <button class="btn-add" onclick="openModal('modal-add-perfume')">
                        <span class="material-icons">add</span> Add Perfume
                    </button>
                </div>
            </div>
            <div class="table-wrap scroll-table">
                <table>
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Background</th>
                            <th>Color</th>
                            <th>Inspired By</th>
                            <th>Description</th>
                            <th>Category</th>
                            <th>Status</th>
                            <th>Edit</th>
                            <th>Delete</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($perfumes)): ?>
                            <tr>
                                <td data-label="Image">
                                    <img src="<?= htmlspecialchars($row['Perfume_Img']) ?>" style="max-width:7rem;max-height:7rem;border-radius:6px;">
                                </td>
                                <td data-label="Background">
                                    <?php if (!empty($row['Perfume_Background'])): ?>
                                        <img src="<?= htmlspecialchars($row['Perfume_Background']) ?>" style="max-width:7rem;max-height:7rem;border-radius:6px;">
                                    <?php else: ?><span style="color:#aaa;">—</span><?php endif; ?>
                                </td>
                                <td data-label="Color">
                                    <?php if (!empty($row['Perfume_Color'])): ?>
                                        <span style="display:inline-block;width:2rem;height:2rem;background:<?= htmlspecialchars($row['Perfume_Color']) ?>;border-radius:4px;border:1px solid #ccc;" title="<?= htmlspecialchars($row['Perfume_Color']) ?>"></span>
                                    <?php else: ?><span style="color:#aaa;">—</span><?php endif; ?>
                                </td>
                                <td data-label="Name"><?= htmlspecialchars($row['Inspired_Scent']) ?></td>
                                <td data-label="Description"><?= limitWords($row['Perfume_Description']) ?></td>
                                <td data-label="Category"><?= $row['Perfume_Category'] ?></td>
                                <td data-label="Status"><span data-status="<?= $row['Perfume_Status'] ?>"><?= $row['Perfume_Status'] ?></span></td>
                                <td data-label="Edit">
                                    <a href="?tab=perfumes&perf_edit_id=<?= $row['Perfume_ID_PK'] ?>" class="btn-icon edit">
                                        <span class="material-icons">edit</span>
                                    </a>
                                </td>
                                <td data-label="Delete">
                                    <button class="btn-icon delete" onclick="openDelPerf(<?= $row['Perfume_ID_PK'] ?>,'<?= htmlspecialchars($row['Inspired_Scent']) ?>',<?= $perfRefError == $row['Perfume_ID_PK'] ? 'true' : 'false' ?>)">
                                        <span class="material-icons">delete</span>
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ══ BOTTLES ══ -->
        <div id="panel-bottles" class="prod-panel <?= $activeTab === 'bottles' ? 'active' : '' ?>">
            <div class="bottle-cols">
                <div class="bottle-mini-tabs">
                    <button class="bottle-mini-tab active" onclick="switchBottleTab('bottles',this)">Bottles</button>
                    <button class="bottle-mini-tab" onclick="switchBottleTab('variants',this)">Variants</button>
                </div>

                <div class="bottle-section active" id="bottle-sec-bottles">
                    <div class="section-header">
                        <h2>Bottles</h2>
                        <div class="section-actions">
                            <div class="filter-wrap">
                                <button class="filter-btn"><span class="material-icons" style="font-size:0.9rem">filter_list</span> Status</button>
                                <div class="filter-menu">
                                    <a href="?tab=bottles">All</a>
                                    <a href="?tab=bottles&bstat=Available">Available</a>
                                    <a href="?tab=bottles&bstat=Unavailable">Unavailable</a>
                                </div>
                            </div>
                            <button class="btn-add" onclick="openModal('modal-add-bottle')"><span class="material-icons">add</span> Add</button>
                        </div>
                    </div>
                    <div class="table-wrap scroll-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Size</th>
                                    <th>Status</th>
                                    <th>Edit</th>
                                    <th>Delete</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = mysqli_fetch_assoc($bottles)): ?>
                                    <tr>
                                        <td data-label="Name"><?= htmlspecialchars($row['Bottle_Name']) ?></td>
                                        <td data-label="Size"><?= $row['Bottle_Size'] ?> ml</td>
                                        <td data-label="Status"><span data-status="<?= $row['Bottle_Status'] ?>"><?= $row['Bottle_Status'] ?></span></td>
                                        <td data-label="Edit">
                                            <a href="?tab=bottles&bottle_edit_id=<?= $row['Bottle_ID_PK'] ?>" class="btn-icon edit"><span class="material-icons">edit</span></a>
                                        </td>
                                        <td data-label="Delete">
                                            <button class="btn-icon delete" onclick="openDelBottle(<?= $row['Bottle_ID_PK'] ?>,'<?= htmlspecialchars($row['Bottle_Name']) ?>',<?= $bottleRefError == $row['Bottle_ID_PK'] ? 'true' : 'false' ?>)">
                                                <span class="material-icons">delete</span>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="bottle-section" id="bottle-sec-variants">
                    <div class="section-header">
                        <h2>Variants</h2>
                        <div class="section-actions">
                            <div class="filter-wrap">
                                <button class="filter-btn"><span class="material-icons" style="font-size:0.9rem">filter_list</span> Status</button>
                                <div class="filter-menu">
                                    <a href="?tab=bottles">All</a>
                                    <a href="?tab=bottles&vstat=Available">Available</a>
                                    <a href="?tab=bottles&vstat=Unavailable">Unavailable</a>
                                </div>
                            </div>
                            <button class="btn-add" onclick="openModal('modal-add-variant')"><span class="material-icons">add</span> Add</button>
                        </div>
                    </div>
                    <div class="table-wrap scroll-table">
                        <table>
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Size (ml)</th>
                                    <th>Image</th>
                                    <th>Status</th>
                                    <th>Edit</th>
                                    <th>Delete</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = mysqli_fetch_assoc($variants)): ?>
                                    <tr>
                                        <td data-label="Name"><?= htmlspecialchars($row['Bottle_Var_Name']) ?></td>
                                        <td data-label="Size"><?= htmlspecialchars($row['Bottle_Size']) ?> ml</td>
                                        <td data-label="Image">
                                            <?php if ($row['Bottle_Img']): ?>
                                                <img src="<?= htmlspecialchars($row['Bottle_Img']) ?>" style="max-width:7rem;max-height:7rem;border-radius:5px;">
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="Status"><span data-status="<?= $row['Bottle_Var_Status'] ?>"><?= $row['Bottle_Var_Status'] ?></span></td>
                                        <td data-label="Edit">
                                            <a href="?tab=bottles&variant_edit_id=<?= $row['Bottle_Var_ID_PK'] ?>" class="btn-icon edit"><span class="material-icons">edit</span></a>
                                        </td>
                                        <td data-label="Delete">
                                            <button class="btn-icon delete" onclick="openDelVariant(<?= $row['Bottle_Var_ID_PK'] ?>,'<?= htmlspecialchars($row['Bottle_Var_Name']) ?>',<?= $variantRefError == $row['Bottle_Var_ID_PK'] ? 'true' : 'false' ?>)">
                                                <span class="material-icons">delete</span>
                                            </button>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- ══ BAR SETUP ══ -->
        <div id="panel-setup" class="prod-panel <?= $activeTab === 'setup' ? 'active' : '' ?>">
            <div class="section-header">
                <h2>Bar Setup</h2>
                <div class="section-actions">
                    <div class="filter-wrap">
                        <button class="filter-btn"><span class="material-icons" style="font-size:0.9rem">filter_list</span> Status</button>
                        <div class="filter-menu">
                            <a href="?tab=setup">All</a>
                            <a href="?tab=setup&sstat=Available">Available</a>
                            <a href="?tab=setup&sstat=Unavailable">Unavailable</a>
                        </div>
                    </div>
                    <button class="btn-add" onclick="openModal('modal-add-setup')"><span class="material-icons">add</span> Add Setup</button>
                </div>
            </div>
            <div class="table-wrap scroll-table">
                <table>
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Name</th>
                            <th>Status</th>
                            <th>Edit</th>
                            <th>Delete</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($setups)): ?>
                            <tr>
                                <td data-label="Image"><img src="<?= htmlspecialchars($row['Bar_Img']) ?>" style="max-width:7rem;max-height:7rem;border-radius:6px;"></td>
                                <td data-label="Name"><?= htmlspecialchars($row['Bar_Name']) ?></td>
                                <td data-label="Status"><span data-status="<?= $row['Bar_Status'] ?>"><?= $row['Bar_Status'] ?? 'Available' ?></span></td>
                                <td data-label="Edit">
                                    <a href="?tab=setup&setup_edit_id=<?= $row['Bar_Setup_ID_PK'] ?>" class="btn-icon edit"><span class="material-icons">edit</span></a>
                                </td>
                                <td data-label="Delete">
                                    <button class="btn-icon delete" onclick="openDelSetup(<?= $row['Bar_Setup_ID_PK'] ?>,'<?= htmlspecialchars($row['Bar_Name']) ?>',<?= $setupRefError == $row['Bar_Setup_ID_PK'] ? 'true' : 'false' ?>)">
                                        <span class="material-icons">delete</span>
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ══ MIRRORS ══ -->
        <div id="panel-mirrors" class="prod-panel <?= $activeTab === 'mirrors' ? 'active' : '' ?>">
            <div class="section-header">
                <h2>Selfie Mirrors</h2>
                <div class="section-actions">
                    <div class="filter-wrap">
                        <button class="filter-btn"><span class="material-icons" style="font-size:0.9rem">filter_list</span> Status</button>
                        <div class="filter-menu">
                            <a href="?tab=mirrors">All</a>
                            <a href="?tab=mirrors&mstat=Available">Available</a>
                            <a href="?tab=mirrors&mstat=Unavailable">Unavailable</a>
                        </div>
                    </div>
                    <button class="btn-add" onclick="openModal('modal-add-mirror')"><span class="material-icons">add</span> Add Mirror</button>
                </div>
            </div>
            <div class="table-wrap scroll-table">
                <table>
                    <thead>
                        <tr>
                            <th>Image</th>
                            <th>Name</th>
                            <th>Status</th>
                            <th>Edit</th>
                            <th>Delete</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($mirrors)): ?>
                            <tr>
                                <td data-label="Image"><img src="<?= htmlspecialchars($row['Mirror_Img']) ?>" style="max-width:7rem;max-height:7rem;border-radius:6px;"></td>
                                <td data-label="Name"><?= htmlspecialchars($row['Mirror_Name']) ?></td>
                                <td data-label="Status"><span data-status="<?= $row['Mirror_Status'] ?>"><?= $row['Mirror_Status'] ?></span></td>
                                <td data-label="Edit">
                                    <a href="?tab=mirrors&mirror_edit_id=<?= $row['Selfie_Mirror_ID_PK'] ?>" class="btn-icon edit"><span class="material-icons">edit</span></a>
                                </td>
                                <td data-label="Delete">
                                    <button class="btn-icon delete" onclick="openDelMirror(<?= $row['Selfie_Mirror_ID_PK'] ?>,'<?= htmlspecialchars($row['Mirror_Name']) ?>',<?= $mirrorRefError == $row['Selfie_Mirror_ID_PK'] ? 'true' : 'false' ?>)">
                                        <span class="material-icons">delete</span>
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- ══ PACKAGES ══ -->
        <div id="panel-packages" class="prod-panel <?= $activeTab === 'packages' ? 'active' : '' ?>">
            <div class="section-header">
                <h2>Packages</h2>
                <div class="section-actions">
                    <div class="filter-wrap">
                        <button class="filter-btn"><span class="material-icons" style="font-size:0.9rem">filter_list</span> Status</button>
                        <div class="filter-menu">
                            <a href="?tab=packages">All</a>
                            <a href="?tab=packages&pkgstat=Available">Available</a>
                            <a href="?tab=packages&pkgstat=Unavailable">Unavailable</a>
                        </div>
                    </div>
                    <button class="btn-add" onclick="openModal('modal-add-package')"><span class="material-icons">add</span> Add Package</button>
                </div>
            </div>
            <div class="table-wrap scroll-table">
                <table>
                    <thead>
                        <tr>
                            <th>Package</th>
                            <th>Bottle</th>
                            <th>Bottles</th>
                            <th>Scents</th>
                            <th>Price</th>
                            <th>Status</th>
                            <th>Edit</th>
                            <th>Delete</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($packages)): ?>
                            <tr>
                                <td data-label="Package"><?= htmlspecialchars($row['Package_Name']) ?></td>
                                <td data-label="Bottle"><?= htmlspecialchars($row['Bottle_Name']) ?></td>
                                <td data-label="Bottles"><?= $row['No_of_Bottles'] ?></td>
                                <td data-label="Scents"><?= $row['No_of_Scent'] ?></td>
                                <td data-label="Price">₱<?= number_format($row['Price'], 2) ?></td>
                                <td data-label="Status"><span data-status="<?= $row['Package_Status'] ?>"><?= $row['Package_Status'] ?></span></td>
                                <td data-label="Edit">
                                    <a href="?tab=packages&pkg_edit_id=<?= $row['Package_ID_PK'] ?>" class="btn-icon edit"><span class="material-icons">edit</span></a>
                                </td>
                                <td data-label="Delete">
                                    <button class="btn-icon delete" onclick="openDelPackage(<?= $row['Package_ID_PK'] ?>,'<?= htmlspecialchars($row['Package_Name']) ?>',<?= $pkgRefError == $row['Package_ID_PK'] ? 'true' : 'false' ?>)">
                                        <span class="material-icons">delete</span>
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

    </div><!-- /ainfocontainer -->


    <!-- ════ ADD / EDIT MODALS ════ -->

    <!-- Add Perfume -->
    <div id="modal-add-perfume" class="modal-overlay">
        <div class="modal-box">
            <button class="modal-close" onclick="closeModal('modal-add-perfume')">&times;</button>
            <h2>Add Perfume</h2>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save_perfume">
                <hr>
                <label>Perfume Name</label><input type="text" name="perfumeName" required>
                <label>Description</label><textarea name="perfumeDescription" required></textarea>
                <label>Perfume Image</label><input type="file" name="image1" accept="image/*">
                <label>Background Image</label><input type="file" name="image2" accept="image/*">
                <label>Perfume Color</label><input type="color" name="image3">
                <div class="forrow">
                    <div>
                        <label>Category</label>
                        <select name="perfumeCategory">
                            <option>Male</option>
                            <option>Female</option>
                            <option>Unisex</option>
                        </select>
                    </div>
                    <div>
                        <label>Status</label>
                        <select name="perfumeStatus">
                            <option>Available</option>
                            <option>Unavailable</option>
                        </select>
                    </div>
                </div>
                <hr>
                <button type="submit" class="btn-submit">Save Perfume</button>
            </form>
        </div>
    </div>

    <!-- Edit Perfume -->
    <div id="modal-edit-perfume" class="modal-overlay <?= $editPerf ? 'open' : '' ?>">
        <div class="modal-box">
            <button class="modal-close" onclick="closeEditPerf()">&times;</button>
            <h2>Edit Perfume</h2>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save_perfume">
                <input type="hidden" name="edit_id" value="<?= $editPerf['Perfume_ID_PK'] ?? '' ?>">
                <hr>
                <label>Perfume Name</label>
                <input type="text" name="perfumeName" value="<?= htmlspecialchars($editPerf['Inspired_Scent'] ?? '') ?>" required>
                <label>Description</label>
                <textarea name="perfumeDescription" required><?= htmlspecialchars($editPerf['Perfume_Description'] ?? '') ?></textarea>
                <label>Perfume Image</label>
                <?php if (!empty($editPerf['Perfume_Img'])): ?>
                    <img src="<?= htmlspecialchars($editPerf['Perfume_Img']) ?>" style="display:block;max-width:8rem;max-height:8rem;border-radius:6px;margin-bottom:0.4rem;" id="editPerfImgPrev">
                <?php endif; ?>
                <input type="file" name="image1" accept="image/*" onchange="previewImg(this,'editPerfImgPrev')">
                <label>Background Image</label>
                <?php if (!empty($editPerf['Perfume_Background'])): ?>
                    <img src="<?= htmlspecialchars($editPerf['Perfume_Background']) ?>" style="display:block;max-width:8rem;max-height:8rem;border-radius:6px;margin-bottom:0.4rem;" id="editPerfBgPrev">
                <?php endif; ?>
                <input type="file" name="image2" accept="image/*" onchange="previewImg(this,'editPerfBgPrev')">
                <label>Perfume Color</label>
                <input type="color" name="image3" value="<?= htmlspecialchars($editPerf['Perfume_Color'] ?? '#000000') ?>">
                <div class="forrow">
                    <div>
                        <label>Category</label>
                        <select name="perfumeCategory">
                            <?php foreach (['Male', 'Female', 'Unisex'] as $c): ?>
                                <option <?= (isset($editPerf['Perfume_Category']) && $editPerf['Perfume_Category'] == $c) ? 'selected' : '' ?>><?= $c ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div>
                        <label>Status</label>
                        <select name="perfumeStatus">
                            <option <?= (isset($editPerf['Perfume_Status']) && $editPerf['Perfume_Status'] == 'Available') ? 'selected' : '' ?>>Available</option>
                            <option <?= (isset($editPerf['Perfume_Status']) && $editPerf['Perfume_Status'] == 'Unavailable') ? 'selected' : '' ?>>Unavailable</option>
                        </select>
                    </div>
                </div>
                <hr>
                <button type="submit" class="btn-submit">Update Perfume</button>
            </form>
        </div>
    </div>

    <!-- Add Bottle -->
    <div id="modal-add-bottle" class="modal-overlay">
        <div class="modal-box">
            <button class="modal-close" onclick="closeModal('modal-add-bottle')">&times;</button>
            <h2>Add Bottle</h2>
            <form method="POST">
                <input type="hidden" name="action" value="add_bottle">
                <hr>
                <label>Bottle Name</label><input type="text" name="Bottle_Name" required>
                <label>Size (ml)</label><input type="number" name="Bottle_Size" required>
                <label>Status</label>
                <select name="Bottle_Status">
                    <option>Available</option>
                    <option>Unavailable</option>
                </select>
                <hr>
                <button type="submit" class="btn-submit">Save Bottle</button>
            </form>
        </div>
    </div>

    <!-- Edit Bottle -->
    <div id="modal-edit-bottle" class="modal-overlay <?= $editBottle ? 'open' : '' ?>">
        <div class="modal-box">
            <button class="modal-close" onclick="closeEditBottle()">&times;</button>
            <h2>Edit Bottle</h2>
            <form method="POST">
                <input type="hidden" name="action" value="edit_bottle">
                <input type="hidden" name="edit_id" value="<?= $editBottle['Bottle_ID_PK'] ?? '' ?>">
                <hr>
                <label>Bottle Name</label><input type="text" name="Bottle_Name" value="<?= htmlspecialchars($editBottle['Bottle_Name'] ?? '') ?>">
                <label>Size (ml)</label><input type="number" name="Bottle_Size" value="<?= $editBottle['Bottle_Size'] ?? '' ?>">
                <label>Status</label>
                <select name="Bottle_Status">
                    <option <?= (isset($editBottle['Bottle_Status']) && $editBottle['Bottle_Status'] == 'Available') ? 'selected' : '' ?>>Available</option>
                    <option <?= (isset($editBottle['Bottle_Status']) && $editBottle['Bottle_Status'] == 'Unavailable') ? 'selected' : '' ?>>Unavailable</option>
                </select>
                <small style="color:#888;display:block;margin-top:0.3rem;">Setting to <strong>Unavailable</strong> will also mark all variants as Unavailable.</small>
                <hr>
                <button type="submit" class="btn-submit">Update Bottle</button>
            </form>
        </div>
    </div>

    <!-- Add Variant -->
    <div id="modal-add-variant" class="modal-overlay">
        <div class="modal-box">
            <button class="modal-close" onclick="closeModal('modal-add-variant')">&times;</button>
            <h2>Add Variant</h2>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_variant">
                <hr>
                <label>Variant Name</label><input type="text" name="Bottle_Var_Name" required>
                <label>Select Bottle</label>
                <select name="Bottle_ID_FK">
                    <?php $bl = mysqli_query($conn, "SELECT * FROM Bottle");
                    while ($b = mysqli_fetch_assoc($bl)): ?>
                        <option value="<?= $b['Bottle_ID_PK'] ?>"><?= htmlspecialchars($b['Bottle_Name']) ?></option>
                    <?php endwhile; ?>
                </select>
                <label>Image</label>
                <input type="file" id="addVarImg" name="Bottle_Img" accept="image/*" onchange="previewImg(this,'addVarPrev')">
                <img id="addVarPrev" class="img-preview">
                <label>Status</label>
                <select name="Bottle_Var_Status">
                    <option>Available</option>
                    <option>Unavailable</option>
                </select>
                <hr>
                <button type="submit" class="btn-submit">Save Variant</button>
            </form>
        </div>
    </div>

    <!-- Edit Variant -->
    <div id="modal-edit-variant" class="modal-overlay <?= $editVariant ? 'open' : '' ?>">
        <div class="modal-box">
            <button class="modal-close" onclick="closeEditVariant()">&times;</button>
            <h2>Edit Variant</h2>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="edit_variant">
                <input type="hidden" name="variant_edit_id" value="<?= $editVariant['Bottle_Var_ID_PK'] ?? '' ?>">
                <hr>
                <label>Variant Name</label>
                <input type="text" name="Bottle_Var_Name" value="<?= htmlspecialchars($editVariant['Bottle_Var_Name'] ?? '') ?>">
                <label>Image</label>
                <input type="file" name="Bottle_Img" accept="image/*" onchange="previewImg(this,'editVarPrev')">
                <img id="editVarPrev" class="img-preview" <?= !empty($editVariant['Bottle_Img']) ? 'src="' . htmlspecialchars($editVariant['Bottle_Img']) . '" style="display:block;"' : '' ?>>
                <label>Status</label>
                <select name="Bottle_Var_Status">
                    <option <?= (isset($editVariant['Bottle_Var_Status']) && $editVariant['Bottle_Var_Status'] == 'Available') ? 'selected' : '' ?>>Available</option>
                    <option <?= (isset($editVariant['Bottle_Var_Status']) && $editVariant['Bottle_Var_Status'] == 'Unavailable') ? 'selected' : '' ?>>Unavailable</option>
                </select>
                <hr>
                <button type="submit" class="btn-submit">Update Variant</button>
            </form>
        </div>
    </div>

    <!-- Add Bar Setup -->
    <div id="modal-add-setup" class="modal-overlay">
        <div class="modal-box">
            <button class="modal-close" onclick="closeModal('modal-add-setup')">&times;</button>
            <h2>Add Bar Setup</h2>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save_setup">
                <hr>
                <label>Bar Name</label><input type="text" name="Bar_Name" required>
                <label>Image</label>
                <input type="file" name="Bar_Img" accept="image/*" onchange="previewImg(this,'addSetupPrev')">
                <img id="addSetupPrev" class="img-preview">
                <label>Status</label>
                <select name="Bar_Status">
                    <option>Available</option>
                    <option>Unavailable</option>
                </select>
                <hr>
                <button type="submit" class="btn-submit">Save Setup</button>
            </form>
        </div>
    </div>

    <!-- Edit Bar Setup -->
    <div id="modal-edit-setup" class="modal-overlay <?= $editSetup ? 'open' : '' ?>">
        <div class="modal-box">
            <button class="modal-close" onclick="closeEditSetup()">&times;</button>
            <h2>Edit Bar Setup</h2>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save_setup">
                <input type="hidden" name="edit_id" value="<?= $editSetup['Bar_Setup_ID_PK'] ?? '' ?>">
                <hr>
                <label>Bar Name</label>
                <input type="text" name="Bar_Name" value="<?= htmlspecialchars($editSetup['Bar_Name'] ?? '') ?>" required>
                <label>Image</label>
                <input type="file" name="Bar_Img" accept="image/*" onchange="previewImg(this,'editSetupPrev')">
                <img id="editSetupPrev" class="img-preview" <?= !empty($editSetup['Bar_Img']) ? 'src="' . htmlspecialchars($editSetup['Bar_Img']) . '" style="display:block;"' : '' ?>>
                <label>Status</label>
                <select name="Bar_Status">
                    <option <?= (isset($editSetup['Bar_Status']) && $editSetup['Bar_Status'] == 'Available') ? 'selected' : '' ?>>Available</option>
                    <option <?= (isset($editSetup['Bar_Status']) && $editSetup['Bar_Status'] == 'Unavailable') ? 'selected' : '' ?>>Unavailable</option>
                </select>
                <hr>
                <button type="submit" class="btn-submit">Update Setup</button>
            </form>
        </div>
    </div>

    <!-- Add Mirror -->
    <div id="modal-add-mirror" class="modal-overlay">
        <div class="modal-box">
            <button class="modal-close" onclick="closeModal('modal-add-mirror')">&times;</button>
            <h2>Add Selfie Mirror</h2>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save_mirror">
                <hr>
                <label>Mirror Name</label><input type="text" name="Mirror_Name" required>
                <label>Image</label>
                <input type="file" name="Mirror_Img" accept="image/*" onchange="previewImg(this,'addMirrorPrev')">
                <img id="addMirrorPrev" class="img-preview">
                <label>Status</label>
                <select name="Mirror_Status">
                    <option>Available</option>
                    <option>Unavailable</option>
                </select>
                <hr>
                <button type="submit" class="btn-submit">Save Mirror</button>
            </form>
        </div>
    </div>

    <!-- Edit Mirror -->
    <div id="modal-edit-mirror" class="modal-overlay <?= $editMirror ? 'open' : '' ?>">
        <div class="modal-box">
            <button class="modal-close" onclick="closeEditMirror()">&times;</button>
            <h2>Edit Selfie Mirror</h2>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save_mirror">
                <input type="hidden" name="edit_id" value="<?= $editMirror['Selfie_Mirror_ID_PK'] ?? '' ?>">
                <hr>
                <label>Mirror Name</label>
                <input type="text" name="Mirror_Name" value="<?= htmlspecialchars($editMirror['Mirror_Name'] ?? '') ?>" required>
                <label>Image</label>
                <input type="file" name="Mirror_Img" accept="image/*" onchange="previewImg(this,'editMirrorPrev')">
                <img id="editMirrorPrev" class="img-preview" <?= !empty($editMirror['Mirror_Img']) ? 'src="' . htmlspecialchars($editMirror['Mirror_Img']) . '" style="display:block;"' : '' ?>>
                <label>Status</label>
                <select name="Mirror_Status">
                    <option <?= (isset($editMirror['Mirror_Status']) && $editMirror['Mirror_Status'] == 'Available') ? 'selected' : '' ?>>Available</option>
                    <option <?= (isset($editMirror['Mirror_Status']) && $editMirror['Mirror_Status'] == 'Unavailable') ? 'selected' : '' ?>>Unavailable</option>
                </select>
                <hr>
                <button type="submit" class="btn-submit">Update Mirror</button>
            </form>
        </div>
    </div>

    <!-- Add Package -->
    <div id="modal-add-package" class="modal-overlay">
        <div class="modal-box">
            <button class="modal-close" onclick="closeModal('modal-add-package')">&times;</button>
            <h2>Add Package</h2>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save_package">
                <hr>
                <label>Package Name</label><input type="text" name="Package_Name" required>
                <label>Image</label>
                <input type="file" name="Package_Img" accept="image/*" onchange="previewImg(this,'addPkgPrev')">
                <img id="addPkgPrev" class="img-preview">
                <label>Bottle</label>
                <select name="Bottle_ID_FK">
                    <?php while ($b = mysqli_fetch_assoc($bottleList)): ?>
                        <option value="<?= $b['Bottle_ID_PK'] ?>"><?= htmlspecialchars($b['Bottle_Name']) ?></option>
                    <?php endwhile; ?>
                </select>
                <div class="forrow">
                    <div><label>No. of Bottles</label><input type="number" min="1" max="200" name="No_of_Bottles" required></div>
                    <div><label>No. of Scents</label><input type="number" min="1" max="200" name="No_of_Scent" required></div>
                </div>
                <div class="forrow">
                    <div><label>Price</label><input type="number" step="0.01" name="Price" required></div>
                    <div>
                        <label>Status</label>
                        <select name="Package_Status">
                            <option>Available</option>
                            <option>Unavailable</option>
                        </select>
                    </div>
                </div>
                <hr>
                <button type="submit" class="btn-submit">Save Package</button>
            </form>
        </div>
    </div>

    <!-- Edit Package -->
    <div id="modal-edit-package" class="modal-overlay <?= $editPackage ? 'open' : '' ?>">
        <div class="modal-box">
            <button class="modal-close" onclick="closeEditPackage()">&times;</button>
            <h2>Edit Package</h2>
            <form method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="save_package">
                <input type="hidden" name="edit_id" value="<?= $editPackage['Package_ID_PK'] ?? '' ?>">
                <hr>
                <label>Package Name</label>
                <input type="text" name="Package_Name" value="<?= htmlspecialchars($editPackage['Package_Name'] ?? '') ?>" required>
                <label>Image</label>
                <input type="file" name="Package_Img" accept="image/*" onchange="previewImg(this,'editPkgPrev')">
                <img id="editPkgPrev" class="img-preview" <?= !empty($editPackage['Package_Img']) ? 'src="' . htmlspecialchars($editPackage['Package_Img']) . '" style="display:block;"' : '' ?>>
                <label>Bottle</label>
                <select name="Bottle_ID_FK">
                    <?php while ($b = mysqli_fetch_assoc($bottleList2)): ?>
                        <option value="<?= $b['Bottle_ID_PK'] ?>" <?= (isset($editPackage['Bottle_ID_FK']) && $editPackage['Bottle_ID_FK'] == $b['Bottle_ID_PK']) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($b['Bottle_Name']) ?>
                        </option>
                    <?php endwhile; ?>
                </select>
                <div class="forrow">
                    <div><label>No. of Bottles</label><input type="number" min="1" max="200" name="No_of_Bottles" value="<?= $editPackage['No_of_Bottles'] ?? '' ?>" required></div>
                    <div><label>No. of Scents</label><input type="number" min="1" max="200" name="No_of_Scent" value="<?= $editPackage['No_of_Scent'] ?? '' ?>" required></div>
                </div>
                <div class="forrow">
                    <div><label>Price</label><input type="number" step="0.01" name="Price" value="<?= $editPackage['Price'] ?? '' ?>" required></div>
                    <div>
                        <label>Status</label>
                        <select name="Package_Status">
                            <option <?= (isset($editPackage['Package_Status']) && $editPackage['Package_Status'] == 'Available') ? 'selected' : '' ?>>Available</option>
                            <option <?= (isset($editPackage['Package_Status']) && $editPackage['Package_Status'] == 'Unavailable') ? 'selected' : '' ?>>Unavailable</option>
                        </select>
                    </div>
                </div>
                <hr>
                <button type="submit" class="btn-submit">Update Package</button>
            </form>
        </div>
    </div>


    <!-- ════ DELETE CONFIRM MODALS ════ -->

    <div id="del-perf" class="modal-overlay del-modal">
        <div class="modal-box">
            <h3 id="delPerfTitle">Confirm Deletion</h3>
            <p id="delPerfMsg"></p>
            <div class="del-btns">
                <form action="manageProducts.php" method="GET">
                    <input type="hidden" name="delete_perf" id="del-perf-id">
                    <input type="hidden" name="tab" value="perfumes">
                    <button type="submit" class="btn-confirm" id="delPerfConfirm">Confirm</button>
                </form>
                <button class="btn-cancel" onclick="closeModal('del-perf')">Cancel</button>
            </div>
        </div>
    </div>

    <div id="del-bottle" class="modal-overlay del-modal">
        <div class="modal-box">
            <h3 id="delBottleTitle">Confirm Deletion</h3>
            <p id="delBottleMsg"></p>
            <div class="del-btns">
                <form action="manageProducts.php" method="GET">
                    <input type="hidden" name="delete_bottle" id="del-bottle-id">
                    <input type="hidden" name="tab" value="bottles">
                    <button type="submit" class="btn-confirm" id="delBottleConfirm">Confirm</button>
                </form>
                <button class="btn-cancel" onclick="closeModal('del-bottle')">Cancel</button>
            </div>
        </div>
    </div>

    <div id="del-variant" class="modal-overlay del-modal">
        <div class="modal-box">
            <h3 id="delVarTitle">Confirm Deletion</h3>
            <p id="delVarMsg"></p>
            <div class="del-btns">
                <form action="manageProducts.php" method="GET">
                    <input type="hidden" name="delete_variant" id="del-var-id">
                    <input type="hidden" name="tab" value="bottles">
                    <button type="submit" class="btn-confirm" id="delVarConfirm">Confirm</button>
                </form>
                <button class="btn-cancel" onclick="closeModal('del-variant')">Cancel</button>
            </div>
        </div>
    </div>

    <div id="del-setup" class="modal-overlay del-modal">
        <div class="modal-box">
            <h3 id="delSetupTitle">Confirm Deletion</h3>
            <p id="delSetupMsg"></p>
            <div class="del-btns">
                <form action="manageProducts.php" method="GET">
                    <input type="hidden" name="delete_setup" id="del-setup-id">
                    <input type="hidden" name="tab" value="setup">
                    <button type="submit" class="btn-confirm" id="delSetupConfirm">Confirm</button>
                </form>
                <button class="btn-cancel" onclick="closeModal('del-setup')">Cancel</button>
            </div>
        </div>
    </div>

    <div id="del-mirror" class="modal-overlay del-modal">
        <div class="modal-box">
            <h3 id="delMirrorTitle">Confirm Deletion</h3>
            <p id="delMirrorMsg"></p>
            <div class="del-btns">
                <form action="manageProducts.php" method="GET">
                    <input type="hidden" name="delete_mirror" id="del-mirror-id">
                    <input type="hidden" name="tab" value="mirrors">
                    <button type="submit" class="btn-confirm" id="delMirrorConfirm">Confirm</button>
                </form>
                <button class="btn-cancel" onclick="closeModal('del-mirror')">Cancel</button>
            </div>
        </div>
    </div>

    <div id="del-package" class="modal-overlay del-modal">
        <div class="modal-box">
            <h3 id="delPkgTitle">Confirm Deletion</h3>
            <p id="delPkgMsg"></p>
            <div class="del-btns">
                <form action="manageProducts.php" method="GET">
                    <input type="hidden" name="delete_package" id="del-pkg-id">
                    <input type="hidden" name="tab" value="packages">
                    <button type="submit" class="btn-confirm" id="delPkgConfirm">Confirm</button>
                </form>
                <button class="btn-cancel" onclick="closeModal('del-package')">Cancel</button>
            </div>
        </div>
    </div>

    <div id="logoutModal" class="modal-overlay del-modal" style="display:none;">
        <div class="modal-box">
            <h3>Confirm Logout</h3>
            <p>Are you sure you want to logout?</p>
            <div class="del-btns">
                <button type="button" class="btn-confirm" onclick="window.location.href='profile.php?logout=true'">Confirm</button>
                <button class="btn-cancel" onclick="document.getElementById('logoutModal').style.display='none'">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        function openLogoutModal() {
            document.getElementById('logoutModal').style.display = 'flex';
        }

        function switchTab(tab) {
            document.querySelectorAll('.prod-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.prod-panel').forEach(p => p.classList.remove('active'));
            event.currentTarget.classList.add('active');
            document.getElementById('panel-' + tab).classList.add('active');
            history.replaceState(null, '', '?tab=' + tab);
        }

        function openModal(id) {
            document.getElementById(id).classList.add('open');
        }

        function closeModal(id) {
            document.getElementById(id).classList.remove('open');
        }

        function closeEditPerf() {
            closeModal('modal-edit-perfume');
            history.replaceState(null, '', '?tab=perfumes');
        }

        function closeEditBottle() {
            closeModal('modal-edit-bottle');
            history.replaceState(null, '', '?tab=bottles');
        }

        function closeEditVariant() {
            closeModal('modal-edit-variant');
            history.replaceState(null, '', '?tab=bottles');
        }

        function closeEditSetup() {
            closeModal('modal-edit-setup');
            history.replaceState(null, '', '?tab=setup');
        }

        function closeEditMirror() {
            closeModal('modal-edit-mirror');
            history.replaceState(null, '', '?tab=mirrors');
        }

        function closeEditPackage() {
            closeModal('modal-edit-package');
            history.replaceState(null, '', '?tab=packages');
        }

        function previewImg(input, previewId) {
            let prev = document.getElementById(previewId);
            if (!prev) {
                prev = document.createElement('img');
                prev.id = previewId;
                prev.className = 'img-preview';
                input.insertAdjacentElement('afterend', prev);
            }
            if (input.files && input.files[0]) {
                prev.src = URL.createObjectURL(input.files[0]);
                prev.style.display = 'block';
            }
        }

        function openDelPerf(id, name, isRef) {
            document.getElementById('del-perf-id').value = id;
            document.getElementById('del-perf').classList.add('open');
            if (isRef) {
                document.getElementById('delPerfTitle').textContent = 'Cannot Delete';
                document.getElementById('delPerfMsg').textContent = '"' + name + '" is used in a booking and cannot be deleted.';
                document.getElementById('delPerfConfirm').style.display = 'none';
            } else {
                document.getElementById('delPerfTitle').textContent = 'Confirm Deletion';
                document.getElementById('delPerfMsg').textContent = 'Delete "' + name + '"? This cannot be undone.';
                document.getElementById('delPerfConfirm').style.display = '';
            }
        }

        function openDelBottle(id, name, isRef) {
            document.getElementById('del-bottle-id').value = id;
            document.getElementById('del-bottle').classList.add('open');
            if (isRef) {
                document.getElementById('delBottleTitle').textContent = 'Cannot Delete';
                document.getElementById('delBottleMsg').textContent = '"' + name + '" is used in a booking or package.';
                document.getElementById('delBottleConfirm').style.display = 'none';
            } else {
                document.getElementById('delBottleTitle').textContent = 'Confirm Deletion';
                document.getElementById('delBottleMsg').textContent = 'Delete "' + name + '" and all its variants?';
                document.getElementById('delBottleConfirm').style.display = '';
            }
        }

        function openDelVariant(id, name, isRef) {
            document.getElementById('del-var-id').value = id;
            document.getElementById('del-variant').classList.add('open');
            if (isRef) {
                document.getElementById('delVarTitle').textContent = 'Cannot Delete';
                document.getElementById('delVarMsg').textContent = '"' + name + '" is used in an existing booking.';
                document.getElementById('delVarConfirm').style.display = 'none';
            } else {
                document.getElementById('delVarTitle').textContent = 'Confirm Deletion';
                document.getElementById('delVarMsg').textContent = 'Delete variant "' + name + '"?';
                document.getElementById('delVarConfirm').style.display = '';
            }
        }

        function openDelSetup(id, name, isRef) {
            document.getElementById('del-setup-id').value = id;
            document.getElementById('del-setup').classList.add('open');
            if (isRef) {
                document.getElementById('delSetupTitle').textContent = 'Cannot Delete';
                document.getElementById('delSetupMsg').textContent = '"' + name + '" is used in an existing booking.';
                document.getElementById('delSetupConfirm').style.display = 'none';
            } else {
                document.getElementById('delSetupTitle').textContent = 'Confirm Deletion';
                document.getElementById('delSetupMsg').textContent = 'Delete "' + name + '"?';
                document.getElementById('delSetupConfirm').style.display = '';
            }
        }

        function openDelMirror(id, name, isRef) {
            document.getElementById('del-mirror-id').value = id;
            document.getElementById('del-mirror').classList.add('open');
            if (isRef) {
                document.getElementById('delMirrorTitle').textContent = 'Cannot Delete';
                document.getElementById('delMirrorMsg').textContent = '"' + name + '" is used in an existing booking.';
                document.getElementById('delMirrorConfirm').style.display = 'none';
            } else {
                document.getElementById('delMirrorTitle').textContent = 'Confirm Deletion';
                document.getElementById('delMirrorMsg').textContent = 'Delete "' + name + '"?';
                document.getElementById('delMirrorConfirm').style.display = '';
            }
        }

        function openDelPackage(id, name, isRef) {
            document.getElementById('del-pkg-id').value = id;
            document.getElementById('del-package').classList.add('open');
            if (isRef) {
                document.getElementById('delPkgTitle').textContent = 'Cannot Delete';
                document.getElementById('delPkgMsg').textContent = '"' + name + '" is used in an existing booking.';
                document.getElementById('delPkgConfirm').style.display = 'none';
            } else {
                document.getElementById('delPkgTitle').textContent = 'Confirm Deletion';
                document.getElementById('delPkgMsg').textContent = 'Delete package "' + name + '"?';
                document.getElementById('delPkgConfirm').style.display = '';
            }
        }

        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', function(e) {
                if (e.target === this) this.classList.remove('open');
            });
        });

        function switchBottleTab(name, btn) {
            document.querySelectorAll('.bottle-section').forEach(s => s.classList.remove('active'));
            document.querySelectorAll('.bottle-mini-tab').forEach(b => b.classList.remove('active'));
            document.getElementById('bottle-sec-' + name).classList.add('active');
            btn.classList.add('active');
        }
    </script>

    <script>
        (function() {
            var burger = document.getElementById('burgerBtn');
            var sidebar = document.getElementById('adminSidebar');
            var overlay = document.getElementById('sidebarOverlay');
            if (!burger || !sidebar || !overlay) return;

            function openSidebar() {
                sidebar.classList.add('drawer-open');
                overlay.classList.add('active');
                burger.classList.add('is-open');
                burger.setAttribute('aria-expanded', 'true');
                document.body.style.overflow = 'hidden';
            }

            function closeSidebar() {
                sidebar.classList.remove('drawer-open');
                overlay.classList.remove('active');
                burger.classList.remove('is-open');
                burger.setAttribute('aria-expanded', 'false');
                document.body.style.overflow = '';
            }

            burger.addEventListener('click', function() {
                sidebar.classList.contains('drawer-open') ? closeSidebar() : openSidebar();
            });
            overlay.addEventListener('click', closeSidebar);
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') closeSidebar();
            });

            var touchStartX = 0;
            sidebar.addEventListener('touchstart', function(e) {
                touchStartX = e.touches[0].clientX;
            }, {
                passive: true
            });
            sidebar.addEventListener('touchend', function(e) {
                if (touchStartX - e.changedTouches[0].clientX > 55) closeSidebar();
            }, {
                passive: true
            });

            var page = window.location.pathname.split('/').pop().split('?')[0];
            sidebar.querySelectorAll('a').forEach(function(a) {
                var href = (a.getAttribute('href') || '').split('?')[0].split('/').pop();
                if (href === page) a.classList.add('nav-active');
            });
        })();
    </script>
</body>

</html>