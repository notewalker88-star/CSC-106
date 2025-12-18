<?php
require_once __DIR__ . '/../config/config.php';

// Check if user is admin
if (!isLoggedIn() || !hasRole(ROLE_ADMIN)) {
    header('Location: ../auth/login.php');
    exit;
}

$success_message = '';
$error_message = '';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['action'])) {
        try {
            $database = new Database();
            $conn = $database->getConnection();

            switch ($_POST['action']) {
                case 'update_status':
                    $message_id = (int)$_POST['message_id'];
                    $status = $_POST['status'];
                    $admin_notes = sanitizeInput($_POST['admin_notes'] ?? '');

                    $query = "UPDATE contact_messages SET status = :status, admin_notes = :admin_notes, updated_at = NOW()";
                    if ($status === 'replied') {
                        $query .= ", replied_at = NOW(), replied_by = :admin_id";
                    }
                    $query .= " WHERE id = :id";

                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(':status', $status);
                    $stmt->bindParam(':admin_notes', $admin_notes);
                    $stmt->bindParam(':id', $message_id);

                    if ($status === 'replied') {
                        $admin_id = getCurrentUserId();
                        $stmt->bindParam(':admin_id', $admin_id);
                    }

                    if ($stmt->execute()) {
                        $success_message = 'Message status updated successfully!';
                    } else {
                        $error_message = 'Failed to update message status.';
                    }
                    break;

                case 'delete_message':
                    $message_id = (int)$_POST['message_id'];

                    $query = "DELETE FROM contact_messages WHERE id = :id";
                    $stmt = $conn->prepare($query);
                    $stmt->bindParam(':id', $message_id);

                    if ($stmt->execute()) {
                        $success_message = 'Message deleted successfully!';
                    } else {
                        $error_message = 'Failed to delete message.';
                    }
                    break;
            }
        } catch (Exception $e) {
            $error_message = 'An error occurred: ' . $e->getMessage();
        }
    }
}

// Get filter parameters
$status_filter = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

