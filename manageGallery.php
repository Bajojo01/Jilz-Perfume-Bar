<?php
session_start();
require("db.php");

if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit();
}

/* UPLOAD IMAGE */
function uploadImage($file)
{
    if (!isset($file['name']) || $file['name'] == "") return null;
    $targetDir = "uploads/gallery/";
    if (!is_dir($targetDir)) mkdir($targetDir, 0777, true);

    // Get extension from MIME type — never trust the original filename
    $mimeType = $file['type'];
    $mimeMap = [
        'image/jpeg' => 'jpg',
        'image/jpg'  => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
        'image/bmp'  => 'bmp',
    ];
    $fileExt = $mimeMap[$mimeType] ?? 'jpg';

    // Use only timestamp + random string — no original filename, no special char issues
    $cleanFilename = time() . "_" . bin2hex(random_bytes(6)) . "." . $fileExt;
    $destPath = $targetDir . $cleanFilename;
    if (move_uploaded_file($file["tmp_name"], $destPath)) return $destPath;
    return null;
}

/* ADD IMAGE */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'addGallery') {
    $imgPath = uploadImage($_FILES['galleryImg'] ?? []);
    $uploadedAt = date('Y-m-d H:i:s');
    if ($imgPath) {
        $stmt = mysqli_prepare($conn, "INSERT INTO gallery_pictures (Img_URL, Uploaded_At) VALUES (?, ?)");
        mysqli_stmt_bind_param($stmt, "ss", $imgPath, $uploadedAt);
        mysqli_stmt_execute($stmt);
    }
    header("Location: manageGallery.php");
    exit();
}

/* DELETE SINGLE IMAGE */
if (isset($_GET['deleteGallery'])) {
    $galleryId = intval($_GET['deleteGallery']);
    $galleryRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT Img_URL FROM gallery_pictures WHERE Gallery_ID_PK=$galleryId"));
    if ($galleryRow && file_exists($galleryRow['Img_URL'])) {
        unlink($galleryRow['Img_URL']);
    }
    $stmt = mysqli_prepare($conn, "DELETE FROM gallery_pictures WHERE Gallery_ID_PK=?");
    mysqli_stmt_bind_param($stmt, "i", $galleryId);
    mysqli_stmt_execute($stmt);
    header("Location: manageGallery.php");
    exit();
}

/* DELETE SELECTED (bulk) */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'deleteSelected') {
    $selectedIds = $_POST['selectedIds'] ?? [];
    foreach ($selectedIds as $galleryId) {
        $galleryId = intval($galleryId);
        $galleryRow = mysqli_fetch_assoc(mysqli_query($conn, "SELECT Img_URL FROM gallery_pictures WHERE Gallery_ID_PK=$galleryId"));
        if ($galleryRow && file_exists($galleryRow['Img_URL'])) unlink($galleryRow['Img_URL']);
        $stmt = mysqli_prepare($conn, "DELETE FROM gallery_pictures WHERE Gallery_ID_PK=?");
        mysqli_stmt_bind_param($stmt, "i", $galleryId);
        mysqli_stmt_execute($stmt);
    }
    header("Location: manageGallery.php");
    exit();
}

/* FILTER BY DATE */
$filterDateFrom = $_GET['dateFrom'] ?? '';
$filterDateTo   = $_GET['dateTo']   ?? '';

$gallerySQL = "SELECT * FROM gallery_pictures";
$whereClause = [];
if ($filterDateFrom) $whereClause[] = "DATE(Uploaded_At) >= '" . mysqli_real_escape_string($conn, $filterDateFrom) . "'";
if ($filterDateTo)   $whereClause[] = "DATE(Uploaded_At) <= '" . mysqli_real_escape_string($conn, $filterDateTo) . "'";
if ($whereClause) $gallerySQL .= " WHERE " . implode(" AND ", $whereClause);
$gallerySQL .= " ORDER BY Uploaded_At DESC";
$galleryResult = mysqli_query($conn, $gallerySQL);
$totalImageCount = mysqli_num_rows($galleryResult);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jilz | Manage Gallery</title>
    <link rel="shortcut icon" href="assets/Logo_Tentative.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans&display=swap" rel="stylesheet">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="admin.css">
    <link rel="stylesheet" href="mobisleStyle.css">

</head>

