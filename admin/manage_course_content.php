<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// --- Corrected Security Check for Multi-Role System ---
// Allows access if the user is an admin OR a manager
if (!isLoggedIn() || (!isAdmin() && !isManager())) {
    header("Location: ../login.php");
    exit();
}

$course_id = $_GET['id'] ?? 0;
if (!$course_id) {
    header("Location: courses.php");
    exit();
}

$course_stmt = $conn->prepare("SELECT course_name FROM courses WHERE course_id = ?");
$course_stmt->bind_param("i", $course_id);
$course_stmt->execute();
$course = $course_stmt->get_result()->fetch_assoc();
$course_stmt->close();

if (!$course) {
    echo "Course not found.";
    exit();
}

$feedback = '';

// --- Action Restriction: Only allow Admins to add content ---
if (isAdmin() && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_content'])) {
    $title = trim($_POST['title']);
    $content_type = $_POST['content_type'];
    $content_data = null;

    // Switch statement to handle various content types
    switch ($content_type) {
        case 'text':
            if (!empty($_POST['content_text'])) {
                $content_data = $_POST['content_text'];
            }
            break;
        case 'video':
            if (isset($_FILES['content_video_file']) && $_FILES['content_video_file']['error'] == 0) {
                $upload_dir = '../uploads/videos/';
                $original_filename = $_FILES['content_video_file']['name'];
                $sanitized_basename = strtolower(preg_replace('/[^a-zA-Z0-9-]/', '-', pathinfo($original_filename, PATHINFO_FILENAME)));
                $file_extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
                $new_filename = uniqid('video_', true) . '-' . $sanitized_basename . '.' . $file_extension;
                $target_file = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['content_video_file']['tmp_name'], $target_file)) {
                    $content_data = $new_filename;
                } else {
                    $feedback = 'Error: Failed to move uploaded file. Check server permissions.';
                }
            } else {
                $feedback = 'An error occurred during file upload.';
            }
            break;
        case 'image':
             if (isset($_FILES['content_image_file']) && $_FILES['content_image_file']['error'] == 0) {
                $upload_dir = '../uploads/images/';
                $original_filename = $_FILES['content_image_file']['name'];
                $sanitized_basename = strtolower(preg_replace('/[^a-zA-Z0-9-]/', '-', pathinfo($original_filename, PATHINFO_FILENAME)));
                $file_extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
                $new_filename = uniqid('image_', true) . '-' . $sanitized_basename . '.' . $file_extension;
                $target_file = $upload_dir . $new_filename;
                
                if (move_uploaded_file($_FILES['content_image_file']['tmp_name'], $target_file)) {
                    $content_data = $new_filename;
                } else { $feedback = 'Error: Failed to move uploaded file.'; }
            } else { $feedback = 'An error occurred during file upload.'; }
            break;
        case 'image_gallery':
            $gallery_items = [];
            $has_upload = false;
            if (isset($_FILES['gallery_images']) && is_array($_FILES['gallery_images']['name'])) {
                foreach ($_FILES['gallery_images']['name'] as $key => $name) {
                    if ($_FILES['gallery_images']['error'][$key] == 0) {
                        $upload_dir = '../uploads/images/';
                        $sanitized_basename = strtolower(preg_replace('/[^a-zA-Z0-9-]/', '-', pathinfo($name, PATHINFO_FILENAME)));
                        $file_extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
                        $new_filename = uniqid('gallery_', true) . '-' . $sanitized_basename . '.' . $file_extension;
                        $target_file = $upload_dir . $new_filename;
                        if (move_uploaded_file($_FILES['gallery_images']['tmp_name'][$key], $target_file)) {
                            $gallery_items[] = [ 'image' => $new_filename, 'text' => $_POST['gallery_texts'][$key] ?? '' ];
                            $has_upload = true;
                        }
                    }
                }
            }
            if ($has_upload) { $content_data = json_encode($gallery_items); }
            else { $feedback = "You must upload at least one image for the gallery."; }
            break;
        case 'quiz_inline':
        case 'quiz_final':
            if (!empty($_POST['quiz_question'])) {
                $options = [];
                if(isset($_POST['options']) && is_array($_POST['options'])) {
                    foreach($_POST['options'] as $key => $value) {
                        if(!empty($value)) { $options[chr(97 + $key)] = $value; }
                    }
                }
                 $quiz_data = [
                    'question' => $_POST['quiz_question'],
                    'options' => $options,
                    'correct_answer' => $_POST['correct_answer'],
                    'image_path' => ''
                ];
                if (isset($_FILES['quiz_image']) && $_FILES['quiz_image']['error'] == 0) {
                    $upload_dir = '../uploads/quiz_images/';
                    $original_filename = $_FILES['quiz_image']['name'];
                    $sanitized_basename = strtolower(preg_replace('/[^a-zA-Z0-9-]/', '-', pathinfo($original_filename, PATHINFO_FILENAME)));
                    $file_extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
                    $new_filename = uniqid('quizimg_', true) . '-' . $sanitized_basename . '.' . $file_extension;
                    $target_file = $upload_dir . $new_filename;
                    if (move_uploaded_file($_FILES['quiz_image']['tmp_name'], $target_file)) {
                        $quiz_data['image_path'] = $new_filename;
                    }
                }
                $content_data = json_encode($quiz_data);
            }
            break;
    }

    if ($content_data !== null && empty($feedback)) {
        $order_result = $conn->query("SELECT MAX(content_order) as max_order FROM course_content WHERE course_id = $course_id");
        $next_order = ($order_result->fetch_assoc()['max_order'] ?? 0) + 1;
        $stmt = $conn->prepare("INSERT INTO course_content (course_id, title, content_type, content_data, content_order) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("isssi", $course_id, $title, $content_type, $content_data, $next_order);
        if ($stmt->execute()) {
             header("Location: manage_course_content.php?id=$course_id");
             exit();
        } else { $feedback = 'Database error.'; }
        $stmt->close();
    } elseif (empty($feedback)) { $feedback = 'Error: The content for the selected type cannot be empty.'; }
}

