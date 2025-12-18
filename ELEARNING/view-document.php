<?php
/**
 * Document Viewer
 * View documents and PDFs in browser
 */

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/classes/Course.php';
require_once __DIR__ . '/classes/Lesson.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: ' . SITE_URL . '/login.php');
    exit();
}

// Get parameters
$type = isset($_GET['type']) ? $_GET['type'] : '';
$file = isset($_GET['file']) ? $_GET['file'] : '';
$lesson_id = isset($_GET['lesson_id']) ? (int)$_GET['lesson_id'] : 0;

// Validate parameters
if (empty($type) || empty($file) || $lesson_id <= 0) {
    header('Location: ' . SITE_URL . '/index.php?error=invalid_parameters');
    exit();
}

// Sanitize filename
$file = basename($file);

try {
    $database = new Database();
    $conn = $database->getConnection();

    if ($type === 'lesson') {
        // Get lesson data
        $lesson = new Lesson();
        $lesson_data = $lesson->getLessonById($lesson_id);

        if (!$lesson_data) {
            header('Location: ' . SITE_URL . '/index.php?error=lesson_not_found');
            exit();
        }

        // Check if user has access to this lesson
        $user_id = $_SESSION['user_id'];
        $user_role = $_SESSION['user_role'];

        // Instructors can view their own lesson files
        if ($user_role === ROLE_INSTRUCTOR && $lesson_data['instructor_id'] == $user_id) {
            $has_access = true;
        }
        // Admins can view any files
        elseif ($user_role === ROLE_ADMIN) {
            $has_access = true;
        }
        // Students: allow if lesson is preview, otherwise must be enrolled
        elseif ($user_role === ROLE_STUDENT) {
            if (!empty($lesson_data['is_preview'])) {
                $has_access = true;
            } else {
                $query = "SELECT COUNT(*) FROM enrollments WHERE student_id = :user_id AND course_id = :course_id";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':user_id', $user_id);
                $stmt->bindParam(':course_id', $lesson_data['course_id']);
                $stmt->execute();
                $has_access = $stmt->fetchColumn() > 0;
            }
        }
        else {
            $has_access = false;
        }

        if (!$has_access) {
            header('Location: ' . SITE_URL . '/index.php?error=access_denied');
            exit();
        }

        // Verify file exists in lesson attachments
        $attachments = json_decode($lesson_data['attachments'], true);
        $file_found = false;
        $original_name = $file;
        $file_size = 0;

        if (is_array($attachments)) {
            foreach ($attachments as $attachment) {
                if ($attachment['filename'] === $file) {
                    $file_found = true;
                    $original_name = $attachment['original_name'];
                    $file_size = $attachment['size'];
                    break;
                }
            }
        }

        if (!$file_found) {
            header('Location: ' . SITE_URL . '/index.php?error=file_not_found');
            exit();
        }

        $file_path = UPLOAD_PATH . 'lessons/' . $file;

    } else {
        header('Location: ' . SITE_URL . '/index.php?error=invalid_type');
        exit();
    }

    // Check if file exists on disk
    if (!file_exists($file_path)) {
        header('Location: ' . SITE_URL . '/index.php?error=file_not_found_disk');
        exit();
    }

    // Get file extension
    $file_extension = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

    // Check if file can be viewed in browser
    $viewable_types = ['pdf', 'txt', 'jpg', 'jpeg', 'png', 'gif', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx'];
    $can_view = in_array($file_extension, $viewable_types);

} catch (Exception $e) {
    header('Location: ' . SITE_URL . '/index.php?error=database_error');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($original_name); ?> - Document Viewer</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- PDF.js -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/pdf.js/3.11.174/pdf.min.js"></script>

    <style>
        :root {
            --primary-color: #667eea;
            --secondary-color: #764ba2;
        }

        body {
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
        }

        .document-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            color: white;
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .document-viewer {
            background: white;
            min-height: calc(100vh - 120px);
            position: relative;
        }

        .viewer-toolbar {
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            padding: 10px 15px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 10px;
        }

        .viewer-controls {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .viewer-controls .btn {
            padding: 5px 10px;
            font-size: 14px;
        }

        .page-info {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 14px;
            color: #6c757d;
        }

        .page-input {
            width: 60px;
            text-align: center;
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 2px 5px;
        }

        .zoom-controls {
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .zoom-select {
            border: 1px solid #dee2e6;
            border-radius: 4px;
            padding: 2px 5px;
            font-size: 14px;
        }

        .pdf-container {
            height: calc(100vh - 200px);
            overflow: auto;
            background: #525659;
            display: flex;
            justify-content: center;
            align-items: flex-start;
            padding: 20px;
        }

        .pdf-canvas {
            box-shadow: 0 4px 8px rgba(0,0,0,0.3);
            background: white;
            margin-bottom: 20px;
        }

        .image-viewer {
            max-width: 100%;
            height: auto;
            border-radius: 10px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .text-viewer {
            background: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 8px;
            padding: 20px;
            font-family: 'Courier New', monospace;
            white-space: pre-wrap;
            max-height: 70vh;
            overflow-y: auto;
        }

        .file-info {
            background: #e9ecef;
            border-radius: 8px;
            padding: 15px;
            margin: 15px;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: none;
        }

        .not-viewable {
            text-align: center;
            padding: 60px 20px;
            color: #6c757d;
        }

        .loading-spinner {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 200px;
            font-size: 18px;
            color: #6c757d;
        }

        .error-message {
            text-align: center;
            padding: 40px 20px;
            color: #dc3545;
        }

        @media (max-width: 768px) {
            .viewer-toolbar {
                flex-direction: column;
                align-items: stretch;
            }

            .viewer-controls {
                justify-content: center;
                flex-wrap: wrap;
            }

            .pdf-container {
                padding: 10px;
                height: calc(100vh - 250px);
            }
        }

        @media print {
            .document-header,
            .viewer-toolbar {
                display: none !important;
            }

            .pdf-container {
                height: auto !important;
                overflow: visible !important;
                background: white !important;
                padding: 0 !important;
            }

            .pdf-canvas {
                box-shadow: none !important;
                page-break-after: always;
            }
        }
    </style>
</head>
<body>
    <!-- Document Header -->
    <div class="document-header">
        <div class="container-fluid">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h5 class="mb-1"><?php echo htmlspecialchars($original_name); ?></h5>
                    <small class="text-light">
                        <i class="fas fa-book me-1"></i><?php echo htmlspecialchars($lesson_data['course_title']); ?> -
                        <?php echo htmlspecialchars($lesson_data['title']); ?>
                    </small>
                </div>
                <div class="col-md-4 text-md-end">
                    <div class="btn-group" role="group">
                        <?php if ($file_extension === 'pdf'): ?>
                        <button onclick="printDocument()" class="btn btn-light btn-sm" title="Print">
                            <i class="fas fa-print"></i>
                        </button>
                        <?php endif; ?>
                        <a href="<?php echo SITE_URL; ?>/download.php?type=lesson&lesson_id=<?php echo $lesson_id; ?>&file=<?php echo urlencode($file); ?>"
                           class="btn btn-light btn-sm" title="Download">
                            <i class="fas fa-download"></i>
                        </a>
                        <a href="<?php echo SITE_URL; ?>/lesson.php?id=<?php echo $lesson_id; ?>"
                           class="btn btn-outline-light btn-sm" title="Back to Lesson">
                            <i class="fas fa-arrow-left"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content -->
    <div class="document-viewer">
        <?php if ($can_view): ?>
            <?php if ($file_extension === 'pdf'): ?>
                <div class="viewer-toolbar">
                    <div class="viewer-controls">
                        <span class="text-muted"><i class="fas fa-file-pdf me-2"></i>PDF Viewer</span>
                    </div>
                    <div class="zoom-controls">
                        <button onclick="printDocument()" class="btn btn-outline-secondary btn-sm" title="Print">
                            <i class="fas fa-print"></i>
                        </button>
                    </div>
                </div>

                <div class="pdf-container">
                    <iframe src="<?php echo SITE_URL; ?>/uploads/lessons/<?php echo rawurlencode($file); ?>" style="width: 100%; height: 100%; border: none; background: white;"></iframe>
                </div>

                <?php elseif (in_array($file_extension, ['jpg', 'jpeg', 'png', 'gif'])): ?>
                    <!-- Image Viewer -->
                    <div class="file-info">
                        <div class="row">
                            <div class="col-md-6">
                                <strong>File Type:</strong> <?php echo strtoupper($file_extension); ?><br>
                                <strong>File Size:</strong> <?php echo formatFileSize($file_size); ?>
                            </div>
                            <div class="col-md-6">
                                <strong>Course:</strong> <?php echo htmlspecialchars($lesson_data['course_title']); ?><br>
                                <strong>Lesson:</strong> <?php echo htmlspecialchars($lesson_data['title']); ?>
                            </div>
                        </div>
                    </div>
                    <div class="text-center p-4">
                        <img src="<?php echo SITE_URL; ?>/uploads/lessons/<?php echo htmlspecialchars($file); ?>"
                             alt="<?php echo htmlspecialchars($original_name); ?>"
                             class="image-viewer">
                    </div>

                <?php elseif ($file_extension === 'txt'): ?>
                    <!-- Text Viewer -->
                    <div class="file-info">
                        <div class="row">
                            <div class="col-md-6">
                                <strong>File Type:</strong> <?php echo strtoupper($file_extension); ?><br>
                                <strong>File Size:</strong> <?php echo formatFileSize($file_size); ?>
                            </div>
                            <div class="col-md-6">
                                <strong>Course:</strong> <?php echo htmlspecialchars($lesson_data['course_title']); ?><br>
                                <strong>Lesson:</strong> <?php echo htmlspecialchars($lesson_data['title']); ?>
                            </div>
                        </div>
                    </div>
                    <div class="p-4">
                        <div class="text-viewer">
                            <?php echo htmlspecialchars(file_get_contents($file_path)); ?>
                        </div>
                    </div>

                <?php elseif (in_array($file_extension, ['doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx'])): ?>
                    <!-- Office Document Viewer -->
                    <?php
                    // Check if we're on localhost - if so, use alternative viewer
                    $is_localhost = (strpos(SITE_URL, 'localhost') !== false || strpos(SITE_URL, '127.0.0.1') !== false);

                    if ($is_localhost): ?>
                        <!-- Localhost Alternative Viewer -->
                        <script>
                            // Redirect to alternative office viewer for localhost
                            window.location.href = '<?php echo SITE_URL; ?>/office-viewer.php?type=lesson&lesson_id=<?php echo $lesson_id; ?>&file=<?php echo urlencode($file); ?>';
                        </script>
                    <?php else: ?>
                        <!-- Online Viewer for Production -->
                        <div class="file-info">
                            <div class="row">
                                <div class="col-md-6">
                                    <strong>File Type:</strong> <?php echo strtoupper($file_extension); ?><br>
                                    <strong>File Size:</strong> <?php echo formatFileSize($file_size); ?>
                                </div>
                                <div class="col-md-6">
                                    <strong>Course:</strong> <?php echo htmlspecialchars($lesson_data['course_title']); ?><br>
                                    <strong>Lesson:</strong> <?php echo htmlspecialchars($lesson_data['title']); ?>
                                </div>
                            </div>
                        </div>

                        <div class="viewer-toolbar">
                            <div class="viewer-controls">
                                <span class="text-muted">
                                    <i class="fas fa-file-<?php echo ($file_extension === 'doc' || $file_extension === 'docx') ? 'word' : (($file_extension === 'ppt' || $file_extension === 'pptx') ? 'powerpoint' : 'excel'); ?> me-2"></i>
                                    Office Document Viewer
                                </span>
                            </div>
                            <div class="zoom-controls">
                                <button onclick="printDocument()" class="btn btn-outline-secondary btn-sm" title="Print">
                                    <i class="fas fa-print"></i>
                                </button>
                            </div>
                        </div>

                        <div class="pdf-container">
                            <div id="loadingOffice" class="loading-spinner">
                                <i class="fas fa-spinner fa-spin me-2"></i>Loading document...
                            </div>

                            <!-- Try multiple viewers -->
                            <iframe id="officeViewer"
                                    style="width: 100%; height: 100%; border: none; background: white; display: none;"
                                    onload="hideOfficeLoading();"
                                    onerror="tryNextViewer();">
                            </iframe>

                            <div id="officeError" class="error-message" style="display: none;">
                                <i class="fas fa-exclamation-triangle me-2"></i>
                                <h5>Document Preview Not Available</h5>
                                <p>This document cannot be previewed online. This may be due to:</p>
                                <ul class="text-start">
                                    <li>Document format restrictions</li>
                                    <li>File access permissions</li>
                                    <li>Network connectivity issues</li>
                                </ul>
                                <p><strong>Please download the document to view it:</strong></p>
                                <a href="<?php echo SITE_URL; ?>/download.php?type=lesson&lesson_id=<?php echo $lesson_id; ?>&file=<?php echo urlencode($file); ?>"
                                   class="btn btn-primary btn-lg">
                                    <i class="fas fa-download me-2"></i>Download <?php echo strtoupper($file_extension); ?> Document
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>

            <?php else: ?>
                <!-- Not Viewable -->
                <div class="not-viewable">
                    <i class="fas fa-file fa-5x mb-4"></i>
                    <h4>Cannot Preview This File Type</h4>
                    <p class="mb-4">
                        This file type (<?php echo strtoupper($file_extension); ?>) cannot be previewed in the browser.<br>
                        Please download the file to view it with the appropriate application.
                    </p>
                    <a href="<?php echo SITE_URL; ?>/download.php?type=lesson&lesson_id=<?php echo $lesson_id; ?>&file=<?php echo urlencode($file); ?>"
                       class="btn btn-primary btn-lg">
                        <i class="fas fa-download me-2"></i>Download File
                    </a>
                </div>
            <?php endif; ?>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        function printDocument(){
            var iframe=document.querySelector('.pdf-container iframe');
            if(iframe&&iframe.contentWindow){iframe.contentWindow.print();}else{window.print();}
        }
        window.printDocument=printDocument;
    </script>

    <?php if (in_array($file_extension, ['doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx'])): ?>
    <script>
        // Office document viewer with multiple fallbacks
        let currentViewerIndex = 0;
        const fileUrl = '<?php echo SITE_URL; ?>/uploads/lessons/<?php echo htmlspecialchars($file); ?>';
        const encodedFileUrl = encodeURIComponent(fileUrl);

        // Different viewer options to try
        const viewers = [
            // Google Docs Viewer
            `https://docs.google.com/gview?url=${encodedFileUrl}&embedded=true`,
            // Microsoft Office Online Viewer
            `https://view.officeapps.live.com/op/embed.aspx?src=${encodedFileUrl}`,
            // Alternative approach - direct file display
            fileUrl
        ];

        // Start loading the document
        function loadOfficeDocument() {
            const iframe = document.getElementById('officeViewer');
            if (currentViewerIndex < viewers.length) {
                console.log(`Trying viewer ${currentViewerIndex + 1}: ${viewers[currentViewerIndex]}`);
                iframe.src = viewers[currentViewerIndex];

                // Set a timeout to try next viewer if this one fails
                setTimeout(() => {
                    if (iframe.style.display === 'none') {
                        tryNextViewer();
                    }
                }, 5000); // Wait 5 seconds before trying next
            } else {
                // All viewers failed
                showOfficeError();
            }
        }

        // Try the next viewer
        function tryNextViewer() {
            currentViewerIndex++;
            if (currentViewerIndex < viewers.length) {
                loadOfficeDocument();
            } else {
                showOfficeError();
            }
        }

        // Hide loading and show iframe
        function hideOfficeLoading() {
            document.getElementById('loadingOffice').style.display = 'none';
            document.getElementById('officeViewer').style.display = 'block';
        }

        // Show error message
        function showOfficeError() {
            document.getElementById('loadingOffice').style.display = 'none';
            document.getElementById('officeViewer').style.display = 'none';
            document.getElementById('officeError').style.display = 'block';
        }

        // Print function for Office documents
        function printDocument() {
            window.print();
        }

        // Start loading when page loads
        document.addEventListener('DOMContentLoaded', function() {
            loadOfficeDocument();
        });

        // Make functions global
        window.showOfficeError = showOfficeError;
        window.tryNextViewer = tryNextViewer;
        window.hideOfficeLoading = hideOfficeLoading;
        window.printDocument = printDocument;
    </script>
    <?php endif; ?>
</body>
</html>
