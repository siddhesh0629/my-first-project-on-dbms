
<?php
session_start();

if (!isset($_SESSION['role'])) {
    header("Location: index.php");
    exit();
}

require_once "config/db_connect.php";

$role = $_SESSION['role'];
$fullName = $_SESSION['full_name'] ?? 'User';

function h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/* -------------------------------------------------
   REPORT 1: WAREHOUSE AGGREGATION
------------------------------------------------- */
$q1 = "
    SELECT
        w.warehouse_name,
        COUNT(DISTINCT s.product_id) AS distinct_products_stored,
        COALESCE(SUM(s.quantity), 0) AS total_units_stored,
        COALESCE(MAX(s.quantity), 0) AS peak_batch_size
    FROM warehouses w
    LEFT JOIN stock s
        ON w.warehouse_id = s.warehouse_id
    GROUP BY w.warehouse_id, w.warehouse_name
    ORDER BY w.warehouse_name
";

$res1 = mysqli_query($conn, $q1);

/* -------------------------------------------------
   REPORT 2: GROUP BY AND HAVING
------------------------------------------------- */
$q2 = "
    SELECT
        c.category_name,
        COUNT(p.product_id) AS product_count,
        AVG(p.price) AS average_price
    FROM categories c
    INNER JOIN products p
        ON c.category_id = p.category_id
    GROUP BY c.category_id, c.category_name
    HAVING COUNT(p.product_id) >= 2
    ORDER BY product_count DESC
";

$res2 = mysqli_query($conn, $q2);

/* -------------------------------------------------
   REPORT 3: NESTED SUBQUERY
------------------------------------------------- */
$q3 = "
    SELECT product_name, sku, price
    FROM products
    WHERE price > (
        SELECT AVG(price)
        FROM products
    )
    ORDER BY price DESC
";

$res3 = mysqli_query($conn, $q3);

/* -------------------------------------------------
   REPORT 4: TRIGGER AUDIT LOG
------------------------------------------------- */
$q4 = "
    SELECT *
    FROM stock_log
    ORDER BY log_id DESC
    LIMIT 20
";

$res4 = mysqli_query($conn, $q4);

/* Check query results */
$reportError = false;

foreach ([$res1, $res2, $res3, $res4] as $reportResult) {
    if ($reportResult === false) {
        $reportError = true;
        break;
    }
}

/* Fetch results so tab changes do not lose data */
function fetchRows($result) {
    $rows = [];

    if ($result) {
        while ($row = mysqli_fetch_assoc($result)) {
            $rows[] = $row;
        }
    }

    return $rows;
}

$warehouses = fetchRows($res1);
$categories = fetchRows($res2);
$expensiveProducts = fetchRows($res3);
$auditLogs = fetchRows($res4);

/* Summary metrics */
$warehouseCount = count($warehouses);
$categoryCount = count($categories);
$expensiveCount = count($expensiveProducts);
$auditCount = count($auditLogs);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>DBMS Reports | Warehouse OS</title>

    <link rel="stylesheet" href="css/style.css">
    <script src="js/app.js"></script>

    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet"
    >

    <link
        rel="stylesheet"
        href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css"
    >

    <link rel="stylesheet" href="css/style.css">
</head>

<body>

