<?php
session_start();
require_once 'includes/functions.php';

// Security check: If there are no results in the session, redirect to the dashboard.
if (!isset($_SESSION['quiz_result'])) {
    redirect('dashboard.php');
}

// Retrieve the results from the session
$result = $_SESSION['quiz_result'];
$score = $result['score'];
$passed = $result['passed'];
$course_id = $result['course_id'];

// Clear the session variable to prevent users from seeing old results by refreshing the page.
unset($_SESSION['quiz_result']);

include 'includes/header.php';
?>

<div class="card" style="text-align: center;">
    <?php if ($passed): ?>
        <h2 style="color: var(--success-color);">Congratulations! You Passed!</h2>
        <p style="font-size: 3rem; font-weight: bold; margin: 20px 0;">🎉</p>
        <p>You have successfully completed the course. Your result is now ready for final sign-off by an administrator.</p>
    <?php else: ?>
        <h2 style="color: var(--error-color);">Unfortunately, You Did Not Pass</h2>
        <p style="font-size: 3rem; font-weight: bold; margin: 20px 0;">😟</p>
        <p>Your score has been submitted for review. An administrator will be in touch to discuss the next steps.</p>
    <?php endif; ?>

    <h3>Your Score: <span style="font-size: 2rem;"><?php echo round($score); ?>%</span></h3>

    <hr style="margin: 30px 0;">

    <div class="actions">
        <a href="dashboard.php" class="button">Return to Dashboard</a>
    </div>
</div>

<?php
include 'includes/footer.php';
?>