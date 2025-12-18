<?php
/**
 * User Class
 * Handles user authentication and management
 */

require_once __DIR__ . '/../config/config.php';

class User {
    private $conn;
    private $table_name = "users";

    public $id;
    public $username;
    public $email;
    public $password;
    public $first_name;
    public $last_name;
    public $role;
    public $profile_image;
    public $bio;
    public $phone;
    public $date_of_birth;
    public $is_active;
    public $email_verified;
    public $created_at;
    public $updated_at;

    public function __construct() {
        $database = new Database();
        $this->conn = $database->getConnection();
    }

    /**
     * Register a new user
     */
    public function register() {
        // Set default profile image if not already set
        if (empty($this->profile_image)) {
            $this->profile_image = 'default-avatar.png';
        }

        $query = "INSERT INTO " . $this->table_name . "
                  SET username=:username, email=:email, password=:password,
                      first_name=:first_name, last_name=:last_name, role=:role,
                      profile_image=:profile_image";

        $stmt = $this->conn->prepare($query);

        // Sanitize inputs
        $this->username = htmlspecialchars(strip_tags($this->username));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->first_name = htmlspecialchars(strip_tags($this->first_name));
        $this->last_name = htmlspecialchars(strip_tags($this->last_name));
        $this->role = htmlspecialchars(strip_tags($this->role));

        // Hash password
        $password_hash = password_hash($this->password, PASSWORD_DEFAULT);

        // Bind values
        $stmt->bindParam(":username", $this->username);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":password", $password_hash);
        $stmt->bindParam(":first_name", $this->first_name);
        $stmt->bindParam(":last_name", $this->last_name);
        $stmt->bindParam(":role", $this->role);
        $stmt->bindParam(":profile_image", $this->profile_image);

        if ($stmt->execute()) {
            $this->id = $this->conn->lastInsertId();
            return true;
        }

