<?php
session_start();

if (!isset($_SESSION['role'])) {
    header("Location: index.php");
    exit();
}

require_once "config/db_connect.php";

$role = $_SESSION['role'];
$canManageStock = in_array($role, ['ADMIN', 'STOCK_MANAGER'], true);

$msg = "";
$err = "";

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrfToken = $_SESSION['csrf_token'];

function e($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

/* ADD STOCK */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_stock'])) {
    if (!$canManageStock) {
        $err = "You do not have permission to modify stock.";
    } elseif (!hash_equals($csrfToken, (string)($_POST['csrf_token'] ?? ''))) {
        $err = "Your session token is invalid. Refresh the page and try again.";
    } else {
        $p_id = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
        $w_id = filter_var($_POST['warehouse_id'] ?? null, FILTER_VALIDATE_INT);
        $qty  = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_INT);

        if ($p_id === false || $p_id === null || $p_id < 1 ||
            $w_id === false || $w_id === null || $w_id < 1 ||
            $qty === false || $qty === null || $qty < 1) {
            $err = "Please select a product, choose a warehouse, and enter a quantity greater than zero.";
        } else {
            $sql = "INSERT INTO stock (product_id, warehouse_id, quantity)
                    VALUES (?, ?, ?)
                    ON DUPLICATE KEY UPDATE quantity = quantity + VALUES(quantity)";
            $stmt = mysqli_prepare($conn, $sql);

            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "iii", $p_id, $w_id, $qty);

                if (mysqli_stmt_execute($stmt)) {
                    mysqli_stmt_close($stmt);
                    header("Location: stock.php?status=added");
                    exit();
                }

                error_log("Stock insert failed: " . mysqli_stmt_error($stmt));
                $err = "Stock could not be added. Please check the selected product and warehouse.";
                mysqli_stmt_close($stmt);
            } else {
                error_log("Stock prepare failed: " . mysqli_error($conn));
                $err = "Unable to process the stock request right now.";
            }
        }
    }
}

/* UPDATE STOCK QUANTITY — fires the stock AFTER UPDATE trigger */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_stock'])) {
    if (!$canManageStock) {
        $err = "You do not have permission to modify stock.";
    } elseif (!hash_equals($csrfToken, (string)($_POST['csrf_token'] ?? ''))) {
        $err = "Your session token is invalid. Refresh the page and try again.";
    } else {
        $s_id = filter_var($_POST['stock_id'] ?? null, FILTER_VALIDATE_INT);
        $qty  = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_INT);

        if ($s_id === false || $s_id === null || $s_id < 1 ||
            $qty === false || $qty === null || $qty < 1) {
            $err = "Select a valid stock record and enter a quantity greater than zero.";
        } else {
            $check = mysqli_prepare(
                $conn,
                "SELECT stock_id, quantity FROM stock WHERE stock_id = ?"
            );

            if (!$check) {
                error_log("Stock check prepare failed: " . mysqli_error($conn));
                $err = "Unable to process the update right now.";
            } else {
                mysqli_stmt_bind_param($check, "i", $s_id);

                if (!mysqli_stmt_execute($check)) {
                    error_log("Stock check failed: " . mysqli_stmt_error($check));
                    $err = "Unable to verify the selected stock record.";
                    mysqli_stmt_close($check);
                } else {
                    $checkResult = mysqli_stmt_get_result($check);
                    $existingStock = $checkResult ? mysqli_fetch_assoc($checkResult) : null;
                    mysqli_stmt_close($check);

                    if (!$existingStock) {
                        $err = "That stock record no longer exists.";
                    } elseif ((int)$existingStock['quantity'] === $qty) {
                        $msg = "The quantity is unchanged; no update was needed.";
                    } else {
                        $stmt = mysqli_prepare(
                            $conn,
                            "UPDATE stock SET quantity = ? WHERE stock_id = ?"
                        );

                        if ($stmt) {
                            mysqli_stmt_bind_param($stmt, "ii", $qty, $s_id);

                            if (mysqli_stmt_execute($stmt)) {
                                mysqli_stmt_close($stmt);
                                header("Location: stock.php?status=updated");
                                exit();
                            }

                            error_log("Stock update failed: " . mysqli_stmt_error($stmt));
                            $err = "Stock could not be updated. Please try again.";
                            mysqli_stmt_close($stmt);
                        } else {
                            error_log("Stock update prepare failed: " . mysqli_error($conn));
                            $err = "Unable to process the update right now.";
                        }
                    }
                }
            }
        }
    }
}

