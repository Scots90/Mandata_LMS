<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') { header("Location: ../login.php"); exit(); }
require_once '../includes/db_connect.php';

// --- 1. Fetch Data ---

// -- Fetch items requiring ADMIN ACTION (these should always be active) --
$action_query = "
    SELECT uc.enrollment_id, u.username, c.course_name, uc.status, uc.retake_request
    FROM user_courses uc
    JOIN users u ON uc.user_id = u.user_id
    JOIN courses c ON uc.course_id = c.course_id
    WHERE (uc.status = 'failed' 
       OR (uc.status = 'completed' AND uc.signed_off = 0)
       OR (uc.status = 'completed' AND uc.retake_request = 1))
       AND uc.is_active = 1
    ORDER BY u.username, c.course_name";
$action_items_result = $conn->query($action_query);

// -- Fetch data for top metric cards (based on active courses) --
$total_users_result = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'student'");
$total_courses_result = $conn->query("SELECT COUNT(*) as count FROM courses");
$completed_enrollments_result = $conn->query("SELECT COUNT(*) as count FROM user_courses WHERE status = 'completed' AND is_active = 1");
$total_users = $total_users_result->fetch_assoc()['count'];
$total_courses = $total_courses_result->fetch_assoc()['count'];
$completed_enrollments = $completed_enrollments_result->fetch_assoc()['count'];

// -- Fetch products for the new filter dropdown --
$products_result = $conn->query("SELECT * FROM products ORDER BY product_name");

// -- Fetch data for the main progress table (shows ALL records, active and inactive) --
$progress_query = "
    SELECT uc.enrollment_id, u.username, c.course_name, uc.status, uc.score, uc.signed_off, uc.is_active,
           uc.completion_date, uc.deadline,
        (SELECT COUNT(*) FROM quiz_attempts qa WHERE qa.enrollment_id = uc.enrollment_id) as attempt_count
    FROM user_courses uc JOIN users u ON uc.user_id = u.user_id JOIN courses c ON uc.course_id = c.course_id
    WHERE u.role = 'student'
    ORDER BY u.username, c.course_name, uc.enrollment_id DESC";
$progress_result = $conn->query($progress_query);


include '../includes/header.php';
?>

<h2>Admin Dashboard</h2>
<p>Welcome, <?php echo htmlspecialchars($_SESSION['username'] ?? 'Admin'); ?>. Manage users, courses, and track progress from here.</p>
<hr>

<h3>Admin Action Required</h3>
<div class="card" style="margin-bottom: 20px;">
    <?php if ($action_items_result && $action_items_result->num_rows > 0): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Course</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php while($item = $action_items_result->fetch_assoc()): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['username']); ?></td>
                        <td><?php echo htmlspecialchars($item['course_name']); ?></td>
                        <td>
                            <?php if ($item['retake_request'] == 1): ?>
                                <span class="status-badge" style="background-color: #ffc107; color: #333;">Retake Requested</span>
                            <?php else: ?>
                                <span class="status-badge status-<?php echo $item['status'] === 'completed' ? 'completed' : 'failed'; ?>">
                                    <?php echo $item['status'] === 'completed' ? 'Pending Sign-off' : 'Failed'; ?>
                                </span>
                            <?php endif; ?>
                        </td>
                        <td class="actions-cell">
                            <?php
                            $action_taken = false;
                            if ($item['retake_request'] == 1) {
                                echo '<a href="force_retake.php?id=' . $item['enrollment_id'] . '" class="button" style="background-color:var(--success-color);">Approve</a>';
                                echo '<a href="deny_retake.php?id=' . $item['enrollment_id'] . '" class="button delete-btn">Deny</a>';
                                $action_taken = true;
                            } else {
                                $latest_attempt_id = 0;
                                $attempt_id_result = $conn->query("SELECT attempt_id FROM quiz_attempts WHERE enrollment_id = {$item['enrollment_id']} ORDER BY attempt_date DESC LIMIT 1");
                                if ($attempt_id_result && $attempt_id_result->num_rows > 0) {
                                    $latest_attempt_id = $attempt_id_result->fetch_assoc()['attempt_id'];
                                }
                                if ($latest_attempt_id > 0) {
                                    echo '<a href="view_attempt.php?id=' . $latest_attempt_id . '" class="button" style="background-color:#6c757d;">View</a>';
                                    $action_taken = true;
                                }
                                if ($item['status'] === 'failed') {
                                    echo '<a href="force_retake.php?id=' . $item['enrollment_id'] . '" class="button" onclick="return confirm(\'Are you sure?\');">Force Retake</a>';
                                    $action_taken = true;
                                } elseif ($item['status'] === 'completed') {
                                    echo '<a href="sign_off.php?enrollment_id=' . $item['enrollment_id'] . '" class="button">Sign Off</a>';
                                    $action_taken = true;
                                }
                            }
                            if (!$action_taken) { echo '&nbsp;'; }
                            ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    <?php else: ?>
        <p>No items currently require your action. Well done! 👍</p>
    <?php endif; ?>
</div>

<hr>

<h3>System Overview</h3>
<div class="dashboard-grid">
    <div class="card stat-card">
        <h3>Total Users</h3>
        <p class="stat-number"><?php echo $total_users; ?></p>
    </div>
    <div class="card stat-card">
        <h3>Total Courses</h3>
        <p class="stat-number"><?php echo $total_courses; ?></p>
    </div>
    <div class="card stat-card">
        <h3>Completed Courses</h3>
        <p class="stat-number"><?php echo $completed_enrollments; ?></p>
    </div>
