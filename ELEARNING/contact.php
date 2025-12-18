<?php
require_once __DIR__ . '/config/config.php';

// Handle form submission
$success_message = '';
$error_message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $name = sanitizeInput($_POST['name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $subject = sanitizeInput($_POST['subject'] ?? '');
    $message = sanitizeInput($_POST['message'] ?? '');

    // Validation
    if (empty($name) || empty($email) || empty($subject) || empty($message)) {
        $error_message = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error_message = 'Please enter a valid email address.';
    } else {
        try {
            $database = new Database();
            $conn = $database->getConnection();

            // Insert contact message into database
            $query = "INSERT INTO contact_messages (name, email, subject, message, created_at)
                      VALUES (:name, :email, :subject, :message, NOW())";
            $stmt = $conn->prepare($query);
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':subject', $subject);
            $stmt->bindParam(':message', $message);

            if ($stmt->execute()) {
                $success_message = 'Thank you for your message! We will get back to you soon.';

                // Send email notification (if email is configured)
                $email_subject = "New Contact Message: " . $subject;
                $email_body = "
                    <h3>New Contact Message</h3>
                    <p><strong>Name:</strong> {$name}</p>
                    <p><strong>Email:</strong> {$email}</p>
                    <p><strong>Subject:</strong> {$subject}</p>
                    <p><strong>Message:</strong></p>
                    <p>{$message}</p>
                ";

                // Try to send email (will fail silently if not configured)
                @sendEmail(SITE_EMAIL, $email_subject, $email_body, $email);

                // Clear form data
                $name = $email = $subject = $message = '';
            } else {
                $error_message = 'Sorry, there was an error sending your message. Please try again.';
            }
        } catch (Exception $e) {
            $error_message = 'Sorry, there was an error sending your message. Please try again.';
        }
    }
}

