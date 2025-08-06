<?php
session_start();
require_once 'includes/functions.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

require_once 'includes/db_connect.php';

// --- Get Course and User Information ---
$course_id = $_GET['id'] ?? 0;
$user_id = $_SESSION['user_id'];
$is_preview = isset($_GET['preview']) && (isAdmin() || isManager());

if (!$course_id) { die("Invalid course ID."); }


// --- FIX: Reworked Content and Quiz Logic ---

// 1. Fetch ALL content items for the course
$all_content_stmt = $conn->prepare("SELECT * FROM course_content WHERE course_id = ? ORDER BY content_order ASC");
$all_content_stmt->bind_param("i", $course_id);
$all_content_stmt->execute();
$all_content_items = $all_content_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$all_content_stmt->close();

// 2. Separate them into logical groups
$main_content_flow = []; // Will hold standard content and inline quizzes in order
$final_quiz_questions = []; // Will hold only the final quiz questions

foreach ($all_content_items as $item) {
    if ($item['content_type'] === 'quiz_final') {
        $final_quiz_questions[] = $item;
    } else {
        // This includes 'text', 'image', 'video', 'image_gallery', and 'quiz_inline'
        $main_content_flow[] = $item;
    }
}

// Security Check & Enrollment
$enrollment_id = 0;
if (!$is_preview) {
    $enrollment_stmt = $conn->prepare("SELECT enrollment_id FROM user_courses WHERE user_id = ? AND course_id = ? AND is_active = 1");
    $enrollment_stmt->bind_param("ii", $user_id, $course_id);
    $enrollment_stmt->execute();
    $enrollment_data = $enrollment_stmt->get_result()->fetch_assoc();
    $enrollment_id = $enrollment_data['enrollment_id'] ?? 0;
    $enrollment_stmt->close();
    if (!$enrollment_id) { die("You are not enrolled in this course."); }
}

// --- 3. Updated Page Logic ---
$is_final_quiz_page = isset($_GET['page']) && $_GET['page'] === 'final_quiz';
$total_main_pages = count($main_content_flow);
$current_page_num = 0;
$current_page_content = null;

if ($is_final_quiz_page) {
    $current_page_num = $total_main_pages + 1; // The final quiz is always the last item
} else {
    $current_page_num = $_GET['page'] ?? 1;
    $current_page_num = max(1, min($total_main_pages, (int)$current_page_num));
    $current_page_index = $current_page_num - 1;
    if (isset($main_content_flow[$current_page_index])) {
        $current_page_content = $main_content_flow[$current_page_index];
    }
}

// Update user's progress if they are a student on a content page
if (!$is_preview && $enrollment_id && !$is_final_quiz_page) {
    $progress_stmt = $conn->prepare("UPDATE user_courses SET last_viewed_page = ?, status = 'in_progress' WHERE enrollment_id = ? AND status = 'not_started'");
    $progress_stmt->bind_param("ii", $current_page_num, $enrollment_id);
    $progress_stmt->execute();
    $progress_stmt->close();
}

$course_name_result = $conn->query("SELECT course_name FROM courses WHERE course_id = $course_id");
$course_name = $course_name_result ? $course_name_result->fetch_assoc()['course_name'] : 'Course';

include 'includes/header.php';
?>

