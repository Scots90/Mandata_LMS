<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// --- Corrected Security Check for Multi-Role System ---
// This page is for Admins only.
if (!isAdmin()) {
    header("Location: ../login.php");
    exit();
}

$feedback = '';

// Handle form submissions for adding a new product
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_product'])) {
    $product_name = trim($_POST['product_name']);
    if (!empty($product_name)) {
        $stmt = $conn->prepare("INSERT INTO products (product_name) VALUES (?)");
        $stmt->bind_param("s", $product_name);
        $feedback = $stmt->execute() ? 'Product added successfully!' : 'Error: Product may already exist.';
        $stmt->close();
    } else {
        $feedback = 'Product name cannot be empty.';
    }
}

// Fetch all products to display in the table
$products_result = $conn->query("SELECT * FROM products ORDER BY product_name");

include '../includes/header.php';
?>

<h2>Manage Products</h2>
<p>Add, edit, or delete product lines. Categories are grouped under these products.</p>

<div class="dashboard-grid">
    <div class="card" style="grid-column: 1 / -1;">
        <h3>Existing Products</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Product Name</th>
                    <th style="width: 150px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($products_result && $products_result->num_rows > 0): ?>
                    <?php while($row = $products_result->fetch_assoc()): ?>
                        <tr id="row-product-<?php echo $row['product_id']; ?>">
                            <td>
                                <span class="view-mode"><?php echo htmlspecialchars($row['product_name']); ?></span>
                                <input type="text" class="edit-mode edit-product-name" value="<?php echo htmlspecialchars($row['product_name']); ?>" style="display:none;">
                            </td>
                            <td class="actions-cell">
                                <button class="button edit-product-btn" data-id="<?php echo $row['product_id']; ?>">Edit</button>
                                <button class="button save-product-btn" data-id="<?php echo $row['product_id']; ?>" style="display:none;">Save</button>
                                <button class="button delete-product-btn" data-id="<?php echo $row['product_id']; ?>">Delete</button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="2">No products found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3>Add New Product</h3>
        <?php if ($feedback) echo "<p><strong>$feedback</strong></p>"; ?>
        <form action="products.php" method="post" class="login-form">
            <div class="form-group">
                <label for="product_name">Product Name</label>
                <input type="text" name="product_name" required>
            </div>
            <button type="submit" name="add_product" class="button">Add Product</button>
        </form>
    </div>
</div>

<?php
$conn->close();
include '../includes/footer.php';
?>