<body class="adminBG">

    <!-- SIDEBAR -->
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

    <!-- MAIN CONTENT -->
    <div class="ainfocontainer">

        <h1 style="font-size:2rem; margin-bottom:1.5rem;">Manage Gallery</h1>

        <!-- Section Header -->
        <div class="galleryPageHeader">
            <h2>Gallery Images</h2>
            <button class="btn-add" onclick="document.getElementById('galleryFileInput').click()">
                <span class="material-icons">add_photo_alternate</span> Upload Image
            </button>
        </div>

        <!-- Hidden upload form -->
        <form id="galleryUploadForm" method="POST" enctype="multipart/form-data" style="display:none;">
            <input type="hidden" name="action" value="addGallery">
            <input type="file" id="galleryFileInput" name="galleryImg" accept="image/*"
                onchange="document.getElementById('galleryUploadForm').submit()">
        </form>

        <!-- Toolbar: date filters + bulk delete -->
        <form method="GET" id="galleryFilterForm">
            <div class="galleryToolbar">
                <div class="galleryFilterGroup">
                    <span class="material-icons" style="font-size:1rem;color:#888;">calendar_today</span>
                    <label>From</label>
                    <input type="date" name="dateFrom" value="<?= htmlspecialchars($filterDateFrom) ?>">
                </div>
                <div class="galleryFilterGroup">
                    <span class="material-icons" style="font-size:1rem;color:#888;">calendar_today</span>
                    <label>To</label>
                    <input type="date" name="dateTo" value="<?= htmlspecialchars($filterDateTo) ?>">
                </div>
                <button type="submit" class="galleryFilterBtn">
                    <span class="material-icons" style="font-size:1rem;">filter_list</span> Filter
                </button>
                <?php if ($filterDateFrom || $filterDateTo): ?>
                    <a href="manageGallery.php" class="galleryClearBtn">Clear</a>
                <?php endif; ?>
                <div class="galleryToolbarSpacer"></div>
                <button type="button" class="galleryBulkDelBtn" id="galleryBulkDelBtn" onclick="openBulkDelete()">
                    <span class="material-icons" style="font-size:1rem;">delete_sweep</span>
                    Delete Selected (<span id="gallerySelCount">0</span>)
                </button>
            </div>
        </form>

        <!-- Image count -->
        <p class="galleryImageCount">
            <?= $totalImageCount ?> image<?= $totalImageCount !== 1 ? 's' : '' ?> found
        </p>

        <!-- Bulk delete form -->
        <form id="galleryBulkDelForm" method="POST" style="display:none;">
            <input type="hidden" name="action" value="deleteSelected">
            <div id="galleryBulkIdsContainer"></div>
        </form>

        <!-- Gallery Grid -->
        <div class="galleryGrid" id="galleryGrid">

            <!-- Upload card -->
            <div class="galleryCard galleryUploadCard"
                onclick="document.getElementById('galleryFileInput').click()"
                title="Upload Image">
                <span class="material-icons">add_photo_alternate</span>
                <span class="galleryUploadLabel">Add Image</span>
            </div>

            <?php if ($totalImageCount === 0): ?>
                <div class="galleryEmptyState" style="grid-column: 2 / -1; align-self: center;">
                    <span class="material-icons">photo_library</span>
                    No images yet. Upload one to get started.
                </div>
            <?php else: ?>
                <?php while ($galleryRow = mysqli_fetch_assoc($galleryResult)): ?>
                    <div class="galleryCard" id="galCard-<?= $galleryRow['Gallery_ID_PK'] ?>">
                        <img src="<?= htmlspecialchars($galleryRow['Img_URL']) ?>"
                            alt="Gallery Image"
                            onclick="openGalleryPreview('<?= htmlspecialchars($galleryRow['Img_URL']) ?>')">
                        <div class="galleryCardOverlay">
                            <div style="display:flex;justify-content:space-between;align-items:flex-start;">
                                <input type="checkbox"
                                    class="galleryCardCheckbox"
                                    data-id="<?= $galleryRow['Gallery_ID_PK'] ?>"
                                    onclick="event.stopPropagation(); toggleGallerySelect(this)"
                                    title="Select">
                            </div>
                            <div>
                                <div class="galleryCardDate">
                                    <?= date('M d, Y', strtotime($galleryRow['Uploaded_At'])) ?>
                                </div>
                                <div class="galleryCardActions">
                                    <button type="button" title="Preview"
                                        onclick="openGalleryPreview('<?= htmlspecialchars($galleryRow['Img_URL']) ?>')">
                                        <span class="material-icons">visibility</span>
                                    </button>
                                    <button type="button" class="galDelBtn" title="Delete"
                                        onclick="openDelGallery(<?= $galleryRow['Gallery_ID_PK'] ?>)">
                                        <span class="material-icons">delete</span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php endif; ?>

        </div><!-- /galleryGrid -->

    </div><!-- /ainfocontainer -->

    <!-- IMAGE PREVIEW OVERLAY -->
    <div class="galleryPreviewOverlay" id="galleryPreviewOverlay"
        onclick="closeGalleryPreview(this, event)">
        <button class="galleryPreviewCloseBtn" onclick="closeGalleryPreview(null)">
            <span class="material-icons">close</span>
        </button>
        <img id="galleryPreviewImg" src="" alt="Preview">
    </div>

    <!-- DELETE SINGLE CONFIRM MODAL -->
    <div class="galleryModalOverlay" id="galDelSingleModal">
        <div class="galleryModalBox">
            <h3>Confirm Deletion</h3>
            <p>Delete this image? This cannot be undone.</p>
            <div class="galleryModalBtns">
                <a id="galDelSingleLink" href="#" class="galConfirmBtn">Confirm</a>
                <button class="galCancelBtn" onclick="closeGalleryModal('galDelSingleModal')">Cancel</button>
            </div>
        </div>
    </div>

    <!-- BULK DELETE CONFIRM MODAL -->
    <div class="galleryModalOverlay" id="galDelBulkModal">
        <div class="galleryModalBox">
            <h3>Confirm Bulk Deletion</h3>
            <p id="galBulkDelMsg">Delete selected images? This cannot be undone.</p>
            <div class="galleryModalBtns">
                <button class="galConfirmBtn" onclick="submitBulkDelete()">Confirm</button>
                <button class="galCancelBtn" onclick="closeGalleryModal('galDelBulkModal')">Cancel</button>
            </div>
        </div>
    </div>

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

    <script>
        /* ── Logout ── */
        function openLogoutModal() {
            document.getElementById('logoutModal').style.display = 'flex';
        }

        /* ── Gallery modal helpers ── */
        function openGalleryModal(id) {
            document.getElementById(id).classList.add('galOpen');
        }

        function closeGalleryModal(id) {
            document.getElementById(id).classList.remove('galOpen');
        }

        document.querySelectorAll('.galleryModalOverlay').forEach(function(overlay) {
            overlay.addEventListener('click', function(e) {
                if (e.target === this) this.classList.remove('galOpen');
            });
        });

        /* ── Delete single ── */
        function openDelGallery(id) {
            document.getElementById('galDelSingleLink').href = 'manageGallery.php?deleteGallery=' + id;
            openGalleryModal('galDelSingleModal');
        }

        /* ── Image preview ── */
        function openGalleryPreview(src) {
            document.getElementById('galleryPreviewImg').src = src;
            document.getElementById('galleryPreviewOverlay').classList.add('galOpen');
        }

        function closeGalleryPreview(el, e) {
            if (el && e && e.target !== el) return;
            document.getElementById('galleryPreviewOverlay').classList.remove('galOpen');
            document.getElementById('galleryPreviewImg').src = '';
        }
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                document.getElementById('galleryPreviewOverlay').classList.remove('galOpen');
            }
        });

        /* ── Checkbox / bulk select ── */
        var galSelectedIds = new Set();

        function toggleGallerySelect(checkbox) {
            var id = checkbox.dataset.id;
            var card = document.getElementById('galCard-' + id);
            if (checkbox.checked) {
                galSelectedIds.add(id);
                card.classList.add('galSelected');
            } else {
                galSelectedIds.delete(id);
                card.classList.remove('galSelected');
            }
            updateGalleryBulkBtn();
        }

        function updateGalleryBulkBtn() {
            var btn = document.getElementById('galleryBulkDelBtn');
            document.getElementById('gallerySelCount').textContent = galSelectedIds.size;
            btn.classList.toggle('galVisible', galSelectedIds.size > 0);
        }

        function openBulkDelete() {
            if (galSelectedIds.size === 0) return;
            document.getElementById('galBulkDelMsg').textContent =
                'Delete ' + galSelectedIds.size + ' selected image' +
                (galSelectedIds.size > 1 ? 's' : '') + '? This cannot be undone.';
            openGalleryModal('galDelBulkModal');
        }

        function submitBulkDelete() {
            var container = document.getElementById('galleryBulkIdsContainer');
            container.innerHTML = '';
            galSelectedIds.forEach(function(id) {
                var inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = 'selectedIds[]';
                inp.value = id;
                container.appendChild(inp);
            });
            document.getElementById('galleryBulkDelForm').submit();
        }
    </script>
</body>

</html>