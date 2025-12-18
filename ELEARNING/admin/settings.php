<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../classes/User.php';

// Check if user is admin
requireRole(ROLE_ADMIN);

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $database = new Database();
        $conn = $database->getConnection();

        // Update settings
        $settings_to_update = [
            'site_name',
            'site_description',
            'site_email',
            'currency',
            'timezone',
            'max_file_size',
            'allowed_file_types',
            'items_per_page',
            'enable_registration',
            'enable_course_reviews',
            'enable_forums',
            'enable_certificates',
            'smtp_host',
            'smtp_port',
            'smtp_username',
            'smtp_password',
            'smtp_encryption',
            'maintenance_mode',
            'maintenance_message',
            'google_analytics_id',
            'facebook_url',
            'twitter_url',
            'linkedin_url',
            'youtube_url'
        ];

        $updated_count = 0;

        foreach ($settings_to_update as $setting_key) {
            if (isset($_POST[$setting_key])) {
                $setting_value = sanitizeInput($_POST[$setting_key]);

                // Special handling for checkboxes
                if (in_array($setting_key, ['enable_registration', 'enable_course_reviews', 'enable_forums', 'enable_certificates', 'maintenance_mode'])) {
                    $setting_value = isset($_POST[$setting_key]) ? '1' : '0';
                }

                // Update or insert setting
                $query = "INSERT INTO settings (setting_key, setting_value) VALUES (:key, :value)
                         ON DUPLICATE KEY UPDATE setting_value = :value, updated_at = CURRENT_TIMESTAMP";
                $stmt = $conn->prepare($query);
                $stmt->bindParam(':key', $setting_key);
                $stmt->bindParam(':value', $setting_value);

                if ($stmt->execute()) {
                    $updated_count++;
                }
            }
        }

        if ($updated_count > 0) {
            $success_message = "Settings updated successfully! ($updated_count settings changed)";
        } else {
            $error_message = "No settings were updated.";
        }

    } catch (Exception $e) {
        $error_message = "Error updating settings: " . $e->getMessage();
    }
}

// Get current settings
$current_settings = getSiteConfig();

// Default values for new settings
$default_settings = [
    'site_name' => 'E-Learning Platform',
    'site_description' => 'Learn new skills with our comprehensive online courses',
    'site_email' => 'info@elearning.com',
    'currency' => 'PHP',
    'timezone' => 'UTC',
    'max_file_size' => '50',
    'allowed_file_types' => 'jpg,jpeg,png,gif,pdf,doc,docx,ppt,pptx,txt,xls,xlsx,rtf,odt,ods,odp,mp4,mp3,avi,mov,wmv',
    'items_per_page' => '12',
    'enable_registration' => '1',
    'enable_course_reviews' => '1',
    'enable_forums' => '1',
    'enable_certificates' => '1',
    'smtp_host' => '',
    'smtp_port' => '587',
    'smtp_username' => '',
    'smtp_password' => '',
    'smtp_encryption' => 'tls',
    'maintenance_mode' => '0',
    'maintenance_message' => 'We are currently performing maintenance. Please check back later.',
    'google_analytics_id' => '',
    'facebook_url' => '',
    'twitter_url' => '',
    'linkedin_url' => '',
    'youtube_url' => ''
];