<div class="app-container">

    <!-- Mobile sidebar backdrop -->
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">

        <div class="sidebar-header">
            <h4>
                <i class="fa-solid fa-boxes-stacked text-info"></i>
                Warehouse OS
            </h4>
            <small>Inventory Management</small>
        </div>

        <div class="sidebar-section-label">WORKSPACE</div>

        <ul class="sidebar-menu">

            <li>
                <a href="dashboard.php">
                    <i class="fa-solid fa-chart-pie"></i>
                    Dashboard
                </a>
            </li>

            <li>
                <a href="products.php">
                    <i class="fa-solid fa-box-open"></i>
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
                <a href="reports.php" class="active">
                    <i class="fa-solid fa-chart-column"></i>
                    DBMS Reports
                </a>
            </li>

        </ul>

        <div class="sidebar-user">
            <div class="d-flex align-items-center gap-2">

                <div class="user-avatar">
                    <i class="fa-solid fa-user"></i>
                </div>

                <div class="flex-grow-1 overflow-hidden">
                    <div class="text-white fw-bold small text-truncate">
                        <?= h($fullName) ?>
                    </div>

                    <span class="badge
                        <?= $role === 'ADMIN'
                            ? 'badge-role-admin'
                            : ($role === 'STOCK_MANAGER'
                                ? 'badge-role-manager'
                                : 'badge-role-user') ?>">
                        <?= h($role) ?>
                    </span>
                </div>

                <a href="logout.php" class="logout-link" title="Sign out">
                    <i class="fa-solid fa-right-from-bracket"></i>
                </a>

            </div>
        </div>

    </aside>

    <!-- MAIN CONTENT -->
    <main class="main-wrapper">

        <!-- TOP NAVBAR -->
        <header class="top-navbar">

            <div class="d-flex align-items-center gap-3">

                <button
                    type="button"
                    class="mobile-menu-btn"
                    id="mobileMenuBtn"
                    aria-label="Open navigation"
                >
                    <i class="fa-solid fa-bars"></i>
                </button>

                <div>
                    <div class="page-eyebrow">DATA INSIGHTS</div>
                    <h5 class="mb-0 fw-bold">DBMS Reports</h5>
                    <small class="text-muted">
                        SQL analytics, subqueries and trigger logs
                    </small>
                </div>

            </div>

            <button
                type="button"
                class="btn btn-outline-secondary"
                id="printReportBtn"
            >
                <i class="fa-solid fa-print me-2"></i>
                Print / Save PDF
            </button>

        </header>

        <div class="content-body">

            <?php if ($reportError): ?>

                <div class="alert alert-danger">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i>
                    One or more reports could not be loaded. Check that the
                    required tables and columns exist in your database.
                </div>

            <?php endif; ?>

            <!-- REPORT INTRO -->
            <div class="reports-welcome mb-4">
                <div>
                    <h4>Analytics Overview</h4>
                    <p>
                        Explore warehouse capacity, category pricing,
                        product comparisons and stock history.
                    </p>
                </div>

                <div class="reports-welcome-icon">
                    <i class="fa-solid fa-chart-line"></i>
                </div>
            </div>

            <!-- SUMMARY CARDS -->
            <div class="row g-3 mb-4">

                <div class="col-sm-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-card-top">
                            <div>
                                <p class="stat-label">Warehouses</p>
                                <h3 class="stat-value">
                                    <?= $warehouseCount ?>
                                </h3>
                                <span class="stat-note">Warehouse summary</span>
                            </div>

                            <div class="stat-icon icon-blue">
                                <i class="fa-solid fa-warehouse"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-card-top">
                            <div>
                                <p class="stat-label">Filtered Categories</p>
                                <h3 class="stat-value">
                                    <?= $categoryCount ?>
                                </h3>
                                <span class="stat-note">Categories with 2+ products</span>
                            </div>

                            <div class="stat-icon icon-purple">
                                <i class="fa-solid fa-layer-group"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-card-top">
                            <div>
                                <p class="stat-label">Above-Average Price</p>
                                <h3 class="stat-value">
                                    <?= $expensiveCount ?>
                                </h3>
                                <span class="stat-note">Products above average</span>
                            </div>

                            <div class="stat-icon icon-green">
                                <i class="fa-solid fa-indian-rupee-sign"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-sm-6 col-xl-3">
                    <div class="stat-card">
                        <div class="stat-card-top">
                            <div>
                                <p class="stat-label">Recent Audit Logs</p>
                                <h3 class="stat-value">
                                    <?= $auditCount ?>
                                </h3>
                                <span class="stat-note">Latest 20 entries maximum</span>
                            </div>

                            <div class="stat-icon icon-orange">
                                <i class="fa-solid fa-clock-rotate-left"></i>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- REPORT TABS -->
            <section class="table-card reports-card">

                <div class="section-heading reports-heading">
                    <div>
                        <h5>Report Explorer</h5>
                        <p class="text-muted small mb-0">
                            Select a report to inspect its results.
                        </p>
                    </div>

                    <span class="report-live-label">
                        <span></span>
                        MySQL Connected
                    </span>
                </div>

                <ul
                    class="nav nav-pills report-tabs"
                    id="reportTabs"
                    role="tablist"
                >

                    <li class="nav-item" role="presentation">
                        <button
                            class="nav-link active"
                            id="warehouse-tab"
                            data-bs-toggle="pill"
                            data-bs-target="#warehouse-report"
                            type="button"
                            role="tab"
                        >
                            <i class="fa-solid fa-warehouse"></i>
                            <span>Warehouse</span>
                        </button>
                    </li>

                    <li class="nav-item" role="presentation">
                        <button
                            class="nav-link"
                            id="having-tab"
                            data-bs-toggle="pill"
                            data-bs-target="#having-report"
                            type="button"
                            role="tab"
                        >
                            <i class="fa-solid fa-filter"></i>
                            <span>HAVING</span>
                        </button>
                    </li>

                    <li class="nav-item" role="presentation">
                        <button
                            class="nav-link"
                            id="subquery-tab"
                            data-bs-toggle="pill"
                            data-bs-target="#subquery-report"
                            type="button"
                            role="tab"
                        >
                            <i class="fa-solid fa-code-branch"></i>
                            <span>Subquery</span>
                        </button>
                    </li>

                    <li class="nav-item" role="presentation">
                        <button
                            class="nav-link"
                            id="audit-tab"
                            data-bs-toggle="pill"
                            data-bs-target="#audit-report"
                            type="button"
                            role="tab"
                        >
                            <i class="fa-solid fa-bolt"></i>
                            <span>Trigger Audit</span>
                        </button>
                    </li>

                </ul>

                <div class="tab-content" id="reportTabsContent">

                    <!-- REPORT 1 -->
                    <div
                        class="tab-pane fade show active"
                        id="warehouse-report"
                        role="tabpanel"
                        aria-labelledby="warehouse-tab"
                        tabindex="0"
                    >

                        <div class="report-description">
                            <div class="report-number">01</div>

                            <div>
                                <h6>Warehouse Aggregation Metrics</h6>
                                <p>
                                    Uses <code>COUNT()</code>,
                                    <code>SUM()</code>,
                                    <code>MAX()</code> and
                                    <code>GROUP BY</code> to summarize stock.
                                </p>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table report-table mb-0">

                                <thead>
                                <tr>
                                    <th>Warehouse</th>
                                    <th>Distinct Products</th>
                                    <th>Total Units</th>
                                    <th>Largest Batch</th>
                                </tr>
                                </thead>

                                <tbody>

                                <?php if (count($warehouses) > 0): ?>

                                    <?php foreach ($warehouses as $r): ?>
                                        <tr>
                                            <td class="fw-semibold">
                                                <i class="fa-solid fa-building me-2 text-muted"></i>
                                                <?= h($r['warehouse_name']) ?>
                                            </td>

                                            <td>
                                                <span class="report-value-pill">
                                                    <?= number_format((int)$r['distinct_products_stored']) ?>
                                                </span>
                                            </td>

                                            <td class="fw-bold">
                                                <?= number_format((int)$r['total_units_stored']) ?>
                                            </td>

                                            <td>
                                                <?= number_format((int)$r['peak_batch_size']) ?>
                                                <span class="text-muted">units</span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>

                                <?php else: ?>

                                    <tr>
                                        <td colspan="4" class="empty-report">
                                            No warehouse records found.
                                        </td>
                                    </tr>

                                <?php endif; ?>

                                </tbody>
                            </table>
                        </div>

                        <div class="report-footer">
                            <i class="fa-solid fa-circle-info me-1"></i>
                            Warehouses without stock are included with zero units.
                        </div>

                    </div>


                    <!-- REPORT 2 -->
                    <div
                        class="tab-pane fade"
                        id="having-report"
                        role="tabpanel"
                        aria-labelledby="having-tab"
                        tabindex="0"
                    >

                        <div class="report-description">
                            <div class="report-number">02</div>

                            <div>
                                <h6>Category Density Analysis</h6>
                                <p>
                                    Uses <code>GROUP BY</code> with
                                    <code>HAVING COUNT(product_id) &gt;= 2</code>
                                    to return categories containing at least two products.
                                </p>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table report-table mb-0">

                                <thead>
                                <tr>
                                    <th>Category</th>
                                    <th>Product Count</th>
                                    <th>Average Unit Price</th>
                                </tr>
                                </thead>

                                <tbody>

                                <?php if (count($categories) > 0): ?>

                                    <?php foreach ($categories as $r): ?>
                                        <tr>
                                            <td class="fw-semibold">
                                                <i class="fa-solid fa-layer-group me-2 text-muted"></i>
                                                <?= h($r['category_name']) ?>
                                            </td>

                                            <td>
                                                <span class="report-value-pill blue-pill">
                                                    <?= number_format((int)$r['product_count']) ?>
                                                    products
                                                </span>
                                            </td>

                                            <td class="price-text">
                                                ₹<?= number_format((float)$r['average_price'], 2) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>

                                <?php else: ?>

                                    <tr>
                                        <td colspan="3" class="empty-report">
                                            No categories currently satisfy the HAVING condition.
                                        </td>
                                    </tr>

                                <?php endif; ?>

                                </tbody>
                            </table>
                        </div>

                        <div class="report-footer">
                            <i class="fa-solid fa-circle-info me-1"></i>
                            HAVING filters grouped results after aggregation.
                        </div>

                    </div>


                    <!-- REPORT 3 -->
                    <div
                        class="tab-pane fade"
                        id="subquery-report"
                        role="tabpanel"
                        aria-labelledby="subquery-tab"
                        tabindex="0"
                    >

                        <div class="report-description">
                            <div class="report-number">03</div>

                            <div>
                                <h6>Above-Average Product Pricing</h6>
                                <p>
                                    Finds products whose unit price exceeds
                                    the average price calculated by a nested subquery.
                                </p>
                            </div>
                        </div>

                        <div class="query-preview">
                            <div class="query-preview-title">
                                <i class="fa-solid fa-terminal me-2"></i>
                                SQL Logic
                            </div>

                            <code>
                                SELECT product_name, sku, price
                                FROM products
                                WHERE price &gt; (SELECT AVG(price) FROM products);
                            </code>
                        </div>

                        <div class="table-responsive">
                            <table class="table report-table mb-0">

                                <thead>
                                <tr>
                                    <th>Product</th>
                                    <th>SKU</th>
                                    <th>Unit Price</th>
                                </tr>
                                </thead>

                                <tbody>

                                <?php if (count($expensiveProducts) > 0): ?>

                                    <?php foreach ($expensiveProducts as $r): ?>
                                        <tr>
                                            <td class="fw-semibold">
                                                <?= h($r['product_name']) ?>
                                            </td>

                                            <td>
                                                <code class="sku-badge">
                                                    <?= h($r['sku']) ?>
                                                </code>
                                            </td>

                                            <td class="price-text">
                                                ₹<?= number_format((float)$r['price'], 2) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>

                                <?php else: ?>

                                    <tr>
                                        <td colspan="3" class="empty-report">
                                            No products are priced above the average.
                                        </td>
                                    </tr>

                                <?php endif; ?>

                                </tbody>
                            </table>
                        </div>

                        <div class="report-footer">
                            <i class="fa-solid fa-circle-info me-1"></i>
                            The average is recalculated from the current products table.
                        </div>

                    </div>


                    <!-- REPORT 4 -->
                    <div
                        class="tab-pane fade"
                        id="audit-report"
                        role="tabpanel"
                        aria-labelledby="audit-tab"
                        tabindex="0"
                    >

                        <div class="report-description">
                            <div class="report-number">04</div>

                            <div>
                                <h6>Stock Trigger Audit Ledger</h6>
                                <p>
                                    Displays the latest records from
                                    <code>stock_log</code> to review stock changes.
                                </p>
                            </div>
                        </div>

                        <div class="table-responsive">
                            <table class="table report-table mb-0">

                                <thead>
                                <tr>
                                    <th>Log ID</th>
                                    <th>Stock ID</th>
                                    <th>Action</th>
                                    <th>Old Qty</th>
                                    <th>New Qty</th>
                                    <th>Timestamp</th>
                                </tr>
                                </thead>

                                <tbody>

                                <?php if (count($auditLogs) > 0): ?>

                                    <?php foreach ($auditLogs as $r): ?>
                                        <tr>
                                            <td class="text-muted">
                                                #<?= (int)$r['log_id'] ?>
                                            </td>

                                            <td class="fw-semibold">
                                                #<?= (int)$r['stock_id'] ?>
                                            </td>

                                            <td>
                                                <?php
                                                $actionType = (string)$r['action_type'];
                                                $isUpdate = $actionType === 'UPDATE_QTY';
                                                ?>

                                                <span class="audit-badge
                                                    <?= $isUpdate ? 'audit-update' : 'audit-delete' ?>">
                                                    <i class="fa-solid
                                                        <?= $isUpdate
                                                            ? 'fa-pen'
                                                            : 'fa-trash' ?> me-1"></i>
                                                    <?= h($actionType) ?>
                                                </span>
                                            </td>

                                            <td>
                                                <?= h($r['old_quantity']) ?>
                                            </td>

                                            <td class="fw-bold">
                                                <?= h($r['new_quantity']) ?>
                                            </td>

                                            <td class="text-muted small">
                                                <?= h($r['change_timestamp']) ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>

                                <?php else: ?>

                                    <tr>
                                        <td colspan="6" class="empty-report">
                                            No trigger log entries have been recorded yet.
                                        </td>
                                    </tr>

                                <?php endif; ?>

                                </tbody>
                            </table>
                        </div>

                        <div class="report-footer">
                            <i class="fa-solid fa-circle-info me-1"></i>
                            The page displays at most 20 recent log records.
                        </div>

                    </div>

                </div>

            </section>

            <div class="reports-bottom-note">
                <i class="fa-solid fa-shield-halved me-2"></i>
                Reports are generated from your current database records.
            </div>

        </div>

    </main>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {

    /* PRINT CURRENT REPORT */
    const printButton = document.getElementById('printReportBtn');

    printButton.addEventListener('click', function () {
        window.print();
    });

    /* MOBILE SIDEBAR */
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    const menuButton = document.getElementById('mobileMenuBtn');

    function closeSidebar() {
        sidebar.classList.remove('show');
        backdrop.classList.remove('show');
    }

    if (sidebar && backdrop && menuButton) {
        menuButton.addEventListener('click', function () {
            sidebar.classList.toggle('show');
            backdrop.classList.toggle('show');
        });

        backdrop.addEventListener('click', closeSidebar);

        sidebar.querySelectorAll('a').forEach(function (link) {
            link.addEventListener('click', closeSidebar);
        });
    }

});
</script>

</body>

</html>

