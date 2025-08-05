<?php
session_start();
require_once 'includes/db_connect.php';

// --- Check for preview mode ---
$is_preview = (isset($_GET['preview']) && $_GET['preview'] == 'true' && isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

// 1. Security & Basic Setup
if (!$is_preview && !isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id'] ?? 0; // Set to 0 if not logged in but in preview
$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$current_page = isset($_GET['page']) ? (int)$_GET['page'] : 1;

if ($course_id === 0) { header("Location: dashboard.php"); exit(); }

// 2. Fetch Course Content
$content_stmt = $conn->prepare("SELECT * FROM course_content WHERE course_id = ? AND content_type != 'quiz_final' ORDER BY content_order ASC");
$content_stmt->bind_param("i", $course_id);
$content_stmt->execute();
$all_content = $content_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$content_stmt->close();
$total_pages = count($all_content);

if (!$is_preview && $total_pages > 0) {
    // Get user's enrollment details
    $enrollment_stmt = $conn->prepare("SELECT enrollment_id, status FROM user_courses WHERE user_id = ? AND course_id = ?");
    $enrollment_stmt->bind_param("ii", $user_id, $course_id);
    $enrollment_stmt->execute();
    $enrollment = $enrollment_stmt->get_result()->fetch_assoc();
    $enrollment_stmt->close();

    if (!$enrollment) { header("Location: dashboard.php"); exit(); }
    $enrollment_id = $enrollment['enrollment_id'];

    // Update status to 'in_progress' if it was 'not_started'
    if ($enrollment['status'] === 'not_started') {
        $update_stmt = $conn->prepare("UPDATE user_courses SET status = 'in_progress' WHERE enrollment_id = ?");
        $update_stmt->bind_param("i", $enrollment_id);
        $update_stmt->execute();
        $update_stmt->close();
    }

    // Save the current page as the last viewed page
    $save_progress_stmt = $conn->prepare("UPDATE user_courses SET last_viewed_page = ? WHERE enrollment_id = ?");
    $save_progress_stmt->bind_param("ii", $current_page, $enrollment_id);
    $save_progress_stmt->execute();
    $save_progress_stmt->close();
}

// 3. Handle Inline Quiz Submission
$feedback = ['message' => '', 'type' => ''];
if (!$is_preview && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_inline_quiz'])) {
    $submitted_answer = $_POST['answer'];
    $content_id = $_POST['content_id'];
    $quiz_stmt = $conn->prepare("SELECT content_data FROM course_content WHERE content_id = ?");
    $quiz_stmt->bind_param("i", $content_id);
    $quiz_stmt->execute();
    $quiz_data = json_decode($quiz_stmt->get_result()->fetch_assoc()['content_data'], true);
    $quiz_stmt->close();
    if ($submitted_answer === $quiz_data['correct_answer']) {
        $feedback = ['message' => 'Correct! You can proceed to the next page.', 'type' => 'success'];
    } else {
        $feedback = ['message' => 'Not quite. Please review the material and try again.', 'type' => 'error'];
    }
}

// 4. Determine Current Page Content
$page_index = $current_page - 1;
if ($page_index < 0 || ($total_pages > 0 && $page_index >= $total_pages)) {
    echo "Invalid page number.";
    exit();
}
$page_content = $total_pages > 0 ? $all_content[$page_index] : ['title' => 'Course Preview'];

include 'includes/header.php';
?>

<div class="course-view-container">
    <?php if ($is_preview): ?>
        <div class="success-message" style="text-align:center;"><strong>PREVIEW MODE</strong></div>
    <?php endif; ?>

    <div class="course-header">
        <h1><?php echo htmlspecialchars($page_content['title']); ?></h1>
        <span class="page-counter">Page <?php echo $current_page; ?> of <?php echo $total_pages; ?></span>
    </div>

    <div class="course-content card">
        <?php
        if ($total_pages > 0) {
            $type = $page_content['content_type'];
            $data = $page_content['content_data'];

            if ($type === 'text') {
                echo '<div class="formatted-text-content">' . $data . '</div>';
            } elseif ($type === 'video') {
                $video_path = '/mandata_lms/uploads/videos/' . htmlspecialchars($data);
                echo '<video controls width="100%"><source src="' . $video_path . '" type="video/mp4">Your browser does not support the video tag.</video>';
            } elseif ($type === 'image') {
                $image_path = '/mandata_lms/uploads/images/' . htmlspecialchars($data);
                echo '<img src="' . $image_path . '" alt="' . htmlspecialchars($page_content['title']) . '" style="max-width: 100%;">';
            } elseif ($type === 'image_gallery') {
                $gallery_items = json_decode($data, true);
                if ($gallery_items) {
                    echo '<div class="slideshow-container">';
                    foreach ($gallery_items as $index => $item) {
                        $image_path = '/mandata_lms/uploads/images/' . htmlspecialchars($item['image']);
                        echo '<div class="slide" style="' . ($index > 0 ? 'display:none;' : '') . '">';
                        echo '  <img src="' . $image_path . '" style="width:100%;">';
                        echo '  <div class="slide-text">' . htmlspecialchars($item['text']) . '</div>';
                        echo '</div>';
                    }
                    echo '<a class="prev-slide">&#10094;</a>';
                    echo '<a class="next-slide">&#10095;</a>';
                    echo '</div>';
                }
            } elseif ($type === 'quiz_inline') {
                $quiz = json_decode($data, true);
                echo '<div class="quiz-question-container">';
                echo '  <h4>Knowledge Check</h4>';
                if (!empty($quiz['image_path'])) {
                    $image_path = '/mandata_lms/uploads/quiz_images/' . htmlspecialchars($quiz['image_path']);
                    echo '<img src="' . $image_path . '" alt="Quiz image" style="max-width: 100%; margin-bottom: 15px;">';
                }
                echo '<p>' . htmlspecialchars($quiz['question']) . '</p>';
                if (!empty($feedback['message'])) { echo "<div class='{$feedback['type']}-message'>{$feedback['message']}</div>"; }
                echo '<form method="POST"><fieldset' . ($is_preview ? ' disabled' : '') . '>';
                echo '<input type="hidden" name="content_id" value="' . $page_content['content_id'] . '">';
                foreach ($quiz['options'] as $key => $option) {
                    echo '<div class="quiz-option">';
                    echo '  <input type="radio" id="option_'.$key.'" name="answer" value="' . $key . '" required>';
                    echo '  <label for="option_'.$key.'">' . htmlspecialchars($option) . '</label>';
                    echo '</div>';
                }
                echo '<button type="submit" name="submit_inline_quiz" class="button">Submit Answer</button></fieldset></form>';
                if ($is_preview) echo '<p><em>Quiz submission is disabled in preview mode.</em></p>';
                echo '</div>';
            }
        } else {
             if ($is_preview) {
                 echo "<p>This course has no content yet. Go to 'Manage Content' to add pages.</p>";
             } else {
                 header("Location: take_quiz.php?id=" . $course_id);
                 exit();
             }
        }
        ?>
    </div>

    <div class="course-navigation">
        <?php $preview_param = $is_preview ? '&preview=true' : ''; ?>
        <?php if ($current_page > 1): ?>
            <a href="course_view.php?id=<?php echo $course_id; ?>&page=<?php echo $current_page - 1 . $preview_param; ?>" class="button">← Previous</a>
        <?php endif; ?>
        <?php
        $can_proceed = ($is_preview || !isset($page_content['content_type']) || $page_content['content_type'] !== 'quiz_inline' || ($feedback['type'] ?? '') === 'success');
        if ($can_proceed):
            if ($current_page < $total_pages): ?>
                <a href="course_view.php?id=<?php echo $course_id; ?>&page=<?php echo $current_page + 1 . $preview_param; ?>" class="button" style="float: right;">Next →</a>
            <?php elseif ($total_pages > 0): ?>
                <a href="take_quiz.php?id=<?php echo $course_id . $preview_param; ?>" class="button" style="float: right; background-color: var(--success-color);">Take Final Quiz →</a>
            <?php endif;
        endif;
        ?>
    </div>
</div>

<?php
include 'includes/footer.php';
?>