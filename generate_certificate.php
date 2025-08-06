<?php
session_start();
require_once 'includes/db_connect.php';
require_once 'includes/functions.php';
require_once 'fpdf/fpdf.php'; // Include the FPDF library

// Security check
if (!isLoggedIn()) { redirect('login.php'); }

$enrollment_id = $_GET['id'] ?? 0;
if (!$enrollment_id) { die("Invalid request."); }

$user_id = $_SESSION['user_id'];
$cert_data = null;

// --- FIX START: Reworked permission logic ---

// 1. Check if the logged-in user is the owner of the certificate
$stmt = $conn->prepare("
    SELECT u.username, c.course_name, uc.completion_date
    FROM user_courses uc
    JOIN users u ON uc.user_id = u.user_id
    JOIN courses c ON uc.course_id = c.course_id
    WHERE uc.enrollment_id = ? AND uc.user_id = ? AND uc.status = 'completed' AND uc.signed_off = 1
");
$stmt->bind_param("ii", $enrollment_id, $user_id);
$stmt->execute();
$cert_data = $stmt->get_result()->fetch_assoc();
$stmt->close();

// 2. If not the owner, check if the user is a Manager and owns the user
if (!$cert_data && isManager()) {
    $manager_id = $user_id;
    $stmt_manager = $conn->prepare("
        SELECT u.username, c.course_name, uc.completion_date
        FROM user_courses uc
        JOIN users u ON uc.user_id = u.user_id
        JOIN courses c ON uc.course_id = c.course_id
        WHERE uc.enrollment_id = ? AND u.manager_id = ? AND uc.status = 'completed' AND uc.signed_off = 1
    ");
    $stmt_manager->bind_param("ii", $enrollment_id, $manager_id);
    $stmt_manager->execute();
    $cert_data = $stmt_manager->get_result()->fetch_assoc();
    $stmt_manager->close();
}

// 3. Finally, check if the user is an Admin
if (!$cert_data && isAdmin()) {
    $stmt_admin = $conn->prepare("
        SELECT u.username, c.course_name, uc.completion_date
        FROM user_courses uc
        JOIN users u ON uc.user_id = u.user_id
        JOIN courses c ON uc.course_id = c.course_id
        WHERE uc.enrollment_id = ? AND uc.status = 'completed' AND uc.signed_off = 1
    ");
    $stmt_admin->bind_param("i", $enrollment_id);
    $stmt_admin->execute();
    $cert_data = $stmt_admin->get_result()->fetch_assoc();
    $stmt_admin->close();
}

// --- FIX END ---

if (!$cert_data) {
    die("Certificate not found or you do not have permission to view it.");
}


// --- PDF Generation Starts Here ---

$pdf = new FPDF('P', 'mm', 'A4'); // 'P' for Portrait
$pdf->AddPage();

$logo_path = 'assets/images/mandata_logo.jpeg';
$signature_path = 'assets/images/signature.jpg';

// ** THE FIX IS HERE: Center the logo **
// A4 Portrait width is 210mm. We'll make the logo 80mm wide.
// X position = (Page Width / 2) - (Image Width / 2) = (210 / 2) - (80 / 2) = 65
$pdf->Image($logo_path, 65, 20, 80);

// Set Font
$pdf->SetFont('Arial', 'B', 32);
$pdf->SetY(100); // Adjust Y position to be below the centered logo

// Title
$pdf->Cell(0, 20, 'Certificate of Completion', 0, 1, 'C');

// Certificate Body
$pdf->Ln(10); // Add a line break
$pdf->SetFont('Arial', '', 20);
$pdf->Cell(0, 15, 'This is to certify that', 0, 1, 'C');

$pdf->SetFont('Arial', 'B', 28);
$pdf->Cell(0, 20, $cert_data['username'], 0, 1, 'C');

$pdf->SetFont('Arial', '', 20);
$pdf->Cell(0, 15, 'has successfully completed the course', 0, 1, 'C');

$pdf->SetFont('Arial', 'I', 24);
$pdf->Cell(0, 20, '"' . $cert_data['course_name'] . '"', 0, 1, 'C');

$pdf->SetFont('Arial', '', 20);
$pdf->Cell(0, 15, 'on ' . date('F j, Y', strtotime($cert_data['completion_date'])), 0, 1, 'C');

// Signature Section (at the bottom)
$pdf->Image($signature_path, 85, 230, 40);
$pdf->SetY(245); // Position cursor below the signature image
$pdf->SetFont('Arial', 'B', 12);
$pdf->Cell(0, 10, 'Iain Scott', 0, 1, 'C');
$pdf->SetFont('Arial', '', 12);
$pdf->Cell(0, 5, 'Implementation Consultant', 0, 1, 'C');


// Output the PDF
$pdf->Output('D', 'Certificate.pdf'); // 'D' forces a download
?>