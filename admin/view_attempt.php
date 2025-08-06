<?php
session_start();
require_once '../includes/db_connect.php';
require_once '../includes/functions.php';

// --- Corrected Security Check ---
if (!isLoggedIn() || (!isAdmin() && !isManager())) {
    header("Location: ../login.php");
    exit();
}

$enrollment_id = $_GET['id'] ?? 0;
if (!$enrollment_id) { die("Invalid enrollment ID."); }

// --- Pagination Logic ---
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$page = max(1, $page);
$offset = ($page - 1);

// --- 1. Fetch ALL Attempts for this Enrollment ---
$stmt_attempts = $conn->prepare("
    SELECT attempt_id, score, passed, attempt_date
    FROM quiz_attempts
    WHERE enrollment_id = ?
    ORDER BY attempt_date ASC
");
$stmt_attempts->bind_param("i", $enrollment_id);
$stmt_attempts->execute();
$all_attempts = $stmt_attempts->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_attempts->close();

$total_attempts = count($all_attempts);
if ($total_attempts === 0) { die("No quiz attempts found for this enrollment."); }
if ($page > $total_attempts) { $page = $total_attempts; $offset = ($page - 1); }

$current_attempt = $all_attempts[$offset];
$attempt_id = $current_attempt['attempt_id'];

// --- 2. Fetch User/Course Details & Perform Security Check ---
$stmt_details = $conn->prepare("
    SELECT u.user_id, u.username, c.course_name
    FROM user_courses uc
    JOIN users u ON uc.user_id = u.user_id
    JOIN courses c ON uc.course_id = c.course_id
    WHERE uc.enrollment_id = ?
");
$stmt_details->bind_param("i", $enrollment_id);
$stmt_details->execute();
$attempt_details = $stmt_details->get_result()->fetch_assoc();
$stmt_details->close();

if (!$attempt_details) { die("Enrollment details not found."); }

// --- FIX: This security check now correctly runs ONLY for managers, not admins ---
if (isManager() && !isAdmin()) {
    $stmt_check = $conn->prepare("SELECT user_id FROM users WHERE user_id = ? AND manager_id = ?");
    $stmt_check->bind_param("ii", $attempt_details['user_id'], $_SESSION['user_id']);
    $stmt_check->execute();
    if ($stmt_check->get_result()->num_rows === 0) {
        header("Location: index.php");
        exit();
    }
    $stmt_check->close();
}

// --- 3. Fetch Questions and Answers for the CURRENT attempt ---
$stmt_answers = $conn->prepare("
    SELECT cc.content_data, uqa.submitted_answer
    FROM user_quiz_answers uqa
    JOIN course_content cc ON uqa.question_id = cc.content_id
    WHERE uqa.attempt_id = ?
    ORDER BY cc.content_order ASC
");
$stmt_answers->bind_param("i", $attempt_id);
$stmt_answers->execute();
$review_data = $stmt_answers->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_answers->close();

include '../includes/header.php';
?>

<h2>Review Quiz Attempt</h2>

<div class="card" style="margin-bottom: 20px;">
    <h3>Attempt <?php echo $page; ?> of <?php echo $total_attempts; ?></h3>
    <p><strong>User:</strong> <?php echo htmlspecialchars($attempt_details['username']); ?></p>
    <p><strong>Course:</strong> <?php echo htmlspecialchars($attempt_details['course_name']); ?></p>
    <p><strong>Attempt Date:</strong> <?php echo date('d-M-Y H:i', strtotime($current_attempt['attempt_date'])); ?></p>
    <p><strong>Score:</strong> <?php echo round($current_attempt['score']); ?>%</p>
    <p><strong>Result:</strong> 
        <?php if($current_attempt['passed']): ?>
            <span style="color: var(--success-color); font-weight: bold;">Pass</span>
        <?php else: ?>
            <span style="color: var(--error-color); font-weight: bold;">Fail</span>
        <?php endif; ?>
    </p>
</div>

<div class="card">
    <h3>Answers for this Attempt</h3>
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
                    $icon = '✓';
                }
                if ($is_user_answer && !$is_correct_answer) {
                    $style = 'color: var(--error-color); text-decoration: line-through;';
                    $icon = '✗';
                }
                ?>
                <p style="<?php echo $style; ?>"><?php echo "{$icon} " . ucfirst($key) . ") " . htmlspecialchars($option); ?></p>
            <?php endforeach; ?>
        </div>
    <?php endforeach; ?>
</div>

<div class="course-navigation" style="margin-top: 20px;">
    <?php if ($page > 1): ?>
        <a href="view_attempt.php?id=<?php echo $enrollment_id; ?>&page=<?php echo $page - 1; ?>" class="button">← Previous Attempt</a>
    <?php endif; ?>
    <?php if ($page < $total_attempts): ?>
        <a href="view_attempt.php?id=<?php echo $enrollment_id; ?>&page=<?php echo $page + 1; ?>" class="button" style="float: right;">Next Attempt →</a>
    <?php endif; ?>
</div>

<a href="index.php" class="button" style="margin-top: 20px;">&larr; Back to Dashboard</a>

<?php
$conn->close();
include '../includes/footer.php';
?>