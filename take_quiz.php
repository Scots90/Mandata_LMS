<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// --- Check for preview mode ---
$is_preview = (isset($_GET['preview']) && $_GET['preview'] == 'true' && isset($_SESSION['role']) && $_SESSION['role'] === 'admin');

// 1. Security & Basic Setup
if (!$is_preview && !isLoggedIn()) { redirect('login.php'); }
$user_id = $_SESSION['user_id'] ?? 0;
$course_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($course_id === 0) { redirect('dashboard.php'); }

// 2. Handle Quiz Submission
if (!$is_preview && $_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Find the user's ACTIVE enrollment for this course
    $enrollment_stmt = $conn->prepare("SELECT enrollment_id FROM user_courses WHERE user_id = ? AND course_id = ? AND is_active = 1");
    $enrollment_stmt->bind_param("ii", $user_id, $course_id);
    $enrollment_stmt->execute();
    $enrollment = $enrollment_stmt->get_result()->fetch_assoc();
    $enrollment_stmt->close();
    
    if (!$enrollment) { die("Error: No active enrollment found to submit this quiz against."); }
    $enrollment_id = $enrollment['enrollment_id'];

    // Process answers
    $submitted_answers = $_POST['answers'] ?? [];
    $correct_answers_stmt = $conn->prepare("SELECT content_id, content_data FROM course_content WHERE course_id = ? AND content_type = 'quiz_final'");
    $correct_answers_stmt->bind_param("i", $course_id);
    $correct_answers_stmt->execute();
    $correct_answers_result = $correct_answers_stmt->get_result();
    $correct_answers = [];
    $total_questions = 0;
    while ($row = $correct_answers_result->fetch_assoc()) {
        $quiz_data = json_decode($row['content_data'], true);
        $correct_answers[$row['content_id']] = $quiz_data['correct_answer'];
        $total_questions++;
    }
    $correct_answers_stmt->close();
    
    $score_count = 0;
    foreach ($submitted_answers as $content_id => $user_answer) {
        if (isset($correct_answers[$content_id]) && $correct_answers[$content_id] === $user_answer) {
            $score_count++;
        }
    }
    $score_percentage = ($total_questions > 0) ? ($score_count / $total_questions) * 100 : 0;
    $passed = ($score_percentage >= 90);

    // Save attempt and individual answers
    $attempt_stmt = $conn->prepare("INSERT INTO quiz_attempts (enrollment_id, score, passed) VALUES (?, ?, ?)");
    $attempt_stmt->bind_param("idi", $enrollment_id, $score_percentage, $passed);
    $attempt_stmt->execute();
    $new_attempt_id = $conn->insert_id;
    $attempt_stmt->close();
    
    $answer_stmt = $conn->prepare("INSERT INTO user_quiz_answers (attempt_id, question_id, submitted_answer) VALUES (?, ?, ?)");
    foreach ($submitted_answers as $question_id => $submitted_answer) {
        $answer_stmt->bind_param("iis", $new_attempt_id, $question_id, $submitted_answer);
        $answer_stmt->execute();
    }
    $answer_stmt->close();

    // Update the active enrollment status
    if ($passed) {
        $update_status_stmt = $conn->prepare("UPDATE user_courses SET status = 'completed', score = ?, completion_date = NOW() WHERE enrollment_id = ?");
        $update_status_stmt->bind_param("di", $score_percentage, $enrollment_id);
    } else {
        $update_status_stmt = $conn->prepare("UPDATE user_courses SET status = 'failed', score = ? WHERE enrollment_id = ?");
        $update_status_stmt->bind_param("di", $score_percentage, $enrollment_id);
    }
    $update_status_stmt->execute();
    $update_status_stmt->close();
    
    $_SESSION['quiz_result'] = [
        'score' => $score_percentage,
        'passed' => $passed,
        'course_id' => $course_id
    ];
    
    redirect('quiz_results.php');
}

// 3. Display the Quiz
$course_stmt = $conn->prepare("SELECT course_name FROM courses WHERE course_id = ?");
$course_stmt->bind_param("i", $course_id);
$course_stmt->execute();
$course = $course_stmt->get_result()->fetch_assoc();
$course_stmt->close();

$quiz_questions_stmt = $conn->prepare("SELECT content_id, title, content_data FROM course_content WHERE course_id = ? AND content_type = 'quiz_final' ORDER BY content_order ASC");
$quiz_questions_stmt->bind_param("i", $course_id);
$quiz_questions_stmt->execute();
$quiz_questions = $quiz_questions_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$quiz_questions_stmt->close();

include 'includes/header.php';
?>

<?php if ($is_preview): ?>
    <div class="success-message" style="text-align:center;"><strong>PREVIEW MODE</strong></div>
<?php endif; ?>

<h2>Final Quiz: <?php echo e($course['course_name']); ?></h2>
<p>You must achieve a score of 90% or higher to pass this course.</p>

<div class="card">
    <form action="take_quiz.php?id=<?php echo $course_id; ?>" method="POST">
        <fieldset <?php echo $is_preview ? 'disabled' : ''; ?>>
            <?php if (empty($quiz_questions)): ?>
                <p>This course does not have a final quiz set up yet.</p>
            <?php else: ?>
                <?php foreach ($quiz_questions as $index => $question_data): ?>
                    <?php $quiz = json_decode($question_data['content_data'], true); ?>
                    <div class="quiz-question-container">
                        <h4>Question <?php echo $index + 1; ?>: <?php echo e($quiz['question']); ?></h4>
                        <?php foreach ($quiz['options'] as $key => $option): ?>
                            <div class="quiz-option">
                                <input type="radio" id="q<?php echo $index; ?>_option_<?php echo $key; ?>" name="answers[<?php echo $question_data['content_id']; ?>]" value="<?php echo $key; ?>" required>
                                <label for="q<?php echo $index; ?>_option_<?php echo $key; ?>"><?php echo e($option); ?></label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
                <button type="submit" class="button">Submit Final Quiz</button>
            <?php endif; ?>
        </fieldset>
        <?php if ($is_preview): ?>
             <p style="margin-top: 15px;"><em>Quiz submission is disabled in preview mode.</em></p>
        <?php endif; ?>
    </form>
</div>

<?php
include 'includes/footer.php';
?>