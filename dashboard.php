<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';

// --- Corrected Security Check for Multi-Role System ---
if (!isLoggedIn()) {
    redirect('login.php');
}
if (!isStudent()) {
    redirect('admin/index.php');
}

$user_id = $_SESSION['user_id'];

// --- Data fetching logic remains the same ---
$stmt_user = $conn->prepare("SELECT username FROM users WHERE user_id = ?");
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$username = $stmt_user->get_result()->fetch_assoc()['username'] ?? 'User';
$stmt_user->close();

$metrics = [];
$stmt_metric = $conn->prepare("SELECT status, COUNT(*) as count FROM user_courses WHERE user_id = ? AND is_active = 1 GROUP BY status");
$stmt_metric->bind_param("i", $user_id);
$stmt_metric->execute();
$metrics_result = $stmt_metric->get_result();
while($row = $metrics_result->fetch_assoc()) {
    $metrics[$row['status']] = $row['count'];
}
$stmt_metric->close();

$stmt_outstanding = $conn->prepare("
    SELECT c.course_id, c.course_name, c.course_description, uc.status, uc.last_viewed_page
    FROM user_courses uc JOIN courses c ON uc.course_id = c.course_id
    WHERE uc.user_id = ? AND uc.status IN ('not_started', 'in_progress') AND uc.is_active = 1
    ORDER BY FIELD(uc.status, 'in_progress', 'not_started'), c.course_name
");
$stmt_outstanding->bind_param("i", $user_id);
$stmt_outstanding->execute();
$outstanding_courses = $stmt_outstanding->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_outstanding->close();

$stmt_history = $conn->prepare("
    SELECT c.course_name, uc.completion_date, qa.score, qa.attempt_id, qa.passed, uc.signed_off, uc.enrollment_id, qa.attempt_date
    FROM quiz_attempts qa
    JOIN user_courses uc ON qa.enrollment_id = uc.enrollment_id
    JOIN courses c ON uc.course_id = c.course_id
    WHERE uc.user_id = ?
    ORDER BY qa.attempt_date DESC
");
$stmt_history->bind_param("i", $user_id);
$stmt_history->execute();
$history_courses_list = $stmt_history->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt_history->close();

include 'includes/header.php';
?>

<div class="dashboard-center">
    <h2>Welcome, <?php echo e($username); ?>!</h2>
    <p>Here is your training summary. Select a course to begin or continue.</p>
</div>
<hr>

<h3>At a Glance</h3>
<div class="dashboard-grid">
    <div class="card stat-card">
        <h3>Outstanding Courses</h3>
        <p class="stat-number"><?php echo ($metrics['not_started'] ?? 0) + ($metrics['in_progress'] ?? 0); ?></p>
    </div>
    <div class="card stat-card">
        <h3>Courses In Progress</h3>
        <p class="stat-number"><?php echo $metrics['in_progress'] ?? 0; ?></p>
    </div>
    <div class="card stat-card">
        <h3>Completed Courses</h3>
        <p class="stat-number"><?php echo $metrics['completed'] ?? 0; ?></p>
    </div>
</div>

<hr>

<h3>Assigned Courses</h3>
<div class="dashboard-grid">
    <?php if (empty($outstanding_courses)): ?>
        <div class="card" style="grid-column: 1 / -1;"><p>You have no outstanding courses to complete. Great work! 🎉</p></div>
    <?php else: ?>
        <?php foreach ($outstanding_courses as $course): ?>
            <div class="card course-card">
                <div>
                    <span class="status-badge status-<?php echo str_replace('_', '-', $course['status']); ?>"><?php echo e(str_replace('_', ' ', $course['status'])); ?></span>
                    <h5><?php echo e($course['course_name']); ?></h5>
                    <p><?php echo e($course['course_description']); ?></p>
                </div>
                <a href="course_view.php?id=<?php echo $course['course_id']; ?>&page=<?php echo $course['last_viewed_page']; ?>" class="button" style="margin-top: auto;">
                    <?php echo ($course['status'] == 'not_started') ? 'Start Course' : 'Resume Course'; ?>
                </a>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<hr>

<h3>Performance History</h3>
<div class="card">
    <?php if (empty($history_courses_list)): ?>
        <p>You have no training history yet.</p>
    <?php else: ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Course Name</th>
                    <th>Date of Attempt</th>
                    <th>Result</th>
                    <th>Score</th>
                    <th>Certificate</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($history_courses_list as $attempt): ?>
                    <tr>
                        <td><?php echo e($attempt['course_name']); ?></td>
                        <td><?php echo date('d-M-Y', strtotime($attempt['attempt_date'])); ?></td>
                        <td>
                            <?php if ($attempt['passed']): ?>
                                <span class="status-badge status-completed">Pass</span>
                            <?php else: ?>
                                <span class="status-badge status-failed">Fail</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo e(round($attempt['score'])); ?>%</td>
                        <td>
                            <?php if ($attempt['passed'] && $attempt['signed_off']): ?>
                                <a href="generate_certificate.php?id=<?php echo $attempt['enrollment_id']; ?>" class="button success">Download</a>
                            <?php elseif ($attempt['passed'] && !$attempt['signed_off']): ?>
                                 <span style="color: #6c757d;">Pending Sign-off</span>
                            <?php else: ?>
                                N/A
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php
$conn->close();
include 'includes/footer.php';
?>