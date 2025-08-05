<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: ../login.php"); exit(); }
require_once '../includes/db_connect.php';

$content_id = $_GET['id'] ?? 0;
if (!$content_id) { header("Location: courses.php"); exit(); }

$feedback = '';

// Handle the form submission to UPDATE the content
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $new_title = trim($_POST['title']);
    if (!empty($new_title)) {
        $stmt_update = $conn->prepare("UPDATE course_content SET title = ? WHERE content_id = ?");
        $stmt_update->bind_param("si", $new_title, $content_id);
        if ($stmt_update->execute()) {
            // Redirect back to the main content page on success
            $course_id = $_POST['course_id']; // Get course_id from hidden input
            header("Location: manage_course_content.php?id=$course_id");
            exit();
        } else {
            $feedback = "Error updating title.";
        }
        $stmt_update->close();
    }
}

// Fetch the current content details to pre-fill the form
$stmt_fetch = $conn->prepare("SELECT * FROM course_content WHERE content_id = ?");
$stmt_fetch->bind_param("i", $content_id);
$stmt_fetch->execute();
$content = $stmt_fetch->get_result()->fetch_assoc();
$stmt_fetch->close();

if (!$content) { die("Content not found."); }

include '../includes/header.php';
?>

<a href="manage_course_content.php?id=<?php echo $content['course_id']; ?>">&larr; Back to Content Management</a>
<h2>Edit Page Title</h2>

<?php if ($feedback): ?>
    <div class="error-message"><?php echo htmlspecialchars($feedback); ?></div>
<?php endif; ?>

<div class="card">
    <form action="edit_course_content.php?id=<?php echo $content_id; ?>" method="post" class="login-form">
        <input type="hidden" name="course_id" value="<?php echo $content['course_id']; ?>">
        
        <div class="form-group">
            <label for="title">Page Title</label>
            <input type="text" id="title" name="title" value="<?php echo htmlspecialchars($content['title']); ?>" required>
        </div>
        <button type="submit" class="button">Update Title</button>
    </form>
</div>

<?php
$conn->close();
include '../includes/footer.php';
?>