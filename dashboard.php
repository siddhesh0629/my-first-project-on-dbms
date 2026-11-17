
<?php
session_start();

if (!isset($_SESSION['role'])) {
    header("Location: index.php");
    exit();
}

require_once "config/db_connect.php";

$role = $_SESSION['role'];
$name = $_SESSION['full_name'] ?? 'User';

// Dashboard statistics
$total_products = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM products")
)['cnt'] ?? 0;

$total_units = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT IFNULL(SUM(quantity), 0) AS sm FROM stock")
)['sm'] ?? 0;

$total_val = mysqli_fetch_assoc(
    mysqli_query(
        $conn,
        "SELECT IFNULL(SUM(s.quantity * p.price), 0) AS val
         FROM stock s
         JOIN products p ON s.product_id = p.product_id"
    )
)['val'] ?? 0;

$total_wh = mysqli_fetch_assoc(
    mysqli_query($conn, "SELECT COUNT(*) AS cnt FROM warehouses")
)['cnt'] ?? 0;

// Warehouse chart data
$wh_labels = [];
$wh_data = [];

$wh_query = mysqli_query(
    $conn,
    "SELECT w.warehouse_name,
            IFNULL(SUM(s.quantity), 0) AS units
     FROM warehouses w
     LEFT JOIN stock s ON w.warehouse_id = s.warehouse_id
     GROUP BY w.warehouse_id, w.warehouse_name"
);

if ($wh_query) {
    while ($row = mysqli_fetch_assoc($wh_query)) {
        $wh_labels[] = $row['warehouse_name'];
        $wh_data[] = (int) $row['units'];
    }
}

// Category valuation chart data
$cat_labels = [];
$cat_data = [];

$cat_query = mysqli_query(
    $conn,
    "SELECT c.category_name,
            IFNULL(SUM(s.quantity * p.price), 0) AS val
     FROM categories c
     JOIN products p ON c.category_id = p.category_id
     JOIN stock s ON p.product_id = s.product_id
     GROUP BY c.category_id, c.category_name"
);

if ($cat_query) {
    while ($row = mysqli_fetch_assoc($cat_query)) {
        $cat_labels[] = $row['category_name'];
        $cat_data[] = (float) $row['val'];
    }
}

// Latest five stock log activities
$recent_logs = mysqli_query(
    $conn,
    "SELECT * FROM stock_log ORDER BY log_id DESC LIMIT 5"
);

$role_badge = 'badge-role-user';

