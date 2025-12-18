<?php
/**
 * Office Document Viewer
 * Alternative viewer for Office documents that works with localhost
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
    <title><?php echo htmlspecialchars($original_name); ?> - Office Viewer</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <style>
        body {
            background-color: #f8f9fa;
            margin: 0;
            padding: 0;
        }

        .document-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 1000;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .office-viewer {
            background: white;
            min-height: calc(100vh - 120px);
            position: relative;
            padding: 20px;
        }

        .file-preview {
            text-align: center;
            padding: 60px 20px;
        }

        .file-icon {
            font-size: 5rem;
            margin-bottom: 20px;
        }

        .word-icon { color: #2b579a; }
        .excel-icon { color: #217346; }
        .powerpoint-icon { color: #d24726; }

        .preview-info {
            background: #e9ecef;
            border-radius: 8px;
            padding: 20px;
            margin: 20px 0;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
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
                        <button onclick="printDocument()" class="btn btn-light btn-sm" title="Print">
                            <i class="fas fa-print"></i>
                        </button>
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
    <div class="office-viewer">
        <div class="file-preview">
            <?php
            $icon_class = 'fas fa-file';
            $icon_color = 'text-secondary';
            $file_type_name = 'Document';

            switch ($file_extension) {
                case 'doc':
                case 'docx':
                    $icon_class = 'fas fa-file-word';
                    $icon_color = 'word-icon';
                    $file_type_name = 'Word Document';
                    break;
                case 'xls':
                case 'xlsx':
                    $icon_class = 'fas fa-file-excel';
                    $icon_color = 'excel-icon';
                    $file_type_name = 'Excel Spreadsheet';
                    break;
                case 'ppt':
                case 'pptx':
                    $icon_class = 'fas fa-file-powerpoint';
                    $icon_color = 'powerpoint-icon';
                    $file_type_name = 'PowerPoint Presentation';
                    break;
            }
            ?>

            <div class="file-icon <?php echo $icon_color; ?>">
                <i class="<?php echo $icon_class; ?>"></i>
            </div>

            <h3><?php echo $file_type_name; ?></h3>
            <h5 class="text-muted"><?php echo htmlspecialchars($original_name); ?></h5>

            <div class="preview-info">
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

            <?php if (in_array($file_extension, ['docx'])): ?>
                <div class="viewer-toolbar d-flex justify-content-between align-items-center mb-3">
                    <span class="text-muted"><i class="fas fa-file-word me-2"></i>DOCX Preview</span>
                    <div>
                        <a href="<?php echo SITE_URL; ?>/download.php?type=lesson&lesson_id=<?php echo $lesson_id; ?>&file=<?php echo urlencode($file); ?>" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-download me-1"></i>Download
                        </a>
                    </div>
                </div>
                <div id="docxViewer" class="p-3 bg-white border rounded" style="min-height: 400px"></div>
            <?php elseif (in_array($file_extension, ['pptx','ppt'])): ?>
                <link rel="stylesheet" href="https://cdn.jsdelivr.net/gh/meshesha/PPTXjs/dist/pptxjs.css">
                <div class="viewer-toolbar d-flex justify-content-between align-items-center mb-3">
                    <span class="text-muted"><i class="fas fa-file-powerpoint me-2"></i>PowerPoint Preview</span>
                    <div>
                        <a href="<?php echo SITE_URL; ?>/download.php?type=lesson&lesson_id=<?php echo $lesson_id; ?>&file=<?php echo urlencode($file); ?>" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-download me-1"></i>Download
                        </a>
                    </div>
                </div>
                <div id="pptxViewer" class="bg-white border rounded" style="min-height: 400px"></div>
            <?php else: ?>
                <div class="alert alert-info">
                    <h6><i class="fas fa-info-circle me-2"></i>Office Document Preview</h6>
                    <p class="mb-3">This file format is not supported for inline preview on localhost.</p>
                    <div class="d-grid gap-2 d-md-block">
                        <a href="<?php echo SITE_URL; ?>/download.php?type=lesson&lesson_id=<?php echo $lesson_id; ?>&file=<?php echo urlencode($file); ?>"
                           class="btn btn-primary btn-lg me-2">
                            <i class="fas fa-download me-2"></i>Download Document
                        </a>
                        <a href="<?php echo SITE_URL; ?>/lesson.php?id=<?php echo $lesson_id; ?>"
                           class="btn btn-outline-secondary btn-lg">
                            <i class="fas fa-arrow-left me-2"></i>Back to Lesson
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

    <?php if (in_array($file_extension, ['docx'])): ?>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/mammoth/1.4.21/mammoth.browser.min.js"></script>
    <script>
        const docxUrl = '<?php echo SITE_URL; ?>/uploads/lessons/<?php echo htmlspecialchars($file); ?>';
        fetch(docxUrl, { credentials: 'same-origin' })
            .then(res => res.arrayBuffer())
            .then(arrayBuffer => mammoth.convertToHtml({ arrayBuffer }))
            .then(result => {
                const container = document.getElementById('docxViewer');
                container.innerHTML = result.value;
            })
            .catch(err => {
                const container = document.getElementById('docxViewer');
                container.innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Failed to render DOCX. Please download the file.</div>';
                console.error(err);
            });
    </script>
    <?php endif; ?>

    <?php if (in_array($file_extension, ['pptx','ppt'])): ?>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip/2.7.0/jszip.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jszip-utils/0.1.0/jszip-utils.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/d3/3.5.17/d3.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/nvd3/1.8.6/nv.d3.min.css" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/nvd3/1.8.6/nv.d3.min.js"></script>
    <script src="https://cdn.jsdelivr.net/gh/bgrins/filereader.js/filereader.js"></script>
    <script src="https://cdn.jsdelivr.net/gh/meshesha/PPTXjs/dist/pptxjs.min.js"></script>
    <script src="https://cdn.jsdelivr.net/gh/meshesha/PPTXjs/dist/divs2slides.js"></script>
    <script>
        const pptxUrl = '<?php echo SITE_URL; ?>/uploads/lessons/<?php echo htmlspecialchars($file); ?>';
        $(function(){
            try {
                $("#pptxViewer").pptxToHtml({
                    pptxFileUrl: pptxUrl,
                    slidesScale: '100%',
                    slideMode: false,
                    keyBoardShortCut: false,
                    mediaProcess: true,
                    slideModeConfig: { first: 1, nav: true, navTxtColor: 'white', showPlayPauseBtn: false, keyBoardShortCut: false, showSlideNum: true, showTotalSlideNum: true, autoSlide: false, loop: false, background: 'black', transition: 'default', transitionTime: 1 }
                });
            } catch (e) {
                document.getElementById('pptxViewer').innerHTML = '<div class="alert alert-danger"><i class="fas fa-exclamation-triangle me-2"></i>Failed to render presentation. Please download the file.</div>';
                console.error(e);
            }
        });
    </script>
    <?php endif; ?>

    <script>
        function printDocument() {
            window.print();
        }
    </script>
</body>
</html>
