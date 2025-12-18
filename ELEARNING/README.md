# E-Learning Platform

A comprehensive PHP-based e-learning management system with modern features and responsive design.

## Features

### 🎓 Core Learning Features
- **Course Management**: Create, edit, and organize courses with multimedia content
- **Lesson System**: Video lessons, text content, and downloadable resources
- **Quiz & Assessments**: Interactive quizzes with multiple question types
- **Progress Tracking**: Real-time progress monitoring and completion certificates
- **Discussion Forums**: Course-specific discussion boards for student interaction

### 👥 User Management
- **Multi-Role System**: Students, Instructors, and Administrators
- **User Profiles**: Customizable profiles with avatars and bio
- **Authentication**: Secure login/registration with password hashing
- **Role-Based Access**: Different permissions for different user types

### 📊 Admin Features
- **Dashboard**: Comprehensive analytics and statistics
- **User Management**: Add, edit, and manage all users
- **Course Approval**: Review and approve instructor-created courses
- **Category Management**: Organize courses into categories
- **System Settings**: Configure site-wide settings

### 🎨 Modern Interface
- **Responsive Design**: Works on desktop, tablet, and mobile devices
- **Bootstrap 5**: Modern and clean user interface
- **Font Awesome Icons**: Beautiful iconography throughout
- **Interactive Elements**: Smooth animations and transitions

## Technology Stack

- **Backend**: PHP 8+ with OOP architecture
- **Database**: MySQL with PDO
- **Frontend**: HTML5, CSS3, JavaScript, Bootstrap 5
- **Icons**: Font Awesome 6
- **Server**: Apache (XAMPP compatible)

## Installation

### Prerequisites
- XAMPP (or similar LAMP/WAMP stack)
- PHP 8.0 or higher
- MySQL 5.7 or higher
- Web browser

### Setup Instructions

1. **Download and Extract**
   ```
   Extract the e-learning system files to your XAMPP htdocs directory:
   C:\xampp\htdocs\elearning\
   ```

2. **Database Setup**
   - Start XAMPP and ensure Apache and MySQL are running
   - Open phpMyAdmin (http://localhost/phpmyadmin)
   - Import the database schema:
     - Click "Import" tab
     - Choose the `database.sql` file
     - Click "Go" to execute

3. **Configuration**
   - The system is pre-configured for XAMPP default settings
   - Database credentials are set in `config/database.php`:
     - Host: localhost
     - Database: elearning_system
     - Username: root
     - Password: (empty)

4. **File Permissions**
   - Ensure the `uploads/` directory is writable
   - The system will auto-create subdirectories as needed

5. **Access the System**
   - Open your web browser
   - Navigate to: `http://localhost/elearning`
   - The homepage should load successfully

### Default Admin Account
- **Username**: admin
- **Email**: admin@elearning.com
- **Password**: admin123

## Directory Structure

```
elearning/
├── admin/              # Admin panel pages
├── assets/             # CSS, JS, and image files
├── auth/               # Authentication pages
├── classes/            # PHP classes (User, Course, etc.)
├── config/             # Configuration files
├── includes/           # Helper functions and includes
├── instructor/         # Instructor dashboard pages
├── student/            # Student dashboard pages
├── uploads/            # File upload directory
├── database.sql        # Database schema
├── index.php          # Homepage
└── README.md          # This file
```

## Usage

### For Students
1. Register as a student
2. Browse available courses
3. Enroll in courses (free or paid)
4. Watch lessons and complete quizzes
5. Track your progress
6. Participate in discussions
7. Download certificates upon completion

### For Instructors
1. Register as an instructor
2. Create and manage courses
3. Upload video lessons and materials
4. Create quizzes and assessments
5. Monitor student progress
6. Interact with students through forums

### For Administrators
1. Access admin panel at `/admin`
2. Manage users, courses, and categories
3. Review and approve courses
4. Monitor system statistics
5. Configure site settings

## Key Features Explained

### Course Creation
- Rich text editor for course descriptions
- Video upload and embedding
- File attachments for downloadable resources
- Course categorization and tagging
- Pricing options (free or paid)

### Assessment System
- Multiple choice questions
- True/false questions
- Short answer questions
- Automatic grading
- Detailed feedback and explanations

### Progress Tracking
- Lesson completion tracking
- Quiz scores and attempts
- Overall course progress
- Time spent learning
- Achievement badges

### Discussion System
- Course-specific forums
- Topic creation and replies
- Instructor moderation
- Student-to-student interaction

## Customization

### Styling
- Modify `assets/css/` files for custom styling
- Bootstrap variables can be customized
- Color scheme defined in CSS custom properties

### Configuration
- Site settings in `config/config.php`
- Database settings in `config/database.php`
- Upload limits and file types configurable

### Adding Features
- Extend existing classes in `classes/` directory
- Add new pages following existing structure
- Use the helper functions in `includes/functions.php`

## Security Features

- Password hashing with PHP's password_hash()
- SQL injection prevention with PDO prepared statements
- XSS protection with input sanitization
- CSRF token protection
- Session management
- File upload validation

## Troubleshooting

### Common Issues

1. **Database Connection Error**
   - Check XAMPP MySQL is running
   - Verify database credentials in `config/database.php`
   - Ensure database exists and is imported

2. **File Upload Issues**
   - Check `uploads/` directory permissions
   - Verify PHP upload settings in php.ini
   - Check file size limits

3. **Login Problems**
   - Clear browser cache and cookies
   - Check database user table
   - Verify password hashing

4. **Page Not Found**
   - Check Apache is running
   - Verify file paths and URLs
   - Check .htaccess if using URL rewriting

### Support
For technical support or questions:
- Check the documentation
- Review error logs in XAMPP
- Verify all prerequisites are met

## License

This e-learning platform is open source and available for educational and commercial use.

## Contributing

Contributions are welcome! Please follow these guidelines:
- Follow PSR coding standards
- Add comments to your code
- Test thoroughly before submitting
- Update documentation as needed

---

**Happy Learning!** 🎓
