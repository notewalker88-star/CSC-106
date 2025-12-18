<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/Course.php';

// Check if user is logged in and is a student
if (!isLoggedIn() || !hasRole(ROLE_STUDENT)) {
    header('Location: ' . SITE_URL . '/auth/login.php');
    exit();
}

// Get course ID
$course_id = isset($_GET['course_id']) ? (int)$_GET['course_id'] : 0;

if (!$course_id) {
    header('Location: ' . SITE_URL . '/courses.php');
    exit();
}

// Get course details
try {
    $database = new Database();
    $conn = $database->getConnection();

    $query = "SELECT c.*, cat.name as category_name, u.first_name, u.last_name
              FROM courses c
              JOIN categories cat ON c.category_id = cat.id
              JOIN users u ON c.instructor_id = u.id
              WHERE c.id = :course_id AND c.is_published = 1";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':course_id', $course_id);
    $stmt->execute();

    if ($stmt->rowCount() == 0) {
        header('Location: ' . SITE_URL . '/courses.php');
        exit();
    }

    $course = $stmt->fetch(PDO::FETCH_ASSOC);

    // Check if course is free
    if ($course['is_free']) {
        header('Location: ' . SITE_URL . '/course.php?id=' . $course_id);
        exit();
    }

    // Check if already enrolled
    $query = "SELECT id FROM enrollments WHERE student_id = :student_id AND course_id = :course_id";
    $stmt = $conn->prepare($query);
    $stmt->bindParam(':student_id', $_SESSION['user_id']);
    $stmt->bindParam(':course_id', $course_id);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        header('Location: ' . SITE_URL . '/course.php?id=' . $course_id);
        exit();
    }

} catch (Exception $e) {
    header('Location: ' . SITE_URL . '/courses.php');
    exit();
}

// Handle payment form submission
$error_message = '';
$success_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // CSRF Protection (optional)
    $csrf_valid = true;
    if (isset($_POST[CSRF_TOKEN_NAME]) && function_exists('verifyCSRFToken')) {
        $csrf_valid = verifyCSRFToken($_POST[CSRF_TOKEN_NAME]);
    }

    if (!$csrf_valid) {
        $error_message = 'Security token validation failed. Please try again.';
    } else {
        $payment_method = $_POST['payment_method'] ?? '';
        $cardholder_name = sanitizeInput($_POST['cardholder_name'] ?? '');
        $card_number = sanitizeInput($_POST['card_number'] ?? '');
        $expiry_month = sanitizeInput($_POST['expiry_month'] ?? '');
        $expiry_year = sanitizeInput($_POST['expiry_year'] ?? '');
        $cvv = sanitizeInput($_POST['cvv'] ?? '');

        // Basic validation
        $errors = [];

    if (empty($payment_method)) {
        $errors[] = 'Please select a payment method.';
    }

    if ($payment_method === 'card') {
        if (empty($cardholder_name)) {
            $errors[] = 'Cardholder name is required.';
        }

        // Use helper function for card validation
        $card_errors = validateCardDetails($card_number, $expiry_month, $expiry_year, $cvv);
        $errors = array_merge($errors, $card_errors);
    }

    if (empty($errors)) {
        // Process payment (simplified for demo)
        // In a real application, you would integrate with a payment gateway

        // Use helper function to process enrollment
        if (processEnrollment($_SESSION['user_id'], $course_id, $course['price'], $payment_method)) {
            // Log activity
            logActivity($_SESSION['user_id'], 'course_purchased', "Purchased course: {$course['title']} for " . formatCurrency($course['price']));

            // Redirect to success page
            header('Location: success.php?course_id=' . $course_id);
            exit();
        } else {
            $error_message = 'Payment processing failed. Please try again.';
        }
        } else {
            $error_message = implode('<br>', $errors);
        }
    }
}

