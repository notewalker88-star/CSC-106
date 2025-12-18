<?php
/**
 * Test Document Viewer Example
 * This shows how the enhanced document viewer works
 */

require_once __DIR__ . '/config/config.php';

// Check if user is logged in
if (!isLoggedIn()) {
    header('Location: ' . SITE_URL . '/login.php');
    exit();
}

try {
    $database = new Database();
    $conn = $database->getConnection();
    
    // Get lessons with attachments
    $query = "SELECT l.id, l.title, l.attachments, c.title as course_title 
              FROM lessons l 
              JOIN courses c ON l.course_id = c.id 
              WHERE l.attachments IS NOT NULL 
              AND l.attachments != 'null' 
              AND l.attachments != '[]'
              LIMIT 10";
    
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $lessons_with_attachments = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (Exception $e) {
    $lessons_with_attachments = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Document Viewer Examples - E-Learning System</title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <style>
        body {
            background-color: #f8f9fa;
        }
        
        .example-card {
            transition: transform 0.2s;
        }
        
        .example-card:hover {
            transform: translateY(-2px);
        }
        
        .file-icon {
            font-size: 2rem;
            margin-bottom: 10px;
        }
        
        .pdf-icon { color: #dc3545; }
        .word-icon { color: #2b579a; }
        .excel-icon { color: #217346; }
        .powerpoint-icon { color: #d24726; }
        .image-icon { color: #6f42c1; }
        .text-icon { color: #6c757d; }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="row">
            <div class="col-12">
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h2><i class="fas fa-file-alt me-2"></i>Enhanced Document Viewer Examples</h2>
                    <a href="<?php echo SITE_URL; ?>/index.php" class="btn btn-outline-primary">
                        <i class="fas fa-arrow-left me-1"></i>Back to Dashboard
                    </a>
                </div>
                
                <div class="alert alert-info">
                    <h5><i class="fas fa-info-circle me-2"></i>New Enhanced Features:</h5>
                    <ul class="mb-0">
                        <li><strong>PDF Viewer:</strong> Advanced controls with page navigation, zoom, and print</li>
                        <li><strong>Office Documents:</strong> Preview Word, Excel, PowerPoint files online</li>
                        <li><strong>Print Support:</strong> Direct printing from the viewer</li>
                        <li><strong>Responsive Design:</strong> Works perfectly on mobile devices</li>
                        <li><strong>Keyboard Navigation:</strong> Use arrow keys to navigate PDF pages</li>
                    </ul>
                </div>
            </div>
        </div>
        
        <?php if (!empty($lessons_with_attachments)): ?>
        <div class="row">
            <div class="col-12">
                <h4 class="mb-3">Available Documents to View:</h4>
            </div>
            
            <?php foreach ($lessons_with_attachments as $lesson): ?>
                <?php 
                $attachments = json_decode($lesson['attachments'], true);
                if (is_array($attachments)):
                ?>
                    <?php foreach ($attachments as $attachment): ?>
                        <?php 
                        $file_extension = strtolower(pathinfo($attachment['original_name'], PATHINFO_EXTENSION));
                        $viewable_types = ['pdf', 'txt', 'jpg', 'jpeg', 'png', 'gif', 'doc', 'docx', 'ppt', 'pptx', 'xls', 'xlsx'];
                        $can_view = in_array($file_extension, $viewable_types);
                        
                        if ($can_view):
                            // Determine icon and color
                            $icon_class = 'fas fa-file';
                            $icon_color = 'text-icon';
                            
                            switch ($file_extension) {
                                case 'pdf':
                                    $icon_class = 'fas fa-file-pdf';
                                    $icon_color = 'pdf-icon';
                                    break;
                                case 'doc':
                                case 'docx':
                                    $icon_class = 'fas fa-file-word';
                                    $icon_color = 'word-icon';
                                    break;
                                case 'xls':
                                case 'xlsx':
                                    $icon_class = 'fas fa-file-excel';
                                    $icon_color = 'excel-icon';
                                    break;
                                case 'ppt':
                                case 'pptx':
                                    $icon_class = 'fas fa-file-powerpoint';
                                    $icon_color = 'powerpoint-icon';
                                    break;
                                case 'jpg':
                                case 'jpeg':
                                case 'png':
                                case 'gif':
                                    $icon_class = 'fas fa-file-image';
                                    $icon_color = 'image-icon';
                                    break;
                                case 'txt':
                                    $icon_class = 'fas fa-file-alt';
                                    $icon_color = 'text-icon';
                                    break;
                            }
                        ?>
                        
                        <div class="col-md-6 col-lg-4 mb-4">
                            <div class="card example-card h-100">
                                <div class="card-body text-center">
                                    <div class="file-icon <?php echo $icon_color; ?>">
                                        <i class="<?php echo $icon_class; ?>"></i>
                                    </div>
                                    <h6 class="card-title"><?php echo htmlspecialchars($attachment['original_name']); ?></h6>
                                    <p class="card-text text-muted small">
                                        <strong>Course:</strong> <?php echo htmlspecialchars($lesson['course_title']); ?><br>
                                        <strong>Lesson:</strong> <?php echo htmlspecialchars($lesson['title']); ?><br>
                                        <strong>Type:</strong> <?php echo strtoupper($file_extension); ?><br>
                                        <strong>Size:</strong> <?php echo formatFileSize($attachment['size']); ?>
                                    </p>
                                    
                                    <div class="d-grid gap-2">
                                        <a href="<?php echo SITE_URL; ?>/view-document.php?type=lesson&lesson_id=<?php echo $lesson['id']; ?>&file=<?php echo urlencode($attachment['filename']); ?>" 
                                           class="btn btn-primary" target="_blank">
                                            <i class="fas fa-eye me-1"></i>View Document
                                        </a>
                                        <a href="<?php echo SITE_URL; ?>/download.php?type=lesson&lesson_id=<?php echo $lesson['id']; ?>&file=<?php echo urlencode($attachment['filename']); ?>" 
                                           class="btn btn-outline-secondary btn-sm">
                                            <i class="fas fa-download me-1"></i>Download
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
        
        <?php else: ?>
        <div class="row">
            <div class="col-12">
                <div class="alert alert-warning text-center">
                    <h5><i class="fas fa-exclamation-triangle me-2"></i>No Documents Found</h5>
                    <p class="mb-3">There are no uploaded documents in the system yet. To test the enhanced document viewer:</p>
                    <ol class="text-start">
                        <li>Go to any course as an instructor</li>
                        <li>Create or edit a lesson</li>
                        <li>Upload a PDF, Word document, or other supported file</li>
                        <li>Click the "View" button to see the enhanced viewer</li>
                    </ol>
                    <a href="<?php echo SITE_URL; ?>/instructor/courses.php" class="btn btn-primary">
                        <i class="fas fa-plus me-1"></i>Create Lesson with Documents
                    </a>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="row mt-5">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list me-2"></i>Supported File Types</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Documents with Enhanced Viewer:</h6>
                                <ul>
                                    <li><i class="fas fa-file-pdf text-danger me-1"></i> PDF - Advanced viewer with navigation and zoom</li>
                                    <li><i class="fas fa-file-word text-primary me-1"></i> Word (DOC, DOCX) - Google Docs preview</li>
                                    <li><i class="fas fa-file-excel text-success me-1"></i> Excel (XLS, XLSX) - Online spreadsheet viewer</li>
                                    <li><i class="fas fa-file-powerpoint text-warning me-1"></i> PowerPoint (PPT, PPTX) - Slide preview</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>Other Supported Types:</h6>
                                <ul>
                                    <li><i class="fas fa-file-image text-info me-1"></i> Images (JPG, PNG, GIF) - Direct display</li>
                                    <li><i class="fas fa-file-alt text-secondary me-1"></i> Text Files (TXT) - Formatted display</li>
                                </ul>
                                
                                <h6 class="mt-3">Features:</h6>
                                <ul>
                                    <li><i class="fas fa-print me-1"></i> Print support</li>
                                    <li><i class="fas fa-mobile-alt me-1"></i> Mobile responsive</li>
                                    <li><i class="fas fa-keyboard me-1"></i> Keyboard navigation</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