<div class="course-view-container">
    <aside class="course-sidebar">
        <h4><?php echo htmlspecialchars($course_name); ?></h4>
        <nav>
            <ul>
                <?php foreach ($main_content_flow as $index => $page): ?>
                    <?php $page_num = $index + 1; ?>
                    <li class="<?php echo ($page_num == $current_page_num) ? 'active' : ''; ?>">
                        <a href="course_view.php?id=<?php echo $course_id; ?>&page=<?php echo $page_num; ?><?php if ($is_preview) echo '&preview=true'; ?>">
                            <?php echo $page_num . '. ' . htmlspecialchars($page['title']); ?>
                        </a>
                    </li>
                <?php endforeach; ?>

                <?php if (!empty($final_quiz_questions)): ?>
                    <li class="<?php echo $is_final_quiz_page ? 'active' : ''; ?>">
                        <a href="course_view.php?id=<?php echo $course_id; ?>&page=final_quiz<?php if ($is_preview) echo '&preview=true'; ?>">
                            Final Quiz
                        </a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    </aside>

    <main class="course-content">
        <?php if ($is_final_quiz_page): ?>
            <h2>Final Quiz</h2>
            <?php if ($is_preview): ?>
                <?php foreach($final_quiz_questions as $index => $question): ?>
                    <?php $quiz_data = json_decode($question['content_data'], true); ?>
                     <div class="quiz-review-item" style="margin-bottom: 25px; padding-bottom: 15px; border-bottom: 1px solid #eee;">
                        <h4>Question <?php echo $index + 1; ?>: <?php echo htmlspecialchars($quiz_data['question']); ?></h4>
                        <ul>
                        <?php foreach($quiz_data['options'] as $key => $option): ?>
                            <li style="<?php echo ($key == $quiz_data['correct_answer']) ? 'font-weight:bold; color:green;' : ''; ?>">
                                <?php echo ucfirst($key); ?>) <?php echo htmlspecialchars($option); ?>
                            </li>
                        <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                 <div class='card text-center'>
                    <h3>Final Quiz</h3>
                    <p>You are about to start the final quiz for this course. Click the button below to begin.</p>
                    <a href='quiz.php?course_id=<?php echo $course_id; ?>&content_id=<?php echo $final_quiz_questions[0]['content_id']; ?>' class='button'>Start Final Quiz</a>
                </div>
            <?php endif; ?>

        <?php elseif ($current_page_content): ?>
            <h2><?php echo htmlspecialchars($current_page_content['title']); ?></h2>
            <?php
            $content_type = $current_page_content['content_type'];
            $content_data = $current_page_content['content_data'];

            switch ($content_type) {
                case 'text':
                    echo "<div>{$content_data}</div>";
                    break;
                case 'image':
                    echo "<img src='uploads/images/{$content_data}' alt='Course Image' style='max-width:100%; height:auto;'>";
                    break;
                case 'video':
                    echo "<video controls style='width:100%;'><source src='uploads/videos/{$content_data}' type='video/mp4'>Your browser does not support the video tag.</video>";
                    break;
                case 'image_gallery':
                    $gallery_items = json_decode($content_data, true);
                    if ($gallery_items) {
                        echo '<div class="slideshow-container">';
                        foreach ($gallery_items as $item) {
                            echo '<div class="slide fade">';
                            echo "<img src='uploads/images/{$item['image']}' style='width:100%'>";
                            if (!empty($item['text'])) {
                                echo "<div class='slide-text'>" . htmlspecialchars($item['text']) . "</div>";
                            }
                            echo '</div>';
                        }
                        echo '<a class="prev-slide">&#10094;</a><a class="next-slide">&#10095;</a>';
                        echo '</div>';
                    }
                    break;
                case 'quiz_inline':
                    if ($is_preview) {
                        $quiz_data = json_decode($content_data, true);
                        echo '<div class="quiz-review-item" style="border:1px solid #ddd; padding:15px; border-radius:5px;">';
                        echo '<h4>Inline Question: ' . htmlspecialchars($quiz_data['question']) . '</h4>';
                        echo '<ul>';
                        foreach($quiz_data['options'] as $key => $option){
                            echo '<li style="' . ($key == $quiz_data['correct_answer'] ? 'font-weight:bold; color:green;' : '') . '">';
                            echo ucfirst($key) . ') ' . htmlspecialchars($option) . '</li>';
                        }
                        echo '</ul></div>';
                    } else {
                        echo "<div class='card text-center'>";
                        echo "<h3>Knowledge Check</h3><p>You have reached a quiz. Click the button below to start.</p>";
                        echo "<a href='quiz.php?course_id={$course_id}&content_id={$current_page_content['content_id']}' class='button'>Start Quiz</a>";
                        echo "</div>";
                    }
                    break;
            }
            ?>
        <?php else: ?>
            <p>Please select a page from the sidebar to view its content.</p>
        <?php endif; ?>

        <div class="course-navigation">
            <?php if (!$is_final_quiz_page && $current_page_num > 1): ?>
                <a href="course_view.php?id=<?php echo $course_id; ?>&page=<?php echo $current_page_num - 1; ?><?php if ($is_preview) echo '&preview=true'; ?>" class="button">← Previous</a>
            <?php endif; ?>
            <?php if (!$is_final_quiz_page && $current_page_num < $total_main_pages): ?>
                <a href="course_view.php?id=<?php echo $course_id; ?>&page=<?php echo $current_page_num + 1; ?><?php if ($is_preview) echo '&preview=true'; ?>" class="button" style="float: right;">Next →</a>
            <?php elseif (!$is_final_quiz_page && !empty($final_quiz_questions)): ?>
                 <a href="course_view.php?id=<?php echo $course_id; ?>&page=final_quiz<?php if ($is_preview) echo '&preview=true'; ?>" class="button" style="float: right;">Go to Final Quiz →</a>
            <?php endif; ?>
        </div>
    </main>
</div>

<?php
$conn->close();
include 'includes/footer.php';
?>