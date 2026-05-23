<?php

session_start();
require("db.php");

/* ── Auth guard ── */
if (!isset($_SESSION['admin_id'])) {
    header("Location: login.php");
    exit();
}

/* ── Get admin username ── */
$adminUsername = 'Admin';
$adminId = (int) $_SESSION['admin_id'];
$adminResult = mysqli_query($conn, "SELECT Username FROM Admin_Information WHERE Admin_ID_PK = $adminId");
if ($adminRow = mysqli_fetch_assoc($adminResult)) {
    $adminUsername = $adminRow['Username'];
}

/* ── Logout handler ── */
if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header("Location: index.php");
    exit();
}

/* ── Determine which month/year to display ── */
$year  = isset($_GET['year'])  ? (int) $_GET['year']  : (int) date('Y');
$month = isset($_GET['month']) ? (int) $_GET['month'] : (int) date('n');

/* Clamp month to 1–12 */
if ($month < 1) {
    $month = 12;
    $year--;
}
if ($month > 12) {
    $month = 1;
    $year++;
}

/* ── Fetch all Approved bookings for the displayed month ── */
$monthPad  = str_pad($month, 2, '0', STR_PAD_LEFT);
$startDate = "$year-$monthPad-01";
$endDate   = date('Y-m-t', strtotime($startDate));

$bookingsRaw = mysqli_query(
    $conn,
    "SELECT
        b.Booking_ID_PK,
        CONCAT(u.First_Name, ' ', u.Last_Name) AS Full_Name,
        u.Username,
        u.Email,
        u.Phone_No,
        b.Event_Date,
        b.Event_Address,
        b.Event_Time_Start,
        b.Event_Time_End,
        b.Event_Type,
        b.Event_Notes,
        p.Package_Name,
        p.Price AS Package_Price,
        bs.Bar_Name,
        bv.Bottle_Var_Name,
        sm.Mirror_Name,
        bp.Additional_Fee,
        bp.Additional_Fee_Description,
        bp.Total_Price
    FROM Booking b
    INNER JOIN User_Information u  ON b.User_ID_FK         = u.User_ID_PK
    INNER JOIN Packages p          ON b.Package_ID_FK      = p.Package_ID_PK
    INNER JOIN Bar_Setup bs        ON b.Bar_Setup_ID_FK    = bs.Bar_Setup_ID_PK
    INNER JOIN Bottle_Variants bv  ON b.Bottle_Var_ID_FK   = bv.Bottle_Var_ID_PK
    INNER JOIN Selfie_Mirror sm    ON b.Selfie_Mirror_ID_FK= sm.Selfie_Mirror_ID_PK
    LEFT  JOIN Booking_Payment bp  ON bp.Booking_ID_FK     = b.Booking_ID_PK
    WHERE b.Booking_Status = 'Approved'
      AND b.Event_Date BETWEEN '$startDate' AND '$endDate'
    ORDER BY b.Event_Date ASC, b.Event_Time_Start ASC"
);

/* Group bookings by day number (1–31) */
$byDay = [];
while ($row = mysqli_fetch_assoc($bookingsRaw)) {
    /* Attach perfumes */
    $pq = mysqli_query(
        $conn,
        "SELECT p.Inspired_Scent FROM Booking_Perfume bp
         INNER JOIN Perfume p ON bp.Perfume_ID_FK = p.Perfume_ID_PK
         WHERE bp.Booking_ID_FK = " . $row['Booking_ID_PK']
    );
    $pnames = [];
    while ($p = mysqli_fetch_assoc($pq)) {
        $pnames[] = $p['Inspired_Scent'];
    }
    $row['Perfumes'] = implode(', ', $pnames);

    $day = (int) date('j', strtotime($row['Event_Date']));
    $byDay[$day][] = $row;
}

/* ── Calendar math ── */
$daysInMonth  = (int) date('t', strtotime($startDate));
$firstWeekday = (int) date('w', strtotime($startDate)); /* 0 = Sunday */
$monthName    = date('F', strtotime($startDate));
$today        = date('Y-m-d');

/* ── Prev / Next month links ── */
$prevMonth = $month - 1;
$prevYear  = $year;
if ($prevMonth < 1) {
    $prevMonth = 12;
    $prevYear--;
}

$nextMonth = $month + 1;
$nextYear  = $year;
if ($nextMonth > 12) {
    $nextMonth = 1;
    $nextYear++;
}

