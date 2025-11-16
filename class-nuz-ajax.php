<?php
/**
 * AJAX Handler Class
 * 
 * Central AJAX request handler for all plugin actions
 * 
 * @package NuzOnlineAcademy
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class NUZ_Ajax {
    
    /**
     * Initialize AJAX handlers
     */
    public static function init() {
        // Dashboard actions
        add_action('wp_ajax_nuz_get_dashboard_stats', array(__CLASS__, 'get_dashboard_stats'));
        add_action('wp_ajax_nuz_get_recent_admissions', array(__CLASS__, 'get_recent_admissions'));
        add_action('wp_ajax_nuz_get_upcoming_courses', array(__CLASS__, 'get_upcoming_courses'));
        add_action('wp_ajax_nuz_get_monthly_enrollment_data', array(__CLASS__, 'get_monthly_enrollment_data'));
        add_action('wp_ajax_nuz_export_dashboard_data', array(__CLASS__, 'export_dashboard_data'));
        add_action('wp_ajax_nuz_get_recent_activity', array(__CLASS__, 'get_recent_activity'));
        add_action('wp_ajax_nuz_get_chart_data', array(__CLASS__, 'get_chart_data'));
        
        // Student management actions
        add_action('wp_ajax_nuz_add_student', array(__CLASS__, 'add_student'));
        add_action('wp_ajax_nuz_edit_student', array(__CLASS__, 'edit_student'));
        add_action('wp_ajax_nuz_delete_student', array(__CLASS__, 'delete_student'));
        add_action('wp_ajax_nuz_get_student_details', array(__CLASS__, 'get_student_details'));
        add_action('wp_ajax_nuz_update_student_status', array(__CLASS__, 'update_student_status'));
        
        // Course management actions
        add_action('wp_ajax_nuz_add_course', array(__CLASS__, 'add_course'));
        add_action('wp_ajax_nuz_edit_course', array(__CLASS__, 'edit_course'));
        add_action('wp_ajax_nuz_delete_course', array(__CLASS__, 'delete_course'));
        add_action('wp_ajax_nuz_get_course_students', array(__CLASS__, 'get_course_students'));
        
        // Payment management actions
        add_action('wp_ajax_nuz_add_payment', array(__CLASS__, 'add_payment'));
        add_action('wp_ajax_nuz_edit_payment', array(__CLASS__, 'edit_payment'));
        add_action('wp_ajax_nuz_delete_payment', array(__CLASS__, 'delete_payment'));
        add_action('wp_ajax_nuz_get_payment_summary', array(__CLASS__, 'get_payment_summary'));
        add_action('wp_ajax_nuz_send_payment_reminder', array(__CLASS__, 'send_payment_reminder'));
        
        // Screenshot actions
        add_action('wp_ajax_nuz_upload_screenshot', array(__CLASS__, 'upload_screenshot'));
        add_action('wp_ajax_nuz_delete_screenshot', array(__CLASS__, 'delete_screenshot'));
        add_action('wp_ajax_nuz_verify_screenshot', array(__CLASS__, 'verify_screenshot'));
        add_action('wp_ajax_nuz_get_student_screenshots', array(__CLASS__, 'get_student_screenshots'));
        
        // Import/Export actions
        add_action('wp_ajax_nuz_export_students', array(__CLASS__, 'export_students'));
        add_action('wp_ajax_nuz_export_payments', array(__CLASS__, 'export_payments'));
        add_action('wp_ajax_nuz_export_all_data', array(__CLASS__, 'export_all_data'));
        add_action('wp_ajax_nuz_import_data', array(__CLASS__, 'import_data'));
        add_action('wp_ajax_nuz_download_sample_csv', array(__CLASS__, 'download_sample_csv'));
        
        // Settings actions
        add_action('wp_ajax_nuz_update_settings', array(__CLASS__, 'update_settings'));
        add_action('wp_ajax_nuz_get_settings', array(__CLASS__, 'get_settings'));
        add_action('wp_ajax_nuz_upload_logo', array(__CLASS__, 'upload_logo'));
        add_action('wp_ajax_nuz_delete_logo', array(__CLASS__, 'delete_logo'));
        add_action('wp_ajax_nuz_reset_demo_data', array(__CLASS__, 'reset_demo_data'));
        
        // Print actions
        add_action('wp_ajax_nuz_print_student_list', array(__CLASS__, 'print_student_list'));
        add_action('wp_ajax_nuz_print_course_list', array(__CLASS__, 'print_course_list'));
        add_action('wp_ajax_nuz_print_payment_report', array(__CLASS__, 'print_payment_report'));
        
        // Search and filter actions
        add_action('wp_ajax_nuz_search_students', array(__CLASS__, 'search_students'));
        add_action('wp_ajax_nuz_filter_students', array(__CLASS__, 'filter_students'));
        add_action('wp_ajax_nuz_filter_payments', array(__CLASS__, 'filter_payments'));
        
        // Non-authenticated actions
        add_action('wp_ajax_nopriv_nuz_student_lookup', array(__CLASS__, 'student_lookup'));
    }
    
    /**
     * Security check for all AJAX requests
     */
    private static function security_check($action = '') {
        // Check nonce
        if (!wp_verify_nonce($_POST['nonce'], 'nuz_nonce')) {
            wp_send_json_error('Security check failed');
        }
        
        // Check user capabilities
        if (!current_user_can('manage_options') && !current_user_can('nuz_view_students')) {
            wp_send_json_error('Insufficient permissions');
        }
        
        // Log action for audit trail
        self::log_ajax_action($action);
    }
    
    /**
     * Log AJAX action for audit trail
     */
    private static function log_ajax_action($action) {
        global $wpdb;
        
        $wpdb->insert(
            $wpdb->prefix . 'nuz_audit_log',
            array(
                'user_id' => get_current_user_id(),
                'action' => $action,
                'ip_address' => $_SERVER['REMOTE_ADDR'],
                'user_agent' => $_SERVER['HTTP_USER_AGENT'],
                'created_at' => current_time('mysql')
            ),
            array('%d', '%s', '%s', '%s', '%s')
        );
    }
    

    
    /**
     * Add new student
     */
    public static function add_student() {
        self::security_check('add_student');
        
        global $wpdb;
        
        // Sanitize input
        $student_data = array(
            'student_id' => self::generate_student_id(),
            'name' => sanitize_text_field($_POST['name']),
            'email' => sanitize_email($_POST['email']),
            'phone' => sanitize_text_field($_POST['phone']),
            'address' => sanitize_textarea_field($_POST['address']),
            'course_id' => intval($_POST['course_id']),
            'admission_date' => sanitize_text_field($_POST['admission_date']),
            'status' => 'active'
        );
        
        // Validate required fields
        if (empty($student_data['name']) || empty($student_data['email']) || empty($student_data['phone'])) {
            wp_send_json_error('Required fields cannot be empty');
        }
        
        // Check for duplicate email
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}nuz_students WHERE email = %s",
            $student_data['email']
        ));
        
        if ($existing) {
            wp_send_json_error('Student with this email already exists');
        }
        
        // Insert student
        $result = $wpdb->insert(
            $wpdb->prefix . 'nuz_students',
            $student_data,
            array('%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s')
        );
        
        if ($result) {
            $student_id = $wpdb->insert_id;
            
            // Create enrollment record
            $enrollment_data = array(
                'student_id' => $student_id,
                'course_id' => $student_data['course_id'],
                'enrollment_date' => $student_data['admission_date'],
                'status' => 'active'
            );
            
            $wpdb->insert(
                $wpdb->prefix . 'nuz_enrollments',
                $enrollment_data,
                array('%d', '%d', '%s', '%s')
            );
            
            wp_send_json_success(array(
                'message' => 'Student added successfully!',
                'student_id' => $student_id
            ));
        } else {
            wp_send_json_error('Failed to add student');
        }
    }
    
    /**
     * Get students list with pagination and search
     */
    public static function get_students() {
        self::security_check('get_students');
        
        global $wpdb;
        
        $page = intval($_POST['page']) ?: 1;
        $per_page = intval($_POST['per_page']) ?: 20;
        $search = sanitize_text_field($_POST['search']) ?: '';
        $course_filter = intval($_POST['course_filter']) ?: 0;
        $status_filter = sanitize_text_field($_POST['status_filter']) ?: '';
        
        $offset = ($page - 1) * $per_page;
        
        $where_conditions = array('1=1');
        $where_values = array();
        
        if (!empty($search)) {
            $where_conditions[] = "(s.name LIKE %s OR s.email LIKE %s OR s.student_id LIKE %s)";
            $search_term = '%' . $wpdb->esc_like($search) . '%';
            $where_values = array_merge($where_values, array($search_term, $search_term, $search_term));
        }
        
        if ($course_filter > 0) {
            $where_conditions[] = "s.course_id = %d";
            $where_values[] = $course_filter;
        }
        
        if (!empty($status_filter)) {
            $where_conditions[] = "s.status = %s";
            $where_values[] = $status_filter;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Get total count
        $total_query = "SELECT COUNT(*) FROM {$wpdb->prefix}nuz_students s WHERE $where_clause";
        if (!empty($where_values)) {
            $total_count = $wpdb->get_var($wpdb->prepare($total_query, $where_values));
        } else {
            $total_count = $wpdb->get_var($total_query);
        }
        
        // Get students
        $query = "SELECT s.*, c.course_name 
                 FROM {$wpdb->prefix}nuz_students s
                 LEFT JOIN {$wpdb->prefix}nuz_courses c ON s.course_id = c.id
                 WHERE $where_clause
                 ORDER BY s.created_at DESC
                 LIMIT %d OFFSET %d";
        
        $query_values = array_merge($where_values, array($per_page, $offset));
        $students = $wpdb->get_results($wpdb->prepare($query, $query_values));
        
        $response = array(
            'students' => $students,
            'total' => intval($total_count),
            'page' => $page,
            'per_page' => $per_page,
            'total_pages' => ceil($total_count / $per_page)
        );
        
        wp_send_json_success($response);
    }
    
    /**
     * Upload screenshot
     */
    public static function upload_screenshot() {
        self::security_check('upload_screenshot');
        
        if (!function_exists('wp_handle_upload')) {
            require_once(ABSPATH . 'wp-admin/includes/file.php');
        }
        
        // Check if file was uploaded
        if (empty($_FILES['screenshot'])) {
            wp_send_json_error('No file uploaded');
        }
        
        $uploadedfile = $_FILES['screenshot'];
        $student_id = intval($_POST['student_id']);
        $screenshot_type = sanitize_text_field($_POST['screenshot_type']);
        $description = sanitize_textarea_field($_POST['description']);
        
        // Validate student exists
        global $wpdb;
        $student_exists = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM {$wpdb->prefix}nuz_students WHERE id = %d",
            $student_id
        ));
        
        if (!$student_exists) {
            wp_send_json_error('Student not found');
        }
        
        // Validate file
        $allowed_types = array('jpg', 'jpeg', 'png', 'gif');
        $file_info = pathinfo($uploadedfile['name']);
        $file_extension = strtolower($file_info['extension']);
        
        if (!in_array($file_extension, $allowed_types)) {
            wp_send_json_error('Invalid file type. Only JPG, JPEG, PNG, and GIF files are allowed.');
        }
        
        // Check file size (max 5MB)
        if ($uploadedfile['size'] > 5 * 1024 * 1024) {
            wp_send_json_error('File size too large. Maximum size is 5MB.');
        }
        
        $upload_overrides = array('test_form' => false);
        $movefile = wp_handle_upload($uploadedfile, $upload_overrides);
        
        if ($movefile && !isset($movefile['error'])) {
            // Save to database
            $screenshot_data = array(
                'student_id' => $student_id,
                'screenshot_type' => $screenshot_type,
                'file_path' => $movefile['url'],
                'file_size' => $uploadedfile['size'],
                'description' => $description,
                'upload_date' => current_time('mysql')
            );
            
            $wpdb->insert(
                $wpdb->prefix . 'nuz_screenshots',
                $screenshot_data,
                array('%d', '%s', '%s', '%d', '%s', '%s')
            );
            
            $screenshot_id = $wpdb->insert_id;
            
            wp_send_json_success(array(
                'message' => 'Screenshot uploaded successfully!',
                'screenshot_id' => $screenshot_id,
                'file_url' => $movefile['url']
            ));
        } else {
            wp_send_json_error($movefile['error'] ?? 'Upload failed');
        }
    }
    
    /**
     * Export data to CSV
     */
    public static function export_students() {
        self::security_check('export_students');
        
        global $wpdb;
        
        $students = $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, c.course_name, 
                    COALESCE(SUM(p.amount), 0) as total_paid,
                    COUNT(e.id) as enrollment_count
             FROM {$wpdb->prefix}nuz_students s
             LEFT JOIN {$wpdb->prefix}nuz_courses c ON s.course_id = c.id
             LEFT JOIN {$wpdb->prefix}nuz_enrollments e ON s.id = e.student_id
             LEFT JOIN {$wpdb->prefix}nuz_payments p ON s.id = p.student_id AND p.payment_status = 'paid'
             GROUP BY s.id
             ORDER BY s.name ASC"
        ));
        
        $csv_data = array();
        $csv_data[] = array(
            'Student ID', 'Name', 'Email', 'Phone', 'Course', 'Admission Date', 
            'Status', 'Total Paid', 'Enrollments', 'Address'
        );
        
        foreach ($students as $student) {
            $csv_data[] = array(
                $student->student_id,
                $student->name,
                $student->email,
                $student->phone,
                $student->course_name,
                $student->admission_date,
                $student->status,
                $student->total_paid,
                $student->enrollment_count,
                $student->address
            );
        }
        
        // Generate CSV content
        $csv_content = '';
        foreach ($csv_data as $row) {
            $csv_content .= implode(',', array_map(function($field) {
                return '"' . str_replace('"', '""', $field) . '"';
            }, $row)) . "\n";
        }
        
        wp_send_json_success(array(
            'csv_content' => $csv_content,
            'filename' => 'nuz-students-' . date('Y-m-d') . '.csv'
        ));
    }
    
    /**
     * Update plugin settings
     */
    public static function update_settings() {
        self::security_check('update_settings');
        
        global $wpdb;
        
        $settings = array(
            'academy_name' => sanitize_text_field($_POST['academy_name']),
            'theme_mode' => sanitize_text_field($_POST['theme_mode']),
            'currency_symbol' => sanitize_text_field($_POST['currency_symbol']),
            'contact_email' => sanitize_email($_POST['contact_email']),
            'contact_phone' => sanitize_text_field($_POST['contact_phone']),
            'max_upload_size' => intval($_POST['max_upload_size']),
            'auto_backup' => sanitize_text_field($_POST['auto_backup']),
            'logo_url' => esc_url_raw($_POST['logo_url'])
        );
        
        foreach ($settings as $key => $value) {
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$wpdb->prefix}nuz_settings WHERE setting_key = %s",
                $key
            ));
            
            if ($existing) {
                $wpdb->update(
                    $wpdb->prefix . 'nuz_settings',
                    array('setting_value' => $value, 'updated_at' => current_time('mysql')),
                    array('id' => $existing),
                    array('%s', '%s'),
                    array('%d')
                );
            } else {
                $wpdb->insert(
                    $wpdb->prefix . 'nuz_settings',
                    array(
                        'setting_key' => $key,
                        'setting_value' => $value,
                        'setting_type' => 'text',
                        'created_at' => current_time('mysql'),
                        'updated_at' => current_time('mysql')
                    ),
                    array('%s', '%s', '%s', '%s', '%s')
                );
            }
        }
        
        wp_send_json_success(array('message' => 'Settings updated successfully!'));
    }
    
    /**
     * Generate unique student ID
     */
    private static function generate_student_id() {
        global $wpdb;
        
        $prefix = 'STU';
        $year = date('Y');
        $month = date('m');
        
        $counter = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) + 1 FROM {$wpdb->prefix}nuz_students 
             WHERE student_id LIKE %s",
            $prefix . '-' . $year . '-' . $month . '-%'
        ));
        
        return $prefix . '-' . $year . '-' . $month . '-' . str_pad($counter, 3, '0', STR_PAD_LEFT);
    }
    
    /**
     * Get dashboard statistics
     */
    public static function get_dashboard_stats() {
        self::security_check('get_dashboard_stats');
        
        global $wpdb;
        
        $stats = array();
        
        // Total counts
        $stats['total_students'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}nuz_students WHERE status = 'active'");
        $stats['total_courses'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}nuz_courses WHERE status = 'active'");
        $stats['total_enrollments'] = $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->prefix}nuz_enrollments WHERE status = 'active'");
        $stats['total_revenue'] = $wpdb->get_var("SELECT SUM(amount) FROM {$wpdb->prefix}nuz_payments WHERE payment_status = 'paid'");
        
        // Monthly stats
        $stats['monthly_revenue'] = $wpdb->get_var($wpdb->prepare(
            "SELECT SUM(amount) FROM {$wpdb->prefix}nuz_payments 
             WHERE payment_status = 'paid' 
             AND payment_date >= %s",
            date('Y-m-01')
        ));
        
        // Recent enrollments
        $stats['recent_enrollments'] = $wpdb->get_results($wpdb->prepare(
            "SELECT s.name, c.course_name, e.enrollment_date 
             FROM {$wpdb->prefix}nuz_enrollments e
             JOIN {$wpdb->prefix}nuz_students s ON e.student_id = s.id
             JOIN {$wpdb->prefix}nuz_courses c ON e.course_id = c.id
             ORDER BY e.enrollment_date DESC LIMIT 5"
        ));
        
        // Payment summary
        $stats['payment_summary'] = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                SUM(CASE WHEN payment_status = 'paid' THEN amount ELSE 0 END) as total_paid,
                SUM(CASE WHEN payment_status = 'pending' THEN amount ELSE 0 END) as total_pending,
                SUM(CASE WHEN payment_status = 'overdue' THEN amount ELSE 0 END) as total_overdue
             FROM {$wpdb->prefix}nuz_payments"
        ));
        
        // Top courses by enrollment
        $stats['top_courses'] = $wpdb->get_results($wpdb->prepare(
            "SELECT c.course_name, COUNT(e.id) as enrollment_count
             FROM {$wpdb->prefix}nuz_courses c
             LEFT JOIN {$wpdb->prefix}nuz_enrollments e ON c.id = e.course_id AND e.status = 'active'
             GROUP BY c.id, c.course_name
             ORDER BY enrollment_count DESC LIMIT 5"
        ));
        
        wp_send_json_success($stats);
    }
    
    /**
     * Get recent admissions (last 5 students)
     */
    public static function get_recent_admissions() {
        self::security_check('get_recent_admissions');
        
        global $wpdb;
        
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 5;
        
        $admissions = $wpdb->get_results($wpdb->prepare(
            "SELECT s.*, c.course_name, e.enrollment_date
             FROM {$wpdb->prefix}nuz_students s
             LEFT JOIN {$wpdb->prefix}nuz_enrollments e ON s.id = e.student_id
             LEFT JOIN {$wpdb->prefix}nuz_courses c ON e.course_id = c.id
             WHERE s.status = 'active'
             ORDER BY s.id DESC
             LIMIT %d",
            $limit
        ));
        
        wp_send_json_success($admissions);
    }
    
    /**
     * Get upcoming courses
     */
    public static function get_upcoming_courses() {
        self::security_check('get_upcoming_courses');
        
        global $wpdb;
        
        $limit = isset($_POST['limit']) ? intval($_POST['limit']) : 5;
        
        $courses = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM {$wpdb->prefix}nuz_courses 
             WHERE status = 'active' AND start_date >= CURDATE()
             ORDER BY start_date ASC
             LIMIT %d",
            $limit
        ));
        
        wp_send_json_success($courses);
    }
    
    /**
     * Get monthly enrollment data for chart
     */
    public static function get_monthly_enrollment_data() {
        self::security_check('get_monthly_enrollment_data');
        
        global $wpdb;
        
        $period = isset($_POST['period']) ? intval($_POST['period']) : 12;
        
        // Get monthly enrollment data for the specified period
        $data = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE_FORMAT(enrollment_date, '%%Y-%%m') as month,
                COUNT(*) as enrollments
             FROM {$wpdb->prefix}nuz_enrollments 
             WHERE enrollment_date >= DATE_SUB(CURDATE(), INTERVAL %d MONTH)
             GROUP BY DATE_FORMAT(enrollment_date, '%%Y-%%m')
             ORDER BY month ASC",
            $period
        ));
        
        // Format data for Chart.js
        $labels = array();
        $values = array();
        
        foreach ($data as $row) {
            $date = new DateTime($row->month . '-01');
            $labels[] = $date->format('M Y');
            $values[] = intval($row->enrollments);
        }
        
        // Fill missing months with 0 enrollments
        $start_date = new DateTime();
        $start_date->sub(new DateInterval('P' . $period . 'M'));
        $current = clone $start_date;
        
        while ($current <= new DateTime()) {
            $month_key = $current->format('M Y');
            if (!in_array($month_key, $labels)) {
                $labels[] = $month_key;
                $values[] = 0;
            }
            $current->add(new DateInterval('P1M'));
        }
        
        // Sort by date
        $combined = array_map(null, $labels, $values);
        usort($combined, function($a, $b) {
            $date_a = DateTime::createFromFormat('M Y', $a[0]);
            $date_b = DateTime::createFromFormat('M Y', $b[0]);
            return $date_a <=> $date_b;
        });
        
        $labels = array_column($combined, 0);
        $values = array_column($combined, 1);
        
        wp_send_json_success(array(
            'labels' => $labels,
            'values' => $values
        ));
    }
    
    /**
     * Export dashboard data
     */
    public static function export_dashboard_data() {
        self::security_check('export_dashboard_data');
        
        global $wpdb;
        
        // Collect all dashboard data
        $data = array();
        
        // Statistics
        $data['statistics'] = $wpdb->get_row(
            "SELECT 
                (SELECT COUNT(*) FROM {$wpdb->prefix}nuz_students WHERE status = 'active') as total_students,
                (SELECT COUNT(*) FROM {$wpdb->prefix}nuz_courses WHERE status = 'active') as total_courses,
                (SELECT COALESCE(SUM(amount), 0) FROM {$wpdb->prefix}nuz_payments WHERE payment_status = 'paid') as total_revenue,
                (SELECT COALESCE(SUM(amount - COALESCE(paid_amount, 0)), 0) FROM {$wpdb->prefix}nuz_payments WHERE payment_status != 'paid') as pending_fees"
        );
        
        // Recent admissions
        $data['recent_admissions'] = $wpdb->get_results(
            "SELECT s.*, c.course_name, e.enrollment_date
             FROM {$wpdb->prefix}nuz_students s
             LEFT JOIN {$wpdb->prefix}nuz_enrollments e ON s.id = e.student_id
             LEFT JOIN {$wpdb->prefix}nuz_courses c ON e.course_id = c.id
             WHERE s.status = 'active'
             ORDER BY s.id DESC LIMIT 10"
        );
        
        // Upcoming courses
        $data['upcoming_courses'] = $wpdb->get_results(
            "SELECT * FROM {$wpdb->prefix}nuz_courses 
             WHERE status = 'active' AND start_date >= CURDATE()
             ORDER BY start_date ASC LIMIT 10"
        );
        
        // Monthly enrollment data
        $data['monthly_enrollments'] = $wpdb->get_results(
            "SELECT 
                DATE_FORMAT(enrollment_date, '%%Y-%%m') as month,
                COUNT(*) as enrollments
             FROM {$wpdb->prefix}nuz_enrollments 
             WHERE enrollment_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
             GROUP BY DATE_FORMAT(enrollment_date, '%%Y-%%m')
             ORDER BY month ASC"
        );
        
        // Export metadata
        $data['export_info'] = array(
            'exported_at' => current_time('mysql'),
            'exported_by' => wp_get_current_user()->display_name,
            'plugin_version' => defined('NUZ_ACADEMY_VERSION') ? NUZ_ACADEMY_VERSION : '1.0.0',
            'wp_version' => get_bloginfo('version')
        );
        
        wp_send_json_success($data);
    }

    /**
     * Get recent activity
     */
    public static function get_recent_activity() {
        self::security_check('get_recent_activity');
        
        global $wpdb;
        
        $activities = $wpdb->get_results($wpdb->prepare(
            "SELECT 'student' as type, s.student_name as name, s.created_at, 'New student registered' as activity
             FROM {$wpdb->prefix}nuz_students s
             UNION ALL
             SELECT 'payment' as type, st.student_name as name, p.created_at, CONCAT('$', p.amount, ' payment received') as activity
             FROM {$wpdb->prefix}nuz_payments p
             JOIN {$wpdb->prefix}nuz_students st ON p.student_id = st.id
             WHERE p.payment_status = 'paid'
             ORDER BY created_at DESC LIMIT 10"
        ));
        
        wp_send_json_success($activities);
    }
}