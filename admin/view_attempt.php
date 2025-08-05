<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: ../login.php"); exit(); }
require_once '../includes/db_connect.php';

$attempt_id = $_GET['id'] ?? 0;
if (!$attempt_id) { die("Invalid attempt ID."); }

// --- 1. Fetch Overall Attempt Details ---
$stmt_details = $conn->prepare("
    SELECT u.username, c.course_name, qa.score, qa.passed
    FROM quiz_attempts qa
    JOIN user_courses uc ON qa.enrollment_id = uc.enrollment_id
    JOIN users u ON uc.user_id = u.user_id
    JOIN courses c ON uc.course_id = c.course_id
    WHERE qa.attempt_id = ?
");
$stmt_details->bind_param("i", $attempt_id);
$stmt_details->execute();
$attempt_details = $stmt_details->get_result()->fetch_assoc();
$stmt_details->close();

if (!$attempt_details) { die("Attempt not found."); }


// --- 2. Fetch All Questions and User Answers for this Attempt ---
$stmt_answers = $conn->prepare("
    SELECT 
        cc.content_data, 
        uqa.submitted_answer
    FROM user_quiz_answers uqa
    JOIN course_content cc ON uqa.question_id = cc.content_id
    WHERE uqa.attempt_id = ?
    ORDER BY cc.content_order ASC
");
$stmt_answers->bind_param("i", $attempt_id);
$stmt_answers->execute();
$answers_result = $stmt_answers->get_result();

$review_data = [];
while ($row = $answers_result->fetch_assoc()) {
    $review_data[] = $row;
}
$stmt_answers->close();


include '../includes/header.php';
?>

<h2>Review Quiz Attempt</h2>

<div class="card" style="margin-bottom: 20px;">
    <h3>Attempt Summary</h3>
    <p><strong>User:</strong> <?php echo htmlspecialchars($attempt_details['username']); ?></p>
    <p><strong>Course:</strong> <?php echo htmlspecialchars($attempt_details['course_name']); ?></p>
    <p><strong>Score:</strong> <?php echo round($attempt_details['score']); ?>%</p>
    <p><strong>Result:</strong> 
        <?php if($attempt_details['passed']): ?>
            <span style="color: var(--success-color); font-weight: bold;">Pass</span>
        <?php else: ?>
            <span style="color: var(--error-color); font-weight: bold;">Fail</span>
        <?php endif; ?>
    </p>
</div>

<div class="card">
    <h3>User's Answers</h3>
    <?php foreach ($review_data as $index => $data): ?>
        <?php $quiz = json_decode($data['content_data'], true); ?>
        <div class="quiz-review-item" style="margin-bottom: 25px; padding-bottom: 15px; border-bottom: 1px solid #eee;">
            <h4>Question <?php echo $index + 1; ?>: <?php echo htmlspecialchars($quiz['question']); ?></h4>
            
            <?php foreach ($quiz['options'] as $key => $option): ?>
                <?php
                $is_user_answer = ($key == $data['submitted_answer']);
                $is_correct_answer = ($key == $quiz['correct_answer']);
                $style = '';
                $icon = '';

                if ($is_correct_answer) {
                    $style = 'color: var(--success-color); font-weight: bold;';
                    $icon = '✓'; // Correct answer
                }
                if ($is_user_answer && !$is_correct_answer) {
                    $style = 'color: var(--error-color); text-decoration: line-through;';
                    $icon = '✗'; // User's incorrect answer
                }
                ?>
                <p style="<?php echo $style; ?>">
                    <?php echo "{$icon} " . ucfirst($key) . ") " . htmlspecialchars($option); ?>
                </p>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
</div>

<a href="index.php" class="button" style="margin-top: 20px;">&larr; Back to Admin Dashboard</a>

<?php
$conn->close();
include '../includes/footer.php';
?>