</div>

<hr>

<div class="card" style="margin-bottom: 20px;">
    <h3>Filter Charts</h3>
    <div class="dashboard-grid" style="grid-template-columns: 1fr 1fr;">
        <div class="form-group">
            <label for="productFilter">Filter by Product</label>
            <select id="productFilter" style="width:100%; padding: 8px;">
                <option value="">All Products</option>
                <?php if ($products_result) : ?>
                    <?php while($product = $products_result->fetch_assoc()): ?>
                        <option value="<?php echo $product['product_id']; ?>"><?php echo htmlspecialchars($product['product_name']); ?></option>
                    <?php endwhile; ?>
                <?php endif; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="categoryFilter">Filter by Category</label>
            <select id="categoryFilter" style="width:100%; padding: 8px;" disabled>
                <option value="">-- Select a Product First --</option>
            </select>
        </div>
    </div>
</div>

<h3>Data Visualization</h3>
<div class="dashboard-grid" style="grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));">
    <div class="card">
        <h3>Course Performance (Pass / Fail)</h3>
        <canvas id="coursePerformanceChart"></canvas>
    </div>
    <div class="card">
        <h3>Course Status per User</h3>
        <canvas id="userBreakdownChart"></canvas>
    </div>
    <div class="card">
        <h3>Average Score per Course (%)</h3>
        <canvas id="averageScoreChart"></canvas>
    </div>
    <div class="card">
        <h3>Question Performance</h3>
        <canvas id="questionBreakdownChart"></canvas>
    </div>
    <div class="card">
        <h3>On-Time vs. Late Completions (Courses)</h3>
        <canvas id="onTimeCourseChart"></canvas>
    </div>
    <div class="card">
        <h3>On-Time vs. Late Completions (Users)</h3>
        <canvas id="onTimeUserChart"></canvas>
    </div>
</div>

<hr>

<h3>Full User Training Progress</h3>
<div class="table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>User</th>
                <th>Course</th>
                <th>Status</th>
                <th>Score</th>
                <th>Attempts</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($progress_result && $progress_result->num_rows > 0): ?>
                <?php while($row = $progress_result->fetch_assoc()): ?>
                    <tr style="<?php echo !$row['is_active'] ? 'background-color: #f8f9fa; color: #6c757d;' : ''; ?>">
                        <td><?php echo htmlspecialchars($row['username']); ?></td>
                        <td><?php echo htmlspecialchars($row['course_name']); ?></td>
                        <td>
                            <?php
                            if (!$row['is_active']) {
                                echo 'Deallocated';
                            } else {
                                echo htmlspecialchars(ucwords(str_replace('_', ' ', $row['status'])));
                                if ($row['status'] === 'completed' && $row['deadline']) {
                                    if ($row['completion_date'] > $row['deadline']) {
                                        echo ' <span style="color:var(--error-color); font-size:0.8rem;">(Late)</span>';
                                    } else {
                                        echo ' <span style="color:var(--success-color); font-size:0.8rem;">(On Time)</span>';
                                    }
                                }
                            }
                            ?>
                        </td>
                        <td><?php echo $row['score'] !== null ? htmlspecialchars(round($row['score'])) . '%' : 'N/A'; ?></td>
                        <td><?php echo $row['attempt_count'] > 0 ? $row['attempt_count'] : 'N/A'; ?></td>
                        <td class="actions-cell">
                            <?php
                            $latest_attempt_id = 0;
                            $attempt_id_result = $conn->query("SELECT attempt_id FROM quiz_attempts WHERE enrollment_id = {$row['enrollment_id']} ORDER BY attempt_date DESC LIMIT 1");
                            if ($attempt_id_result && $attempt_id_result->num_rows > 0) {
                                $latest_attempt_id = $attempt_id_result->fetch_assoc()['attempt_id'];
                            }
                            $action_taken = false;
                            if ($latest_attempt_id > 0) {
                                echo '<a href="view_attempt.php?id=' . $latest_attempt_id . '" class="button" style="background-color:#6c757d;">View</a>';
                                $action_taken = true;
                            }
                            if ($row['status'] === 'failed' && $row['is_active']) {
                                echo '<a href="force_retake.php?id=' . $row['enrollment_id'] . '" class="button" onclick="return confirm(\'Are you sure?\');">Force Retake</a>';
                                $action_taken = true;
                            } elseif ($row['status'] === 'completed' && !$row['signed_off'] && $row['is_active']) {
                                echo '<a href="sign_off.php?enrollment_id=' . $row['enrollment_id'] . '" class="button">Sign Off</a>';
                                $action_taken = true;
                            } elseif ($row['status'] === 'completed' && $row['signed_off']) {
                                echo '<a href="../generate_certificate.php?id=' . $row['enrollment_id'] . '" class="button" style="background-color:var(--success-color);">Certificate</a>';
                                $action_taken = true;
                            }
                            if (!$action_taken) { echo '&nbsp;'; }
                            ?>
                        </td>
                    </tr>
                <?php endwhile; ?>
            <?php else: ?>
                <tr><td colspan="6">No user progress records found.</td></tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
$conn->close();
include '../includes/footer.php';
?>