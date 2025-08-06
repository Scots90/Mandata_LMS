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

// Handle form submissions for adding a new category
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_category'])) {
    $category_name = trim($_POST['category_name']);
    $product_id = $_POST['product_id'];

    if (!empty($category_name) && !empty($product_id)) {
        $stmt = $conn->prepare("INSERT INTO course_categories (category_name, product_id) VALUES (?, ?)");
        $stmt->bind_param("si", $category_name, $product_id);
        $feedback = $stmt->execute() ? 'Category added successfully!' : 'Error: Category name may already exist for this product.';
        $stmt->close();
    } else {
        $feedback = 'Category name and product are required.';
    }
}

// Fetch all products for the dropdowns
$products_result = $conn->query("SELECT * FROM products ORDER BY product_name");
$products_for_edit_result = $conn->query("SELECT * FROM products ORDER BY product_name");

// Fetch all categories with their associated product
$categories_result = $conn->query("SELECT cc.*, p.product_name FROM course_categories cc JOIN products p ON cc.product_id = p.product_id ORDER BY p.product_name, cc.category_name");

include '../includes/header.php';
?>

<h2>Manage Categories</h2>
<p>Add, edit, or delete course categories. Categories must be assigned to a product.</p>

<div class="dashboard-grid">
    <div class="card" style="grid-column: 1 / -1;">
        <h3>Existing Categories</h3>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Category Name</th>
                    <th>Product</th>
                    <th style="width: 150px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($categories_result && $categories_result->num_rows > 0): ?>
                    <?php while($row = $categories_result->fetch_assoc()): ?>
                        <tr id="row-category-<?php echo $row['category_id']; ?>">
                            <td>
                                <span class="view-mode"><?php echo htmlspecialchars($row['category_name']); ?></span>
                                <input type="text" class="edit-mode edit-category-name" value="<?php echo htmlspecialchars($row['category_name']); ?>" style="display:none;">
                            </td>
                            <td>
                                <span class="view-mode"><?php echo htmlspecialchars($row['product_name']); ?></span>
                                <select class="edit-mode edit-category-product" style="display:none;">
                                    <?php mysqli_data_seek($products_for_edit_result, 0); // Reset pointer ?>
                                    <?php while($product = $products_for_edit_result->fetch_assoc()): ?>
                                        <option value="<?php echo $product['product_id']; ?>" <?php echo ($product['product_id'] == $row['product_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($product['product_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </td>
                            <td class="actions-cell">
                                <button class="button edit-category-btn" data-id="<?php echo $row['category_id']; ?>">Edit</button>
                                <button class="button save-category-btn" data-id="<?php echo $row['category_id']; ?>" style="display:none;">Save</button>
                                <button class="button delete-category-btn" data-id="<?php echo $row['category_id']; ?>">Delete</button>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr><td colspan="3">No categories found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <div class="card">
        <h3>Add New Category</h3>
        <?php if ($feedback) echo "<p><strong>$feedback</strong></p>"; ?>
        <form action="categories.php" method="post" class="login-form">
            <div class="form-group">
                <label for="category_name">Category Name</label>
                <input type="text" name="category_name" required>
            </div>
            <div class="form-group">
                <label for="product_id">Assign to Product</label>
                <select name="product_id" required style="width:100%; padding: 8px;">
                    <option value="">-- Select a Product --</option>
                    <?php if ($products_result && $products_result->num_rows > 0): ?>
                        <?php while($product = $products_result->fetch_assoc()): ?>
                            <option value="<?php echo $product['product_id']; ?>"><?php echo htmlspecialchars($product['product_name']); ?></option>
                        <?php endwhile; ?>
                    <?php endif; ?>
                </select>
            </div>
            <button type="submit" name="add_category" class="button">Add Category</button>
        </form>
    </div>
</div>

<?php
$conn->close();
include '../includes/footer.php';
?>