<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// Security check: Any logged-in user can view the catalog.
if (!isLoggedIn()) {
    header("Location: login.php");
    exit();
}

// Handle enrollment POST request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['enroll'])) {
    $course_id = $_POST['course_id'];
    $user_id = $_SESSION['user_id'];

    if ($course_id > 0 && $user_id > 0) {
        $check_stmt = $conn->prepare("SELECT enrollment_id FROM user_courses WHERE user_id = ? AND course_id = ? AND is_active = 1");
        $check_stmt->bind_param("ii", $user_id, $course_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();

        if ($check_result->num_rows == 0) {
            $insert_stmt = $conn->prepare("INSERT INTO user_courses (user_id, course_id) VALUES (?, ?)");
            $insert_stmt->bind_param("ii", $user_id, $course_id);
            $insert_stmt->execute();
            $insert_stmt->close();
            header("Location: course_view.php?id=$course_id");
            exit();
        }
        $check_stmt->close();
    }
}

// --- NEW: Fetch all products for the new filter dropdown ---
$products = $conn->query("SELECT * FROM products ORDER BY product_name");

include 'includes/header.php';
?>

<h2>Course Catalog</h2>
<p>Browse available courses and enroll to start learning.</p>

<div class="dashboard-grid" style="grid-template-columns: 1fr 1fr; margin-bottom: 20px; max-width: 820px;">
    <div class="form-group">
        <label for="enroll_product_filter">Filter by Product</label>
        <select id="enroll_product_filter" style="width:100%; padding: 8px;">
            <option value="">All Products</option>
            <?php if ($products && $products->num_rows > 0): ?>
                <?php while($product = $products->fetch_assoc()): ?>
                    <option value="<?php echo $product['product_id']; ?>"><?php echo htmlspecialchars($product['product_name']); ?></option>
                <?php endwhile; ?>
            <?php endif; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="enroll_category_filter">Filter by Category</label>
        <select id="enroll_category_filter" style="width:100%; padding: 8px;" disabled>
            <option value="">-- Select a Product First --</option>
        </select>
    </div>
</div>


<div id="course-catalog-container" class="dashboard-grid">
    </div>


<?php
$conn->close();
include 'includes/footer.php';
?>