$content_list = $conn->query("SELECT * FROM course_content WHERE course_id = $course_id ORDER BY content_order ASC");
include '../includes/header.php';
?>

<script src="https://cdn.tiny.cloud/1/66oiyhuk4hf779dj81x86m1gk3gj5d74a61j0lejozagv4r2/tinymce/7/tinymce.min.js" referrerpolicy="origin"></script>

<a href="courses.php">&larr; Back to Courses</a>
<h2>Manage Content for: <?php echo htmlspecialchars($course['course_name']); ?></h2>
<?php if (!empty($feedback) && isAdmin()): ?><div class="error-message"><?php echo htmlspecialchars($feedback); ?></div><?php endif; ?>

<div class="card" style="margin-bottom: 20px;">
    <h3>Course Pages <?php if(isAdmin()): ?><span style="font-size: 0.8rem; color: #666;">(Click and drag rows to reorder)</span><?php endif; ?></h3>
    <table class="data-table">
        <thead><tr><th>Order</th><th>Title</th><th>Type</th><?php if(isAdmin()): ?><th>Actions</th><?php endif; ?></tr></thead>
        <tbody <?php if(isAdmin()): ?>id="sortable-content"<?php endif; ?>>
            <?php if ($content_list && $content_list->num_rows > 0): while($content = $content_list->fetch_assoc()): ?>
                <tr id="content_<?php echo $content['content_id']; ?>" style="<?php echo isAdmin() ? 'cursor: move;' : ''; ?>">
                    <td><?php echo $content['content_order']; ?></td>
                    <td><?php echo htmlspecialchars($content['title']); ?></td>
                    <td><?php echo ucfirst(str_replace('_', ' ', $content['content_type'])); ?></td>
                    <?php if(isAdmin()): ?>
                    <td class="actions-cell">
                        <a href="edit_course_content.php?id=<?php echo $content['content_id']; ?>" class="button">Edit</a>
                        <button class="button delete-btn delete-content-btn" data-id="<?php echo $content['content_id']; ?>">Delete</button>
                    </td>
                    <?php endif; ?>
                </tr>
            <?php endwhile; else: ?>
                <tr><td colspan="<?php echo isAdmin() ? '4' : '3'; ?>">No content yet.<?php if(isAdmin()): ?> Add some below.<?php endif; ?></td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php if(isAdmin()): ?>
<div class="card">
    <h3>Add New Page/Content</h3>
    <form id="add-content-form" method="POST" enctype="multipart/form-data">
        <div class="form-group">
            <label for="title">Page Title</label>
            <input type="text" name="title" required>
        </div>
        <div class="form-group">
            <label for="content_type">Content Type</label>
            <select name="content_type" id="content_type_select" required style="width:100%; padding: 8px;">
                <option value="text">Text</option>
                <option value="video">Video (Upload)</option>
                <option value="image">Image (Upload)</option>
                <option value="image_gallery">Image Gallery / Slideshow</option>
                <option value="quiz_inline">Inline Quiz Question</option>
                <option value="quiz_final">Final Quiz Question</option>
            </select>
        </div>
        <div id="dynamic-content-fields"></div>
        <button type="submit" name="add_content" class="button">Add to Course</button>
    </form>
</div>
<?php endif; ?>

<?php
$conn->close();
include '../includes/footer.php';
?>