<?php
/**
 * Database Helper Class
 * 
 * Provides utility methods for database operations
 * 
 * @package NuzOnlineAcademy
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class NUZ_DB {
    
    /**
     * WordPress database instance
     */
    private static $wpdb;
    
    /**
     * Initialize database helper
     */
    public static function init() {
        global $wpdb;
        self::$wpdb = $wpdb;
    }
    
    /**
     * Student operations
     */
    
    /**
     * Get all students with course information
     */
    public static function get_students($args = array()) {
        $defaults = array(
            'limit' => 20,
            'offset' => 0,
            'search' => '',
            'course_id' => 0,
            'status' => '',
            'orderby' => 'created_at',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where_conditions = array('1=1');
        $where_values = array();
        
        // Search filter
        if (!empty($args['search'])) {
            $search_term = '%' . self::$wpdb->esc_like($args['search']) . '%';
            $where_conditions[] = "(s.name LIKE %s OR s.email LIKE %s OR s.student_id LIKE %s)";
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        // Course filter
        if ($args['course_id'] > 0) {
            $where_conditions[] = "s.course_id = %d";
            $where_values[] = $args['course_id'];
        }
        
        // Status filter
        if (!empty($args['status'])) {
            $where_conditions[] = "s.status = %s";
            $where_values[] = $args['status'];
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // Order by clause
        $orderby = sanitize_text_field($args['orderby']);
        $order = strtoupper(sanitize_text_field($args['order'])) === 'ASC' ? 'ASC' : 'DESC';
        $order_clause = "ORDER BY s.{$orderby} {$order}";
        
        $query = "SELECT s.*, c.course_name, c.price as course_price,
                        COUNT(e.id) as total_enrollments,
                        COALESCE(SUM(p.amount), 0) as total_paid,
                        COALESCE(SUM(CASE WHEN p.payment_status = 'pending' THEN p.amount ELSE 0 END), 0) as pending_amount
                 FROM {$self::$wpdb->prefix}nuz_students s
                 LEFT JOIN {$self::$wpdb->prefix}nuz_courses c ON s.course_id = c.id
                 LEFT JOIN {$self::$wpdb->prefix}nuz_enrollments e ON s.id = e.student_id
                 LEFT JOIN {$self::$wpdb->prefix}nuz_payments p ON s.id = p.student_id
                 WHERE {$where_clause}
                 GROUP BY s.id
                 {$order_clause}
                 LIMIT %d OFFSET %d";
        
        $query_values = array_merge($where_values, array($args['limit'], $args['offset']));
        
        return self::$wpdb->get_results(self::$wpdb->prepare($query, $query_values));
    }
    
    /**
     * Get student count
     */
    public static function get_student_count($args = array()) {
        $defaults = array(
            'search' => '',
            'course_id' => 0,
            'status' => ''
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where_conditions = array('1=1');
        $where_values = array();
        
        if (!empty($args['search'])) {
            $search_term = '%' . self::$wpdb->esc_like($args['search']) . '%';
            $where_conditions[] = "(name LIKE %s OR email LIKE %s OR student_id LIKE %s)";
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        if ($args['course_id'] > 0) {
            $where_conditions[] = "course_id = %d";
            $where_values[] = $args['course_id'];
        }
        
        if (!empty($args['status'])) {
            $where_conditions[] = "status = %s";
            $where_values[] = $args['status'];
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        $query = "SELECT COUNT(*) FROM {$self::$wpdb->prefix}nuz_students WHERE {$where_clause}";
        
        if (!empty($where_values)) {
            return self::$wpdb->get_var(self::$wpdb->prepare($query, $where_values));
        } else {
            return self::$wpdb->get_var($query);
        }
    }
    
    /**
     * Get single student by ID
     */
    public static function get_student($student_id) {
        return self::$wpdb->get_row(self::$wpdb->prepare(
            "SELECT s.*, c.course_name, c.price as course_price,
                    COUNT(e.id) as total_enrollments,
                    COALESCE(SUM(p.amount), 0) as total_paid
             FROM {$self::$wpdb->prefix}nuz_students s
             LEFT JOIN {$self::$wpdb->prefix}nuz_courses c ON s.course_id = c.id
             LEFT JOIN {$self::$wpdb->prefix}nuz_enrollments e ON s.id = e.student_id
             LEFT JOIN {$self::$wpdb->prefix}nuz_payments p ON s.id = p.student_id AND p.payment_status = 'paid'
             WHERE s.id = %d
             GROUP BY s.id",
            $student_id
        ));
    }
    
    /**
     * Update student
     */
    public static function update_student($student_id, $data) {
        $data['updated_at'] = current_time('mysql');
        
        return self::$wpdb->update(
            self::$wpdb->prefix . 'nuz_students',
            $data,
            array('id' => $student_id),
            self::get_format_array($data),
            array('%d')
        );
    }
    
    /**
     * Delete student
     */
    public static function delete_student($student_id) {
        // Delete related records first
        self::$wpdb->delete(self::$wpdb->prefix . 'nuz_enrollments', array('student_id' => $student_id), array('%d'));
        self::$wpdb->delete(self::$wpdb->prefix . 'nuz_payments', array('student_id' => $student_id), array('%d'));
        self::$wpdb->delete(self::$wpdb->prefix . 'nuz_screenshots', array('student_id' => $student_id), array('%d'));
        
        // Delete student
        return self::$wpdb->delete(
            self::$wpdb->prefix . 'nuz_students',
            array('id' => $student_id),
            array('%d')
        );
    }
    
    /**
     * Course operations
     */
    
    /**
     * Get all courses
     */
    public static function get_courses($args = array()) {
        $defaults = array(
            'limit' => 20,
            'offset' => 0,
            'search' => '',
            'status' => '',
            'orderby' => 'created_at',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where_conditions = array('1=1');
        $where_values = array();
        
        if (!empty($args['search'])) {
            $search_term = '%' . self::$wpdb->esc_like($args['search']) . '%';
            $where_conditions[] = "(course_name LIKE %s OR instructor LIKE %s OR course_code LIKE %s)";
            $where_values[] = $search_term;
            $where_values[] = $search_term;
            $where_values[] = $search_term;
        }
        
        if (!empty($args['status'])) {
            $where_conditions[] = "status = %s";
            $where_values[] = $args['status'];
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $query = "SELECT c.*, COUNT(e.id) as enrolled_students,
                        COALESCE(SUM(p.amount), 0) as total_revenue
                 FROM {$self::$wpdb->prefix}nuz_courses c
                 LEFT JOIN {$self::$wpdb->prefix}nuz_enrollments e ON c.id = e.course_id AND e.status = 'active'
                 LEFT JOIN {$self::$wpdb->prefix}nuz_payments p ON c.id = p.course_id AND p.payment_status = 'paid'
                 WHERE {$where_clause}
                 GROUP BY c.id
                 ORDER BY c.{$args['orderby']} {$args['order']}
                 LIMIT %d OFFSET %d";
        
        $query_values = array_merge($where_values, array($args['limit'], $args['offset']));
        
        return self::$wpdb->get_results(self::$wpdb->prepare($query, $query_values));
    }
    
    /**
     * Get single course by ID
     */
    public static function get_course($course_id) {
        return self::$wpdb->get_row(self::$wpdb->prepare(
            "SELECT c.*, COUNT(e.id) as enrolled_students,
                    COALESCE(SUM(p.amount), 0) as total_revenue
             FROM {$self::$wpdb->prefix}nuz_courses c
             LEFT JOIN {$self::$wpdb->prefix}nuz_enrollments e ON c.id = e.course_id AND e.status = 'active'
             LEFT JOIN {$self::$wpdb->prefix}nuz_payments p ON c.id = p.course_id AND p.payment_status = 'paid'
             WHERE c.id = %d
             GROUP BY c.id",
            $course_id
        ));
    }
    
    /**
     * Payment operations
     */
    
    /**
     * Get payments with student and course information
     */
    public static function get_payments($args = array()) {
        $defaults = array(
            'limit' => 20,
            'offset' => 0,
            'student_id' => 0,
            'course_id' => 0,
            'status' => '',
            'date_from' => '',
            'date_to' => '',
            'orderby' => 'payment_date',
            'order' => 'DESC'
        );
        
        $args = wp_parse_args($args, $defaults);
        
        $where_conditions = array('1=1');
        $where_values = array();
        
        if ($args['student_id'] > 0) {
            $where_conditions[] = "p.student_id = %d";
            $where_values[] = $args['student_id'];
        }
        
        if ($args['course_id'] > 0) {
            $where_conditions[] = "p.course_id = %d";
            $where_values[] = $args['course_id'];
        }
        
        if (!empty($args['status'])) {
            $where_conditions[] = "p.payment_status = %s";
            $where_values[] = $args['status'];
        }
        
        if (!empty($args['date_from'])) {
            $where_conditions[] = "p.payment_date >= %s";
            $where_values[] = $args['date_from'];
        }
        
        if (!empty($args['date_to'])) {
            $where_conditions[] = "p.payment_date <= %s";
            $where_values[] = $args['date_to'];
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        $query = "SELECT p.*, s.name as student_name, s.student_id, c.course_name
                 FROM {$self::$wpdb->prefix}nuz_payments p
                 LEFT JOIN {$self::$wpdb->prefix}nuz_students s ON p.student_id = s.id
                 LEFT JOIN {$self::$wpdb->prefix}nuz_courses c ON p.course_id = c.id
                 WHERE {$where_clause}
                 ORDER BY p.{$args['orderby']} {$args['order']}
                 LIMIT %d OFFSET %d";
        
        $query_values = array_merge($where_values, array($args['limit'], $args['offset']));
        
        return self::$wpdb->get_results(self::$wpdb->prepare($query, $query_values));
    }
    
    /**
     * Get payment statistics
     */
    public static function get_payment_stats($args = array()) {
        $defaults = array(
            'date_from' => date('Y-m-01'), // Start of current month
            'date_to' => date('Y-m-t')     // End of current month
        );
        
        $args = wp_parse_args($args, $defaults);
        
        return self::$wpdb->get_row(self::$wpdb->prepare(
            "SELECT 
                COUNT(*) as total_payments,
                SUM(CASE WHEN payment_status = 'paid' THEN amount ELSE 0 END) as total_paid,
                SUM(CASE WHEN payment_status = 'pending' THEN amount ELSE 0 END) as total_pending,
                SUM(CASE WHEN payment_status = 'overdue' THEN amount ELSE 0 END) as total_overdue,
                COUNT(CASE WHEN payment_status = 'paid' THEN 1 END) as paid_count,
                COUNT(CASE WHEN payment_status = 'pending' THEN 1 END) as pending_count,
                COUNT(CASE WHEN payment_status = 'overdue' THEN 1 END) as overdue_count
             FROM {$self::$wpdb->prefix}nuz_payments
             WHERE payment_date >= %s AND payment_date <= %s",
            $args['date_from'],
            $args['date_to']
        ));
    }
    
    /**
     * Screenshot operations
     */
    
    /**
     * Get student screenshots
     */
    public static function get_student_screenshots($student_id) {
        return self::$wpdb->get_results(self::$wpdb->prepare(
            "SELECT * FROM {$self::$wpdb->prefix}nuz_screenshots
             WHERE student_id = %d
             ORDER BY upload_date DESC",
            $student_id
        ));
    }
    
    /**
     * Settings operations
     */
    
    /**
     * Get all settings
     */
    public static function get_settings() {
        $results = self::$wpdb->get_results("SELECT setting_key, setting_value FROM {$self::$wpdb->prefix}nuz_settings");
        
        $settings = array();
        foreach ($results as $result) {
            $settings[$result->setting_key] = $result->setting_value;
        }
        
        return $settings;
    }
    
    /**
     * Get single setting
     */
    public static function get_setting($key, $default = '') {
        $value = self::$wpdb->get_var(self::$wpdb->prepare(
            "SELECT setting_value FROM {$self::$wpdb->prefix}nuz_settings WHERE setting_key = %s",
            $key
        ));
        
        return $value !== null ? $value : $default;
    }
    
    /**
     * Update setting
     */
    public static function update_setting($key, $value) {
        $existing = self::$wpdb->get_var(self::$wpdb->prepare(
            "SELECT id FROM {$self::$wpdb->prefix}nuz_settings WHERE setting_key = %s",
            $key
        ));
        
        if ($existing) {
            return self::$wpdb->update(
                self::$wpdb->prefix . 'nuz_settings',
                array('setting_value' => $value, 'updated_at' => current_time('mysql')),
                array('id' => $existing),
                array('%s', '%s'),
                array('%d')
            );
        } else {
            return self::$wpdb->insert(
                self::$wpdb->prefix . 'nuz_settings',
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
    
    /**
     * Dashboard statistics
     */
    
    /**
     * Get dashboard statistics
     */
    public static function get_dashboard_stats() {
        $stats = array();
        
        // Students statistics
        $stats['total_students'] = self::$wpdb->get_var("SELECT COUNT(*) FROM {$self::$wpdb->prefix}nuz_students WHERE status = 'active'");
        $stats['new_students_this_month'] = self::$wpdb->get_var(self::$wpdb->prepare(
            "SELECT COUNT(*) FROM {$self::$wpdb->prefix}nuz_students WHERE status = 'active' AND created_at >= %s",
            date('Y-m-01')
        ));
        
        // Courses statistics
        $stats['total_courses'] = self::$wpdb->get_var("SELECT COUNT(*) FROM {$self::$wpdb->prefix}nuz_courses WHERE status = 'active'");
        $stats['active_enrollments'] = self::$wpdb->get_var("SELECT COUNT(*) FROM {$self::$wpdb->prefix}nuz_enrollments WHERE status = 'active'");
        
        // Revenue statistics
        $stats['total_revenue'] = self::$wpdb->get_var("SELECT SUM(amount) FROM {$self::$wpdb->prefix}nuz_payments WHERE payment_status = 'paid'");
        $stats['monthly_revenue'] = self::$wpdb->get_var(self::$wpdb->prepare(
            "SELECT SUM(amount) FROM {$self::$wpdb->prefix}nuz_payments 
             WHERE payment_status = 'paid' AND payment_date >= %s",
            date('Y-m-01')
        ));
        
        // Payment statistics
        $stats['pending_payments'] = self::$wpdb->get_var("SELECT COUNT(*) FROM {$self::$wpdb->prefix}nuz_payments WHERE payment_status = 'pending'");
        $stats['overdue_payments'] = self::$wpdb->get_var("SELECT COUNT(*) FROM {$self::$wpdb->prefix}nuz_payments WHERE payment_status = 'overdue'");
        
        return $stats;
    }
    
    /**
     * Get chart data for analytics
     */
    public static function get_chart_data($type = 'monthly_revenue') {
        switch ($type) {
            case 'monthly_revenue':
                return self::get_monthly_revenue_chart();
            case 'enrollment_trends':
                return self::get_enrollment_trends_chart();
            case 'payment_methods':
                return self::get_payment_methods_chart();
            default:
                return array();
        }
    }
    
    /**
     * Get monthly revenue chart data
     */
    private static function get_monthly_revenue_chart() {
        $results = self::$wpdb->get_results("
            SELECT 
                DATE_FORMAT(payment_date, '%Y-%m') as month,
                SUM(amount) as revenue
            FROM {$self::$wpdb->prefix}nuz_payments 
            WHERE payment_status = 'paid' 
            AND payment_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
            GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
            ORDER BY month
        ");
        
        $labels = array();
        $data = array();
        
        foreach ($results as $row) {
            $labels[] = date('M Y', strtotime($row->month . '-01'));
            $data[] = floatval($row->revenue);
        }
        
        return array(
            'labels' => $labels,
            'data' => $data
        );
    }
    
    /**
     * Utility methods
     */
    
    /**
     * Get data format array for wpdb operations
     */
    private static function get_format_array($data) {
        $formats = array();
        foreach ($data as $value) {
            if (is_int($value)) {
                $formats[] = '%d';
            } elseif (is_float($value)) {
                $formats[] = '%f';
            } else {
                $formats[] = '%s';
            }
        }
        return $formats;
    }
    
    /**
     * Clean up old demo data
     */
    public static function clean_demo_data() {
        self::$wpdb->query("TRUNCATE TABLE {$self::$wpdb->prefix}nuz_students");
        self::$wpdb->query("TRUNCATE TABLE {$self::$wpdb->prefix}nuz_payments");
        self::$wpdb->query("TRUNCATE TABLE {$self::$wpdb->prefix}nuz_enrollments");
        self::$wpdb->query("TRUNCATE TABLE {$self::$wpdb->prefix}nuz_screenshots");
        
        // Reset course demo data
        self::$wpdb->query("DELETE FROM {$self::$wpdb->prefix}nuz_courses WHERE course_code LIKE 'DEMO-%'");
        
        return true;
    }
    
    /**
     * Export data to CSV format
     */
    public static function export_to_csv($type = 'students') {
        switch ($type) {
            case 'students':
                return self::export_students_to_csv();
            case 'payments':
                return self::export_payments_to_csv();
            case 'courses':
                return self::export_courses_to_csv();
            default:
                return false;
        }
    }
    
    /**
     * Export students to CSV
     */
    private static function export_students_to_csv() {
        $students = self::$wpdb->get_results("
            SELECT s.*, c.course_name, c.price as course_price
            FROM {$self::$wpdb->prefix}nuz_students s
            LEFT JOIN {$self::$wpdb->prefix}nuz_courses c ON s.course_id = c.id
            ORDER BY s.name ASC
        ");
        
        $csv_data = array();
        $csv_data[] = array('Student ID', 'Name', 'Email', 'Phone', 'Course', 'Course Price', 'Admission Date', 'Status', 'Address');
        
        foreach ($students as $student) {
            $csv_data[] = array(
                $student->student_id,
                $student->name,
                $student->email,
                $student->phone,
                $student->course_name,
                $student->course_price,
                $student->admission_date,
                $student->status,
                $student->address
            );
        }
        
        return $csv_data;
    }
    
    /**
     * Database backup
     */
    public static function create_backup() {
        $backup_data = array();
        
        // Get all tables
        $tables = array(
            'nuz_students',
            'nuz_courses', 
            'nuz_payments',
            'nuz_enrollments',
            'nuz_screenshots',
            'nuz_settings'
        );
        
        foreach ($tables as $table) {
            $full_table_name = self::$wpdb->prefix . $table;
            $backup_data[$table] = self::$wpdb->get_results("SELECT * FROM {$full_table_name}", ARRAY_A);
        }
        
        return $backup_data;
    }
    
    /**
     * Restore from backup
     */
    public static function restore_backup($backup_data) {
        if (!is_array($backup_data)) {
            return false;
        }
        
        try {
            foreach ($backup_data as $table => $data) {
                if (!is_array($data)) continue;
                
                $full_table_name = self::$wpdb->prefix . $table;
                
                // Clear existing data
                self::$wpdb->query("TRUNCATE TABLE {$full_table_name}");
                
                // Insert backup data
                foreach ($data as $row) {
                    self::$wpdb->insert($full_table_name, $row);
                }
            }
            
            return true;
        } catch (Exception $e) {
            error_log('NUZ Academy Backup Restore Error: ' . $e->getMessage());
            return false;
        }
    }
}