if ($role === 'ADMIN') {
    $role_badge = 'badge-role-admin';
} elseif ($role === 'STOCK_MANAGER') {
    $role_badge = 'badge-role-manager';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Dashboard | Warehouse Management System</title>

    <script src="js/app.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link rel="stylesheet" href="css/style.css">
</head>

<body>

<div class="app-container">

    <!-- Sidebar -->
    <aside class="sidebar" id="sidebar">

        <div class="sidebar-header">
            <h4>
                <i class="fa-solid fa-boxes-stacked"></i>
                Warehouse OS
            </h4>
            <small class="text-secondary">Inventory Management</small>
        </div>

        <ul class="sidebar-menu">
            <li>
                <a href="dashboard.php" class="active">
                    <i class="fa-solid fa-chart-pie"></i>
                    Dashboard
                </a>
            </li>

            <li>
                <a href="products.php">
                    <i class="fa-solid fa-box"></i>
                    Products Catalog
                </a>
            </li>

            <li>
                <a href="stock.php">
                    <i class="fa-solid fa-warehouse"></i>
                    Stock Inventory
                </a>
            </li>

            <li>
                <a href="reports.php">
                    <i class="fa-solid fa-file-invoice"></i>
                    DBMS Reports
                </a>
            </li>
        </ul>

        <!-- Logged-in user -->
        <div class="sidebar-user">
            <div class="d-flex align-items-center justify-content-between gap-2">
                <div>
                    <div class="text-white fw-bold small">
                        <?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>
                    </div>

                    <span class="badge <?php echo $role_badge; ?>">
                        <?php echo htmlspecialchars($role, ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                </div>

                <a href="logout.php" class="text-danger" title="Sign out">
                    <i class="fa-solid fa-right-from-bracket fa-lg"></i>
                </a>
            </div>
        </div>

    </aside>

    <!-- Mobile sidebar background -->
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <!-- Main area -->
    <main class="main-wrapper">

        <!-- Top navigation -->
        <header class="top-navbar">

            <div class="d-flex align-items-center gap-3">
                <button
                    type="button"
                    class="mobile-menu-btn"
                    id="mobileMenuBtn"
                    aria-label="Open navigation menu"
                    aria-controls="sidebar"
                    aria-expanded="false"
                >
                    <i class="fa-solid fa-bars"></i>
                </button>

                <div>
                    <h5>Inventory Dashboard</h5>
                    <small class="text-muted">
                        Welcome back,
                        <?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>
                    </small>
                </div>
            </div>

            <div class="d-flex align-items-center gap-2">
                <span class="badge bg-light text-dark border px-3 py-2">
                    <i class="fa-solid fa-user-shield text-primary me-1"></i>
                    <?php echo htmlspecialchars($role, ENT_QUOTES, 'UTF-8'); ?>
                </span>

                <a href="logout.php" class="btn btn-outline-danger btn-sm">
                    <i class="fa-solid fa-right-from-bracket me-1"></i>
                    <span class="d-none d-sm-inline">Logout</span>
                </a>
            </div>

        </header>

        <!-- Dashboard content -->
        <div class="content-body">

            <div class="d-flex justify-content-between align-items-center flex-wrap gap-3 mb-4">
                <div>
                    <h1 class="page-title">Dashboard Overview</h1>
                    <p class="page-subtitle mb-0">
                        Monitor your products, stock levels and inventory value.
                    </p>
                </div>

                <a href="stock.php" class="btn btn-primary">
                    <i class="fa-solid fa-plus me-2"></i>
                    Manage Stock
                </a>
            </div>

            <!-- Statistics cards -->
            <div class="row g-3 mb-4">

                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="stat-card">
                        <div class="d-flex align-items-center justify-content-between gap-3">
                            <div>
                                <h6>Total Products</h6>
                                <h3><?php echo number_format((int) $total_products); ?></h3>
                                <p><i class="fa-solid fa-box me-1"></i> Products registered</p>
                            </div>

                            <div class="stat-icon blue">
                                <i class="fa-solid fa-box-open"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="stat-card">
                        <div class="d-flex align-items-center justify-content-between gap-3">
                            <div>
                                <h6>Units in Stock</h6>
                                <h3><?php echo number_format((int) $total_units); ?></h3>
                                <p><i class="fa-solid fa-cubes me-1"></i> Total available units</p>
                            </div>

                            <div class="stat-icon green">
                                <i class="fa-solid fa-cubes-stacked"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="stat-card">
                        <div class="d-flex align-items-center justify-content-between gap-3">
                            <div>
                                <h6>Inventory Value</h6>
                                <h3>₹<?php echo number_format((float) $total_val, 2); ?></h3>
                                <p><i class="fa-solid fa-indian-rupee-sign me-1"></i> Current stock value</p>
                            </div>

                            <div class="stat-icon orange">
                                <i class="fa-solid fa-wallet"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-sm-6 col-xl-3">
                    <div class="stat-card">
                        <div class="d-flex align-items-center justify-content-between gap-3">
                            <div>
                                <h6>Warehouses</h6>
                                <h3><?php echo number_format((int) $total_wh); ?></h3>
                                <p><i class="fa-solid fa-location-dot me-1"></i> Registered locations</p>
                            </div>

                            <div class="stat-icon purple">
                                <i class="fa-solid fa-warehouse"></i>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Charts -->
            <div class="row g-4 mb-4">

                <div class="col-12 col-xl-7">
                    <div class="table-card p-3 p-md-4 h-100">
                        <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                            <div>
                                <h5 class="fw-bold mb-1">Warehouse Stock</h5>
                                <small class="text-muted">Units stored at each location</small>
                            </div>

                            <span class="stat-icon blue">
                                <i class="fa-solid fa-chart-column"></i>
                            </span>
                        </div>

                        <div style="position: relative; height: 300px;">
                            <canvas id="barWarehouseChart"></canvas>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-xl-5">
                    <div class="table-card p-3 p-md-4 h-100">
                        <div class="d-flex align-items-center justify-content-between gap-2 mb-3">
                            <div>
                                <h5 class="fw-bold mb-1">Category Valuation</h5>
                                <small class="text-muted">Stock value by category</small>
                            </div>

                            <span class="stat-icon green">
                                <i class="fa-solid fa-chart-pie"></i>
                            </span>
                        </div>

                        <div style="position: relative; height: 300px;">
                            <canvas id="pieCategoryChart"></canvas>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Recent stock activity -->
            <div class="table-card">

                <div class="card-heading">
                    <div>
                        <h5>
                            <i class="fa-solid fa-clock-rotate-left text-primary me-2"></i>
                            Recent Stock Activity
                        </h5>
                        <p>Latest changes recorded in the database audit log.</p>
                    </div>

                    <a href="reports.php" class="btn btn-light btn-sm">
                        View Reports
                        <i class="fa-solid fa-arrow-right ms-1"></i>
                    </a>
                </div>

                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Log ID</th>
                                <th>Stock ID</th>
                                <th>Action</th>
                                <th>Old Quantity</th>
                                <th>New Quantity</th>
                                <th>Timestamp</th>
                            </tr>
                        </thead>

                        <tbody>
                        <?php if ($recent_logs && mysqli_num_rows($recent_logs) > 0): ?>

                            <?php while ($log = mysqli_fetch_assoc($recent_logs)): ?>
                                <tr>
                                    <td class="text-muted">
                                        #<?php echo (int) $log['log_id']; ?>
                                    </td>

                                    <td>
                                        Stock #<?php echo (int) $log['stock_id']; ?>
                                    </td>

                                    <td>
                                        <?php
                                        $action = $log['action_type'] ?? '';
                                        $action_class = ($action === 'UPDATE_QTY')
                                            ? 'badge-success'
                                            : 'badge-warning';
                                        ?>

                                        <span class="badge <?php echo $action_class; ?>">
                                            <?php echo htmlspecialchars($action, ENT_QUOTES, 'UTF-8'); ?>
                                        </span>
                                    </td>

                                    <td>
                                        <?php echo htmlspecialchars((string) $log['old_quantity'], ENT_QUOTES, 'UTF-8'); ?>
                                    </td>

                                    <td class="fw-bold">
                                        <?php echo htmlspecialchars((string) $log['new_quantity'], ENT_QUOTES, 'UTF-8'); ?>
                                    </td>

                                    <td class="text-muted small">
                                        <?php echo htmlspecialchars((string) $log['change_timestamp'], ENT_QUOTES, 'UTF-8'); ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>

                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-4">
                                    <i class="fa-solid fa-inbox fa-2x mb-2 d-block"></i>
                                    No stock activity recorded yet.
                                </td>
                            </tr>
                        <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>

            <p class="text-muted small mt-4 mb-0">
                <i class="fa-solid fa-database me-1"></i>
                Dashboard data is loaded from your MySQL database.
            </p>

        </div>
    </main>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// Warehouse stock bar chart
const warehouseCanvas = document.getElementById('barWarehouseChart');

if (warehouseCanvas) {
    new Chart(warehouseCanvas, {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($wh_labels); ?>,
            datasets: [{
                label: 'Units Stored',
                data: <?php echo json_encode($wh_data); ?>,
                backgroundColor: 'rgba(2, 132, 199, 0.75)',
                borderColor: '#0284c7',
                borderWidth: 1,
                borderRadius: 7,
                maxBarThickness: 55
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: '#0f172a',
                    padding: 12,
                    cornerRadius: 8
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: '#e2e8f0'
                    },
                    ticks: {
                        precision: 0
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
}

// Category valuation doughnut chart
const categoryCanvas = document.getElementById('pieCategoryChart');

if (categoryCanvas) {
    new Chart(categoryCanvas, {
        type: 'doughnut',
        data: {
            labels: <?php echo json_encode($cat_labels); ?>,
            datasets: [{
                data: <?php echo json_encode($cat_data); ?>,
                backgroundColor: [
                    '#0284c7',
                    '#10b981',
                    '#f59e0b',
                    '#8b5cf6',
                    '#ec4899',
                    '#64748b',
                    '#14b8a6'
                ],
                borderColor: '#ffffff',
                borderWidth: 3,
                hoverOffset: 7
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            cutout: '65%',
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        padding: 16,
                        usePointStyle: true,
                        pointStyle: 'circle',
                        boxWidth: 9
                    }
                },
                tooltip: {
                    callbacks: {
                        label: function(context) {
                            const value = Number(context.raw || 0);
                            return ' ' + context.label + ': ₹' +
                                value.toLocaleString('en-IN', {
                                    minimumFractionDigits: 2,
                                    maximumFractionDigits: 2
                                });
                        }
                    }
                }
            }
        }
    });
}

// Responsive mobile sidebar
(function () {
    const sidebar = document.getElementById('sidebar');
    const menuButton = document.getElementById('mobileMenuBtn');
    const backdrop = document.getElementById('sidebarBackdrop');

    if (!sidebar || !menuButton || !backdrop) return;

    function openMenu() {
        sidebar.classList.add('show');
        backdrop.classList.add('show');
        menuButton.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
    }

    function closeMenu() {
        sidebar.classList.remove('show');
        backdrop.classList.remove('show');
        menuButton.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
    }

    menuButton.addEventListener('click', function () {
        if (sidebar.classList.contains('show')) {
            closeMenu();
        } else {
            openMenu();
        }
    });

    backdrop.addEventListener('click', closeMenu);

    sidebar.querySelectorAll('.sidebar-menu a').forEach(function (link) {
        link.addEventListener('click', closeMenu);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') {
            closeMenu();
        }
    });

    window.addEventListener('resize', function () {
        if (window.innerWidth > 900) {
            closeMenu();
        }
    });
})();
</script>

</body>
</html>