// Get categories for navigation
try {
    $database = new Database();
    $conn = $database->getConnection();

    $query = "SELECT * FROM categories WHERE is_active = 1 ORDER BY name LIMIT 4";
    $stmt = $conn->prepare($query);
    $stmt->execute();
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $categories = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - <?php echo SITE_NAME; ?></title>
    <meta name="description" content="Get in touch with <?php echo SITE_NAME; ?>. We're here to help with any questions about our online courses and learning platform.">

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">

    <style>
        .hero-section {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 100px 0 80px;
        }

        .contact-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }

        .contact-card:hover {
            transform: translateY(-5px);
        }

        .contact-icon {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            margin: 0 auto 20px;
        }

        .form-control:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 12px 30px;
            border-radius: 25px;
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #5a6fd8 0%, #6a4190 100%);
            transform: translateY(-2px);
        }

        .map-container {
            height: 400px;
            background: #f8f9fa;
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #6c757d;
        }

        .social-links a {
            display: inline-block;
            width: 50px;
            height: 50px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            text-align: center;
            line-height: 50px;
            border-radius: 50%;
            margin: 0 10px;
            transition: transform 0.3s ease;
        }

        .social-links a:hover {
            transform: translateY(-3px);
            color: white;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm fixed-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <i class="fas fa-graduation-cap me-2 text-primary"></i><?php echo SITE_NAME; ?>
            </a>

            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="index.php">Home</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="courses.php">Courses</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="about.php">About</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="contact.php">Contact</a>
                    </li>
                </ul>

                <ul class="navbar-nav">
                    <?php if (isLoggedIn()): ?>
                        <?php if (hasRole(ROLE_ADMIN)): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="admin/index.php">
                                    <i class="fas fa-cog me-1"></i>Admin
                                </a>
                            </li>
                        <?php elseif (hasRole(ROLE_INSTRUCTOR)): ?>
                            <li class="nav-item">
                                <a class="nav-link" href="instructor/index.php">
                                    <i class="fas fa-chalkboard-teacher me-1"></i>Instructor
                                </a>
                            </li>
                        <?php else: ?>
                            <li class="nav-item">
                                <a class="nav-link" href="student/index.php">
                                    <i class="fas fa-user-graduate me-1"></i>Dashboard
                                </a>
                            </li>
                        <?php endif; ?>
                        <li class="nav-item">
                            <a class="nav-link" href="auth/logout.php">
                                <i class="fas fa-sign-out-alt me-1"></i>Logout
                            </a>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="auth/login.php">Login</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="auth/register.php">Register</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero-section">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-lg-6">
                    <h1 class="display-4 fw-bold mb-4">Get in Touch</h1>
                    <p class="lead mb-4">
                        Have questions about our courses or need support? We're here to help!
                        Reach out to us and we'll get back to you as soon as possible.
                    </p>
                    <div class="d-flex align-items-center">
                        <i class="fas fa-clock me-3 fa-2x"></i>
                        <div>
                            <h6 class="mb-1">Response Time</h6>
                            <p class="mb-0">Usually within 24 hours</p>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 text-center">
                    <i class="fas fa-envelope fa-10x opacity-25"></i>
                </div>
            </div>
        </div>
    </section>

    <!-- Contact Section -->
    <section class="py-5">
        <div class="container">
            <!-- Contact Info Cards -->
            <div class="row mb-5">
                <div class="col-lg-4 mb-4">
                    <div class="card contact-card h-100 text-center p-4">
                        <div class="contact-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <h5>Email Us</h5>
                        <p class="text-muted mb-3">Send us an email and we'll respond within 24 hours</p>
                        <a href="mailto:<?php echo SITE_EMAIL; ?>" class="text-primary fw-bold">
                            <?php echo SITE_EMAIL; ?>
                        </a>
                    </div>
                </div>

                <div class="col-lg-4 mb-4">
                    <div class="card contact-card h-100 text-center p-4">
                        <div class="contact-icon">
                            <i class="fas fa-phone"></i>
                        </div>
                        <h5>Call Us</h5>
                        <p class="text-muted mb-3">Speak directly with our support team</p>
                        <a href="tel:+63021234567" class="text-primary fw-bold">
                            +63 (02) 123-4567
                        </a>
                    </div>
                </div>

                <div class="col-lg-4 mb-4">
                    <div class="card contact-card h-100 text-center p-4">
                        <div class="contact-icon">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <h5>Visit Us</h5>
                        <p class="text-muted mb-3">Come visit our office for in-person support</p>
                        <address class="text-primary fw-bold mb-0">
                            123 Education Street<br>
                            Manila, Philippines 1000
                        </address>
                    </div>
                </div>
            </div>

            <!-- Contact Form and Map -->
            <div class="row">
                <div class="col-lg-8 mb-4">
                    <div class="card contact-card p-4">
                        <h3 class="mb-4">Send us a Message</h3>

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

                        <form method="POST" action="">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="name" class="form-label">Full Name *</label>
                                    <input type="text" class="form-control" id="name" name="name"
                                           value="<?php echo htmlspecialchars($name ?? ''); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="email" class="form-label">Email Address *</label>
                                    <input type="email" class="form-control" id="email" name="email"
                                           value="<?php echo htmlspecialchars($email ?? ''); ?>" required>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="subject" class="form-label">Subject *</label>
                                <select class="form-select" id="subject" name="subject" required>
                                    <option value="">Choose a subject...</option>
                                    <option value="General Inquiry" <?php echo (isset($subject) && $subject === 'General Inquiry') ? 'selected' : ''; ?>>General Inquiry</option>
                                    <option value="Course Support" <?php echo (isset($subject) && $subject === 'Course Support') ? 'selected' : ''; ?>>Course Support</option>
                                    <option value="Technical Issue" <?php echo (isset($subject) && $subject === 'Technical Issue') ? 'selected' : ''; ?>>Technical Issue</option>
                                    <option value="Payment Issue" <?php echo (isset($subject) && $subject === 'Payment Issue') ? 'selected' : ''; ?>>Payment Issue</option>
                                    <option value="Instructor Application" <?php echo (isset($subject) && $subject === 'Instructor Application') ? 'selected' : ''; ?>>Instructor Application</option>
                                    <option value="Partnership" <?php echo (isset($subject) && $subject === 'Partnership') ? 'selected' : ''; ?>>Partnership</option>
                                    <option value="Other" <?php echo (isset($subject) && $subject === 'Other') ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>

                            <div class="mb-4">
                                <label for="message" class="form-label">Message *</label>
                                <textarea class="form-control" id="message" name="message" rows="6"
                                          placeholder="Please describe your inquiry in detail..." required><?php echo htmlspecialchars($message ?? ''); ?></textarea>
                            </div>

                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-paper-plane me-2"></i>Send Message
                            </button>
                        </form>
                    </div>
                </div>

                <div class="col-lg-4">
                    <div class="card contact-card p-4 mb-4">
                        <h5 class="mb-3">Office Hours</h5>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Monday - Friday:</span>
                            <span class="fw-bold">9:00 AM - 6:00 PM</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Saturday:</span>
                            <span class="fw-bold">10:00 AM - 4:00 PM</span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span>Sunday:</span>
                            <span class="fw-bold">Closed</span>
                        </div>
                        <small class="text-muted">
                            <i class="fas fa-info-circle me-1"></i>
                            All times are in Philippine Standard Time (PST)
                        </small>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- FAQ Section -->
    <section class="py-5 bg-light">
        <div class="container">
            <div class="row">
                <div class="col-lg-8 mx-auto">
                    <h2 class="text-center mb-5">Frequently Asked Questions</h2>

                    <div class="accordion" id="faqAccordion">
                        <div class="accordion-item mb-3">
                            <h2 class="accordion-header" id="faq1">
                                <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapse1">
                                    How do I enroll in a course?
                                </button>
                            </h2>
                            <div id="collapse1" class="accordion-collapse collapse show" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    To enroll in a course, simply browse our course catalog, select the course you're interested in, and click the "Enroll Now" button. For paid courses, you'll need to complete the payment process before gaining access.
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item mb-3">
                            <h2 class="accordion-header" id="faq2">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse2">
                                    What payment methods do you accept?
                                </button>
                            </h2>
                            <div id="collapse2" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    We accept major credit cards (Visa, MasterCard), as well as local payment methods like GCash and PayMaya. All payments are processed securely through our payment gateway.
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item mb-3">
                            <h2 class="accordion-header" id="faq3">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse3">
                                    Can I get a refund if I'm not satisfied?
                                </button>
                            </h2>
                            <div id="collapse3" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Yes, we offer a 30-day money-back guarantee for all paid courses. If you're not satisfied with your purchase, contact us within 30 days for a full refund.
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item mb-3">
                            <h2 class="accordion-header" id="faq4">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse4">
                                    How do I become an instructor?
                                </button>
                            </h2>
                            <div id="collapse4" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    To become an instructor, please contact us through this form with the subject "Instructor Application". Include your qualifications, teaching experience, and the topics you'd like to teach. Our team will review your application and get back to you.
                                </div>
                            </div>
                        </div>

                        <div class="accordion-item">
                            <h2 class="accordion-header" id="faq5">
                                <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse5">
                                    Do you offer certificates upon completion?
                                </button>
                            </h2>
                            <div id="collapse5" class="accordion-collapse collapse" data-bs-parent="#faqAccordion">
                                <div class="accordion-body">
                                    Yes, upon successful completion of a course, you'll receive a certificate of completion that you can download and share on your professional profiles.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-dark text-white py-5">
        <div class="container">
            <div class="row">
                <div class="col-lg-4 mb-4">
                    <h5><i class="fas fa-graduation-cap me-2"></i><?php echo SITE_NAME; ?></h5>
                    <p class="text-muted">
                        Empowering learners worldwide with quality online education.
                        Learn new skills, advance your career, and achieve your goals.
                    </p>
                </div>
                <div class="col-lg-2 col-md-6 mb-4">
                    <h6>Quick Links</h6>
                    <ul class="list-unstyled">
                        <li><a href="courses.php" class="text-muted">Courses</a></li>
                        <li><a href="about.php" class="text-muted">About</a></li>
                        <li><a href="contact.php" class="text-muted">Contact</a></li>
                        <li><a href="help.php" class="text-muted">Help</a></li>
                    </ul>
                </div>
                <div class="col-lg-2 col-md-6 mb-4">
                    <h6>Categories</h6>
                    <ul class="list-unstyled">
                        <?php foreach (array_slice($categories, 0, 4) as $category): ?>
                            <li><a href="courses.php?category=<?php echo $category['id']; ?>" class="text-muted">
                                <?php echo htmlspecialchars($category['name']); ?>
                            </a></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <div class="col-lg-4 mb-4">
                    <h6>Contact Info</h6>
                    <p class="text-muted mb-1">
                        <i class="fas fa-envelope me-2"></i>
                        <?php echo SITE_EMAIL; ?>
                    </p>
                    <p class="text-muted mb-3">
                        <i class="fas fa-phone me-2"></i>
                        +63 (02) 123-4567
                    </p>

                </div>
            </div>
            <hr class="my-4">
            <div class="row align-items-center">
                <div class="col-md-6">
                    <p class="text-muted mb-0">&copy; <?php echo date('Y'); ?> <?php echo SITE_NAME; ?>. All rights reserved.</p>
                </div>
                <div class="col-md-6 text-md-end">
                    <a href="privacy.php" class="text-muted me-3">Privacy Policy</a>
                    <a href="terms.php" class="text-muted">Terms of Service</a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>