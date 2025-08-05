<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: ../login.php"); exit(); }
require_once '../includes/db_connect.php';

$feedback = '';

// Handle 'Add Course' POST request
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_course'])) {
    $course_name = trim($_POST['course_name']);
    $course_description = trim($_POST['course_description']);
    $category_id = $_POST['category_id_for_course']; // Updated field name

    if (!empty($course_name) && !empty($category_id)) {
        $stmt = $conn->prepare("INSERT INTO courses (course_name, course_description, category_id, created_by) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssii", $course_name, $course_description, $category_id, $_SESSION['user_id']);
        $feedback = $stmt->execute() ? 'Course added successfully!' : 'Error adding course.';
        $stmt->close();
    } else {
        $feedback = 'Product, Category, and Course Name are required.';
    }
}

// Fetch data for page display
$products = $conn->query("SELECT * FROM products ORDER BY product_name");
$courses = $conn->query("SELECT c.*, cc.category_name FROM courses c LEFT JOIN course_categories cc ON c.category_id = cc.category_id ORDER BY c.course_name");
$categories_for_edit = $conn->query("SELECT * FROM course_categories ORDER BY category_name"); // For the edit dropdown

$category_options_for_edit = '';
if ($categories_for_edit) {
    while ($cat = $categories_for_edit->fetch_assoc()) {
        $category_options_for_edit .= "<option value='{$cat['category_id']}'>" . htmlspecialchars($cat['category_name']) . "</option>";
    }
}

include '../includes/header.php';
?>

<h2>Manage Courses</h2>
<p>Add, edit, or delete courses. To assign courses, use the "Assign Courses" page.</p>
<?php if ($feedback) echo "<div class='success-message'><strong>$feedback</strong></div>"; ?>

<div class="card" style="margin-bottom: 20px;">
    <h3>Existing Courses</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th>Course Name</th>
                <th>Category</th>
                <th>Description</th>
                <th style="width: 280px;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($courses && $courses->num_rows > 0): ?>
                <?php while($row = $courses->fetch_assoc()): ?>
                    <tr id="row-course-<?php echo $row['course_id']; ?>">
                        <td>
                            <span class="view-mode"><?php echo htmlspecialchars($row['course_name']); ?></span>
                            <input type="text" class="edit-mode edit-course-name" value="<?php echo htmlspecialchars($row['course_name']); ?>" style="display:none;">
                        </td>
                        <td>
                            <span class="view-mode"><?php echo htmlspecialchars($row['category_name'] ?? 'N/A'); ?></span>
                            <select class="edit-mode edit-course-category" style="display:none;">
                                <?php echo str_replace("value='{$row['category_id']}'", "value='{$row['category_id']}' selected", $category_options_for_edit); ?>
                            </select>
                        </td>
                        <td>
                            <span class="view-mode"><?php echo htmlspecialchars($row['course_description']); ?></span>
                            <input type="text" class="edit-mode edit-course-description" value="<?php echo htmlspecialchars($row['course_description']); ?>" style="display:none;">
                        </td>
                        <td class="actions-cell">
                            <a href="../course_view.php?id=<?php echo $row['course_id']; ?>&preview=true" class="button" target="_blank">Preview</a>
                            <a href="manage_course_content.php?id=<?php echo $row['course_id']; ?>" class="button">Content</a>
                            <button class="button edit-course-btn" data-id="<?php echo $row['course_id']; ?>">Edit</button>
                            <button class="button save-course-btn" data-id="<?php echo $row['course_id']; ?>" style="display:none;">Save</button>
                            <button class="button delete-course-btn" data-id="<?php echo $row['course_id']; ?>">Delete</button>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="4">No courses found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<div class="card">
    <h3>Add New Course</h3>
    <form action="courses.php" method="post" class="login-form">
        <div class="form-group">
            <label>1. Select Product</label>
            <select id="product_filter_course" required style="width:100%; padding: 8px;">
                 <option value="">-- Choose a Product --</option>
                <?php if ($products && $products->num_rows > 0): while($row = $products->fetch_assoc()): ?>
                    <option value="<?php echo $row['product_id']; ?>"><?php echo htmlspecialchars($row['product_name']); ?></option>
                <?php endwhile; endif; ?>
            </select>
        </div>
        <div class="form-group">
            <label>2. Select Category</label>
            <select id="category_filter_course" name="category_id_for_course" required disabled style="width:100%; padding: 8px;">
                 <option value="">-- Select a Product First --</option>
            </select>
        </div>
        <div class="form-group">
            <label>3. Course Name</label>
            <input type="text" name="course_name" required>
        </div>
        <div class="form-group">
            <label>4. Description</label>
            <textarea name="course_description" rows="3" style="width:100%; padding: 8px;"></textarea>
        </div>
        <button type="submit" name="add_course" class="button">Add Course</button>
    </form>
</div>

<?php
$conn->close();
include '../includes/footer.php';
?>