// Merge current settings with defaults
$settings = array_merge($default_settings, $current_settings);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - <?php echo SITE_NAME; ?></title>
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
        .settings-section {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        .settings-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px 20px;
            border-radius: 10px 10px 0 0;
            margin: 0;
        }
        .settings-body {
            padding: 20px;
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
            <h1><i class="fas fa-cog me-2"></i>System Settings</h1>
            <div class="text-muted">
                Configure your e-learning platform
            </div>
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

        <form method="POST">
            <!-- General Settings -->
            <div class="settings-section">
                <h5 class="settings-header">
                    <i class="fas fa-globe me-2"></i>General Settings
                </h5>
                <div class="settings-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="site_name" class="form-label">Site Name</label>
                            <input type="text" class="form-control" id="site_name" name="site_name"
                                   value="<?php echo htmlspecialchars($settings['site_name']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="site_email" class="form-label">Site Email</label>
                            <input type="email" class="form-control" id="site_email" name="site_email"
                                   value="<?php echo htmlspecialchars($settings['site_email']); ?>" required>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="site_description" class="form-label">Site Description</label>
                        <textarea class="form-control" id="site_description" name="site_description" rows="3"><?php echo htmlspecialchars($settings['site_description']); ?></textarea>
                        <div class="form-text">This will be used for SEO meta descriptions.</div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="currency" class="form-label">Default Currency</label>
                            <select class="form-select" id="currency" name="currency">
                                <option value="PHP" <?php echo $settings['currency'] === 'PHP' ? 'selected' : ''; ?>>PHP - Philippine Peso</option>
                                <option value="USD" <?php echo $settings['currency'] === 'USD' ? 'selected' : ''; ?>>USD - US Dollar</option>
                                <option value="EUR" <?php echo $settings['currency'] === 'EUR' ? 'selected' : ''; ?>>EUR - Euro</option>
                                <option value="GBP" <?php echo $settings['currency'] === 'GBP' ? 'selected' : ''; ?>>GBP - British Pound</option>
                                <option value="CAD" <?php echo $settings['currency'] === 'CAD' ? 'selected' : ''; ?>>CAD - Canadian Dollar</option>
                                <option value="AUD" <?php echo $settings['currency'] === 'AUD' ? 'selected' : ''; ?>>AUD - Australian Dollar</option>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="timezone" class="form-label">Default Timezone</label>
                            <select class="form-select" id="timezone" name="timezone">
                                <option value="UTC" <?php echo $settings['timezone'] === 'UTC' ? 'selected' : ''; ?>>UTC</option>
                                <option value="America/New_York" <?php echo $settings['timezone'] === 'America/New_York' ? 'selected' : ''; ?>>Eastern Time</option>
                                <option value="America/Chicago" <?php echo $settings['timezone'] === 'America/Chicago' ? 'selected' : ''; ?>>Central Time</option>
                                <option value="America/Denver" <?php echo $settings['timezone'] === 'America/Denver' ? 'selected' : ''; ?>>Mountain Time</option>
                                <option value="America/Los_Angeles" <?php echo $settings['timezone'] === 'America/Los_Angeles' ? 'selected' : ''; ?>>Pacific Time</option>
                                <option value="Europe/London" <?php echo $settings['timezone'] === 'Europe/London' ? 'selected' : ''; ?>>London</option>
                                <option value="Europe/Paris" <?php echo $settings['timezone'] === 'Europe/Paris' ? 'selected' : ''; ?>>Paris</option>
                                <option value="Asia/Tokyo" <?php echo $settings['timezone'] === 'Asia/Tokyo' ? 'selected' : ''; ?>>Tokyo</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <!-- File Upload Settings -->
            <div class="settings-section">
                <h5 class="settings-header">
                    <i class="fas fa-upload me-2"></i>File Upload Settings
                </h5>
                <div class="settings-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="max_file_size" class="form-label">Maximum File Size (MB)</label>
                            <input type="number" class="form-control" id="max_file_size" name="max_file_size"
                                   value="<?php echo htmlspecialchars($settings['max_file_size']); ?>" min="1" max="500">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="items_per_page" class="form-label">Items Per Page</label>
                            <input type="number" class="form-control" id="items_per_page" name="items_per_page"
                                   value="<?php echo htmlspecialchars($settings['items_per_page']); ?>" min="5" max="50">
                        </div>
                    </div>

                    <div class="mb-3">
                        <label for="allowed_file_types" class="form-label">Allowed File Types</label>
                        <input type="text" class="form-control" id="allowed_file_types" name="allowed_file_types"
                               value="<?php echo htmlspecialchars($settings['allowed_file_types']); ?>">
                        <div class="form-text">Comma-separated list of file extensions (e.g., jpg,png,pdf,mp4)</div>
                    </div>
                </div>
            </div>

            <!-- Feature Settings -->
            <div class="settings-section">
                <h5 class="settings-header">
                    <i class="fas fa-toggle-on me-2"></i>Feature Settings
                </h5>
                <div class="settings-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="enable_registration" name="enable_registration"
                                       <?php echo $settings['enable_registration'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="enable_registration">
                                    <strong>Enable User Registration</strong><br>
                                    <small class="text-muted">Allow new users to register accounts</small>
                                </label>
                            </div>

                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="enable_course_reviews" name="enable_course_reviews"
                                       <?php echo $settings['enable_course_reviews'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="enable_course_reviews">
                                    <strong>Enable Course Reviews</strong><br>
                                    <small class="text-muted">Allow students to rate and review courses</small>
                                </label>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="enable_forums" name="enable_forums"
                                       <?php echo $settings['enable_forums'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="enable_forums">
                                    <strong>Enable Discussion Forums</strong><br>
                                    <small class="text-muted">Allow course discussions and Q&A</small>
                                </label>
                            </div>

                            <div class="form-check form-switch mb-3">
                                <input class="form-check-input" type="checkbox" id="enable_certificates" name="enable_certificates"
                                       <?php echo $settings['enable_certificates'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="enable_certificates">
                                    <strong>Enable Certificates</strong><br>
                                    <small class="text-muted">Issue completion certificates to students</small>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Email Settings -->
            <div class="settings-section">
                <h5 class="settings-header">
                    <i class="fas fa-envelope me-2"></i>Email Settings (SMTP)
                </h5>
                <div class="settings-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="smtp_host" class="form-label">SMTP Host</label>
                            <input type="text" class="form-control" id="smtp_host" name="smtp_host"
                                   value="<?php echo htmlspecialchars($settings['smtp_host']); ?>"
                                   placeholder="smtp.gmail.com">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="smtp_port" class="form-label">SMTP Port</label>
                            <input type="number" class="form-control" id="smtp_port" name="smtp_port"
                                   value="<?php echo htmlspecialchars($settings['smtp_port']); ?>"
                                   placeholder="587">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="smtp_encryption" class="form-label">Encryption</label>
                            <select class="form-select" id="smtp_encryption" name="smtp_encryption">
                                <option value="tls" <?php echo $settings['smtp_encryption'] === 'tls' ? 'selected' : ''; ?>>TLS</option>
                                <option value="ssl" <?php echo $settings['smtp_encryption'] === 'ssl' ? 'selected' : ''; ?>>SSL</option>
                                <option value="none" <?php echo $settings['smtp_encryption'] === 'none' ? 'selected' : ''; ?>>None</option>
                            </select>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="smtp_username" class="form-label">SMTP Username</label>
                            <input type="text" class="form-control" id="smtp_username" name="smtp_username"
                                   value="<?php echo htmlspecialchars($settings['smtp_username']); ?>"
                                   placeholder="your-email@gmail.com">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="smtp_password" class="form-label">SMTP Password</label>
                            <input type="password" class="form-control" id="smtp_password" name="smtp_password"
                                   value="<?php echo htmlspecialchars($settings['smtp_password']); ?>"
                                   placeholder="Your app password">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Maintenance Mode -->
            <div class="settings-section">
                <h5 class="settings-header">
                    <i class="fas fa-tools me-2"></i>Maintenance Mode
                </h5>
                <div class="settings-body">
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" id="maintenance_mode" name="maintenance_mode"
                               <?php echo $settings['maintenance_mode'] ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="maintenance_mode">
                            <strong>Enable Maintenance Mode</strong><br>
                            <small class="text-muted">Temporarily disable the site for maintenance</small>
                        </label>
                    </div>

                    <div class="mb-3">
                        <label for="maintenance_message" class="form-label">Maintenance Message</label>
                        <textarea class="form-control" id="maintenance_message" name="maintenance_message" rows="3"><?php echo htmlspecialchars($settings['maintenance_message']); ?></textarea>
                    </div>
                </div>
            </div>

            <!-- Analytics & Social -->
            <div class="settings-section">
                <h5 class="settings-header">
                    <i class="fas fa-chart-line me-2"></i>Analytics & Social Media
                </h5>
                <div class="settings-body">
                    <div class="mb-3">
                        <label for="google_analytics_id" class="form-label">Google Analytics ID</label>
                        <input type="text" class="form-control" id="google_analytics_id" name="google_analytics_id"
                               value="<?php echo htmlspecialchars($settings['google_analytics_id']); ?>"
                               placeholder="G-XXXXXXXXXX">
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="facebook_url" class="form-label">Facebook URL</label>
                            <input type="url" class="form-control" id="facebook_url" name="facebook_url"
                                   value="<?php echo htmlspecialchars($settings['facebook_url']); ?>"
                                   placeholder="https://facebook.com/yourpage">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="twitter_url" class="form-label">Twitter URL</label>
                            <input type="url" class="form-control" id="twitter_url" name="twitter_url"
                                   value="<?php echo htmlspecialchars($settings['twitter_url']); ?>"
                                   placeholder="https://twitter.com/youraccount">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="linkedin_url" class="form-label">LinkedIn URL</label>
                            <input type="url" class="form-control" id="linkedin_url" name="linkedin_url"
                                   value="<?php echo htmlspecialchars($settings['linkedin_url']); ?>"
                                   placeholder="https://linkedin.com/company/yourcompany">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="youtube_url" class="form-label">YouTube URL</label>
                            <input type="url" class="form-control" id="youtube_url" name="youtube_url"
                                   value="<?php echo htmlspecialchars($settings['youtube_url']); ?>"
                                   placeholder="https://youtube.com/c/yourchannel">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Save Button -->
            <div class="text-center mb-4">
                <button type="submit" class="btn btn-primary btn-lg px-5">
                    <i class="fas fa-save me-2"></i>Save All Settings
                </button>
            </div>
        </form>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Show confirmation when maintenance mode is enabled
        document.getElementById('maintenance_mode').addEventListener('change', function() {
            if (this.checked) {
                if (!confirm('Are you sure you want to enable maintenance mode? This will make the site unavailable to regular users.')) {
                    this.checked = false;
                }
            }
        });

        // Auto-save indication
        const form = document.querySelector('form');
        const saveButton = document.querySelector('button[type="submit"]');
        const originalText = saveButton.innerHTML;

        form.addEventListener('submit', function() {
            saveButton.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i>Saving...';
            saveButton.disabled = true;
        });
    </script>
</body>
</html>
