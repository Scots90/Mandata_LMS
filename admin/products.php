<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: ../login.php"); exit(); }
require_once '../includes/db_connect.php';

$feedback = '';

// Handle 'Add Product' POST request (standard form submission for adding new products)
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_product'])) {
    $product_name = trim($_POST['product_name']);
    if (!empty($product_name)) {
        $stmt = $conn->prepare("INSERT INTO products (product_name) VALUES (?)");
        $stmt->bind_param("s", $product_name);
        $feedback = $stmt->execute() ? 'Product added successfully!' : 'Error: Product may already exist.';
        $stmt->close();
    }
}

// Fetch all products for the list
$products = $conn->query("SELECT * FROM products ORDER BY product_name");

include '../includes/header.php';
?>

<h2>Manage Products</h2>
<p>Add, edit, or delete product lines that your course categories will belong to.</p>

<div class="dashboard-grid">
    <div class="card" style="grid-column: 1 / -1;">
        <h3>Existing Products</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Product ID</th>
                    <th>Product Name</th>
                    <th style="width: 150px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($products && $products->num_rows > 0): ?>
                    <?php while($row = $products->fetch_assoc()): ?>
                        <tr id="row-product-<?php echo $row['product_id']; ?>">
                            <td><?php echo $row['product_id']; ?></td>
                            <td>
                                <span class="view-mode"><?php echo htmlspecialchars($row['product_name']); ?></span>
                                <input type="text" class="edit-mode edit-product-name" value="<?php echo htmlspecialchars($row['product_name']); ?>" style="display:none; width: 100%;">
                            </td>
                            <td class="actions-cell">
                                <button class="button edit-product-btn" data-id="<?php echo $row['product_id']; ?>">Edit</button>
                                <button class="button save-product-btn" data-id="<?php echo $row['product_id']; ?>" style="display:none;">Save</button>
                                <button class="button delete-btn delete-product-btn" data-id="<?php echo $row['product_id']; ?>">Delete</button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="3">No products found. Add one below.</td></tr>
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
                <input type="text" id="product_name" name="product_name" required>
            </div>
            <button type="submit" name="add_product" class="button">Add Product</button>
        </form>
    </div>
</div>


<?php
$conn->close();
include '../includes/footer.php';
?>