$page_title = 'Checkout - ' . $course['title'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($page_title); ?> - <?php echo SITE_NAME; ?></title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <style>
        body {
            background-color: #f8f9fa;
        }
        .checkout-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .payment-method-card {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            transition: all 0.3s ease;
        }
        .payment-method-card:hover {
            border-color: #007bff;
            background-color: #f8f9fa;
        }
        .payment-method-card.selected {
            border-color: #007bff;
            background-color: #e7f3ff;
        }
        .course-thumbnail {
            max-height: 150px;
            object-fit: cover;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="<?php echo SITE_URL; ?>">
                <i class="fas fa-graduation-cap me-2"></i><?php echo SITE_NAME; ?>
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="<?php echo SITE_URL; ?>/student/courses.php">
                    <i class="fas fa-arrow-left me-1"></i>Back to Courses
                </a>
            </div>
        </div>
    </nav>

<div class="container mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-8">
            <div class="card checkout-card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-credit-card me-2"></i>Secure Checkout
                    </h4>
                </div>
                <div class="card-body">
                    <?php if ($error_message): ?>
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle me-2"></i><?php echo $error_message; ?>
                        </div>
                    <?php endif; ?>

                    <div class="row">
                        <!-- Course Summary -->
                        <div class="col-md-5">
                            <h5 class="mb-3">Order Summary</h5>
                            <div class="card bg-light">
                                <div class="card-body">
                                    <?php if ($course['thumbnail']): ?>
                                        <img src="<?php echo SITE_URL . '/uploads/courses/' . $course['thumbnail']; ?>"
                                             alt="<?php echo htmlspecialchars($course['title']); ?>"
                                             class="img-fluid rounded mb-3 course-thumbnail">
                                    <?php else: ?>
                                        <div class="bg-primary text-white d-flex align-items-center justify-content-center rounded mb-3"
                                             style="height: 150px;">
                                            <i class="fas fa-book fa-3x"></i>
                                        </div>
                                    <?php endif; ?>

                                    <h6><?php echo htmlspecialchars($course['title']); ?></h6>
                                    <p class="text-muted small mb-2">
                                        by <?php echo htmlspecialchars($course['first_name'] . ' ' . $course['last_name']); ?>
                                    </p>
                                    <p class="text-muted small mb-3">
                                        Category: <?php echo htmlspecialchars($course['category_name']); ?>
                                    </p>

                                    <hr>
                                    <div class="d-flex justify-content-between">
                                        <span>Course Price:</span>
                                        <strong><?php echo formatCurrency($course['price']); ?></strong>
                                    </div>
                                    <hr>
                                    <div class="d-flex justify-content-between">
                                        <strong>Total:</strong>
                                        <strong class="text-primary"><?php echo formatCurrency($course['price']); ?></strong>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Payment Form -->
                        <div class="col-md-7">
                            <h5 class="mb-3">Payment Information</h5>
                            <form method="POST" id="paymentForm" novalidate>
                                <!-- CSRF Protection -->
                                <?php if (function_exists('generateCSRFToken')): ?>
                                    <input type="hidden" name="<?php echo CSRF_TOKEN_NAME; ?>" value="<?php echo generateCSRFToken(); ?>">
                                <?php endif; ?>

                                <div class="mb-4">
                                    <label class="form-label fw-bold">Choose Payment Method</label>
                                    <div class="row g-3">
                                        <div class="col-12">
                                            <div class="payment-method-card p-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="payment_method" id="card" value="card" checked>
                                                    <label class="form-check-label w-100" for="card">
                                                        <div class="d-flex align-items-center">
                                                            <i class="fas fa-credit-card me-3 text-primary fa-lg"></i>
                                                            <div>
                                                                <strong>Credit/Debit Card</strong>
                                                                <small class="d-block text-muted">Visa, Mastercard, American Express</small>
                                                            </div>
                                                        </div>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="payment-method-card p-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="payment_method" id="gcash" value="gcash">
                                                    <label class="form-check-label w-100" for="gcash">
                                                        <div class="d-flex align-items-center">
                                                            <i class="fas fa-mobile-alt me-3 text-success fa-lg"></i>
                                                            <div>
                                                                <strong>GCash</strong>
                                                                <small class="d-block text-muted">Pay using your GCash wallet</small>
                                                            </div>
                                                        </div>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-12">
                                            <div class="payment-method-card p-3">
                                                <div class="form-check">
                                                    <input class="form-check-input" type="radio" name="payment_method" id="paymaya" value="paymaya">
                                                    <label class="form-check-label w-100" for="paymaya">
                                                        <div class="d-flex align-items-center">
                                                            <i class="fas fa-wallet me-3 text-info fa-lg"></i>
                                                            <div>
                                                                <strong>PayMaya</strong>
                                                                <small class="d-block text-muted">Pay using your PayMaya account</small>
                                                            </div>
                                                        </div>
                                                    </label>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div id="card-details">
                                    <div class="mb-3">
                                        <label for="cardholder_name" class="form-label">Cardholder Name *</label>
                                        <input type="text" class="form-control" id="cardholder_name" name="cardholder_name"
                                               value="<?php echo htmlspecialchars($cardholder_name ?? ''); ?>"
                                               placeholder="Enter name as shown on card" required>
                                        <div class="invalid-feedback">
                                            Please enter the cardholder name.
                                        </div>
                                    </div>

                                    <div class="mb-3">
                                        <label for="card_number" class="form-label">Card Number *</label>
                                        <div class="input-group">
                                            <input type="text" class="form-control" id="card_number" name="card_number"
                                                   placeholder="1234 5678 9012 3456" maxlength="19" required
                                                   pattern="[0-9\s]{13,19}" autocomplete="cc-number">
                                            <span class="input-group-text">
                                                <i class="fas fa-credit-card text-muted"></i>
                                            </span>
                                        </div>
                                        <div class="invalid-feedback">
                                            Please enter a valid card number.
                                        </div>
                                    </div>

                                    <div class="row">
                                        <div class="col-md-6">
                                            <label class="form-label">Expiry Date *</label>
                                            <div class="row">
                                                <div class="col-6">
                                                    <select class="form-select" name="expiry_month" required>
                                                        <option value="">Month</option>
                                                        <?php for ($i = 1; $i <= 12; $i++): ?>
                                                            <option value="<?php echo sprintf('%02d', $i); ?>"
                                                                <?php echo (isset($expiry_month) && $expiry_month == sprintf('%02d', $i)) ? 'selected' : ''; ?>>
                                                                <?php echo sprintf('%02d', $i); ?>
                                                            </option>
                                                        <?php endfor; ?>
                                                    </select>
                                                    <div class="invalid-feedback">
                                                        Select month
                                                    </div>
                                                </div>
                                                <div class="col-6">
                                                    <select class="form-select" name="expiry_year" required>
                                                        <option value="">Year</option>
                                                        <?php for ($i = date('Y'); $i <= date('Y') + 10; $i++): ?>
                                                            <option value="<?php echo $i; ?>"
                                                                <?php echo (isset($expiry_year) && $expiry_year == $i) ? 'selected' : ''; ?>>
                                                                <?php echo $i; ?>
                                                            </option>
                                                        <?php endfor; ?>
                                                    </select>
                                                    <div class="invalid-feedback">
                                                        Select year
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="cvv" class="form-label">CVV *</label>
                                            <div class="input-group">
                                                <input type="text" class="form-control" id="cvv" name="cvv"
                                                       placeholder="123" maxlength="4" required
                                                       pattern="[0-9]{3,4}" autocomplete="cc-csc">
                                                <span class="input-group-text">
                                                    <i class="fas fa-question-circle text-muted"
                                                       title="3-4 digit security code on back of card"></i>
                                                </span>
                                            </div>
                                            <div class="invalid-feedback">
                                                Please enter CVV code.
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div id="ewallet-details" style="display: none;">
                                    <div class="alert alert-info">
                                        <i class="fas fa-info-circle me-2"></i>
                                        You will be redirected to complete your payment securely.
                                    </div>
                                </div>

                                <hr>

                                <div class="d-flex justify-content-between align-items-center">
                                    <a href="<?php echo SITE_URL . '/course.php?id=' . $course_id; ?>" class="btn btn-outline-secondary">
                                        <i class="fas fa-arrow-left me-2"></i>Back to Course
                                    </a>
                                    <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                                        <i class="fas fa-lock me-2"></i>
                                        <span id="btnText">Complete Payment - <?php echo formatCurrency($course['price']); ?></span>
                                        <span id="btnSpinner" class="spinner-border spinner-border-sm ms-2" style="display: none;"></span>
                                    </button>
                                </div>

                                <div class="mt-3">
                                    <small class="text-muted">
                                        <i class="fas fa-shield-alt me-1"></i>
                                        Your payment information is secure and encrypted
                                    </small>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const paymentMethods = document.querySelectorAll('input[name="payment_method"]');
    const cardDetails = document.getElementById('card-details');
    const ewalletDetails = document.getElementById('ewallet-details');
    const paymentForm = document.getElementById('paymentForm');
    const submitBtn = document.getElementById('submitBtn');
    const btnText = document.getElementById('btnText');
    const btnSpinner = document.getElementById('btnSpinner');

    // Payment method selection
    paymentMethods.forEach(method => {
        method.addEventListener('change', function() {
            // Update visual selection
            document.querySelectorAll('.payment-method-card').forEach(card => {
                card.classList.remove('selected');
            });
            this.closest('.payment-method-card').classList.add('selected');

            // Show/hide relevant sections
            if (this.value === 'card') {
                cardDetails.style.display = 'block';
                ewalletDetails.style.display = 'none';
            } else {
                cardDetails.style.display = 'none';
                ewalletDetails.style.display = 'block';
            }
        });
    });

    // Format card number
    const cardNumberInput = document.getElementById('card_number');
    if (cardNumberInput) {
        cardNumberInput.addEventListener('input', function() {
            let value = this.value.replace(/\s/g, '').replace(/[^0-9]/gi, '');
            let formattedValue = value.match(/.{1,4}/g)?.join(' ') || value;
            this.value = formattedValue;

            // Basic card type detection
            const cardIcon = this.parentElement.querySelector('.fa-credit-card');
            if (value.startsWith('4')) {
                cardIcon.className = 'fab fa-cc-visa text-primary';
            } else if (value.startsWith('5')) {
                cardIcon.className = 'fab fa-cc-mastercard text-warning';
            } else if (value.startsWith('3')) {
                cardIcon.className = 'fab fa-cc-amex text-info';
            } else {
                cardIcon.className = 'fas fa-credit-card text-muted';
            }
        });
    }

    // CVV input validation
    const cvvInput = document.getElementById('cvv');
    if (cvvInput) {
        cvvInput.addEventListener('input', function() {
            this.value = this.value.replace(/[^0-9]/g, '');
        });
    }

    // Form submission
    paymentForm.addEventListener('submit', function(e) {
        e.preventDefault();

        // Show loading state
        submitBtn.disabled = true;
        btnText.style.display = 'none';
        btnSpinner.style.display = 'inline-block';

        // Validate form
        if (this.checkValidity()) {
            // Submit form after short delay for UX
            setTimeout(() => {
                this.submit();
            }, 1000);
        } else {
            // Reset button state if validation fails
            submitBtn.disabled = false;
            btnText.style.display = 'inline';
            btnSpinner.style.display = 'none';

            // Show validation errors
            this.classList.add('was-validated');
        }
    });

    // Initialize first payment method as selected
    document.querySelector('.payment-method-card').classList.add('selected');
});
</script>

</body>
</html>
