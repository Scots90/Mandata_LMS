<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// Security Check
if (!isLoggedIn()) { redirect('login.php'); }
if (isAdmin()) { redirect('admin/index.php'); }

$user_id = $_SESSION['user_id'];
$feedback = '';

// This part handles the form submission when a user clicks "Enroll"
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['enroll'])) {
    $course_id_to_enroll = $_POST['course_id'];
    if ($course_id_to_enroll > 0) {
        $stmt_enroll = $conn->prepare("INSERT IGNORE INTO user_courses (user_id, course_id, status) VALUES (?, ?, 'not_started')");
        $stmt_enroll->bind_param("ii", $user_id, $course_id_to_enroll);
        if ($stmt_enroll->execute() && $stmt_enroll->affected_rows > 0) {
            $feedback = "You have successfully enrolled! You can start the course from your dashboard.";
        } else {
            $feedback = "There was an error, or you are already enrolled in this course.";
        }
        $stmt_enroll->close();
    }
}

// Fetch categories for the filter dropdown
$categories_result = $conn->query("SELECT * FROM course_categories ORDER BY category_name");

include 'includes/header.php';
?>

<h2>Course Catalog</h2>
<p>Browse and enroll in available training courses.</p>

<?php if ($feedback): ?>
    <div class="success-message" style="margin-bottom: 20px;"><?php echo e($feedback); ?></div>
<?php endif; ?>

<div class="card" style="margin-bottom: 20px;">
    <div class="form-group">
        <label for="enroll_category_filter">Filter by Category</label>
        <select id="enroll_category_filter" style="width:100%; padding: 8px;">
            <option value="">All Categories</option>
            <?php if ($categories_result) : ?>
                <?php while($category = $categories_result->fetch_assoc()): ?>
                    <option value="<?php echo $category['category_id']; ?>"><?php echo htmlspecialchars($category['category_name']); ?></option>
                <?php endwhile; ?>
            <?php endif; ?>
        </select>
    </div>
</div>

<div id="course-catalog-container" class="dashboard-grid">
    </div>


<?php
$conn->close();
include 'includes/footer.php';
?>