// Build query
try {
    $database = new Database();
    $conn = $database->getConnection();

    $where_conditions = [];
    $params = [];

    if (!empty($status_filter)) {
        $where_conditions[] = "cm.status = :status";
        $params[':status'] = $status_filter;
    }

    if (!empty($search)) {
        $where_conditions[] = "(cm.name LIKE :search OR cm.email LIKE :search OR cm.subject LIKE :search OR cm.message LIKE :search)";
        $params[':search'] = '%' . $search . '%';
    }

    $where_clause = '';
    if (!empty($where_conditions)) {
        $where_clause = 'WHERE ' . implode(' AND ', $where_conditions);
    }

    // Get total count
    $count_query = "SELECT COUNT(*) as total FROM contact_messages cm " . $where_clause;
    $stmt = $conn->prepare($count_query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $total_messages = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
    $total_pages = ceil($total_messages / $limit);

    // Get messages
    $query = "SELECT cm.*, u.first_name as admin_first_name, u.last_name as admin_last_name
              FROM contact_messages cm
              LEFT JOIN users u ON cm.replied_by = u.id
              " . $where_clause . "
              ORDER BY cm.created_at DESC
              LIMIT :limit OFFSET :offset";

    $stmt = $conn->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindParam(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get status counts
    $stats_query = "SELECT
                        COUNT(*) as total,
                        COALESCE(SUM(CASE WHEN status = 'new' THEN 1 ELSE 0 END), 0) as new_count,
                        COALESCE(SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END), 0) as read_count,
                        COALESCE(SUM(CASE WHEN status = 'replied' THEN 1 ELSE 0 END), 0) as replied_count,
                        COALESCE(SUM(CASE WHEN status = 'closed' THEN 1 ELSE 0 END), 0) as closed_count
                    FROM contact_messages";
    $stmt = $conn->prepare($stats_query);
    $stmt->execute();
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Ensure all values are integers to prevent null issues
    $stats['total'] = (int)($stats['total'] ?? 0);
    $stats['new_count'] = (int)($stats['new_count'] ?? 0);
    $stats['read_count'] = (int)($stats['read_count'] ?? 0);
    $stats['replied_count'] = (int)($stats['replied_count'] ?? 0);
    $stats['closed_count'] = (int)($stats['closed_count'] ?? 0);

} catch (Exception $e) {
    $messages = [];
    $total_pages = 1;
    $stats = [
        'total' => 0,
        'new_count' => 0,
        'read_count' => 0,
        'replied_count' => 0,
        'closed_count' => 0
    ];
    $error_message = 'Error loading messages: ' . $e->getMessage();
}

function getStatusBadgeClass($status) {
    switch ($status) {
        case 'new': return 'bg-primary';
        case 'read': return 'bg-info';
        case 'replied': return 'bg-success';
        case 'closed': return 'bg-secondary';
        default: return 'bg-secondary';
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Messages - <?php echo SITE_NAME; ?></title>
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
        .message-card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            transition: transform 0.2s ease;
        }
        .message-card:hover {
            transform: translateY(-2px);
        }
        .message-preview {
            max-height: 60px;
            overflow: hidden;
            text-overflow: ellipsis;
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
            <h1><i class="fas fa-envelope me-2"></i>Contact Messages</h1>
            <button class="btn btn-primary" onclick="location.reload()">
                <i class="fas fa-sync-alt me-2"></i>Refresh
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
                                <p class="mb-0">Total Messages</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-envelope fa-2x"></i>
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
                                <h3><?php echo number_format($stats['new_count']); ?></h3>
                                <p class="mb-0">New Messages</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-bell fa-2x"></i>
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
                                <h3><?php echo number_format($stats['replied_count']); ?></h3>
                                <p class="mb-0">Replied</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-reply fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-lg-3 col-md-6 mb-3">
                <div class="card stat-card bg-secondary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h3><?php echo number_format($stats['closed_count']); ?></h3>
                                <p class="mb-0">Closed</p>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-check fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label for="status" class="form-label">Filter by Status</label>
                        <select name="status" id="status" class="form-select">
                            <option value="">All Status</option>
                            <option value="new" <?php echo $status_filter === 'new' ? 'selected' : ''; ?>>New</option>
                            <option value="read" <?php echo $status_filter === 'read' ? 'selected' : ''; ?>>Read</option>
                            <option value="replied" <?php echo $status_filter === 'replied' ? 'selected' : ''; ?>>Replied</option>
                            <option value="closed" <?php echo $status_filter === 'closed' ? 'selected' : ''; ?>>Closed</option>
                        </select>
                    </div>
                    <div class="col-md-6">
                        <label for="search" class="form-label">Search Messages</label>
                        <input type="text" name="search" id="search" class="form-control"
                               placeholder="Search by name, email, subject, or message..."
                               value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search me-1"></i>Filter
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Messages List -->
        <div class="row">
            <?php if (!empty($messages)): ?>
                <?php foreach ($messages as $message): ?>
                    <div class="col-12 mb-3">
                        <div class="card message-card">
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <div class="d-flex justify-content-between align-items-start mb-2">
                                            <h6 class="mb-1">
                                                <strong><?php echo htmlspecialchars($message['name']); ?></strong>
                                                <small class="text-muted">
                                                    (<a href="mailto:<?php echo htmlspecialchars($message['email']); ?>">
                                                        <?php echo htmlspecialchars($message['email']); ?>
                                                    </a>)
                                                </small>
                                            </h6>
                                            <span class="badge <?php echo getStatusBadgeClass($message['status']); ?>">
                                                <?php echo ucfirst($message['status']); ?>
                                            </span>
                                        </div>

                                        <h6 class="text-primary mb-2">
                                            <?php echo htmlspecialchars($message['subject']); ?>
                                        </h6>

                                        <div class="message-preview text-muted mb-2">
                                            <?php echo htmlspecialchars(substr($message['message'], 0, 200)) . (strlen($message['message']) > 200 ? '...' : ''); ?>
                                        </div>

                                        <small class="text-muted">
                                            <i class="fas fa-calendar me-1"></i>
                                            <?php echo date('M j, Y g:i A', strtotime($message['created_at'])); ?>

                                            <?php if ($message['replied_at']): ?>
                                                <span class="ms-3">
                                                    <i class="fas fa-reply me-1"></i>
                                                    Replied on <?php echo date('M j, Y', strtotime($message['replied_at'])); ?>
                                                    <?php if ($message['admin_first_name']): ?>
                                                        by <?php echo htmlspecialchars($message['admin_first_name'] . ' ' . $message['admin_last_name']); ?>
                                                    <?php endif; ?>
                                                </span>
                                            <?php endif; ?>
                                        </small>

                                        <?php if ($message['admin_notes']): ?>
                                            <div class="mt-2">
                                                <small class="text-info">
                                                    <i class="fas fa-sticky-note me-1"></i>
                                                    <strong>Admin Notes:</strong> <?php echo htmlspecialchars($message['admin_notes']); ?>
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <div class="col-md-4 text-end">
                                        <div class="btn-group-vertical w-100" role="group">
                                            <button class="btn btn-outline-primary btn-sm mb-1"
                                                    onclick="viewMessage(<?php echo $message['id']; ?>)">
                                                <i class="fas fa-eye me-1"></i>View Full
                                            </button>
                                            <button class="btn btn-outline-success btn-sm mb-1"
                                                    onclick="updateStatus(<?php echo $message['id']; ?>)">
                                                <i class="fas fa-edit me-1"></i>Update Status
                                            </button>
                                            <a href="mailto:<?php echo htmlspecialchars($message['email']); ?>?subject=Re: <?php echo urlencode($message['subject']); ?>"
                                               class="btn btn-outline-info btn-sm mb-1">
                                                <i class="fas fa-reply me-1"></i>Reply
                                            </a>
                                            <button class="btn btn-outline-danger btn-sm"
                                                    onclick="deleteMessage(<?php echo $message['id']; ?>)">
                                                <i class="fas fa-trash me-1"></i>Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12">
                    <div class="text-center py-5">
                        <i class="fas fa-envelope fa-3x text-muted mb-3"></i>
                        <h5 class="text-muted">No messages found</h5>
                        <p class="text-muted">
                            <?php if (!empty($search) || !empty($status_filter)): ?>
                                Try adjusting your search criteria.
                            <?php else: ?>
                                No contact messages have been received yet.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <nav aria-label="Messages pagination" class="mt-4">
                <ul class="pagination justify-content-center">
                    <?php if ($page > 1): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page - 1])); ?>">
                                <i class="fas fa-chevron-left"></i>
                            </a>
                        </li>
                    <?php endif; ?>

                    <?php for ($i = max(1, $page - 2); $i <= min($total_pages, $page + 2); $i++): ?>
                        <li class="page-item <?php echo $i == $page ? 'active' : ''; ?>">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $i])); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>

                    <?php if ($page < $total_pages): ?>
                        <li class="page-item">
                            <a class="page-link" href="?<?php echo http_build_query(array_merge($_GET, ['page' => $page + 1])); ?>">
                                <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    <?php endif; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>

    <!-- View Message Modal -->
    <div class="modal fade" id="viewMessageModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">View Message</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="messageContent">
                    <!-- Content will be loaded here -->
                </div>
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div class="modal fade" id="updateStatusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Update Message Status</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="message_id" id="updateMessageId">

                        <div class="mb-3">
                            <label for="updateStatus" class="form-label">Status</label>
                            <select name="status" id="updateStatus" class="form-select" required>
                                <option value="new">New</option>
                                <option value="read">Read</option>
                                <option value="replied">Replied</option>
                                <option value="closed">Closed</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label for="adminNotes" class="form-label">Admin Notes</label>
                            <textarea name="admin_notes" id="adminNotes" class="form-control" rows="3"
                                      placeholder="Add any internal notes about this message..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewMessage(messageId) {
            // Find the message data from the page
            const messageCards = document.querySelectorAll('.message-card');
            let messageData = null;

            messageCards.forEach(card => {
                const viewBtn = card.querySelector(`button[onclick="viewMessage(${messageId})"]`);
                if (viewBtn) {
                    const cardBody = card.querySelector('.card-body');
                    const name = cardBody.querySelector('strong').textContent;
                    const email = cardBody.querySelector('a[href^="mailto:"]').textContent;
                    const subject = cardBody.querySelector('.text-primary').textContent;
                    const messageText = cardBody.querySelector('.message-preview').textContent;
                    const date = cardBody.querySelector('.text-muted').textContent;

                    messageData = { name, email, subject, message: messageText, date };
                }
            });

            if (messageData) {
                document.getElementById('messageContent').innerHTML = `
                    <div class="mb-3">
                        <strong>From:</strong> ${messageData.name} (${messageData.email})
                    </div>
                    <div class="mb-3">
                        <strong>Subject:</strong> ${messageData.subject}
                    </div>
                    <div class="mb-3">
                        <strong>Date:</strong> ${messageData.date}
                    </div>
                    <div class="mb-3">
                        <strong>Message:</strong>
                        <div class="border p-3 mt-2 bg-light">
                            ${messageData.message}
                        </div>
                    </div>
                `;

                new bootstrap.Modal(document.getElementById('viewMessageModal')).show();
            }
        }

        function updateStatus(messageId) {
            document.getElementById('updateMessageId').value = messageId;
            new bootstrap.Modal(document.getElementById('updateStatusModal')).show();
        }

        function deleteMessage(messageId) {
            if (confirm('Are you sure you want to delete this message? This action cannot be undone.')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.innerHTML = `
                    <input type="hidden" name="action" value="delete_message">
                    <input type="hidden" name="message_id" value="${messageId}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>