/* ── JSON encode bookings for JS modal ── */
$bookingsJson = json_encode($byDay, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Jilz | Admin – Event Calendar</title>
    <link rel="shortcut icon" href="assets/Logo_Tentative.png" type="image/x-icon">
    <link href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="admin.css?v=5">
    <link rel="stylesheet" href="adminMobile.css">
</head>

<body class="adminBG">

    <!-- Sidebar overlay for mobile -->
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

    <!-- ── Sidebar ── -->
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

    <!-- ── Main content ── -->
    <div class="calPageWrap">
        <h1>Event Calendar</h1>
        <p class="calPageSubtitle"></p>

        <!-- Month navigation -->
        <div class="calNavBar">
            <a class="calNavBtn" href="eventCalendar.php?month=<?= $prevMonth; ?>&year=<?= $prevYear; ?>" title="Previous month">&#8249;</a>
            <div>
                <span class="calMonthLabel"><?= $monthName; ?></span>
                <span class="calMonthYear"> <?= $year; ?></span>
            </div>
            <a class="calNavBtn" href="eventCalendar.php?month=<?= $nextMonth; ?>&year=<?= $nextYear; ?>" title="Next month">&#8250;</a>
        </div>

        <!-- Summary pills -->
        <?php
        $totalEventsThisMonth = array_sum(array_map('count', $byDay));
        $daysWithEvents       = count($byDay);
        ?>
        <div class="calSummaryRow">
            <div class="calSummaryPill">
                <?= $totalEventsThisMonth; ?><span><?= $totalEventsThisMonth === 1 ? 'event' : 'events'; ?> this month</span>
            </div>
            <?php if ($daysWithEvents > 0): ?>
                <div class="calSummaryPill">
                    <?= $daysWithEvents; ?><span><?= $daysWithEvents === 1 ? 'active day' : 'active days'; ?></span>
                </div>
            <?php endif; ?>
        </div>

        <!-- Legend -->
        <div class="calLegendRow">
            <div class="calLegendItem">
                <div class="calLegendDot" style="background:#D4AF37;"></div>
                Confirmed event
            </div>
            <div class="calLegendItem">
                <div class="calLegendDot" style="background:#D4AF37;border-radius:50%;"></div>
                Today
            </div>
        </div>

        <!-- Calendar grid -->
        <div class="calGrid">

            <!-- Day-of-week headers -->
            <div class="calDowRow">
                <?php foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $dow): ?>
                    <div class="calDowCell"><?= $dow; ?></div>
                <?php endforeach; ?>
            </div>

            <!-- Weeks -->
            <div class="calWeeksWrap">
                <?php
                $cellCount  = 0;
                $totalCells = $firstWeekday + $daysInMonth;
                $rows       = ceil($totalCells / 7);

                for ($row = 0; $row < $rows; $row++):
                ?>
                    <div class="calWeek">
                        <?php for ($col = 0; $col < 7; $col++):
                            $cellIndex = $row * 7 + $col;
                            $dayNum    = $cellIndex - $firstWeekday + 1;
                            $isValid   = ($dayNum >= 1 && $dayNum <= $daysInMonth);
                            $isToday   = $isValid && (sprintf('%04d-%02d-%02d', $year, $month, $dayNum) === $today);
                            $hasEvents = $isValid && isset($byDay[$dayNum]);
                            $eventList = $hasEvents ? $byDay[$dayNum] : [];
                        ?>
                            <div class="calCell<?= !$isValid ? ' empty' : ''; ?><?= $isToday ? ' isToday' : ''; ?><?= $hasEvents ? ' hasEvents' : ''; ?>"
                                <?= $hasEvents ? "onclick=\"openDayModal($dayNum)\" data-day=\"$dayNum\"" : ''; ?>>

                                <?php if ($isValid): ?>
                                    <div class="calDayNum"><?= $dayNum; ?></div>

                                    <?php
                                    /* Show up to 2 pills then overflow badge */
                                    $maxPills = 2;
                                    foreach (array_slice($eventList, 0, $maxPills) as $evt):
                                    ?>
                                        <div class="calEventPill" onclick="openDayModal(<?= $dayNum; ?>); event.stopPropagation();">
                                            <div class="calEventDot"></div>
                                            <span class="calEventPillText">
                                                <?= htmlspecialchars($evt['Full_Name']); ?>
                                            </span>
                                        </div>
                                    <?php endforeach; ?>

                                    <?php if (count($eventList) > $maxPills): ?>
                                        <div class="calMoreBadge">+<?= count($eventList) - $maxPills; ?> more</div>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </div>
                        <?php endfor; ?>
                    </div>
                <?php endfor; ?>

                <?php if ($totalEventsThisMonth === 0): ?>
                    <div style="grid-column: 1 / -1;">
                        <div class="calEmptyState">
                            <div class="calEmptyText">No confirmed events this month</div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- ── Event Detail Modal ── -->
    <div class="evtModalOverlay" id="evtModal">
        <div class="evtModalBox">
            <!-- Modal header -->
            <div class="evtModalHeader">
                <div>
                    <p class="evtModalDateLabel" id="evtModalDateLabel"></p>
                    <p class="evtModalTitle" id="evtModalTitle"></p>
                </div>
                <button class="evtModalCloseBtn" onclick="closeEvtModal()">&#10005;</button>
            </div>

            <!-- Booking tabs (multiple bookings on the same day) -->
            <div class="evtTabRow" id="evtTabRow"></div>

            <!-- Dynamic panels injected by JS -->
            <div id="evtPanelsWrap"></div>
        </div>
    </div>

    <!-- Logout confirm modal -->
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

    <!-- ── JavaScript ── -->
    <script>
        /* All approved bookings for this month, grouped by day */
        const bookingsByDay = <?= $bookingsJson; ?>;

        /* Month/year currently displayed */
        const calMonth = <?= $month; ?>;
        const calYear = <?= $year; ?>;

        /* Month name lookup for modal header */
        const monthNames = [
            'January', 'February', 'March', 'April', 'May', 'June',
            'July', 'August', 'September', 'October', 'November', 'December'
        ];

        /* ── Safely escape HTML to prevent XSS ── */
        function esc(str) {
            if (!str) return '—';
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        /* ── Format PHP time string to 12-hour display ── */
        function formatTime(timeStr) {
            if (!timeStr) return '—';
            const parts = timeStr.split(':');
            let h = parseInt(parts[0], 10);
            const m = parts[1] || '00';
            const ampm = h >= 12 ? 'PM' : 'AM';
            h = h % 12 || 12;
            return `${h}:${m} ${ampm}`;
        }

        /* ── Format PHP currency ── */
        function formatPHP(val) {
            const num = parseFloat(val) || 0;
            return '₱ ' + num.toLocaleString('en-PH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        /* ── Build the HTML for a single booking detail panel ── */
        function buildPanel(b, idx) {
            const timeRange = formatTime(b.Event_Time_Start) + ' – ' + formatTime(b.Event_Time_End);
            const total = b.Total_Price ? formatPHP(b.Total_Price) : formatPHP(b.Package_Price);
            const addFee = parseFloat(b.Additional_Fee || 0);

            return `
                <div class="evtPanel${idx === 0 ? ' active' : ''}" id="evtPanel${idx}">
                    <p class="evtCustomerName">${esc(b.Full_Name)}</p>

                    <!-- Quick details row -->
                    <div class="evtInfoGrid">
                        <div class="evtInfoItem">
                            <p class="evtLbl">Time</p>
                            <p class="evtVal">${timeRange}</p>
                        </div>
                        <div class="evtInfoItem">
                            <p class="evtLbl">Package</p>
                            <p class="evtVal gold">${esc(b.Package_Name)}</p>
                        </div>
                        <div class="evtInfoItem span2">
                            <p class="evtLbl">Address</p>
                            <p class="evtVal">${esc(b.Event_Address)}</p>
                        </div>
                    </div>

                    <div class="evtDivider"></div>

                    <!-- General info -->
                    <p class="evtSectionTitle">Contact &amp; Event Info</p>
                    <div class="evtInfoGrid">
                        <div class="evtInfoItem">
                            <p class="evtLbl">Phone</p>
                            <p class="evtVal">${esc(b.Phone_No)}</p>
                        </div>
                        <div class="evtInfoItem">
                            <p class="evtLbl">Email</p>
                            <p class="evtVal">${esc(b.Email)}</p>
                        </div>
                        <div class="evtInfoItem">
                            <p class="evtLbl">Event Type</p>
                            <p class="evtVal">${esc(b.Event_Type)}</p>
                        </div>
                        <div class="evtInfoItem">
                            <p class="evtLbl">Username</p>
                            <p class="evtVal">${esc(b.Username)}</p>
                        </div>
                    </div>

                    <div class="evtDivider"></div>

                    <!-- Setup info -->
                    <p class="evtSectionTitle">Setup Details</p>
                    <div class="evtInfoGrid">
                        <div class="evtInfoItem">
                            <p class="evtLbl">Bar Setup</p>
                            <p class="evtVal">${esc(b.Bar_Name)}</p>
                        </div>
                        <div class="evtInfoItem">
                            <p class="evtLbl">Bottle Variant</p>
                            <p class="evtVal">${esc(b.Bottle_Var_Name)}</p>
                        </div>
                        <div class="evtInfoItem">
                            <p class="evtLbl">Selfie Mirror</p>
                            <p class="evtVal">${esc(b.Mirror_Name)}</p>
                        </div>
                        <div class="evtInfoItem">
                            <p class="evtLbl">Perfumes</p>
                            <p class="evtVal">${esc(b.Perfumes) || '—'}</p>
                        </div>
                    </div>

                    <div class="evtDivider"></div>

                    <!-- Pricing -->
                    <p class="evtSectionTitle">Pricing</p>
                    <div class="evtInfoGrid">
                        <div class="evtInfoItem">
                            <p class="evtLbl">Package Price</p>
                            <p class="evtVal">${formatPHP(b.Package_Price)}</p>
                        </div>
                        ${addFee > 0 ? `
                        <div class="evtInfoItem">
                            <p class="evtLbl">Additional Fee</p>
                            <p class="evtVal">${formatPHP(addFee)}</p>
                        </div>
                        <div class="evtInfoItem span2">
                            <p class="evtLbl">Fee Description</p>
                            <p class="evtVal">${esc(b.Additional_Fee_Description)}</p>
                        </div>` : ''}
                        <div class="evtInfoItem">
                            <p class="evtLbl">Total</p>
                            <p class="evtVal gold">${total}</p>
                        </div>
                    </div>

                    ${b.Event_Notes && b.Event_Notes.trim() ? `
                    <div class="evtDivider"></div>
                    <p class="evtSectionTitle">Notes</p>
                    <p class="evtVal" style="font-size:0.88rem;font-weight:400;">${esc(b.Event_Notes)}</p>
                    ` : ''}
                </div>
            `;
        }

        /* ── Open the modal for a given day ── */
        function openDayModal(dayNum) {
            const events = bookingsByDay[dayNum];
            if (!events || events.length === 0) return;

            const dateStr = monthNames[calMonth - 1] + ' ' + dayNum + ', ' + calYear;

            /* Update modal header */
            document.getElementById('evtModalDateLabel').textContent = dateStr;
            document.getElementById('evtModalTitle').textContent =
                events.length === 1 ?
                '1 Confirmed Event' :
                events.length + ' Confirmed Events';

            /* Build tabs (only show if >1 booking) */
            const tabRow = document.getElementById('evtTabRow');
            tabRow.innerHTML = '';

            if (events.length > 1) {
                events.forEach(function(b, i) {
                    const tab = document.createElement('button');
                    tab.className = 'evtTab' + (i === 0 ? ' active' : '');
                    tab.textContent = 'Booking #' + b.Booking_ID_PK;
                    tab.setAttribute('data-tab', i);
                    tab.addEventListener('click', function() {
                        /* Switch active tab and panel */
                        tabRow.querySelectorAll('.evtTab').forEach(t => t.classList.remove('active'));
                        document.querySelectorAll('.evtPanel').forEach(p => p.classList.remove('active'));
                        tab.classList.add('active');
                        document.getElementById('evtPanel' + i).classList.add('active');
                    });
                    tabRow.appendChild(tab);
                });
            }

            /* Build panels */
            const panelsWrap = document.getElementById('evtPanelsWrap');
            panelsWrap.innerHTML = events.map(function(b, i) {
                return buildPanel(b, i);
            }).join('');

            /* Open modal */
            document.getElementById('evtModal').classList.add('open');
            document.body.style.overflow = 'hidden';
        }

        /* ── Close the modal ── */
        function closeEvtModal() {
            document.getElementById('evtModal').classList.remove('open');
            document.body.style.overflow = '';
        }

        /* Close on backdrop click */
        document.getElementById('evtModal').addEventListener('click', function(e) {
            if (e.target === this) closeEvtModal();
        });

        /* Close on Escape key */
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeEvtModal();
        });

        /* ── Logout modal ── */
        function openLogoutModal() {
            document.getElementById('logoutModal').style.display = 'flex';
        }
    </script>

    <!-- ── Burger menu JS ── -->
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

            /* Swipe to close */
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

            /* Highlight active nav link */
            var page = window.location.pathname.split('/').pop().split('?')[0];
            sidebar.querySelectorAll('a').forEach(function(a) {
                var href = (a.getAttribute('href') || '').split('?')[0].split('/').pop();
                if (href === page) a.classList.add('nav-active');
            });
        })();
    </script>

</body>

</html>