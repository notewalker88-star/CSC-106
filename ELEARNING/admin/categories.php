<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/User.php';

// Check if user is admin
requireRole(ROLE_ADMIN);

$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        try {
            $database = new Database();
            $conn = $database->getConnection();

            switch ($_POST['action']) {
                case 'add_category':
                    $name = sanitizeInput($_POST['name']);
                    $description = sanitizeInput($_POST['description']);
                    $slug = generateSlug($name);

                    // Check if slug already exists
                    $query = "SELECT COUNT(*) as count FROM categories WHERE slug = :slug";
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(':slug', $slug);
                    $stmt->execute();

                    if ($stmt->fetch(PDO::FETCH_ASSOC)['count'] > 0) {
                        $slug = $slug . '-' . time();
                    }

                    $query = "INSERT INTO categories (name, description, slug) VALUES (:name, :description, :slug)";
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(':name', $name);
                    $stmt->bindParam(':description', $description);
                    $stmt->bindParam(':slug', $slug);

                    if ($stmt->execute()) {
                        $success_message = 'Category created successfully!';
                    } else {
                        $error_message = 'Failed to create category.';
                    }
                    break;

                case 'edit_category':
                    $id = (int)$_POST['category_id'];
                    $name = sanitizeInput($_POST['name']);
                    $description = sanitizeInput($_POST['description']);

                    $query = "UPDATE categories SET name = :name, description = :description WHERE id = :id";
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(':name', $name);
                    $stmt->bindParam(':description', $description);
                    $stmt->bindParam(':id', $id);

                    if ($stmt->execute()) {
                        $success_message = 'Category updated successfully!';
                    } else {
                        $error_message = 'Failed to update category.';
                    }
                    break;

                case 'toggle_status':
                    $id = (int)$_POST['category_id'];

                    $query = "UPDATE categories SET is_active = NOT is_active WHERE id = :id";
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(':id', $id);

                    if ($stmt->execute()) {
                        $success_message = 'Category status updated successfully!';
                    } else {
                        $error_message = 'Failed to update category status.';
                    }
                    break;

                case 'delete_category':
                    $id = (int)$_POST['category_id'];

                    // Check if category has courses
                    $query = "SELECT COUNT(*) as count FROM courses WHERE category_id = :id";
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(':id', $id);
                    $stmt->execute();

                    $course_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

                    if ($course_count > 0) {
                        $error_message = "Cannot delete category. It has {$course_count} courses assigned to it.";
                    } else {
                        $query = "DELETE FROM categories WHERE id = :id";
                        $stmt = $conn->prepare($query);
                        $stmt->bindParam(':id', $id);

                        if ($stmt->execute()) {
                            $success_message = 'Category deleted successfully!';
                        } else {
                            $error_message = 'Failed to delete category.';
                        }
                    }
                    break;
            }
        } catch (Exception $e) {
            $error_message = 'Database error: ' . $e->getMessage();
        }
    }
}

// Handle AJAX requests
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json');

    try {
        $database = new Database();
        $conn = $database->getConnection();

        switch ($_GET['ajax']) {
            case 'toggle_status':
                $id = (int)$_GET['category_id'];
                $query = "UPDATE categories SET is_active = NOT is_active WHERE id = :id";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':id', $id);
                $result = $stmt->execute();
                echo json_encode(['success' => $result]);
                exit;

            case 'get_category':
                $id = (int)$_GET['category_id'];
                $query = "SELECT * FROM categories WHERE id = :id";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':id', $id);
                $stmt->execute();

                if ($category = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    echo json_encode(['success' => true, 'category' => $category]);
                } else {
                    echo json_encode(['success' => false]);
                }
                exit;

            case 'get_course_count':
                $id = (int)$_GET['category_id'];
                $query = "SELECT COUNT(*) as count FROM courses WHERE category_id = :id";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':id', $id);
                $stmt->execute();

                $count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];
                echo json_encode(['success' => true, 'count' => $count]);
                exit;
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}

