<?php
session_start();
// Admin security check
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: ../login.php");
    exit();
}
require_once '../includes/db_connect.php';

$category_id = $_GET['id'] ?? 0;
$feedback = '';

// Handle form submission for UPDATE
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $category_name = trim($_POST['category_name']);
    $category_id_post = $_POST['category_id'];

    if (!empty($category_name)) {
        $stmt = $conn->prepare("UPDATE course_categories SET category_name = ? WHERE category_id = ?");
        $stmt->bind_param("si", $category_name, $category_id_post);
        if ($stmt->execute()) {
            header("Location: categories.php"); // Redirect on success
            exit();
        } else {
            $feedback = "Error updating category.";
        }
        $stmt->close();
    }
}

// Fetch the current category data to pre-fill the form
$stmt = $conn->prepare("SELECT * FROM course_categories WHERE category_id = ?");
$stmt->bind_param("i", $category_id);
$stmt->execute();
$category = $stmt->get_result()->fetch_assoc();

if (!$category) {
    echo "Category not found.";
    exit();
}

include '../includes/header.php';
?>

<h2>Edit Category</h2>
<?php if ($feedback) echo "<p><strong>$feedback</strong></p>"; ?>

<div class="card">
    <form action="edit_category.php?id=<?php echo $category_id; ?>" method="post" class="login-form">
        <input type="hidden" name="category_id" value="<?php echo $category['category_id']; ?>">
        <div class="form-group">
            <label for="category_name">Category Name</label>
            <input type="text" id="category_name" name="category_name" value="<?php echo htmlspecialchars($category['category_name']); ?>" required>
        </div>
        <button type="submit" class="button">Update Category</button>
    </form>
</div>

<?php
$conn->close();
include '../includes/footer.php';
?>