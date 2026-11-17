
<?php
session_start();

if (!isset($_SESSION['role'])) {
    header("Location: index.php");
    exit();
}

require_once "config/db_connect.php";

$role = $_SESSION['role'];
$fullName = $_SESSION['full_name'] ?? 'User';

if (!isset($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function h($value) {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

$msg = $_SESSION['product_msg'] ?? '';
$err = $_SESSION['product_err'] ?? '';

unset($_SESSION['product_msg'], $_SESSION['product_err']);

/* -------------------------------------------------
   HANDLE PRODUCT ACTIONS
------------------------------------------------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $token)) {
        $err = "Invalid request. Please refresh the page and try again.";
    } else {
        $action = $_POST['action'] ?? '';

        /* ADD PRODUCT - ADMIN ONLY */
        if ($action === 'add_product') {
            if ($role !== 'ADMIN') {
                $err = "Only administrators can add products.";
            } else {
                $productName = trim($_POST['product_name'] ?? '');
                $sku = strtoupper(trim($_POST['sku'] ?? ''));
                $price = filter_var($_POST['price'] ?? null, FILTER_VALIDATE_FLOAT);
                $categoryId = filter_var(
                    $_POST['category_id'] ?? null,
                    FILTER_VALIDATE_INT
                );
                $supplierId = filter_var(
                    $_POST['supplier_id'] ?? null,
                    FILTER_VALIDATE_INT
                );

                if (
                    $productName === '' ||
                    $sku === '' ||
                    $price === false ||
                    $price <= 0 ||
                    $categoryId === false ||
                    $categoryId === null ||
                    $categoryId <= 0 ||
                    $supplierId === false ||
                    $supplierId === null ||
                    $supplierId <= 0
                ) {
                    $err = "Please enter valid product details. Price must be greater than zero.";
                } else {
                    $stmt = mysqli_prepare(
                        $conn,
                        "INSERT INTO products
                        (product_name, sku, price, category_id, supplier_id)
                        VALUES (?, ?, ?, ?, ?)"
                    );

                    mysqli_stmt_bind_param(
                        $stmt,
                        "ssdii",
                        $productName,
                        $sku,
                        $price,
                        $categoryId,
                        $supplierId
                    );

                    if (mysqli_stmt_execute($stmt)) {
                        $_SESSION['product_msg'] = "Product added successfully.";
                    } else {
                        $_SESSION['product_err'] =
                            "Could not add product. Check that the SKU is unique and the category and supplier exist.";
                    }

                    mysqli_stmt_close($stmt);

                    header("Location: products.php");
                    exit();
                }
            }
        }

        /* UPDATE PRICE - ADMIN ONLY */
        elseif ($action === 'update_price') {
            if ($role !== 'ADMIN') {
                $err = "Only administrators can update product prices.";
            } else {
                $productId = filter_var(
                    $_POST['product_id'] ?? null,
                    FILTER_VALIDATE_INT
                );
                $newPrice = filter_var(
                    $_POST['new_price'] ?? null,
                    FILTER_VALIDATE_FLOAT
                );

                if (
                    $productId === false ||
                    $productId === null ||
                    $productId <= 0 ||
                    $newPrice === false ||
                    $newPrice <= 0
                ) {
                    $err = "Enter a valid product and a price greater than zero.";
                } else {
                    $stmt = mysqli_prepare(
                        $conn,
                        "UPDATE products SET price = ? WHERE product_id = ?"
                    );

                    mysqli_stmt_bind_param(
                        $stmt,
                        "di",
                        $newPrice,
                        $productId
                    );

                    if (mysqli_stmt_execute($stmt)) {
                        $_SESSION['product_msg'] = "Product price updated successfully.";
                    } else {
                        $_SESSION['product_err'] = "Unable to update product price.";
                    }

                    mysqli_stmt_close($stmt);

                    header("Location: products.php");
                    exit();
                }
            }
        }

        /* DELETE PRODUCT - ADMIN ONLY */
        elseif ($action === 'delete_product') {
            if ($role !== 'ADMIN') {
                $err = "Only administrators can delete products.";
            } else {
                $productId = filter_var(
                    $_POST['product_id'] ?? null,
                    FILTER_VALIDATE_INT
                );

                if (
                    $productId === false ||
                    $productId === null ||
                    $productId <= 0
                ) {
                    $err = "Invalid product ID.";
                } else {
                    $stmt = mysqli_prepare(
                        $conn,
                        "DELETE FROM products WHERE product_id = ?"
                    );

                    mysqli_stmt_bind_param($stmt, "i", $productId);

                    if (mysqli_stmt_execute($stmt)) {
                        if (mysqli_stmt_affected_rows($stmt) > 0) {
                            $_SESSION['product_msg'] = "Product deleted successfully.";
                        } else {
                            $_SESSION['product_err'] = "Product not found.";
                        }
                    } else {
                        $_SESSION['product_err'] =
                            "Unable to delete this product. It may be referenced by existing stock records.";
                    }

                    mysqli_stmt_close($stmt);

                    header("Location: products.php");
                    exit();
                }
            }
        }
    }
}

/* -------------------------------------------------
   FETCH PRODUCTS, CATEGORIES AND SUPPLIERS
------------------------------------------------- */

$query = "
    SELECT
        p.product_id,
        p.product_name,
        p.sku,
        p.price,
        c.category_name,
        s.supplier_name
    FROM products p
    INNER JOIN categories c
        ON p.category_id = c.category_id
    INNER JOIN suppliers s
        ON p.supplier_id = s.supplier_id
    ORDER BY p.product_id DESC
";

$result = mysqli_query($conn, $query);

if (!$result) {
    die("Unable to load products. Please check your database connection and table structure.");
}

$products = [];

while ($row = mysqli_fetch_assoc($result)) {
    $products[] = $row;
}

$categories = [];
$categoryResult = mysqli_query(
    $conn,
    "SELECT category_id, category_name FROM categories ORDER BY category_name"
);

if ($categoryResult) {
    while ($row = mysqli_fetch_assoc($categoryResult)) {
        $categories[] = $row;
    }
}

$suppliers = [];
$supplierResult = mysqli_query(
    $conn,
    "SELECT supplier_id, supplier_name FROM suppliers ORDER BY supplier_name"
);

if ($supplierResult) {
    while ($row = mysqli_fetch_assoc($supplierResult)) {
        $suppliers[] = $row;
    }
}

$totalProducts = count($products);
$totalValue = 0;

foreach ($products as $product) {
    $totalValue += (float)$product['price'];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Products | Warehouse OS</title>

    <link rel="stylesheet" href="css/style.css">
    <script src="js/app.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
          rel="stylesheet">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">

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
                <a href="products.php" class="active">
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
                <a href="reports.php">
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
                <button type="button"
                        class="mobile-menu-btn"
                        id="mobileMenuBtn"
                        aria-label="Open navigation">
                    <i class="fa-solid fa-bars"></i>
                </button>

                <div>
                    <div class="page-eyebrow">INVENTORY MANAGEMENT</div>
                    <h5 class="mb-0 fw-bold">Products Catalog</h5>
                    <small class="text-muted">
                        Manage your product master records
                    </small>
                </div>
            </div>

            <?php if ($role === 'ADMIN'): ?>
                <button type="button"
                        class="btn btn-primary"
                        data-bs-toggle="modal"
                        data-bs-target="#addProductModal">
                    <i class="fa-solid fa-plus me-2"></i>
                    <span>Add Product</span>
                </button>
            <?php else: ?>
                <button type="button"
                        class="btn btn-outline-secondary"
                        disabled
                        title="Admin access required">
                    <i class="fa-solid fa-lock me-2"></i>
                    Read Only
                </button>
            <?php endif; ?>

        </header>

        <div class="content-body">

            <!-- ALERTS -->
            <?php if ($msg !== ''): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="fa-solid fa-circle-check me-2"></i>
                    <?= h($msg) ?>
                    <button type="button" class="btn-close"
                            data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <?php if ($err !== ''): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i>
                    <?= h($err) ?>
                    <button type="button" class="btn-close"
                            data-bs-dismiss="alert" aria-label="Close"></button>
                </div>
            <?php endif; ?>

            <!-- SUMMARY CARDS -->
            <div class="row g-3 mb-4">

                <div class="col-md-6 col-xl-4">
                    <div class="stat-card">
                        <div class="stat-card-top">
                            <div>
                                <p class="stat-label">Total Products</p>
                                <h3 class="stat-value"><?= $totalProducts ?></h3>
                                <span class="stat-note">Registered catalog items</span>
                            </div>

                            <div class="stat-icon icon-blue">
                                <i class="fa-solid fa-boxes-stacked"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-xl-4">
                    <div class="stat-card">
                        <div class="stat-card-top">
                            <div>
                                <p class="stat-label">Catalog Price Total</p>
                                <h3 class="stat-value">
                                    ₹<?= number_format($totalValue, 2) ?>
                                </h3>
                                <span class="stat-note">Sum of listed unit prices</span>
                            </div>

                            <div class="stat-icon icon-green">
                                <i class="fa-solid fa-indian-rupee-sign"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-md-6 col-xl-4">
                    <div class="stat-card">
                        <div class="stat-card-top">
                            <div>
                                <p class="stat-label">Your Access</p>
                                <h3 class="stat-value fs-4">
                                    <?= $role === 'ADMIN' ? 'Administrator' : 'Read Only' ?>
                                </h3>
                                <span class="stat-note">
                                    <?= $role === 'ADMIN'
                                        ? 'Full catalog management'
                                        : 'View product information' ?>
                                </span>
                            </div>

                            <div class="stat-icon icon-purple">
                                <i class="fa-solid fa-shield-halved"></i>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- PRODUCT TABLE CARD -->
            <section class="table-card">

                <div class="section-heading">
                    <div>
                        <h5 class="mb-1">Product Directory</h5>
                        <p class="text-muted small mb-0">
                            Search and filter your registered products.
                        </p>
                    </div>

                    <span class="record-count" id="productCounter">
                        <?= $totalProducts ?> products
                    </span>
                </div>

                <!-- SEARCH AND FILTER -->
                <div class="product-toolbar">

                    <div class="search-box">
                        <i class="fa-solid fa-magnifying-glass"></i>
                        <input type="search"
                               id="liveSearchInput"
                               placeholder="Search by product name or SKU..."
                               aria-label="Search products">
                    </div>

                    <select id="categoryFilter"
                            class="form-select category-select"
                            aria-label="Filter by category">
                        <option value="">All Categories</option>

                        <?php foreach ($categories as $category): ?>
                            <option value="<?= h($category['category_name']) ?>">
                                <?= h($category['category_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>

                    <button type="button"
                            class="btn btn-light border"
                            id="resetFilters">
                        <i class="fa-solid fa-rotate-left me-1"></i>
                        Reset
                    </button>

                </div>

                <!-- TABLE -->
                <div class="table-responsive">
                    <table class="table align-middle mb-0" id="productsTable">

                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Product</th>
                            <th>SKU</th>
                            <th>Unit Price</th>
                            <th>Category</th>
                            <th>Supplier</th>
                            <th class="text-end">Actions</th>
                        </tr>
                        </thead>

                        <tbody>

                        <?php foreach ($products as $product): ?>
                            <tr class="product-row">

                                <td>
                                    <span class="product-id">
                                        #<?= (int)$product['product_id'] ?>
                                    </span>
                                </td>

                                <td>
                                    <div class="product-name product-title">
                                        <?= h($product['product_name']) ?>
                                    </div>
                                    <small class="text-muted">Product record</small>
                                </td>

                                <td>
                                    <span class="sku-badge product-sku">
                                        <?= h($product['sku']) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="price-text">
                                        ₹<?= number_format((float)$product['price'], 2) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="category-badge product-category">
                                        <?= h($product['category_name']) ?>
                                    </span>
                                </td>

                                <td>
                                    <span class="supplier-text">
                                        <i class="fa-regular fa-building me-1"></i>
                                        <?= h($product['supplier_name']) ?>
                                    </span>
                                </td>

                                <td class="text-end">
                                    <?php if ($role === 'ADMIN'): ?>

                                        <div class="d-inline-flex gap-1">

                                            <button type="button"
                                                    class="btn btn-sm btn-outline-primary edit-price-btn"
                                                    data-id="<?= (int)$product['product_id'] ?>"
                                                    data-name="<?= h($product['product_name']) ?>"
                                                    data-price="<?= h($product['price']) ?>"
                                                    title="Update price">
                                                <i class="fa-solid fa-pen"></i>
                                            </button>

                                            <form method="POST"
                                                  action="products.php"
                                                  class="delete-form"
                                                  data-name="<?= h($product['product_name']) ?>">

                                                <input type="hidden" name="csrf_token"
                                                       value="<?= h($_SESSION['csrf_token']) ?>">

                                                <input type="hidden" name="action"
                                                       value="delete_product">

                                                <input type="hidden" name="product_id"
                                                       value="<?= (int)$product['product_id'] ?>">

                                                <button type="submit"
                                                        class="btn btn-sm btn-outline-danger"
                                                        title="Delete product">
                                                    <i class="fa-solid fa-trash"></i>
                                                </button>
                                            </form>

                                        </div>

                                    <?php else: ?>
                                        <span class="readonly-label">
                                            <i class="fa-solid fa-lock me-1"></i>
                                            View only
                                        </span>
                                    <?php endif; ?>
                                </td>

                            </tr>
                        <?php endforeach; ?>

                        <tr id="noProductsRow"
                            style="<?= $totalProducts === 0 ? '' : 'display:none;' ?>">
                            <td colspan="7" class="text-center py-5">
                                <div class="empty-state">
                                    <i class="fa-solid fa-box-open"></i>
                                    <h6 class="mt-3">No products found</h6>
                                    <p class="text-muted small mb-0">
                                        Add a product or change your search filters.
                                    </p>
                                </div>
                            </td>
                        </tr>

                        </tbody>
                    </table>
                </div>

                <div class="table-footer">
                    <span id="tableSummary">
                        Showing <?= $totalProducts ?> products
                    </span>
                    <span><i class="fa-solid fa-database me-1"></i> MySQL Database</span>
                </div>

            </section>

            <p class="text-muted small mt-3 mb-0">
                <i class="fa-solid fa-circle-info me-1"></i>
                Product prices represent catalog unit prices, not total stock valuation.
            </p>

        </div>
    </main>
</div>


<?php if ($role === 'ADMIN'): ?>

<!-- ADD PRODUCT MODAL -->
<div class="modal fade" id="addProductModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <form method="POST" action="products.php" id="addProductForm">

                <div class="modal-header">
                    <div>
                        <h5 class="modal-title fw-bold">Add New Product</h5>
                        <p class="text-muted small mb-0">
                            Register a new catalog item.
                        </p>
                    </div>
                    <button type="button" class="btn-close"
                            data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">

                    <input type="hidden" name="csrf_token"
                           value="<?= h($_SESSION['csrf_token']) ?>">

                    <input type="hidden" name="action" value="add_product">

                    <div class="mb-3">
                        <label class="form-label">Product Name</label>
                        <input type="text"
                               name="product_name"
                               class="form-control"
                               maxlength="150"
                               placeholder="e.g. Laptop or SSD"
                               required>
                    </div>

                    <div class="row g-3">

                        <div class="col-md-6">
                            <label class="form-label">SKU Code</label>
                            <input type="text"
                                   name="sku"
                                   class="form-control"
                                   maxlength="100"
                                   placeholder="e.g. LAP-001"
                                   required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">Unit Price (₹)</label>
                            <input type="number"
                                   name="price"
                                   class="form-control"
                                   min="0.01"
                                   step="0.01"
                                   placeholder="0.00"
                                   required>
                        </div>

                    </div>

                    <div class="mt-3">
                        <label class="form-label">Category</label>
                        <select name="category_id" class="form-select" required>
                            <option value="">Choose a category</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?= (int)$category['category_id'] ?>">
                                    <?= h($category['category_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="mt-3">
                        <label class="form-label">Supplier</label>
                        <select name="supplier_id" class="form-select" required>
                            <option value="">Choose a supplier</option>
                            <?php foreach ($suppliers as $supplier): ?>
                                <option value="<?= (int)$supplier['supplier_id'] ?>">
                                    <?= h($supplier['supplier_name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php if (count($categories) === 0 || count($suppliers) === 0): ?>
                        <div class="alert alert-warning mt-3 mb-0">
                            Add at least one category and supplier before registering products.
                        </div>
                    <?php endif; ?>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light border"
                            data-bs-dismiss="modal">Cancel</button>

                    <button type="submit" class="btn btn-primary"
                            <?= (count($categories) === 0 || count($suppliers) === 0)
                                ? 'disabled' : '' ?>>
                        <i class="fa-solid fa-check me-1"></i>
                        Save Product
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>


<!-- UPDATE PRICE MODAL -->
<div class="modal fade" id="updatePriceModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <form method="POST" action="products.php" id="updatePriceForm">

                <div class="modal-header">
                    <div>
                        <h5 class="modal-title fw-bold">Update Product Price</h5>
                        <p class="text-muted small mb-0">
                            Change the unit price for this product.
                        </p>
                    </div>
                    <button type="button" class="btn-close"
                            data-bs-dismiss="modal" aria-label="Close"></button>
                </div>

                <div class="modal-body">

                    <input type="hidden" name="csrf_token"
                           value="<?= h($_SESSION['csrf_token']) ?>">

                    <input type="hidden" name="action" value="update_price">

                    <input type="hidden" name="product_id" id="edit_product_id">

                    <div class="mb-3">
                        <label class="form-label">Selected Product</label>
                        <input type="text"
                               id="edit_product_name"
                               class="form-control"
                               readonly>
                    </div>

                    <div>
                        <label class="form-label">New Unit Price (₹)</label>
                        <input type="number"
                               name="new_price"
                               id="edit_new_price"
                               class="form-control"
                               min="0.01"
                               step="0.01"
                               required>
                    </div>

                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-light border"
                            data-bs-dismiss="modal">Cancel</button>

                    <button type="submit" class="btn btn-primary">
                        <i class="fa-solid fa-floppy-disk me-1"></i>
                        Save Changes
                    </button>
                </div>

            </form>
        </div>
    </div>
</div>

<?php endif; ?>


<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function () {

    /* LIVE SEARCH AND CATEGORY FILTER */
    const searchInput = document.getElementById('liveSearchInput');
    const categoryFilter = document.getElementById('categoryFilter');
    const resetButton = document.getElementById('resetFilters');
    const rows = Array.from(document.querySelectorAll('.product-row'));
    const counter = document.getElementById('productCounter');
    const summary = document.getElementById('tableSummary');
    const noProductsRow = document.getElementById('noProductsRow');

    function filterProducts() {
        const query = searchInput.value.toLowerCase().trim();
        const selectedCategory = categoryFilter.value.toLowerCase().trim();

        let visibleCount = 0;

        rows.forEach(function (row) {
            const title = row.querySelector('.product-title').textContent.toLowerCase();
            const sku = row.querySelector('.product-sku').textContent.toLowerCase();
            const category = row.querySelector('.product-category').textContent.toLowerCase();

            const matchesSearch =
                title.includes(query) || sku.includes(query);

            const matchesCategory =
                selectedCategory === '' || category === selectedCategory;

            const visible = matchesSearch && matchesCategory;

            row.style.display = visible ? '' : 'none';

            if (visible) {
                visibleCount++;
            }
        });

        noProductsRow.style.display = visibleCount === 0 ? '' : 'none';

        counter.textContent = visibleCount + ' of ' + rows.length + ' products';
        summary.textContent = 'Showing ' + visibleCount + ' products';
    }

    searchInput.addEventListener('input', filterProducts);
    categoryFilter.addEventListener('change', filterProducts);

    resetButton.addEventListener('click', function () {
        searchInput.value = '';
        categoryFilter.value = '';
        filterProducts();
        searchInput.focus();
    });

    filterProducts();


    /* UPDATE PRICE MODAL */
    document.querySelectorAll('.edit-price-btn').forEach(function (button) {
        button.addEventListener('click', function () {
            document.getElementById('edit_product_id').value =
                this.dataset.id;

            document.getElementById('edit_product_name').value =
                this.dataset.name;

            document.getElementById('edit_new_price').value =
                this.dataset.price;

            const modalElement = document.getElementById('updatePriceModal');
            bootstrap.Modal.getOrCreateInstance(modalElement).show();
        });
    });


    /* DELETE CONFIRMATION */
    document.querySelectorAll('.delete-form').forEach(function (form) {
        form.addEventListener('submit', function (event) {
            const productName = this.dataset.name || 'this product';

            if (!window.confirm(
                'Are you sure you want to delete "' + productName + '"?\n\n' +
                'This action cannot be undone.'
            )) {
                event.preventDefault();
            }
        });
    });


    /* MOBILE SIDEBAR */
    const sidebar = document.getElementById('sidebar');
    const backdrop = document.getElementById('sidebarBackdrop');
    const menuButton = document.getElementById('mobileMenuBtn');

    function closeSidebar() {
        sidebar.classList.remove('show');
        backdrop.classList.remove('show');
    }

    if (menuButton && sidebar && backdrop) {
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