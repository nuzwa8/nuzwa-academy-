<?php
/**
 * Assets Loader Class
 * 
 * Handles loading of scripts, styles, and other assets
 * 
 * @package NuzOnlineAcademy
 * @since 1.0.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

class NUZ_Assets {
    
    /**
     * Initialize assets loading
     */
    public static function init() {
        add_action('admin_enqueue_scripts', array(__CLASS__, 'admin_assets'));
        add_action('admin_head', array(__CLASS__, 'admin_head_scripts'));
        add_action('admin_footer', array(__CLASS__, 'admin_footer_scripts'));
        
        // Register and enqueue assets
        add_action('wp_enqueue_scripts', array(__CLASS__, 'frontend_assets'));
    }
    
    /**
     * Load admin assets
     */
    public static function admin_assets($hook) {
        // Only load on our plugin pages
        if (strpos($hook, 'nuz-') === false) {
            return;
        }
        
        // Register Google Fonts
        wp_enqueue_style('google-fonts', 'https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap', array(), NUZ_PLUGIN_VERSION);
        
        // Register Bootstrap (optional - can be replaced with custom styles)
        wp_enqueue_style('bootstrap-css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css', array(), '5.3.0');
        wp_enqueue_script('bootstrap-js', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js', array('jquery'), '5.3.0', true);
        
        // Register Font Awesome
        wp_enqueue_style('fontawesome', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css', array(), '6.4.0');
        
        // Register Chart.js
        wp_enqueue_script('chart-js', 'https://cdn.jsdelivr.net/npm/chart.js', array(), '4.4.0', true);
        
        // Register DataTables (optional)
        wp_enqueue_style('datatables-css', 'https://cdn.datatables.net/1.13.7/css/dataTables.bootstrap5.min.css', array(), '1.13.7');
        wp_enqueue_script('datatables-js', 'https://cdn.datatables.net/1.13.7/js/jquery.dataTables.min.js', array('jquery'), '1.13.7', true);
        wp_enqueue_script('datatables-bootstrap-js', 'https://cdn.datatables.net/1.13.7/js/dataTables.bootstrap5.min.js', array('datatables-js'), '1.13.7', true);
        
        // Register Select2 for enhanced select dropdowns
        wp_enqueue_style('select2-css', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css', array(), '4.1.0-rc.0');
        wp_enqueue_script('select2-js', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', array('jquery'), '4.1.0-rc.0', true);
        
        // Register DateTime picker
        wp_enqueue_style('flatpickr-css', 'https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css', array(), '4.6.13');
        wp_enqueue_script('flatpickr-js', 'https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js', array(), '4.6.13', true);
        
        // Register PapaParse for CSV import/export
        wp_enqueue_script('papaparse', 'https://cdn.jsdelivr.net/npm/papaparse@5.4.1/papaparse.min.js', array(), '5.4.1', true);
        
        // Plugin specific CSS
        wp_enqueue_style('nuz-common', NUZ_PLUGIN_URL . 'nuz-common.css', array(), NUZ_PLUGIN_VERSION);
        wp_enqueue_style('nuz-online-academy', NUZ_PLUGIN_URL . 'nuz-online-academy.css', array('nuz-common'), NUZ_PLUGIN_VERSION);
        
        // Plugin specific JavaScript
        wp_enqueue_script('nuz-common', NUZ_PLUGIN_URL . 'nuz-common.js', array('jquery'), NUZ_PLUGIN_VERSION, true);
        wp_enqueue_script('nuz-online-academy', NUZ_PLUGIN_URL . 'nuz-online-academy.js', array('jquery', 'nuz-common'), NUZ_PLUGIN_VERSION, true);
        
        // Localize script with AJAX data
        wp_localize_script('nuz-online-academy', 'nuz_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('nuz_nonce'),
            'plugin_url' => NUZ_PLUGIN_URL,
            'current_page' => self::get_current_plugin_page(),
            'strings' => array(
                // Loading states
                'loading' => __('Loading...', 'nuz-online-academy'),
                'saving' => __('Saving...', 'nuz-online-academy'),
                'uploading' => __('Uploading...', 'nuz-online-academy'),
                'processing' => __('Processing...', 'nuz-online-academy'),
                
                // Success messages
                'success' => __('Success!', 'nuz-online-academy'),
                'saved' => __('Data saved successfully!', 'nuz-online-academy'),
                'uploaded' => __('File uploaded successfully!', 'nuz-online-academy'),
                'exported' => __('Data exported successfully!', 'nuz-online-academy'),
                'updated' => __('Settings updated successfully!', 'nuz-online-academy'),
                
                // Error messages
                'error' => __('Error occurred', 'nuz-online-academy'),
                'upload_failed' => __('File upload failed', 'nuz-online-academy'),
                'save_failed' => __('Failed to save data', 'nuz-online-academy'),
                'network_error' => __('Network error occurred', 'nuz-online-academy'),
                
                // Validation messages
                'required_field' => __('This field is required', 'nuz-online-academy'),
                'invalid_email' => __('Please enter a valid email address', 'nuz-online-academy'),
                'invalid_phone' => __('Please enter a valid phone number', 'nuz-online-academy'),
                'file_too_large' => __('File size is too large', 'nuz-online-academy'),
                'invalid_file_type' => __('Invalid file type', 'nuz-online-academy'),
                
                // Confirmation messages
                'confirm_delete' => __('Are you sure you want to delete this item?', 'nuz-online-academy'),
                'confirm_reset' => __('Are you sure you want to reset all demo data?', 'nuz-online-academy'),
                'confirm_export' => __('Do you want to export all data?', 'nuz-online-academy'),
                
                // Form labels
                'student_name' => __('Student Name', 'nuz-online-academy'),
                'email_address' => __('Email Address', 'nuz-online-academy'),
                'phone_number' => __('Phone Number', 'nuz-online-academy'),
                'course' => __('Course', 'nuz-online-academy'),
                'admission_date' => __('Admission Date', 'nuz-online-academy'),
                'payment_amount' => __('Payment Amount', 'nuz-online-academy'),
                'payment_date' => __('Payment Date', 'nuz-online-academy'),
                
                // Status labels
                'active' => __('Active', 'nuz-online-academy'),
                'inactive' => __('Inactive', 'nuz-online-academy'),
                'paid' => __('Paid', 'nuz-online-academy'),
                'pending' => __('Pending', 'nuz-online-academy'),
                'overdue' => __('Overdue', 'nuz-online-academy'),
                
                // Menu items
                'dashboard' => __('Dashboard', 'nuz-online-academy'),
                'students' => __('Students', 'nuz-online-academy'),
                'courses' => __('Courses', 'nuz-online-academy'),
                'payments' => __('Payments', 'nuz-online-academy'),
                'settings' => __('Settings', 'nuz-online-academy'),
                
                // Actions
                'add_new' => __('Add New', 'nuz-online-academy'),
                'edit' => __('Edit', 'nuz-online-academy'),
                'delete' => __('Delete', 'nuz-online-academy'),
                'save' => __('Save', 'nuz-online-academy'),
                'cancel' => __('Cancel', 'nuz-online-academy'),
                'search' => __('Search', 'nuz-online-academy'),
                'filter' => __('Filter', 'nuz-online-academy'),
                'export' => __('Export', 'nuz-online-academy'),
                'import' => __('Import', 'nuz-online-academy'),
                'print' => __('Print', 'nuz-online-academy')
            )
        ));
    }
    
    /**
     * Load admin head scripts
     */
    public static function admin_head_scripts() {
        if (strpos($_GET['page'] ?? '', 'nuz-') === false) {
            return;
        }
        
        // Add custom CSS variables for theme
        $settings = self::get_plugin_settings();
        $theme_mode = $settings['theme_mode'] ?? 'light';
        $primary_color = $settings['primary_color'] ?? '#4f46e5';
        $secondary_color = $settings['secondary_color'] ?? '#10b981';
        
        echo '<style id="nuz-theme-vars">
            :root {
                --nuz-primary-color: ' . esc_attr($primary_color) . ';
                --nuz-secondary-color: ' . esc_attr($secondary_color) . ';
                --nuz-theme-mode: ' . esc_attr($theme_mode) . ';
            }
            body.admin-color-' . esc_attr($theme_mode) . ' {
                --wp-admin-theme-color: ' . esc_attr($primary_color) . ';
            }
        </style>';
    }
    
    /**
     * Load admin footer scripts
     */
    public static function admin_footer_scripts() {
        if (strpos($_GET['page'] ?? '', 'nuz-') === false) {
            return;
        }
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            // Initialize tooltips
            $('[data-bs-toggle="tooltip"]').tooltip();
            
            // Initialize popovers
            $('[data-bs-toggle="popover"]').popover();
            
            // Auto-hide alerts
            setTimeout(function() {
                $('.alert').fadeOut();
            }, 5000);
            
            // Add loading class to forms during submission
            $('form').on('submit', function() {
                $(this).addClass('loading');
                var submitBtn = $(this).find('button[type="submit"], input[type="submit"]');
                submitBtn.prop('disabled', true);
            });
        });
        </script>
        <?php
    }
    
    /**
     * Load frontend assets (if needed for public pages)
     */
    public static function frontend_assets() {
        // Only load if needed for frontend student portal
        if (is_page('student-portal') || is_page('course-dashboard')) {
            wp_enqueue_style('nuz-frontend', NUZ_PLUGIN_URL . 'nuz-frontend.css', array(), NUZ_PLUGIN_VERSION);
            wp_enqueue_script('nuz-frontend', NUZ_PLUGIN_URL . 'nuz-frontend.js', array('jquery'), NUZ_PLUGIN_VERSION, true);
            
            wp_localize_script('nuz-frontend', 'nuz_student_portal', array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('nuz_student_nonce'),
                'student_id' => get_current_user_id()
            ));
        }
    }
    
    /**
     * Get current plugin page
     */
    private static function get_current_plugin_page() {
        $current_page = $_GET['page'] ?? '';
        
        if (strpos($current_page, 'nuz-') !== false) {
            return str_replace('nuz-', '', $current_page);
        }
        
        return 'dashboard';
    }
    
    /**
     * Get plugin settings
     */
    private static function get_plugin_settings() {
        global $wpdb;
        
        $settings = array();
        $results = $wpdb->get_results("SELECT setting_key, setting_value FROM {$wpdb->prefix}nuz_settings");
        
        foreach ($results as $result) {
            $settings[$result->setting_key] = $result->setting_value;
        }
        
        return $settings;
    }
    
    /**
     * Register custom post types and taxonomies (if needed)
     */
    public static function register_post_types() {
        // Register lesson post type for course content
        register_post_type('nuz_lesson', array(
            'labels' => array(
                'name' => __('Lessons', 'nuz-online-academy'),
                'singular_name' => __('Lesson', 'nuz-online-academy'),
            ),
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'edit.php?post_type=nuz_course',
            'supports' => array('title', 'editor', 'thumbnail'),
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ));
        
        // Register course categories taxonomy
        register_taxonomy('nuz_course_category', 'nuz_course', array(
            'labels' => array(
                'name' => __('Course Categories', 'nuz-online-academy'),
                'singular_name' => __('Course Category', 'nuz-online-academy'),
            ),
            'hierarchical' => true,
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => true,
        ));
    }
    
    /**
     * Enqueue media uploader
     */
    public static function enqueue_media_uploader() {
        wp_enqueue_media();
        wp_enqueue_script('media-upload');
        wp_enqueue_script('thickbox');
        wp_enqueue_style('thickbox');
    }
    
    /**
     * Register admin color schemes
     */
    public static function add_admin_color_schemes() {
        wp_admin_css_color(
            'nuz_academy',
            __('NUZ Academy', 'nuz-online-academy'),
            NUZ_PLUGIN_URL . 'admin-colors.css',
            array('#4f46e5', '#10b981', '#f59e0b', '#ef4444')
        );
    }
    
    /**
     * Add custom CSS for admin color scheme
     */
    public static function custom_admin_color_css() {
        echo '<style>
            .wp-admin #wpadminbar {
                background: linear-gradient(135deg, #4f46e5 0%, #7c3aed 100%);
            }
            .wp-admin #adminmenu {
                background: linear-gradient(180deg, #1e293b 0%, #0f172a 100%);
            }
            .wp-admin #adminmenu .wp-menu-image.dashicons-admin-generic:before {
                background: linear-gradient(135deg, #4f46e5, #7c3aed);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
            }
        </style>';
    }
}