/* DELETE STOCK — POST request */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_stock'])) {
    if (!$canManageStock) {
        $err = "You do not have permission to delete stock.";
    } elseif (!hash_equals($csrfToken, (string)($_POST['csrf_token'] ?? ''))) {
        $err = "Your session token is invalid. Refresh the page and try again.";
    } else {
        $s_id = filter_var($_POST['stock_id'] ?? null, FILTER_VALIDATE_INT);

        if ($s_id === false || $s_id === null || $s_id < 1) {
            $err = "Invalid stock record selected.";
        } else {
            $stmt = mysqli_prepare($conn, "DELETE FROM stock WHERE stock_id = ?");

            if ($stmt) {
                mysqli_stmt_bind_param($stmt, "i", $s_id);

                if (mysqli_stmt_execute($stmt)) {
                    if (mysqli_stmt_affected_rows($stmt) > 0) {
                        mysqli_stmt_close($stmt);
                        header("Location: stock.php?status=deleted");
                        exit();
                    }

                    $err = "That stock record no longer exists.";
                } else {
                    error_log("Stock deletion failed: " . mysqli_stmt_error($stmt));
                    $err = "Stock could not be deleted. Please try again.";
                }

                mysqli_stmt_close($stmt);
            } else {
                error_log("Delete prepare failed: " . mysqli_error($conn));
                $err = "Unable to process the delete request right now.";
            }
        }
    }
}

/* SUCCESS MESSAGES */
if (isset($_GET['status'])) {
    if ($_GET['status'] === 'added') {
        $msg = "Stock added successfully. Inventory has been updated.";
    } elseif ($_GET['status'] === 'deleted') {
        $msg = "Stock record deleted successfully. Check stock_log for the audit entry.";
    } elseif ($_GET['status'] === 'updated') {
        $msg = "Stock quantity updated successfully. Check stock_log for the trigger audit entry.";
    }
}

/* FETCH INVENTORY */
$result = mysqli_query($conn, "SELECT * FROM view_current_stock ORDER BY stock_id ASC");

$stockRows = [];
$totalUnits = 0;
$lowStockCount = 0;
$totalValuation = 0.0;

if ($result) {
    while ($row = mysqli_fetch_assoc($result)) {
        $stockRows[] = $row;
        $quantity = (int)($row['quantity'] ?? 0);
        $totalUnits += $quantity;

        if ($quantity < 50) {
            $lowStockCount++;
        }

        $totalValuation += (float)($row['total_valuation'] ?? 0);
    }
} else {
    error_log("Inventory query failed: " . mysqli_error($conn));
    $err = "Inventory could not be loaded. Please check your database view.";
}

$prods = mysqli_query(
    $conn,
    "SELECT product_id, product_name FROM products ORDER BY product_name ASC"
);
$warehouses = mysqli_query(
    $conn,
    "SELECT warehouse_id, warehouse_name FROM warehouses ORDER BY warehouse_name ASC"
);

$warehouseRows = [];
if ($warehouses) {
    while ($w = mysqli_fetch_assoc($warehouses)) {
        $warehouseRows[] = $w;
    }
}

$productRows = [];
if ($prods) {
    while ($p = mysqli_fetch_assoc($prods)) {
        $productRows[] = $p;
    }
}

