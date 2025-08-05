<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: ../login.php"); exit(); }
require_once '../includes/db_connect.php';

$feedback = '';

// Handle 'Add Category' POST request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_category'])) {
    $category_name = trim($_POST['category_name']);
    $product_id = $_POST['product_id'];

    if (!empty($category_name) && !empty($product_id)) {
        $stmt = $conn->prepare("INSERT INTO course_categories (product_id, category_name) VALUES (?, ?)");
        $stmt->bind_param("is", $product_id, $category_name);
        $feedback = $stmt->execute() ? 'Category added successfully!' : 'Error: Category may already exist.';
        $stmt->close();
    } else {
        $feedback = 'Product and Category Name are required.';
    }
}

// Fetch all products for dropdowns
$products_result = $conn->query("SELECT * FROM products ORDER BY product_name");
$products_for_edit = [];
if ($products_result) {
    while($row = $products_result->fetch_assoc()){
        $products_for_edit[] = $row;
    }
}

// Fetch all categories with their product names
$categories = $conn->query("
    SELECT cc.*, p.product_name 
    FROM course_categories cc 
    LEFT JOIN products p ON cc.product_id = p.product_id 
    ORDER BY p.product_name, cc.category_name
");

include '../includes/header.php';
?>

<h2>Manage Categories</h2>
<p>Assign each category to a high-level product.</p>

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
                <?php if ($categories && $categories->num_rows > 0): ?>
                    <?php while($row = $categories->fetch_assoc()): ?>
                        <tr id="row-category-<?php echo $row['category_id']; ?>">
                            <td>
                                <span class="view-mode"><?php echo htmlspecialchars($row['category_name']); ?></span>
                                <input type="text" class="edit-mode edit-category-name" value="<?php echo htmlspecialchars($row['category_name']); ?>" style="display:none;">
                            </td>
                            <td>
                                <span class="view-mode"><?php echo htmlspecialchars($row['product_name'] ?? 'N/A'); ?></span>
                                <select class="edit-mode edit-category-product" style="display:none;">
                                    <?php foreach($products_for_edit as $product): ?>
                                        <option value="<?php echo $product['product_id']; ?>" <?php echo ($product['product_id'] == $row['product_id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($product['product_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td class="actions-cell">
                                <button class="button edit-category-btn" data-id="<?php echo $row['category_id']; ?>">Edit</button>
                                <button class="button save-category-btn" data-id="<?php echo $row['category_id']; ?>" style="display:none;">Save</button>
                                <button class="button delete-btn delete-category-btn" data-id="<?php echo $row['category_id']; ?>">Delete</button>
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
                <label for="product_id">Product</label>
                <select id="product_id" name="product_id" required style="width:100%; padding: 8px;">
                    <option value="">-- Choose a Product --</option>
                    <?php foreach($products_for_edit as $product): ?>
                        <option value="<?php echo $product['product_id']; ?>"><?php echo htmlspecialchars($product['product_name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="category_name">New Category Name</label>
                <input type="text" id="category_name" name="category_name" required>
            </div>
            <button type="submit" name="add_category" class="button">Add Category</button>
        </form>
    </div>
</div>

<?php
$conn->close();
include '../includes/footer.php';
?>