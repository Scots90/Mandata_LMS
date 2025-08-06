<?php
// /includes/header.php
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mandata LMS</title>
    
    <link rel="stylesheet" href="/mandata_lms/assets/css/style.css">
    
    <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.3/themes/base/jquery-ui.css">

</head>
<body>

<header class="main-header">
    <div class="container">
        <a href="/mandata_lms/index.php" class="logo"><h1>Mandata LMS</h1></a>
        
        <button class="mobile-menu-toggle" aria-label="Open Navigation Menu">
            <span></span>
            <span></span>
            <span></span>
        </button>

        <nav class="main-nav">
            <ul>
                <?php if (isset($_SESSION['user_id'])): ?>
                    <?php // --- FIX: Use the safe helper functions to check roles --- ?>
                    <?php if (isAdmin()): ?>
                        <li><a href="/mandata_lms/admin/index.php">Admin Dashboard</a></li>
                        <li><a href="/mandata_lms/admin/users.php">Manage Users</a></li>
                        <li><a href="/mandata_lms/admin/products.php">Manage Products</a></li>
                        <li><a href="/mandata_lms/admin/categories.php">Manage Categories</a></li>
                        <li><a href="/mandata_lms/admin/courses.php">Manage Courses</a></li>
                        <li><a href="/mandata_lms/admin/assign_courses.php">Assign Courses</a></li>
                    <?php elseif (isManager()): ?>
                        <li><a href="/mandata_lms/admin/index.php">Manager Dashboard</a></li>
                        <li><a href="/mandata_lms/admin/users.php">Add Users</a></li>
                        <li><a href="/mandata_lms/admin/assign_courses.php">Assign Courses</a></li>
                    <?php endif; ?>
                    
                    <?php if (isStudent()): ?>
                         <li><a href="/mandata_lms/dashboard.php">My Dashboard</a></li>
                         <li><a href="/mandata_lms/enroll.php">Course Catalog</a></li>
                    <?php endif; ?>

                    <li><a href="/mandata_lms/logout.php">Logout</a></li>
                <?php else: ?>
                    <li><a href="/mandata_lms/login.php">Login</a></li>
                    <li><a href="/mandata_lms/register.php">Register</a></li>
                <?php endif; ?>
            </ul>
        </nav>
    </div>
</header>

<main class="container">