if (!$prods || !$warehouses) {
    error_log("Stock dropdown query failed: " . mysqli_error($conn));
    if (!$err) {
        $err = "Product or warehouse options could not be loaded.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stock Inventory | Warehouse OS</title>

    <link rel="stylesheet" href="css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

    <style>
        .stock-heading { display:flex; justify-content:space-between; align-items:center; gap:16px; flex-wrap:wrap; }
        .stock-summary { display:grid; grid-template-columns:repeat(4,minmax(0,1fr)); gap:16px; margin-bottom:24px; }
        .stock-stat { background:var(--card-bg,#fff); border:1px solid var(--border-color,#e8edf3); border-radius:14px; padding:19px; min-width:0; box-shadow:0 3px 14px rgba(20,35,60,.035); }
        .stock-stat-top { display:flex; align-items:center; justify-content:space-between; gap:10px; margin-bottom:15px; }
        .stock-stat-icon { width:42px; height:42px; border-radius:12px; display:grid; place-items:center; background:#eaf4ff; color:#2563eb; font-size:17px; }
        .stock-stat-icon.green { color:#15803d; background:#e9f9ef; }
        .stock-stat-icon.orange { color:#c2410c; background:#fff1e7; }
        .stock-stat-icon.purple { color:#7c3aed; background:#f2eaff; }
        .stock-stat-label { color:#748094; font-size:12px; font-weight:600; margin-bottom:5px; }
        .stock-stat-value { color:#17233b; font-size:clamp(20px,2vw,27px); font-weight:750; line-height:1.25; overflow-wrap:anywhere; }
        .stock-toolbar { display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px; margin-bottom:16px; }
        .stock-filters { display:flex; align-items:center; gap:10px; flex:1; flex-wrap:wrap; }
        .stock-search-wrap { position:relative; flex:1; min-width:210px; max-width:390px; }
        .stock-search-wrap > i { position:absolute; left:13px; top:50%; transform:translateY(-50%); color:#8994a5; }
        .stock-search-wrap input { width:100%; min-height:40px; padding:9px 36px 9px 38px; border:1px solid #dfe5ee; border-radius:9px; background:#fff; outline:none; }
        .stock-search-wrap input:focus,.stock-filters select:focus { border-color:#60a5fa; box-shadow:0 0 0 3px rgba(59,130,246,.10); }
        .stock-search-clear { position:absolute; right:8px; top:50%; transform:translateY(-50%); border:0; background:transparent; color:#758195; padding:5px; }
        .stock-filters select { width:auto; min-width:190px; min-height:40px; border-radius:9px; border-color:#dfe5ee; }
        .stock-table-card { background:#fff; border:1px solid #e8edf3; border-radius:14px; overflow:hidden; box-shadow:0 3px 14px rgba(20,35,60,.035); }
        .stock-table-head { padding:17px 20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px; border-bottom:1px solid #edf0f5; }
        #stockTable { white-space:nowrap; }
        #stockTable thead th { background:#f8fafc; color:#64748b; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:.045em; padding:14px 16px; border-bottom:1px solid #e9edf4; }
        #stockTable tbody td { padding:15px 16px; vertical-align:middle; border-color:#eff2f6; }
        #stockTable tbody tr { transition:background .15s ease; }
        #stockTable tbody tr:hover { background:#f8fbff; }
        .stock-product-icon { display:inline-grid; place-items:center; width:35px; height:35px; margin-right:10px; border-radius:10px; background:#eef5ff; color:#2563eb; vertical-align:middle; }
        .stock-quantity { display:inline-flex; align-items:center; gap:7px; border-radius:7px; padding:6px 9px; font-size:12px; font-weight:700; }
        .stock-quantity.low { background:#fff1e6; color:#b45309; }
        .stock-quantity.normal { background:#e9f9ef; color:#15803d; }
        .stock-empty { text-align:center; padding:48px 20px!important; color:#7b8798; white-space:normal; }
        .stock-empty i { font-size:32px; color:#a7b3c4; margin-bottom:12px; }
        .stock-empty h6 { color:#334155; font-weight:700; }
        .stock-modal-icon { display:inline-grid; place-items:center; width:38px; height:38px; background:#e9f9ef; color:#15803d; border-radius:11px; }
        .stock-modal .form-label { color:#475569; font-weight:600; font-size:13px; }
        .stock-modal .form-control,.stock-modal .form-select { min-height:44px; border-color:#dfe5ee; border-radius:9px; }
        .stock-modal .form-control:focus,.stock-modal .form-select:focus { border-color:#60a5fa; box-shadow:0 0 0 3px rgba(59,130,246,.10); }
        @media (max-width:1000px) { .stock-summary { grid-template-columns:repeat(2,minmax(0,1fr)); } }
        @media (max-width:600px) {
            .stock-summary { gap:10px; }
            .stock-stat { padding:13px; }
            .stock-stat-icon { width:35px; height:35px; }
            .stock-filters,.stock-search-wrap,.stock-filters select { width:100%; max-width:none; }
            .stock-toolbar > * { width:100%; }
            .stock-heading .btn { width:100%; }
            #stockTable thead th,#stockTable tbody td { padding:12px; }
        }
    </style>
</head>
<body>
<div class="app-container">
    <aside class="sidebar">
        <div class="sidebar-header">
            <h4><i class="fa-solid fa-boxes-stacked text-info"></i> Warehouse OS</h4>
        </div>
        <ul class="sidebar-menu">
            <li><a href="dashboard.php"><i class="fa-solid fa-chart-pie"></i> Dashboard</a></li>
            <li><a href="products.php"><i class="fa-solid fa-box"></i> Products Catalog</a></li>
            <li><a href="stock.php" class="active"><i class="fa-solid fa-warehouse"></i> Stock Inventory</a></li>
            <li><a href="reports.php"><i class="fa-solid fa-file-invoice"></i> DBMS Reports</a></li>
        </ul>
        <div class="sidebar-user">
            <div class="d-flex align-items-center justify-content-between gap-2">
                <div>
                    <div class="text-white fw-bold small">
                        <?= e($_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User') ?>
                    </div>
                    <span class="badge <?= $role === 'ADMIN' ? 'badge-role-admin' : ($role === 'STOCK_MANAGER' ? 'badge-role-manager' : 'badge-role-user') ?>">
                        <?= e($role) ?>
                    </span>
                </div>
                <a href="logout.php" class="text-danger" title="Sign out" aria-label="Sign out">
                    <i class="fa-solid fa-right-from-bracket fa-lg"></i>
                </a>
            </div>
        </div>
    </aside>

    <main class="main-wrapper">
        <header class="top-navbar">
            <div>
                <h5 class="mb-1 fw-bold">Stock Inventory</h5>
                <small class="text-muted">Monitor stock levels, warehouse allocation and inventory value.</small>
            </div>
            <?php if ($canManageStock): ?>
                <button type="button" class="btn btn-success btn-sm px-3 fw-semibold"
                        data-bs-toggle="modal" data-bs-target="#addStockModal">
                    <i class="fa-solid fa-plus me-1"></i> Add New Stock
                </button>
            <?php else: ?>
                <button type="button" class="btn btn-outline-secondary btn-sm" disabled>
                    <i class="fa-solid fa-lock me-1"></i> Read-only access
                </button>
            <?php endif; ?>
        </header>

        <div class="content-body">
            <?php if ($msg): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fa-solid fa-circle-check me-2"></i><?= e($msg) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>
            <?php if ($err): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i><?= e($err) ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <section class="stock-summary" aria-label="Inventory summary">
                <div class="stock-stat">
                    <div class="stock-stat-top"><div class="stock-stat-label">Stock Records</div><div class="stock-stat-icon"><i class="fa-solid fa-layer-group"></i></div></div>
                    <div class="stock-stat-value"><?= number_format(count($stockRows)) ?></div>
                    <small class="text-muted">Product and warehouse entries</small>
                </div>
                <div class="stock-stat">
                    <div class="stock-stat-top"><div class="stock-stat-label">Total Units</div><div class="stock-stat-icon green"><i class="fa-solid fa-cubes-stacked"></i></div></div>
                    <div class="stock-stat-value"><?= number_format($totalUnits) ?></div>
                    <small class="text-muted">Units currently recorded</small>
                </div>
                <div class="stock-stat">
                    <div class="stock-stat-top"><div class="stock-stat-label">Low Stock</div><div class="stock-stat-icon orange"><i class="fa-solid fa-triangle-exclamation"></i></div></div>
                    <div class="stock-stat-value"><?= number_format($lowStockCount) ?></div>
                    <small class="text-muted">Records with fewer than 50 units</small>
                </div>
                <div class="stock-stat">
                    <div class="stock-stat-top"><div class="stock-stat-label">Inventory Value</div><div class="stock-stat-icon purple"><i class="fa-solid fa-indian-rupee-sign"></i></div></div>
                    <div class="stock-stat-value">₹<?= number_format($totalValuation, 2) ?></div>
                    <small class="text-muted">Total recorded stock valuation</small>
                </div>
            </section>

            <section class="stock-toolbar">
                <div class="stock-filters">
                    <div class="stock-search-wrap">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="search" id="stockSearchInput" placeholder="Search product, SKU or batch ID..." aria-label="Search stock inventory">
                        <button type="button" class="stock-search-clear" id="clearStockSearch" title="Clear search" aria-label="Clear search">
                            <i class="fa-solid fa-xmark"></i>
                        </button>
                    </div>
                    <select id="warehouseFilter" class="form-select form-select-sm" aria-label="Filter by warehouse">
                        <option value="">All warehouses</option>
                        <?php foreach ($warehouseRows as $w): ?>
                            <option value="<?= e($w['warehouse_name']) ?>"><?= e($w['warehouse_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <select id="quantityFilter" class="form-select form-select-sm" aria-label="Filter by stock level">
                        <option value="">All stock levels</option>
                        <option value="low">Low stock (&lt; 50)</option>
                        <option value="normal">Healthy stock (50+)</option>
                        <option value="zero">Zero units</option>
                    </select>
                </div>
                <button type="button" class="btn btn-outline-secondary btn-sm" id="resetStockFilters">
                    <i class="fa-solid fa-rotate-left me-1"></i> Reset filters
                </button>
            </section>

            <section class="stock-table-card">
                <div class="stock-table-head">
                    <div>
                        <h6 class="mb-1 fw-bold">Inventory Records</h6>
                        <small class="text-muted">Search and filter stock across all warehouses.</small>
                    </div>
                    <span class="badge bg-light text-dark border" id="stockCounter"><?= count($stockRows) ?> records</span>
                </div>
                <div class="table-responsive">
                    <table class="table mb-0" id="stockTable">
                        <thead>
                        <tr>
                            <th>Batch ID</th><th>Product</th><th>SKU</th><th>Warehouse</th>
                            <th>Quantity</th><th>Valuation</th><th>Last Updated</th><th class="text-end">Actions</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($stockRows as $row): ?>
                            <?php
                            $stockId = (int)($row['stock_id'] ?? 0);
                            $quantity = (int)($row['quantity'] ?? 0);
                            $productName = (string)($row['product_name'] ?? '');
                            $sku = (string)($row['sku'] ?? '');
                            $warehouseName = (string)($row['warehouse_name'] ?? '');
                            $quantityClass = $quantity < 50 ? 'low' : 'normal';
                            ?>
                            <tr class="stock-row"
                                data-product="<?= e($productName) ?>"
                                data-sku="<?= e($sku) ?>"
                                data-batch="<?= $stockId ?>"
                                data-warehouse="<?= e($warehouseName) ?>"
                                data-quantity="<?= $quantity ?>">
                                <td><span class="text-muted fw-bold">#<?= $stockId ?></span></td>
                                <td>
                                    <span class="stock-product-icon"><i class="fa-solid fa-box"></i></span>
                                    <span class="fw-semibold stock-product"><?= e($productName) ?></span>
                                </td>
                                <td><code class="bg-light px-2 py-1 rounded border text-primary"><?= e($sku) ?></code></td>
                                <td><span class="badge bg-light text-dark border stock-wh"><i class="fa-solid fa-location-dot text-danger me-1"></i><?= e($warehouseName) ?></span></td>
                                <td>
                                    <span class="stock-quantity <?= $quantityClass ?>">
                                        <i class="fa-solid <?= $quantity < 50 ? 'fa-circle-exclamation' : 'fa-circle-check' ?>"></i>
                                        <?= number_format($quantity) ?> units
                                    </span>
                                </td>
                                <td class="fw-bold">₹<?= number_format((float)($row['total_valuation'] ?? 0), 2) ?></td>
                                <td class="text-muted small"><?= e($row['last_updated'] ?? '—') ?></td>
                                <td class="text-end">
                                    <?php if ($canManageStock): ?>
                                        <button type="button"
                                                class="btn btn-outline-primary btn-sm me-1 edit-stock-btn"
                                                data-stock-id="<?= $stockId ?>"
                                                data-product-name="<?= e($productName) ?>"
                                                data-warehouse-name="<?= e($warehouseName) ?>"
                                                data-quantity="<?= $quantity ?>"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editStockModal"
                                                title="Edit stock quantity"
                                                aria-label="Edit stock record <?= $stockId ?>">
                                            <i class="fa-solid fa-pen-to-square"></i>
                                        </button>
                                        <form method="POST" action="stock.php" class="d-inline delete-stock-form"
                                              data-stock-id="<?= $stockId ?>" data-product-name="<?= e($productName) ?>">
                                            <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                                            <input type="hidden" name="stock_id" value="<?= $stockId ?>">
                                            <button type="submit" name="delete_stock" value="1"
                                                    class="btn btn-outline-danger btn-sm"
                                                    title="Delete stock record" aria-label="Delete stock record <?= $stockId ?>">
                                                <i class="fa-solid fa-trash-can"></i>
                                            </button>
                                        </form>
                                    <?php else: ?>
                                        <span class="text-muted small" title="Read-only access"><i class="fa-solid fa-lock"></i></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <tr id="noStockResults" style="<?= count($stockRows) ? 'display:none;' : '' ?>">
                            <td colspan="8" class="stock-empty">
                                <i class="fa-solid fa-box-open d-block"></i>
                                <h6>No inventory records found</h6>
                                <p class="mb-0">Try changing your filters or add a stock entry.</p>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
                <div class="px-3 py-3 border-top d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <small class="text-muted"><i class="fa-solid fa-circle-info me-1"></i> Low-stock warning threshold: fewer than 50 units per record.</small>
                    <small class="text-muted">Warehouse OS · Inventory module</small>
                </div>
            </section>
        </div>
    </main>
</div>

<!-- ADD STOCK MODAL -->
<?php if ($canManageStock): ?>
<div class="modal fade stock-modal" id="addStockModal" tabindex="-1" aria-labelledby="addStockModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-bottom-0 px-4 pt-4">
                <div class="d-flex align-items-center gap-3">
                    <span class="stock-modal-icon"><i class="fa-solid fa-cubes-stacked"></i></span>
                    <div>
                        <h5 class="modal-title fw-bold mb-1" id="addStockModalLabel">Add Stock</h5>
                        <small class="text-muted">Update your warehouse inventory</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="stock.php" id="addStockForm">
                <div class="modal-body px-4 py-3">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="add_stock" value="1">
                    <div class="mb-3">
                        <label for="product_id" class="form-label">Product</label>
                        <select name="product_id" id="product_id" class="form-select" required>
                            <option value="">Choose a product</option>
                            <?php foreach ($productRows as $p): ?>
                                <option value="<?= (int)$p['product_id'] ?>"><?= e($p['product_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="warehouse_id" class="form-label">Destination warehouse</label>
                        <select name="warehouse_id" id="warehouse_id" class="form-select" required>
                            <option value="">Choose a warehouse</option>
                            <?php foreach ($warehouseRows as $w): ?>
                                <option value="<?= (int)$w['warehouse_id'] ?>"><?= e($w['warehouse_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-2">
                        <label for="quantity" class="form-label">Quantity to add</label>
                        <div class="input-group">
                            <input type="number" name="quantity" id="quantity" class="form-control" min="1" step="1" placeholder="e.g. 100" required>
                            <span class="input-group-text">units</span>
                        </div>
                        <div class="form-text mt-2"><i class="fa-solid fa-circle-info me-1"></i> If the same product already exists in this warehouse, its quantity will be increased.</div>
                    </div>
                </div>
                <div class="modal-footer border-top-0 px-4 pb-4">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success px-4" id="submitStockButton"><i class="fa-solid fa-check me-1"></i> Confirm Stock</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- EDIT STOCK MODAL -->
<div class="modal fade stock-modal" id="editStockModal" tabindex="-1" aria-labelledby="editStockModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header px-4 pt-4">
                <div class="d-flex align-items-center gap-3">
                    <span class="stock-modal-icon"><i class="fa-solid fa-pen-to-square"></i></span>
                    <div>
                        <h5 class="modal-title fw-bold mb-1" id="editStockModalLabel">Edit Stock</h5>
                        <small class="text-muted">Update an existing stock quantity</small>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="stock.php" id="editStockForm">
                <div class="modal-body px-4 py-3">
                    <input type="hidden" name="csrf_token" value="<?= e($csrfToken) ?>">
                    <input type="hidden" name="update_stock" value="1">
                    <input type="hidden" name="stock_id" id="edit_stock_id">
                    <div class="mb-3">
                        <label for="edit_product_name" class="form-label">Product</label>
                        <input type="text" id="edit_product_name" class="form-control" readonly>
                    </div>
                    <div class="mb-3">
                        <label for="edit_warehouse_name" class="form-label">Warehouse</label>
                        <input type="text" id="edit_warehouse_name" class="form-control" readonly>
                    </div>
                    <div class="mb-2">
                        <label for="edit_quantity" class="form-label">New quantity</label>
                        <div class="input-group">
                            <input type="number" name="quantity" id="edit_quantity" class="form-control" min="1" step="1" required>
                            <span class="input-group-text">units</span>
                        </div>
                        <div class="form-text mt-2">This replaces the current quantity; it does not add to it. The database trigger should log the change.</div>
                    </div>
                </div>
                <div class="modal-footer px-4 pb-4">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveStockUpdate"><i class="fa-solid fa-check me-1"></i> Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function () {
    const searchInput = document.getElementById('stockSearchInput');
    const warehouseFilter = document.getElementById('warehouseFilter');
    const quantityFilter = document.getElementById('quantityFilter');
    const clearSearch = document.getElementById('clearStockSearch');
    const resetButton = document.getElementById('resetStockFilters');
    const counter = document.getElementById('stockCounter');
    const emptyRow = document.getElementById('noStockResults');
    const rows = Array.from(document.querySelectorAll('.stock-row'));

    function filterStock() {
        const query = searchInput.value.toLowerCase().trim();
        const selectedWarehouse = warehouseFilter.value.toLowerCase();
        const selectedQuantity = quantityFilter.value;
        let visibleCount = 0;

        rows.forEach(function (row) {
            const searchableText = [
                row.dataset.product, row.dataset.sku,
                row.dataset.batch, row.dataset.warehouse
            ].join(' ').toLowerCase();

            const warehouse = (row.dataset.warehouse || '').toLowerCase();
            const quantity = Number(row.dataset.quantity || 0);
            const matchesSearch = searchableText.includes(query);
            const matchesWarehouse = !selectedWarehouse || warehouse === selectedWarehouse;
            let matchesQuantity = true;

            if (selectedQuantity === 'low') {
                matchesQuantity = quantity > 0 && quantity < 50;
            } else if (selectedQuantity === 'normal') {
                matchesQuantity = quantity >= 50;
            } else if (selectedQuantity === 'zero') {
                matchesQuantity = quantity === 0;
            }

            const visible = matchesSearch && matchesWarehouse && matchesQuantity;
            row.style.display = visible ? '' : 'none';
            if (visible) visibleCount++;
        });

        counter.textContent = 'Showing ' + visibleCount + ' of ' + rows.length + ' records';
        emptyRow.style.display = visibleCount === 0 ? '' : 'none';
        clearSearch.style.visibility = searchInput.value ? 'visible' : 'hidden';
    }

    searchInput.addEventListener('input', filterStock);
    warehouseFilter.addEventListener('change', filterStock);
    quantityFilter.addEventListener('change', filterStock);

    clearSearch.addEventListener('click', function () {
        searchInput.value = '';
        searchInput.focus();
        filterStock();
    });

    resetButton.addEventListener('click', function () {
        searchInput.value = '';
        warehouseFilter.value = '';
        quantityFilter.value = '';
        filterStock();
    });

    // Confirm stock deletion.
    document.querySelectorAll('.delete-stock-form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            const confirmed = window.confirm(
                'Delete stock record #' + form.dataset.stockId +
                ' for "' + form.dataset.productName + '"?\n\nThis will remove the stock record.'
            );
            if (!confirmed) event.preventDefault();
        });
    });

    // Populate the Edit Stock modal.
    document.querySelectorAll('.edit-stock-btn').forEach(function (button) {
        button.addEventListener('click', function () {
            document.getElementById('edit_stock_id').value = button.dataset.stockId;
            document.getElementById('edit_product_name').value = button.dataset.productName;
            document.getElementById('edit_warehouse_name').value = button.dataset.warehouseName;
            document.getElementById('edit_quantity').value = button.dataset.quantity;
        });
    });

    // Prevent accidental double submission of Add Stock.
    const addStockForm = document.getElementById('addStockForm');
    const submitButton = document.getElementById('submitStockButton');

    if (addStockForm && submitButton) {
        addStockForm.addEventListener('submit', function (event) {
            if (!addStockForm.checkValidity()) {
                event.preventDefault();
                addStockForm.reportValidity();
                return;
            }
            submitButton.disabled = true;
            submitButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Adding stock...';
        });
    }

    // Prevent accidental double submission of Update Stock.
    const editStockForm = document.getElementById('editStockForm');
    const saveStockButton = document.getElementById('saveStockUpdate');

    if (editStockForm && saveStockButton) {
        editStockForm.addEventListener('submit', function (event) {
            if (!editStockForm.checkValidity()) {
                event.preventDefault();
                editStockForm.reportValidity();
                return;
            }
            saveStockButton.disabled = true;
            saveStockButton.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
        });
    }

    filterStock();
});
</script>
</body>
</html>
