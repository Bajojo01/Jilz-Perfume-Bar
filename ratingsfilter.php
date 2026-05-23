<?php
session_start();
require("db.php");
// ======================secure======================
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

// ====================== TOGGLE HIDE/UNHIDE ======================
if (isset($_GET['toggle_hide'])) {
    $id = intval($_GET['toggle_hide']);
    $type = $_GET['type'] ?? 'perfume';

    if ($type === 'perfume') {
        $sql = "UPDATE Perfume_Ratings SET is_hidden = NOT is_hidden WHERE Perfume_Rating_ID_PK = ?";
        $redirect = 'perfume';
    } else {
        $sql = "UPDATE Package_Ratings SET is_hidden = NOT is_hidden WHERE Package_Rating_ID_PK = ?";
        $redirect = 'package';
    }

    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "i", $id);
    mysqli_stmt_execute($stmt);

    header("Location: ratingsfilter.php?tab=" . $redirect);
    exit();
}

// ====================== FETCH DATA ======================
$activeTab = $_GET['tab'] ?? 'perfume';

// Perfume Ratings
$perfRatings = mysqli_query($conn, "
    SELECT pr.*, p.Inspired_Scent, p.Perfume_Img, u.First_Name, u.Last_Name
    FROM Perfume_Ratings pr
    JOIN Perfume p ON pr.Perfume_ID_FK = p.Perfume_ID_PK
    JOIN User_Information u ON pr.User_ID_FK = u.User_ID_PK
    ORDER BY pr.Perfume_Rating_ID_PK DESC
");

// Package Ratings
$pkgRatings = mysqli_query($conn, "
    SELECT pr.*, p.Package_Name, p.Package_Img, u.First_Name, u.Last_Name
    FROM Package_Ratings pr
    JOIN Packages p ON pr.Package_ID_FK = p.Package_ID_PK
    JOIN User_Information u ON pr.User_ID_FK = u.User_ID_PK
    ORDER BY pr.Package_Rating_ID_PK DESC
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jilz | Ratings Filter</title>
    <link rel="shortcut icon" href="assets/Logo_Tentative.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="adminProductsMobile.css">
    <style>
        .rating-stars {
            color: #D4AF37;
            font-size: 1.3rem;
        }
        .description-cell {
            max-width: 450px;
        }
        .desc-content {
            margin-top: 8px;
            line-height: 1.5;
        }
        .hidden-row {
            background: #fff3f3;
        }
        .status-hidden {
            color: #d32f2f;
            font-weight: 600;
            font-style: italic;
        }
        .btn-toggle {
            display: flex;
            align-items: center;
            gap: 6px;
            padding: 8px 14px;
            border: none;
            border-radius: 6px;
            font-size: 0.95rem;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn-toggle.hide {
            background: #ffebee;
            color: #d32f2f;
        }
        .btn-toggle.unhide {
            background: #e8f5e9;
            color: #2e7d32;
        }
        .btn-toggle:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body class="adminBG">

    <!-- Sidebar overlay for mobile drawer -->
    <div class="sidebar-overlay" id="sidebarOverlay"></div>

    <!-- Mobile top bar -->
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

    <!-- Sidebar navigation -->
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
        <h1>Ratings Management</h1>
      
        <!-- Tabs -->
        <nav class="prod-nav">
            <button class="prod-tab <?= $activeTab === 'perfume' ? 'active' : '' ?>" onclick="switchTab('perfume')">
                <span class="material-icons">spa</span> Perfume Ratings
            </button>
            <button class="prod-tab <?= $activeTab === 'package' ? 'active' : '' ?>" onclick="switchTab('package')">
                <span class="material-icons">inventory_2</span> Package Ratings
            </button>
        </nav>

        <!-- Perfume Ratings -->
        <div id="panel-perfume" class="prod-panel <?= $activeTab === 'perfume' ? 'active' : '' ?>">
            <h2>Perfume Ratings</h2>
            <div class="table-wrap scroll-table">
                <table>
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>User</th>
                            <th>Rating</th>
                            <th>Description</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($perfRatings)):
                            $isHidden = !empty($row['is_hidden']);
                        ?>
                        <tr class="<?= $isHidden ? 'hidden-row' : '' ?>">
                            <td data-label="Product">
                                <img src="<?= htmlspecialchars($row['Perfume_Img']) ?>" style="width:70px; border-radius:8px; margin-right:10px;">
                                <strong><?= htmlspecialchars($row['Inspired_Scent']) ?></strong>
                            </td>
                            <td data-label="User"><?= htmlspecialchars($row['First_Name'] . ' ' . $row['Last_Name']) ?></td>
                            <td data-label="Rating">
                                <div class="rating-stars">
                                    <?= str_repeat('★', (int)$row['Rating']) . str_repeat('☆', 5 - (int)$row['Rating']) ?>
                                </div>
                            </td>
                            <td data-label="Description" class="description-cell">
                                <?php if ($isHidden): ?>
                                    <span class="status-hidden">Hidden</span>
                                <?php else: ?>
                                    <div class="desc-content">
                                        <?= nl2br(htmlspecialchars($row['Description'] ?? 'No description provided.')) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td data-label="Actions">
                                <button class="btn-toggle <?= $isHidden ? 'unhide' : 'hide' ?>"
                                        onclick="toggleHide(<?= $row['Perfume_Rating_ID_PK'] ?>, 'perfume')">
                                    <span class="material-icons"><?= $isHidden ? 'visibility' : 'visibility_off' ?></span>
                                    <?= $isHidden ? 'Unhide' : 'Hide' ?>
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Package Ratings -->
        <div id="panel-package" class="prod-panel <?= $activeTab === 'package' ? 'active' : '' ?>">
            <h2>Package Ratings</h2>
            <div class="table-wrap scroll-table">
                <table>
                    <thead>
                        <tr>
                            <th>Package</th>
                            <th>User</th>
                            <th>Rating</th>
                            <th>Description</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php while ($row = mysqli_fetch_assoc($pkgRatings)):
                            $isHidden = !empty($row['is_hidden']);
                        ?>
                        <tr class="<?= $isHidden ? 'hidden-row' : '' ?>">
                            <td data-label="Package">
                                <img src="<?= htmlspecialchars($row['Package_Img']) ?>" style="width:70px; border-radius:8px; margin-right:10px;">
                                <strong><?= htmlspecialchars($row['Package_Name']) ?></strong>
                            </td>
                            <td data-label="User"><?= htmlspecialchars($row['First_Name'] . ' ' . $row['Last_Name']) ?></td>
                            <td data-label="Rating">
                                <div class="rating-stars">
                                    <?= str_repeat('★', (int)$row['Rating']) . str_repeat('☆', 5 - (int)$row['Rating']) ?>
                                </div>
                            </td>
                            <td data-label="Description" class="description-cell">
                                <?php if ($isHidden): ?>
                                    <span class="status-hidden">Hidden</span>
                                <?php else: ?>
                                    <div class="desc-content">
                                        <?= nl2br(htmlspecialchars($row['Description'] ?? 'No description provided.')) ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td data-label="Actions">
                                <button class="btn-toggle <?= $isHidden ? 'unhide' : 'hide' ?>"
                                        onclick="toggleHide(<?= $row['Package_Rating_ID_PK'] ?>, 'package')">
                                    <span class="material-icons"><?= $isHidden ? 'visibility' : 'visibility_off' ?></span>
                                    <?= $isHidden ? 'Unhide' : 'Hide' ?>
                                </button>
                            </td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Logout Modal -->
    <div id="logoutModal" class="modal-overlay del-modal" style="display:none;">
        <div class="modal-box">
            <h3>Confirm Logout</h3>
            <p>Are you sure you want to logout?</p>
            <div class="del-btns">
                <button type="button" class="btn-confirm" onclick="window.location.href='?logout=true'">Confirm</button>
                <button class="btn-cancel" onclick="document.getElementById('logoutModal').style.display='none'">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tab) {
            document.querySelectorAll('.prod-tab').forEach(t => t.classList.remove('active'));
            document.querySelectorAll('.prod-panel').forEach(p => p.classList.remove('active'));
            
            event.currentTarget.classList.add('active');
            document.getElementById('panel-' + tab).classList.add('active');
            history.replaceState(null, '', '?tab=' + tab);
        }

        function toggleHide(id, type) {
            if (confirm('Are you sure you want to hide/unhide this rating?')) {
                window.location.href = `ratingsfilter.php?toggle_hide=${id}&type=${type}`;
            }
        }

        function openLogoutModal() {
            document.getElementById('logoutModal').style.display = 'flex';
        }

        // Mobile Sidebar Script (same as manageProducts.php)
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
        })();
    </script>
</body>
</html>