        return false;
    }

    /**
     * Login user (legacy method - supports both username and email)
     */
    public function login($username_or_email, $password) {
        $query = "SELECT id, username, email, password, first_name, last_name, role, is_active
                  FROM " . $this->table_name . "
                  WHERE (username = :username_or_email OR email = :username_or_email)
                  AND is_active = 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":username_or_email", $username_or_email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (password_verify($password, $row['password'])) {
                $this->id = $row['id'];
                $this->username = $row['username'];
                $this->email = $row['email'];
                $this->first_name = $row['first_name'];
                $this->last_name = $row['last_name'];
                $this->role = $row['role'];

                // Set session variables
                $_SESSION['user_id'] = $this->id;
                $_SESSION['username'] = $this->username;
                $_SESSION['user_role'] = $this->role;
                $_SESSION['user_name'] = $this->first_name . ' ' . $this->last_name;

                return true;
            }
        }

        return false;
    }

    /**
     * Login user with email only
     */
    public function loginWithEmail($email, $password) {
        $query = "SELECT id, username, email, password, first_name, last_name, role, is_active
                  FROM " . $this->table_name . "
                  WHERE email = :email AND is_active = 1";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (password_verify($password, $row['password'])) {
                $this->id = $row['id'];
                $this->username = $row['username'];
                $this->email = $row['email'];
                $this->first_name = $row['first_name'];
                $this->last_name = $row['last_name'];
                $this->role = $row['role'];

                // Set session variables
                $_SESSION['user_id'] = $this->id;
                $_SESSION['username'] = $this->username;
                $_SESSION['user_role'] = $this->role;
                $_SESSION['user_name'] = $this->first_name . ' ' . $this->last_name;

                return true;
            }
        }

        return false;
    }

    /**
     * Logout user
     */
    public function logout() {
        session_unset();
        session_destroy();
        return true;
    }

    /**
     * Check if username exists
     */
    public function usernameExists($username) {
        $query = "SELECT id FROM " . $this->table_name . " WHERE username = :username";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":username", $username);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * Check if email exists
     */
    public function emailExists($email) {
        $query = "SELECT id FROM " . $this->table_name . " WHERE email = :email";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":email", $email);
        $stmt->execute();

        return $stmt->rowCount() > 0;
    }

    /**
     * Get user by ID
     */
    public function getUserById($id) {
        $query = "SELECT * FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $id);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            $this->id = $row['id'];
            $this->username = $row['username'];
            $this->email = $row['email'];
            $this->first_name = $row['first_name'];
            $this->last_name = $row['last_name'];
            $this->role = $row['role'];
            $this->profile_image = $row['profile_image'];
            $this->bio = $row['bio'];
            $this->phone = $row['phone'];
            $this->date_of_birth = $row['date_of_birth'];
            $this->is_active = $row['is_active'];
            $this->email_verified = $row['email_verified'];
            $this->created_at = $row['created_at'];
            $this->updated_at = $row['updated_at'];

            return true;
        }

        return false;
    }

    /**
     * Update user profile
     */
    public function updateProfile() {
        $query = "UPDATE " . $this->table_name . "
                  SET first_name=:first_name, last_name=:last_name, email=:email,
                      bio=:bio, phone=:phone, date_of_birth=:date_of_birth,
                      updated_at=CURRENT_TIMESTAMP
                  WHERE id=:id";

        $stmt = $this->conn->prepare($query);

        // Sanitize inputs
        $this->first_name = htmlspecialchars(strip_tags($this->first_name));
        $this->last_name = htmlspecialchars(strip_tags($this->last_name));
        $this->email = htmlspecialchars(strip_tags($this->email));
        $this->bio = htmlspecialchars(strip_tags($this->bio));
        $this->phone = htmlspecialchars(strip_tags($this->phone));

        // Bind values
        $stmt->bindParam(":first_name", $this->first_name);
        $stmt->bindParam(":last_name", $this->last_name);
        $stmt->bindParam(":email", $this->email);
        $stmt->bindParam(":bio", $this->bio);
        $stmt->bindParam(":phone", $this->phone);
        $stmt->bindParam(":date_of_birth", $this->date_of_birth);
        $stmt->bindParam(":id", $this->id);

        return $stmt->execute();
    }

    /**
     * Change password
     */
    public function changePassword($current_password, $new_password) {
        // First verify current password
        $query = "SELECT password FROM " . $this->table_name . " WHERE id = :id";
        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $this->id);
        $stmt->execute();

        if ($stmt->rowCount() > 0) {
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (password_verify($current_password, $row['password'])) {
                // Update password
                $query = "UPDATE " . $this->table_name . "
                          SET password=:password, updated_at=CURRENT_TIMESTAMP
                          WHERE id=:id";

                $stmt = $this->conn->prepare($query);
                $password_hash = password_hash($new_password, PASSWORD_DEFAULT);

                $stmt->bindParam(":password", $password_hash);
                $stmt->bindParam(":id", $this->id);

                return $stmt->execute();
            }
        }

        return false;
    }

    /**
     * Get all users with pagination
     */
    public function getAllUsers($page = 1, $limit = 10, $role = null) {
        $offset = ($page - 1) * $limit;

        $where_clause = "";
        if ($role) {
            $where_clause = "WHERE role = :role";
        }

        $query = "SELECT id, username, email, first_name, last_name, role,
                         profile_image, is_active, email_verified, created_at
                  FROM " . $this->table_name . "
                  " . $where_clause . "
                  ORDER BY created_at DESC
                  LIMIT :limit OFFSET :offset";

        $stmt = $this->conn->prepare($query);

        if ($role) {
            $stmt->bindParam(":role", $role);
        }

        $stmt->bindParam(":limit", $limit, PDO::PARAM_INT);
        $stmt->bindParam(":offset", $offset, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get total user count
     */
    public function getTotalUsers($role = null) {
        $where_clause = "";
        if ($role) {
            $where_clause = "WHERE role = :role";
        }

        $query = "SELECT COUNT(*) as total FROM " . $this->table_name . " " . $where_clause;
        $stmt = $this->conn->prepare($query);

        if ($role) {
            $stmt->bindParam(":role", $role);
        }

        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        return $row['total'];
    }

    /**
     * Toggle user active status
     */
    public function toggleActiveStatus($user_id) {
        $query = "UPDATE " . $this->table_name . "
                  SET is_active = NOT is_active, updated_at=CURRENT_TIMESTAMP
                  WHERE id = :id";

        $stmt = $this->conn->prepare($query);
        $stmt->bindParam(":id", $user_id);

        return $stmt->execute();
    }

    /**
     * Delete user
     */
    public function deleteUser($user_id) {
        try {
            // Start transaction
            $this->conn->beginTransaction();

            // Create a temporary user object to get user data
            $temp_user = new User();
            $user_found = $temp_user->getUserById($user_id);

            if (!$user_found) {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'User not found'];
            }

            // Store user info for logging
            $user_name = $temp_user->first_name . ' ' . $temp_user->last_name;
            $user_profile_image = $temp_user->profile_image;

            // Get counts for informational purposes
            $query = "SELECT COUNT(*) as count FROM enrollments WHERE student_id = :user_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":user_id", $user_id);
            $stmt->execute();
            $enrollment_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

            $query = "SELECT COUNT(*) as count FROM courses WHERE instructor_id = :user_id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":user_id", $user_id);
            $stmt->execute();
            $course_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

            // Delete profile image if exists and it's not a default avatar
            if ($user_profile_image &&
                $user_profile_image !== 'default-avatar.png' &&
                $user_profile_image !== 'default.png') {
                $file_path = UPLOAD_PATH . 'profiles/' . $user_profile_image;
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }

            // Delete user from database (CASCADE will handle related data)
            $query = "DELETE FROM " . $this->table_name . " WHERE id = :id";
            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":id", $user_id);

            if ($stmt->execute()) {
                $this->conn->commit();

                // Log the deletion activity (if activity logging is available)
                try {
                    if (function_exists('logActivity')) {
                        logActivity($_SESSION['user_id'] ?? null, 'user_deleted', "Deleted user: $user_name (ID: $user_id)");
                    }
                } catch (Exception $log_error) {
                    // Continue even if logging fails
                }

                // Build success message with details
                $message = 'User deleted successfully';
                if ($enrollment_count > 0 || $course_count > 0) {
                    $message .= ' along with ';
                    $details = [];
                    if ($enrollment_count > 0) {
                        $details[] = "{$enrollment_count} enrollment" . ($enrollment_count > 1 ? 's' : '');
                    }
                    if ($course_count > 0) {
                        $details[] = "{$course_count} course" . ($course_count > 1 ? 's' : '');
                    }
                    $message .= implode(' and ', $details);
                }
                $message .= '.';

                return ['success' => true, 'message' => $message];
            } else {
                $this->conn->rollBack();
                return ['success' => false, 'message' => 'Failed to delete user from database'];
            }

        } catch (Exception $e) {
            $this->conn->rollBack();
            return ['success' => false, 'message' => 'Error: ' . $e->getMessage()];
        }
    }

    /**
     * Get user's full name
     */
    public function getFullName() {
        return $this->first_name . ' ' . $this->last_name;
    }

    /**
     * Upload profile image
     */
    public function uploadProfileImage($file) {
        $allowed_types = ALLOWED_IMAGE_TYPES;
        $upload_dir = UPLOAD_PATH . 'profiles/';

        $result = uploadFile($file, $upload_dir, $allowed_types, 5 * 1024 * 1024); // 5MB limit for avatars

        if ($result['success']) {
            // Delete old profile image if exists
            if ($this->profile_image && file_exists($upload_dir . $this->profile_image)) {
                unlink($upload_dir . $this->profile_image);
            }

            // Update profile image in database
            $query = "UPDATE " . $this->table_name . "
                      SET profile_image = :profile_image, updated_at = CURRENT_TIMESTAMP
                      WHERE id = :id";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":profile_image", $result['filename']);
            $stmt->bindParam(":id", $this->id);

            if ($stmt->execute()) {
                $this->profile_image = $result['filename'];
                return $result;
            } else {
                // Delete uploaded file if database update fails
                unlink($result['filepath']);
                return ['success' => false, 'message' => 'Failed to update profile image in database.'];
            }
        }

        return $result;
    }

    /**
     * Delete profile image
     */
    public function deleteProfileImage() {
        if ($this->profile_image) {
            $file_path = UPLOAD_PATH . 'profiles/' . $this->profile_image;

            // Delete file if exists
            if (file_exists($file_path)) {
                unlink($file_path);
            }

            // Update database
            $query = "UPDATE " . $this->table_name . "
                      SET profile_image = NULL, updated_at = CURRENT_TIMESTAMP
                      WHERE id = :id";

            $stmt = $this->conn->prepare($query);
            $stmt->bindParam(":id", $this->id);

            if ($stmt->execute()) {
                $this->profile_image = null;
                return true;
            }
        }

        return false;
    }

    /**
     * Get user's profile image URL
     */
    public function getProfileImageUrl() {
        if ($this->profile_image && file_exists(UPLOAD_PATH . 'profiles/' . $this->profile_image)) {
            return SITE_URL . '/uploads/profiles/' . $this->profile_image;
        }

        return SITE_URL . '/assets/images/default-avatar.png';
    }

    /**
     * Get avatar HTML with fallback
     */
    public function getAvatarHtml($size = 40, $class = 'rounded-circle') {
        $url = $this->getProfileImageUrl();
        $name = htmlspecialchars($this->getFullName());

        return '<img src="' . $url . '" alt="' . $name . '" class="' . $class . '"
                     style="width: ' . $size . 'px; height: ' . $size . 'px; object-fit: cover;"
                     onerror="this.src=\'' . SITE_URL . '/assets/images/default-avatar.png\'">';
    }

    /**
     * Generate avatar initials as fallback
     */
    public function getAvatarInitials() {
        $initials = '';
        if ($this->first_name) {
            $initials .= strtoupper(substr($this->first_name, 0, 1));
        }
        if ($this->last_name) {
            $initials .= strtoupper(substr($this->last_name, 0, 1));
        }

        return $initials ?: strtoupper(substr($this->username, 0, 2));
    }
}
?>