// Get categories with course counts
try {
    $database = new Database();
    $conn = $database->getConnection();

    $query = "SELECT c.*, COUNT(co.id) as course_count
              FROM categories c
              LEFT JOIN courses co ON c.id = co.category_id
              GROUP BY c.id
              ORDER BY c.name";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get statistics
    $stats = [];

    $query = "SELECT COUNT(*) as count FROM categories";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $stats['total'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    $query = "SELECT COUNT(*) as count FROM categories WHERE is_active = 1";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $stats['active'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    $query = "SELECT COUNT(*) as count FROM categories WHERE is_active = 0";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $stats['inactive'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    $query = "SELECT COUNT(DISTINCT category_id) as count FROM courses";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $stats['used'] = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

} catch (Exception $e) {
    $categories = [];
    $stats = ['total' => 0, 'active' => 0, 'inactive' => 0, 'used' => 0];
}

// Category icons mapping
$category_icons = [
    'programming' => 'fas fa-code',
    'web-development' => 'fas fa-globe',
    'data-science' => 'fas fa-chart-bar',
    'design' => 'fas fa-paint-brush',
    'business' => 'fas fa-briefcase',
    'marketing' => 'fas fa-bullhorn',
    'photography' => 'fas fa-camera',
    'music' => 'fas fa-music',
    'health' => 'fas fa-heartbeat',
    'fitness' => 'fas fa-dumbbell',
    'cooking' => 'fas fa-utensils',
    'language' => 'fas fa-language',
    'science' => 'fas fa-flask',
    'mathematics' => 'fas fa-calculator',
    'art' => 'fas fa-palette',
    'writing' => 'fas fa-pen-fancy'
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Category Management - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            min-height: 100vh;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .sidebar .nav-link {
            color: rgba(255,255,255,0.8);
            padding: 12px 20px;
            border-radius: 8px;
            margin: 2px 0;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            color: white;
            background: rgba(255,255,255,0.1);
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .category-card {
            border: none;
            border-radius: 15px;
            overflow: hidden;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            height: 100%;
        }
        .category-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
        }
        .category-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: white;
            margin: 0 auto 15px;
        }
        .stat-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .stat-card:hover {
            transform: translateY(-3px);
        }
        .category-status {
            position: absolute;
            top: 10px;
            right: 10px;
        }
        .category-actions {
            position: absolute;
            bottom: 10px;
            right: 10px;
            opacity: 0;
            transition: opacity 0.3s ease;
        }
        .category-card:hover .category-actions {
            opacity: 1;
        }
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }
    </style>
</head>
<body>
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content -->
    <div class="main-content">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h1><i class="fas fa-tags me-2"></i>Category Management</h1>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                <i class="fas fa-plus me-2"></i>Add Category
            </button>
        </div>

        <!-- Flash Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle me-2"></i><?php echo $success_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-circle me-2"></i><?php echo $error_message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stat-card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h3><?php echo number_format($stats['total']); ?></h3>
                                <p class="mb-0">Total Categories</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-tags fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stat-card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h3><?php echo number_format($stats['active']); ?></h3>
                                <p class="mb-0">Active Categories</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-check-circle fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stat-card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h3><?php echo number_format($stats['inactive']); ?></h3>
                                <p class="mb-0">Inactive Categories</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-pause-circle fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stat-card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h3><?php echo number_format($stats['used']); ?></h3>
                                <p class="mb-0">Categories in Use</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-book fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Categories Grid -->
        <div class="row">
            <?php if (!empty($categories)): ?>
                <?php foreach ($categories as $category): ?>
                    <?php
                    $icon = $category_icons[$category['slug']] ?? 'fas fa-folder';
                    $gradient_colors = [
                        'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
                        'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
                        'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
                        'linear-gradient(135deg, #43e97b 0%, #38f9d7 100%)',
                        'linear-gradient(135deg, #fa709a 0%, #fee140 100%)',
                        'linear-gradient(135deg, #a8edea 0%, #fed6e3 100%)',
                        'linear-gradient(135deg, #ff9a9e 0%, #fecfef 100%)',
                        'linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%)'
                    ];
                    $gradient = $gradient_colors[array_rand($gradient_colors)];
                    ?>
                    <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
                        <div class="card category-card position-relative">
                            <!-- Status Badge -->
                            <div class="category-status">
                                <span class="badge bg-<?php echo $category['is_active'] ? 'success' : 'secondary'; ?>">
                                    <?php echo $category['is_active'] ? 'Active' : 'Inactive'; ?>
                                </span>
                            </div>

                            <!-- Action Buttons -->
                            <div class="category-actions">
                                <div class="btn-group-vertical btn-group-sm" role="group">
                                    <button class="btn btn-outline-primary"
                                            onclick="editCategory(<?php echo $category['id']; ?>)"
                                            title="Edit Category">
                                        <i class="fas fa-edit"></i>
                                    </button>
                                    <button class="btn btn-outline-<?php echo $category['is_active'] ? 'warning' : 'success'; ?>"
                                            onclick="toggleStatus(<?php echo $category['id']; ?>)"
                                            title="<?php echo $category['is_active'] ? 'Deactivate' : 'Activate'; ?>">
                                        <i class="fas fa-<?php echo $category['is_active'] ? 'pause' : 'play'; ?>"></i>
                                    </button>
                                    <button class="btn btn-outline-danger"
                                            onclick="deleteCategory(<?php echo $category['id']; ?>, '<?php echo htmlspecialchars($category['name']); ?>', <?php echo $category['course_count']; ?>)"
                                            title="Delete Category">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="card-body text-center p-4">
                                <div class="category-icon" style="background: <?php echo $gradient; ?>;">
                                    <i class="<?php echo $icon; ?>"></i>
                                </div>

                                <h5 class="card-title mb-2"><?php echo htmlspecialchars($category['name']); ?></h5>
                                <p class="card-text text-muted small mb-3">
                                    <?php echo htmlspecialchars($category['description']); ?>
                                </p>

                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted">
                                        <i class="fas fa-book me-1"></i>
                                        <?php echo $category['course_count']; ?> courses
                                    </small>
                                    <small class="text-muted">
                                        <i class="fas fa-link me-1"></i>
                                        <?php echo $category['slug']; ?>
                                    </small>
                                </div>
                            </div>

                            <div class="card-footer bg-light">
                                <small class="text-muted">
                                    <i class="fas fa-calendar me-1"></i>
                                    Created <?php echo timeAgo($category['created_at']); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="text-center py-5">
                        <i class="fas fa-tags fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No categories found</h5>
                        <p class="text-muted">Create your first category to organize courses.</p>
                        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal">
                            <i class="fas fa-plus me-2"></i>Create First Category
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Category Modal -->
    <div class="modal fade" id="addCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-plus me-2"></i>Add New Category
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add_category">

                        <div class="mb-3">
                            <label for="name" class="form-label">Category Name</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" id="description" name="description" rows="3"
                                      placeholder="Brief description of this category..."></textarea>
                        </div>

                        <div class="alert alert-info">
                            <i class="fas fa-info-circle me-2"></i>
                            <small>The URL slug will be automatically generated from the category name.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Create Category
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Category Modal -->
    <div class="modal fade" id="editCategoryModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-edit me-2"></i>Edit Category
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" id="editCategoryForm">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="edit_category">
                        <input type="hidden" name="category_id" id="edit_category_id">

                        <div class="mb-3">
                            <label for="edit_name" class="form-label">Category Name</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>

                        <div class="mb-3">
                            <label for="edit_description" class="form-label">Description</label>
                            <textarea class="form-control" id="edit_description" name="description" rows="3"></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">URL Slug</label>
                            <input type="text" class="form-control" id="edit_slug" readonly>
                            <div class="form-text">Slug cannot be changed to maintain URL consistency</div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save me-2"></i>Update Category
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editCategory(categoryId) {
            fetch(`categories.php?ajax=get_category&category_id=${categoryId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('edit_category_id').value = data.category.id;
                        document.getElementById('edit_name').value = data.category.name;
                        document.getElementById('edit_description').value = data.category.description;
                        document.getElementById('edit_slug').value = data.category.slug;

                        new bootstrap.Modal(document.getElementById('editCategoryModal')).show();
                    }
                });
        }

        function toggleStatus(categoryId) {
            if (confirm('Are you sure you want to change this category\'s status?')) {
                fetch(`categories.php?ajax=toggle_status&category_id=${categoryId}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            location.reload();
                        } else {
                            alert('Failed to update category status');
                        }
                    });
            }
        }

        function deleteCategory(categoryId, categoryName, courseCount) {
            if (courseCount > 0) {
                alert(`Cannot delete "${categoryName}" because it has ${courseCount} courses assigned to it. Please move or delete the courses first.`);
                return;
            }

            if (confirm(`Are you sure you want to delete the category "${categoryName}"? This action cannot be undone.`)) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_category">
                    <input type="hidden" name="category_id" value="${categoryId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }

        // Auto-generate slug from name in add form
        document.getElementById('name').addEventListener('input', function() {
            // This is just for preview - actual slug generation happens server-side
        });
    </script>
</body>
</html>
