<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// Security Check: Allows access if the user is an admin OR a manager
if (!isLoggedIn() || (!isAdmin() && !isManager())) {
    header("Location: ../login.php");
    exit();
}

$feedback = '';

// Action Restriction: Only allow Admins to add a course
if (isAdmin() && $_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['add_course'])) {
    $course_name = trim($_POST['course_name']);
    $course_description = trim($_POST['course_description']);
    $category_id = $_POST['category_id_for_course'];

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
$categories_for_edit_query = $conn->query("SELECT * FROM course_categories ORDER BY category_name");
$category_options_for_edit = '';
if ($categories_for_edit_query) {
    while ($cat = $categories_for_edit_query->fetch_assoc()) {
        $category_options_for_edit .= "<option value='{$cat['category_id']}'>" . htmlspecialchars($cat['category_name']) . "</option>";
    }
}

include '../includes/header.php';
?>

<h2>Manage Courses</h2>
<p>
    <?php if (isAdmin()): ?>
        Add, edit, or delete courses. To manage content or assign courses, use the respective buttons.
    <?php else: ?>
        You can preview courses and view their content. To assign courses, please use the "Assign Courses" page.
    <?php endif; ?>
</p>

<?php if ($feedback && isAdmin()): ?>
    <div class='success-message'><strong><?php echo htmlspecialchars($feedback); ?></strong></div>
<?php endif; ?>

<div class="card" style="margin-bottom: 20px;">
    <h3>Existing Courses</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th>Course Details</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($courses && $courses->num_rows > 0): ?>
                <?php while($row = $courses->fetch_assoc()): ?>
                    <tr id="row-course-<?php echo $row['course_id']; ?>">
                        <td>
                            <div class="view-mode">
                                <strong><?php echo htmlspecialchars($row['course_name']); ?></strong><br>
                                <small>Category: <?php echo htmlspecialchars($row['category_name'] ?? 'N/A'); ?></small>
                                <p><?php echo htmlspecialchars($row['course_description']); ?></p>
                            </div>
                            <div class="edit-mode" style="display:none;">
                                <div class="form-group">
                                    <label>Course Name</label>
                                    <input type="text" class="edit-course-name" value="<?php echo htmlspecialchars($row['course_name']); ?>">
                                </div>
                                <div class="form-group">
                                    <label>Category</label>
                                    <select class="edit-course-category">
                                        <?php echo str_replace("value='{$row['category_id']}'", "value='{$row['category_id']}' selected", $category_options_for_edit); ?>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Description</label>
                                     <input type="text" class="edit-course-description" value="<?php echo htmlspecialchars($row['course_description']); ?>">
                                </div>
                            </div>
                            
                            <div class="actions-cell-inline" style="margin-top: 10px; border-top: 1px solid #eee; padding-top: 10px;">
                                <a href="../course_view.php?id=<?php echo $row['course_id']; ?>&preview=true" class="button" target="_blank">Preview</a>
                                <a href="manage_course_content.php?id=<?php echo $row['course_id']; ?>" class="button">Content</a>
                                <?php if (isAdmin()): ?>
                                <button class="button edit-course-btn" data-id="<?php echo $row['course_id']; ?>">Edit</button>
                                <button class="button save-course-btn" data-id="<?php echo $row['course_id']; ?>" style="display:none;">Save</button>
                                <button class="button delete-course-btn" data-id="<?php echo $row['course_id']; ?>">Delete</button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td>No courses found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if (isAdmin()): ?>
<div class="card">
    <h3>Add New Course</h3>
    <form action="courses.php" method="post" class="login-form">
        <div class="form-group">
            <label>1. Select Product</label>
            <select id="product_filter_course" required style="width:100%; padding: 8px;">
                 <option value="">-- Choose a Product --</option>
                <?php mysqli_data_seek($products, 0); // Reset pointer for reuse ?>
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
<?php endif; ?>

<?php
$conn->close();
include '../includes/footer.php';
?>