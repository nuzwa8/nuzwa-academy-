<?php
/**
 * Plugin Activator Class
 * 
 * Handles plugin activation, database creation, and role management
 * 
 * @package NuzOnlineAcademy
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class NUZ_Activator {
    
    /**
     * Plugin activation
     */
    public static function activate() {
        self::create_tables();
        self::create_roles();
        self::insert_demo_data();
        self::create_upload_dirs();
        self::set_plugin_version();
        
        // Clear rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public static function deactivate() {
        // Clear scheduled hooks
        wp_clear_scheduled_hook('nuz_daily_reports');
        
        // Clear rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Create database tables
     */
    private static function create_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // Students table
        $students_table = $wpdb->prefix . 'nuz_students';
        $students_sql = "CREATE TABLE $students_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            student_id varchar(20) NOT NULL,
            name varchar(255) NOT NULL,
            email varchar(100) NOT NULL,
            phone varchar(20) NOT NULL,
            address text,
            course_id int(11) DEFAULT NULL,
            admission_date date NOT NULL,
            status enum('active', 'inactive', 'graduated', 'dropped') DEFAULT 'active',
            profile_image varchar(255) DEFAULT NULL,
            notes text,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY student_id (student_id),
            KEY course_id (course_id),
            KEY status (status)
        ) $charset_collate;";
        
        // Courses table
        $courses_table = $wpdb->prefix . 'nuz_courses';
        $courses_sql = "CREATE TABLE $courses_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            course_code varchar(20) NOT NULL,
            course_name varchar(255) NOT NULL,
            description text,
            instructor varchar(255) NOT NULL,
            duration_weeks int(11) DEFAULT NULL,
            price decimal(10,2) NOT NULL,
            max_students int(11) DEFAULT NULL,
            start_date date DEFAULT NULL,
            end_date date DEFAULT NULL,
            status enum('active', 'inactive', 'completed') DEFAULT 'active',
            syllabus_url varchar(255) DEFAULT NULL,
            thumbnail_url varchar(255) DEFAULT NULL,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY course_code (course_code)
        ) $charset_collate;";
        
        // Payments table
        $payments_table = $wpdb->prefix . 'nuz_payments';
        $payments_sql = "CREATE TABLE $payments_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            student_id int(11) NOT NULL,
            course_id int(11) NOT NULL,
            amount decimal(10,2) NOT NULL,
            payment_date date NOT NULL,
            payment_method enum('cash', 'bank_transfer', 'card', 'online', 'cheque') NOT NULL,
            payment_status enum('paid', 'partial', 'pending', 'overdue', 'refunded') DEFAULT 'pending',
            reference_number varchar(100) DEFAULT NULL,
            notes text,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY student_id (student_id),
            KEY course_id (course_id),
            KEY payment_status (payment_status),
            KEY payment_date (payment_date)
        ) $charset_collate;";
        
        // Enrollments table
        $enrollments_table = $wpdb->prefix . 'nuz_enrollments';
        $enrollments_sql = "CREATE TABLE $enrollments_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            student_id int(11) NOT NULL,
            course_id int(11) NOT NULL,
            enrollment_date date NOT NULL,
            completion_date date DEFAULT NULL,
            status enum('active', 'completed', 'dropped', 'suspended') DEFAULT 'active',
            progress_percentage int(3) DEFAULT 0,
            grade varchar(5) DEFAULT NULL,
            certificate_url varchar(255) DEFAULT NULL,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY enrollment_id (student_id, course_id),
            KEY student_id (student_id),
            KEY course_id (course_id),
            KEY status (status)
        ) $charset_collate;";
        
        // Screenshots table
        $screenshots_table = $wpdb->prefix . 'nuz_screenshots';
        $screenshots_sql = "CREATE TABLE $screenshots_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            student_id int(11) NOT NULL,
            screenshot_type enum('face', 'work', 'assignment', 'certificate', 'other') NOT NULL,
            file_path varchar(255) NOT NULL,
            file_size int(11) DEFAULT NULL,
            upload_date timestamp DEFAULT CURRENT_TIMESTAMP,
            description text,
            is_verified tinyint(1) DEFAULT 0,
            verified_by int(11) DEFAULT NULL,
            verified_at timestamp NULL DEFAULT NULL,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY student_id (student_id),
            KEY screenshot_type (screenshot_type),
            KEY is_verified (is_verified)
        ) $charset_collate;";
        
        // Settings table
        $settings_table = $wpdb->prefix . 'nuz_settings';
        $settings_sql = "CREATE TABLE $settings_table (
            id int(11) NOT NULL AUTO_INCREMENT,
            setting_key varchar(100) NOT NULL,
            setting_value text,
            setting_type enum('text', 'number', 'boolean', 'json', 'url') DEFAULT 'text',
            description text,
            created_at timestamp DEFAULT CURRENT_TIMESTAMP,
            updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY setting_key (setting_key)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        
        dbDelta($students_sql);
        dbDelta($courses_sql);
        dbDelta($payments_sql);
        dbDelta($enrollments_sql);
        dbDelta($screenshots_sql);
        dbDelta($settings_sql);
    }
    
    /**
     * Create user roles
     */
    private static function create_roles() {
        // Add roles
        add_role('nuz_instructor', 'Academy Instructor', array(
            'read' => true,
            'nuz_view_students' => true,
            'nuz_view_courses' => true,
            'nuz_view_payments' => true,
            'nuz_upload_screenshots' => true,
            'nuz_edit_assignments' => true
        ));
        
        add_role('nuz_student', 'Academy Student', array(
            'read' => true,
            'nuz_view_own_data' => true,
            'nuz_upload_work' => true
        ));
        
        // Add capabilities to Administrator
        $admin_role = get_role('administrator');
        if ($admin_role) {
            $admin_role->add_cap('nuz_manage_academy');
            $admin_role->add_cap('nuz_view_students');
            $admin_role->add_cap('nuz_view_courses');
            $admin_role->add_cap('nuz_view_payments');
            $admin_role->add_cap('nuz_manage_students');
            $admin_role->add_cap('nuz_manage_courses');
            $admin_role->add_cap('nuz_manage_payments');
            $admin_role->add_cap('nuz_upload_screenshots');
            $admin_role->add_cap('nuz_manage_settings');
            $admin_role->add_cap('nuz_export_data');
            $admin_role->add_cap('nuz_import_data');
        }
    }
    
    /**
     * Insert demo data
     */
    private static function insert_demo_data() {
        global $wpdb;
        
        // Check if demo data already exists
        $existing_courses = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}nuz_courses");
        if ($existing_courses > 0) {
            return; // Demo data already exists
        }
        
        // Demo courses
        $demo_courses = array(
            array(
                'course_code' => 'WEB-101',
                'course_name' => 'Web Design Fundamentals',
                'description' => 'Learn HTML, CSS, and basic web development concepts. Perfect for beginners.',
                'instructor' => 'John Smith',
                'duration_weeks' => 12,
                'price' => 299.99,
                'max_students' => 25,
                'start_date' => date('Y-m-d', strtotime('+7 days')),
                'end_date' => date('Y-m-d', strtotime('+7 days +12 weeks')),
                'status' => 'active'
            ),
            array(
                'course_code' => 'VID-201',
                'course_name' => 'Video Editing Mastery',
                'description' => 'Master video editing with industry-standard tools and techniques.',
                'instructor' => 'Sarah Johnson',
                'duration_weeks' => 16,
                'price' => 449.99,
                'max_students' => 20,
                'start_date' => date('Y-m-d', strtotime('+14 days')),
                'end_date' => date('Y-m-d', strtotime('+14 days +16 weeks')),
                'status' => 'active'
            ),
            array(
                'course_code' => 'COPY-301',
                'course_name' => 'Professional Copywriting',
                'description' => 'Learn persuasive writing for marketing, advertising, and digital media.',
                'instructor' => 'Michael Davis',
                'duration_weeks' => 10,
                'price' => 199.99,
                'max_students' => 30,
                'start_date' => date('Y-m-d', strtotime('+21 days')),
                'end_date' => date('Y-m-d', strtotime('+21 days +10 weeks')),
                'status' => 'active'
            )
        );
        
        foreach ($demo_courses as $course) {
            $wpdb->insert(
                $wpdb->prefix . 'nuz_courses',
                $course,
                array('%s', '%s', '%s', '%s', '%d', '%f', '%d', '%s', '%s', '%s')
            );
        }
        
        // Demo students
        $demo_students = array(
            array(
                'student_id' => 'STU-001',
                'name' => 'Ahmed Khan',
                'email' => 'ahmed.khan@email.com',
                'phone' => '+92 300 123 4567',
                'address' => 'Lahore, Punjab, Pakistan',
                'course_id' => 1,
                'admission_date' => date('Y-m-d', strtotime('-30 days')),
                'status' => 'active'
            ),
            array(
                'student_id' => 'STU-002',
                'name' => 'Fatima Ali',
                'email' => 'fatima.ali@email.com',
                'phone' => '+92 301 234 5678',
                'address' => 'Karachi, Sindh, Pakistan',
                'course_id' => 2,
                'admission_date' => date('Y-m-d', strtotime('-25 days')),
                'status' => 'active'
            ),
            array(
                'student_id' => 'STU-003',
                'name' => 'Muhammad Hassan',
                'email' => 'hassan.m@email.com',
                'phone' => '+92 302 345 6789',
                'address' => 'Islamabad, Capital Territory, Pakistan',
                'course_id' => 3,
                'admission_date' => date('Y-m-d', strtotime('-20 days')),
                'status' => 'active'
            ),
            array(
                'student_id' => 'STU-004',
                'name' => 'Aisha Malik',
                'email' => 'aisha.malik@email.com',
                'phone' => '+92 303 456 7890',
                'address' => 'Peshawar, KPK, Pakistan',
                'course_id' => 1,
                'admission_date' => date('Y-m-d', strtotime('-15 days')),
                'status' => 'active'
            ),
            array(
                'student_id' => 'STU-005',
                'name' => 'Omar Farooq',
                'email' => 'omar.farooq@email.com',
                'phone' => '+92 304 567 8901',
                'address' => 'Quetta, Balochistan, Pakistan',
                'course_id' => 2,
                'admission_date' => date('Y-m-d', strtotime('-10 days')),
                'status' => 'active'
            )
        );
        
        foreach ($demo_students as $student) {
            $wpdb->insert(
                $wpdb->prefix . 'nuz_students',
                $student,
                array('%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s')
            );
        }
        
        // Demo payments
        $demo_payments = array(
            array(
                'student_id' => 1,
                'course_id' => 1,
                'amount' => 149.99,
                'payment_date' => date('Y-m-d', strtotime('-30 days')),
                'payment_method' => 'bank_transfer',
                'payment_status' => 'paid',
                'reference_number' => 'TXN001'
            ),
            array(
                'student_id' => 1,
                'course_id' => 1,
                'amount' => 150.00,
                'payment_date' => date('Y-m-d', strtotime('-15 days')),
                'payment_method' => 'card',
                'payment_status' => 'paid',
                'reference_number' => 'TXN002'
            ),
            array(
                'student_id' => 2,
                'course_id' => 2,
                'amount' => 225.00,
                'payment_date' => date('Y-m-d', strtotime('-25 days')),
                'payment_method' => 'cash',
                'payment_status' => 'paid',
                'reference_number' => 'TXN003'
            ),
            array(
                'student_id' => 2,
                'course_id' => 2,
                'amount' => 224.99,
                'payment_date' => date('Y-m-d', strtotime('-10 days')),
                'payment_method' => 'online',
                'payment_status' => 'paid',
                'reference_number' => 'TXN004'
            ),
            array(
                'student_id' => 3,
                'course_id' => 3,
                'amount' => 199.99,
                'payment_date' => date('Y-m-d', strtotime('-20 days')),
                'payment_method' => 'bank_transfer',
                'payment_status' => 'paid',
                'reference_number' => 'TXN005'
            )
        );
        
        foreach ($demo_payments as $payment) {
            $wpdb->insert(
                $wpdb->prefix . 'nuz_payments',
                $payment,
                array('%d', '%d', '%f', '%s', '%s', '%s', '%s')
            );
        }
        
        // Demo settings
        $demo_settings = array(
            array('setting_key' => 'academy_name', 'setting_value' => 'NUZ Online Academy', 'setting_type' => 'text'),
            array('setting_key' => 'theme_mode', 'setting_value' => 'light', 'setting_type' => 'text'),
            array('setting_key' => 'currency_symbol', 'setting_value' => '$', 'setting_type' => 'text'),
            array('setting_key' => 'logo_url', 'setting_value' => '', 'setting_type' => 'url'),
            array('setting_key' => 'contact_email', 'setting_value' => 'info@nuzonline.com', 'setting_type' => 'text'),
            array('setting_key' => 'contact_phone', 'setting_value' => '+1-555-123-4567', 'setting_type' => 'text'),
            array('setting_key' => 'max_upload_size', 'setting_value' => '5', 'setting_type' => 'number'),
            array('setting_key' => 'auto_backup', 'setting_value' => 'true', 'setting_type' => 'boolean')
        );
        
        foreach ($demo_settings as $setting) {
            $wpdb->insert(
                $wpdb->prefix . 'nuz_settings',
                $setting,
                array('%s', '%s', '%s')
            );
        }
    }
    
    /**
     * Create upload directories
     */
    private static function create_upload_dirs() {
        $upload_dir = wp_upload_dir();
        $plugin_uploads = $upload_dir['basedir'] . '/nuz-academy';
        
        if (!file_exists($plugin_uploads)) {
            wp_mkdir_p($plugin_uploads);
            
            // Create subdirectories
            $subdirs = array('screenshots', 'certificates', 'documents', 'thumbnails');
            foreach ($subdirs as $subdir) {
                wp_mkdir_p($plugin_uploads . '/' . $subdir);
                
                // Create .htaccess for security
                $htaccess_content = "Order Deny,Allow\nDeny from all\n";
                file_put_contents($plugin_uploads . '/' . $subdir . '/.htaccess', $htaccess_content);
            }
        }
    }
    
    /**
     * Set plugin version
     */
    private static function set_plugin_version() {
        update_option('nuz_academy_version', NUZ_PLUGIN_VERSION);
        update_option('nuz_academy_activation_time', current_time('mysql'));
    }
}