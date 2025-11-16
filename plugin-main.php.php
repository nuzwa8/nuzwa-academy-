<?php
/**
 * Plugin Name: Baba Academy Admissions
 * Description: بابا اکیڈمی کے لیے سادہ مگر محفوظ (admission management) — (courses fixed fee), (admission form), (screenshot upload), (due date) اور (dashboard)۔
 * Version: 1.0.0
 * Author: Baba Academy
 * Text Domain: baba-academy
 * Domain Path: /languages
 */

namespace SSM\BA;

if ( ! defined('ABSPATH') ) exit;

/**
 * --------------------------------------------------------------------
 * بنیادی کونسٹنٹس + ہیلپرز
 * --------------------------------------------------------------------
 */
const VERSION   = '1.0.0';
const NS        = 'ssm-ba';
const CAP       = 'manage_baba_academy';
const ROLE_KEY  = 'ba_manager';
const NONCE_KEY = 'ssm_ba_nonce';
const UPLOAD_MAX_MB = 5; // زیادہ سے زیادہ اپلوڈ سائز (MB) — امیج کے لیے

/**
 * ٹیبّل نام رجسٹری — ہمیشہ مرکزی سچائی
 */
function table_names() : array {
	global $wpdb;
	$prefix = $wpdb->prefix;
	return [
		'courses'    => "{$prefix}ba_courses",
		'admissions' => "{$prefix}ba_admissions",
	];
}

/**
 * WP ٹائم زون میں تاریخ/دنوں کا حساب
 */
function wp_datetime_from_string( string $dateYmd ) : ?\DateTimeImmutable {
	try {
		$tz   = wp_timezone();
		$dt   = new \DateTimeImmutable($dateYmd, $tz);
		// normalize Y-m-d only
		return \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $dt->format('Y-m-d 00:00:00'), $tz) ?: $dt;
	} catch (\Throwable $e) {
		return null;
	}
}

function days_left_until( string $dateYmd ) : ?int {
	$due = wp_datetime_from_string($dateYmd);
	if (!$due) return null;
	$now = new \DateTimeImmutable('now', wp_timezone());
	$diff = $due->diff($now);
	$days = (int) ($diff->invert ? $diff->days : -$diff->days);
	return $days; // مثبت = دن باقی، منفی = اوورڈیو
}

/**
 * --------------------------------------------------------------------
 * ایکٹیویشن: (dbDelta) + (roles/capabilities) + ڈیفالٹ کورس
 * --------------------------------------------------------------------
 */
register_activation_hook(__FILE__, __NAMESPACE__ . '\\on_activate');
function on_activate() {
	global $wpdb;
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	$charset_collate = $wpdb->get_charset_collate();
	$tbl = table_names();

	$sql_courses = "CREATE TABLE {$tbl['courses']} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		title VARCHAR(190) NOT NULL,
		fixed_fee BIGINT UNSIGNED NOT NULL DEFAULT 0,
		status TINYINT(1) NOT NULL DEFAULT 1,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id),
		KEY status (status)
	) $charset_collate;";

	$sql_adm = "CREATE TABLE {$tbl['admissions']} (
		id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
		student_name VARCHAR(190) NOT NULL,
		phone VARCHAR(50) NOT NULL,
		notes TEXT NULL,
		course_id BIGINT UNSIGNED NOT NULL,
		total_fee BIGINT UNSIGNED NOT NULL DEFAULT 0,
		paid_amount BIGINT UNSIGNED NOT NULL DEFAULT 0,
		remaining_amount BIGINT UNSIGNED NOT NULL DEFAULT 0,
		due_date DATE NULL,
		screenshot_url VARCHAR(255) NULL,
		status TINYINT(1) NOT NULL DEFAULT 0,
		created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id),
		KEY course_id (course_id),
		KEY status (status),
		KEY due_date (due_date)
	) $charset_collate;";

	dbDelta($sql_courses);
	dbDelta($sql_adm);

	// رول/کیپ
	add_role(ROLE_KEY, __('Baba Academy Manager','baba-academy'), [
		'read' => true,
		CAP    => true,
	]);
	$admin = get_role('administrator');
	if ($admin && ! $admin->has_cap(CAP)) {
		$admin->add_cap(CAP);
	}

	// ڈیفالٹ کورس اگر نہیں موجود تو بنائیں — "ویب بیسڈ سافٹ ویئر ڈیویلپمنٹ" 50,000
	$exists = (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$tbl['courses']} WHERE title='Web-based Software Development' LIMIT 1" );
	if ( $exists === 0 ) {
		$wpdb->insert(
			$tbl['courses'],
			[
				'title'      => 'Web-based Software Development',
				'fixed_fee'  => 50000,
				'status'     => 1,
				'created_at' => current_time('mysql'),
			],
			['%s','%d','%d','%s']
		);
	}

	update_option('ssm_ba_version', VERSION);
}

/**
 * --------------------------------------------------------------------
 * ایڈمن مینو + اسکرینز کنٹینرز (templates کے ساتھ)
 * --------------------------------------------------------------------
 */
add_action('admin_menu', __NAMESPACE__ . '\\register_menus');
function register_menus() {
	if ( ! current_user_can(CAP) ) {
		// پھر بھی ٹاپ لیول بن جاتا ہے، لہٰذا کیپ چیک کے ساتھ رینڈرنگ کریں گے
	}
	add_menu_page(
		__('Baba Academy','baba-academy'),
		__('Baba Academy','baba-academy'),
		CAP,
		NS . '-dashboard',
		__NAMESPACE__ . '\\render_dashboard',
		'dashicons-welcome-learn-more',
		56
	);
	add_submenu_page(
		NS . '-dashboard',
		__('Admissions','baba-academy'),
		__('Admissions','baba-academy'),
		CAP,
		NS . '-dashboard',
		__NAMESPACE__ . '\\render_dashboard'
	);
	add_submenu_page(
		NS . '-dashboard',
		__('New Admission','baba-academy'),
		__('New Admission','baba-academy'),
		CAP,
		NS . '-new',
		__NAMESPACE__ . '\\render_new_admission'
	);
	add_submenu_page(
		NS . '-dashboard',
		__('Courses','baba-academy'),
		__('Courses','baba-academy'),
		CAP,
		NS . '-courses',
		__NAMESPACE__ . '\\render_courses'
	);
	add_submenu_page(
		NS . '-dashboard',
		__('Settings','baba-academy'),
		__('Settings','baba-academy'),
		CAP,
		NS . '-settings',
		__NAMESPACE__ . '\\render_settings'
	);
}

function render_screen_shell( string $screen_id, string $title ){
	if ( ! current_user_can(CAP) ) {
		wp_die( __('آپ کو اس صفحہ تک رسائی کی اجازت نہیں۔','baba-academy') );
	}
	echo '<div class="wrap"><h1>'. esc_html($title) .'</h1>';
	echo '<div id="'. esc_attr($screen_id) .'"></div>';
	// تمام templates ایک ہی جگہ لوڈ — JS بعد میں mount کرے گا
	render_templates();
	echo '</div>';
}

function render_dashboard(){
	render_screen_shell(NS.'-app-dashboard', __('داخلے — ڈیش بورڈ','baba-academy'));
}
function render_new_admission(){
	render_screen_shell(NS.'-app-new', __('نیا داخلہ فارم','baba-academy'));
}
function render_courses(){
	render_screen_shell(NS.'-app-courses', __('کورسز (فکس فیس)','baba-academy'));
}
function render_settings(){
	render_screen_shell(NS.'-app-settings', __('سیٹنگز','baba-academy'));
}

/**
 * --------------------------------------------------------------------
 * ایڈمن اسکرپٹس/سی ایس ایس (enqueue) + (wp_localize_script)
 * --------------------------------------------------------------------
 */
add_action('admin_enqueue_scripts', __NAMESPACE__ . '\\enqueue_assets');
function enqueue_assets($hook) {
	$page = isset($_GET['page']) ? sanitize_text_field($_GET['page']) : '';
	if (strpos($page, NS.'-') !== 0) return;

	$ver = VERSION;
	$base = plugin_dir_url(__FILE__);
	wp_enqueue_style(NS.'-style', $base.'assets/css/style.css', [], $ver);
	wp_enqueue_script(NS.'-app', $base.'assets/js/app.js', ['jquery'], $ver, true);

	$nonce = wp_create_nonce(NONCE_KEY);
	wp_localize_script(NS.'-app', 'ssmData', [
		'ajaxUrl'   => admin_url('admin-ajax.php'),
		'nonce'     => $nonce,
		'cap'       => current_user_can(CAP),
		'i18n'      => [
			'save' => __('محفوظ کریں','baba-academy'),
			'delete' => __('حذف کریں','baba-academy'),
			'loading' => __('لوڈ ہورہا ہے...','baba-academy'),
			'error' => __('کوئی مسئلہ پیش آگیا۔ دوبارہ کوشش کریں۔','baba-academy'),
		],
		'limits'    => [
			'uploadMaxMB' => UPLOAD_MAX_MB,
		],
	]);
}

/**
 * --------------------------------------------------------------------
 * HTML Templates — ہر اسکرین کے لیے <template> بلاکس
 * --------------------------------------------------------------------
 */
function render_templates(){
	?>
	<!-- Dashboard: Admissions List -->
	<template id="<?php echo esc_attr(NS); ?>-tpl-dashboard">
		<div class="ssm ssm-dashboard">
			<div class="ssm-controls" role="region" aria-label="<?php esc_attr_e('فلٹرز','baba-academy'); ?>">
				<select id="filter-course" aria-label="<?php esc_attr_e('کورس منتخب کریں','baba-academy'); ?>">
					<option value=""><?php esc_html_e('تمام کورسز','baba-academy'); ?></option>
				</select>
				<select id="filter-status" aria-label="<?php esc_attr_e('اسٹیٹس منتخب کریں','baba-academy'); ?>">
					<option value=""><?php esc_html_e('تمام اسٹیٹس','baba-academy'); ?></option>
					<option value="0"><?php esc_html_e('زیرِالتواء','baba-academy'); ?></option>
					<option value="1"><?php esc_html_e('ادا شدہ','baba-academy'); ?></option>
					<option value="-1"><?php esc_html_e('اوورڈیو','baba-academy'); ?></option>
				</select>
				<button class="button button-primary" id="btn-refresh"><?php esc_html_e('ری فریش','baba-academy'); ?></button>
			</div>
			<div class="ssm-table-wrap" tabindex="0">
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th><?php esc_html_e('طالب علم','baba-academy'); ?></th>
							<th><?php esc_html_e('کورس','baba-academy'); ?></th>
							<th><?php esc_html_e('کل فیس','baba-academy'); ?></th>
							<th><?php esc_html_e('ادا شدہ','baba-academy'); ?></th>
							<th><?php esc_html_e('بقایا','baba-academy'); ?></th>
							<th><?php esc_html_e('ادائیگی کی تاریخ','baba-academy'); ?></th>
							<th><?php esc_html_e('باقی دن','baba-academy'); ?></th>
							<th><?php esc_html_e('اسٹیٹس','baba-academy'); ?></th>
							<th><?php esc_html_e('اسکرین شاٹ','baba-academy'); ?></th>
						</tr>
					</thead>
					<tbody id="adm-rows"></tbody>
				</table>
			</div>
			<div class="ssm-pagination" aria-label="<?php esc_attr_e('صفحہ بندی','baba-academy'); ?>">
				<button class="button" id="pg-prev"><?php esc_html_e('پچھلا','baba-academy'); ?></button>
				<span id="pg-info" aria-live="polite"></span>
				<button class="button" id="pg-next"><?php esc_html_e('اگلا','baba-academy'); ?></button>
			</div>
		</div>
	</template>

	<!-- New Admission Form -->
	<template id="<?php echo esc_attr(NS); ?>-tpl-new">
		<form class="ssm ssm-form" id="form-admission" enctype="multipart/form-data">
			<div class="ssm-grid">
				<label>
					<span><?php esc_html_e('نام','baba-academy'); ?></span>
					<input type="text" name="student_name" required />
				</label>
				<label>
					<span><?php esc_html_e('فون نمبر','baba-academy'); ?></span>
					<input type="tel" name="phone" required />
				</label>
				<label class="ssm-col-2">
					<span><?php esc_html_e('اضافی معلومات','baba-academy'); ?></span>
					<textarea name="notes" rows="3"></textarea>
				</label>
				<label>
					<span><?php esc_html_e('کورس منتخب کریں','baba-academy'); ?></span>
					<select name="course_id" id="course-select" required>
						<option value=""><?php esc_html_e('منتخب کریں','baba-academy'); ?></option>
					</select>
				</label>
				<label>
					<span><?php esc_html_e('کل فیس (فکس)','baba-academy'); ?></span>
					<input type="number" name="total_fee" id="total-fee" readonly />
				</label>
				<label>
					<span><?php esc_html_e('کتنی رقم ابھی ادا کی','baba-academy'); ?></span>
					<input type="number" name="paid_amount" id="paid-amount" min="0" step="1" required />
				</label>
				<label>
					<span><?php esc_html_e('بقایا رقم','baba-academy'); ?></span>
					<input type="number" name="remaining_amount" id="remaining-amount" readonly />
				</label>
				<label>
					<span><?php esc_html_e('بقایا ادائیگی کی تاریخ','baba-academy'); ?></span>
					<input type="date" name="due_date" required />
				</label>
				<label class="ssm-col-2">
					<span><?php esc_html_e('ادائیگی اسکرین شاٹ (اختیاری)','baba-academy'); ?></span>
					<input type="file" name="screenshot" accept="image/jpeg,image/png,image/webp" />
					<small><?php printf( esc_html__('زیادہ سے زیادہ فائل سائز: %dMB','baba-academy'), (int) UPLOAD_MAX_MB ); ?></small>
				</label>
			</div>
			<div>
				<button class="button button-primary" type="submit"><?php esc_html_e('داخلہ محفوظ کریں','baba-academy'); ?></button>
			</div>
		</form>
	</template>

	<!-- Courses (Fixed Fee) -->
	<template id="<?php echo esc_attr(NS); ?>-tpl-courses">
		<div class="ssm ssm-courses">
			<form id="form-course" class="ssm-form-inline" autocomplete="off">
				<input type="text" name="title" placeholder="<?php esc_attr_e('کورس عنوان','baba-academy'); ?>" required />
				<input type="number" name="fixed_fee" placeholder="<?php esc_attr_e('فکس فیس (ہندسوں میں)','baba-academy'); ?>" min="0" step="1" required />
				<button class="button button-primary" type="submit"><?php esc_html_e('کورس شامل کریں','baba-academy'); ?></button>
			</form>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e('عنوان','baba-academy'); ?></th>
						<th><?php esc_html_e('فکس فیس','baba-academy'); ?></th>
						<th><?php esc_html_e('حالت','baba-academy'); ?></th>
					</tr>
				</thead>
				<tbody id="course-rows"></tbody>
			</table>
		</div>
	</template>

	<!-- Settings -->
	<template id="<?php echo esc_attr(NS); ?>-tpl-settings">
		<div class="ssm ssm-settings">
			<p><?php esc_html_e('ابھی بنیادی سیٹنگز خودکار ہیں۔ اگلے ورژنز میں مزید اختیارات شامل ہوں گے۔','baba-academy'); ?></p>
		</div>
	</template>
	<?php
}

/**
 * --------------------------------------------------------------------
 * AJAX: کورسز — لسٹ/شامل
 * --------------------------------------------------------------------
 */
add_action('wp_ajax_'.NS.'_list_courses', __NAMESPACE__ . '\\ajax_list_courses');
function ajax_list_courses(){
	check_ajax_referer(NONCE_KEY, 'nonce');
	if ( ! current_user_can(CAP) ) wp_send_json_error(['msg' => __('اجازت نہیں','baba-academy')], 403);

	global $wpdb;
	$tbl = table_names();
	$rows = $wpdb->get_results("SELECT id, title, fixed_fee, status FROM {$tbl['courses']} WHERE status=1 ORDER BY id DESC", ARRAY_A);
	wp_send_json_success(['items' => array_map(function($r){
		return [
			'id'   => (int) $r['id'],
			'title'=> sanitize_text_field($r['title']),
			'fixed_fee' => (int) $r['fixed_fee'],
			'status' => (int) $r['status'],
		];
	}, $rows)]);
}

add_action('wp_ajax_'.NS.'_save_course', __NAMESPACE__ . '\\ajax_save_course');
function ajax_save_course(){
	check_ajax_referer(NONCE_KEY, 'nonce');
	if ( ! current_user_can(CAP) ) wp_send_json_error(['msg' => __('اجازت نہیں','baba-academy')], 403);

	$title = isset($_POST['title']) ? sanitize_text_field(wp_unslash($_POST['title'])) : '';
	$fee   = isset($_POST['fixed_fee']) ? absint($_POST['fixed_fee']) : 0;

	if ($title === '' || $fee <= 0) {
		wp_send_json_error(['msg' => __('عنوان اور درست فیس درکار ہے۔','baba-academy')], 422);
	}

	global $wpdb;
	$tbl = table_names();
	$ok = $wpdb->insert($tbl['courses'], [
		'title'      => $title,
		'fixed_fee'  => $fee,
		'status'     => 1,
		'created_at' => current_time('mysql'),
	], ['%s','%d','%d','%s']);

	if (!$ok) {
		wp_send_json_error(['msg' => __('محفوظ نہ ہو سکا۔','baba-academy')], 500);
	}
	wp_send_json_success(['msg' => __('کورس شامل ہوگیا۔','baba-academy')]);
}

/**
 * --------------------------------------------------------------------
 * AJAX: داخلہ — محفوظ کریں (اسکرین شاٹ اپلوڈ کے ساتھ)
 * --------------------------------------------------------------------
 */
add_action('wp_ajax_'.NS.'_create_admission', __NAMESPACE__ . '\\ajax_create_admission');
function ajax_create_admission(){
	check_ajax_referer(NONCE_KEY, 'nonce');
	if ( ! current_user_can(CAP) ) wp_send_json_error(['msg' => __('اجازت نہیں','baba-academy')], 403);

	$student_name = isset($_POST['student_name']) ? sanitize_text_field(wp_unslash($_POST['student_name'])) : '';
	$phone        = isset($_POST['phone']) ? sanitize_text_field(wp_unslash($_POST['phone'])) : '';
	$notes        = isset($_POST['notes']) ? sanitize_textarea_field(wp_unslash($_POST['notes'])) : '';
	$course_id    = isset($_POST['course_id']) ? absint($_POST['course_id']) : 0;
	$total_fee    = isset($_POST['total_fee']) ? absint($_POST['total_fee']) : 0;
	$paid_amount  = isset($_POST['paid_amount']) ? absint($_POST['paid_amount']) : 0;
	$remaining    = isset($_POST['remaining_amount']) ? absint($_POST['remaining_amount']) : 0;
	$due_date     = isset($_POST['due_date']) ? sanitize_text_field(wp_unslash($_POST['due_date'])) : '';

	if ($student_name === '' || $phone === '' || $course_id <= 0 || $total_fee <= 0 || $due_date === '') {
		wp_send_json_error(['msg' => __('درکار فیلڈز نامکمل ہیں۔','baba-academy')], 422);
	}

	// کورس کی فکس فیس enforce
	global $wpdb;
	$tbl = table_names();
	$fixed = (int) $wpdb->get_var( $wpdb->prepare("SELECT fixed_fee FROM {$tbl['courses']} WHERE id=%d AND status=1", $course_id) );
	if ($fixed <= 0) {
		wp_send_json_error(['msg' => __('کورس دستیاب نہیں۔','baba-academy')], 422);
	}
	if ($total_fee !== $fixed) {
		wp_send_json_error(['msg' => __('کل فیس ایڈمن کے مقررہ فکس کے مطابق ہونی چاہیے۔','baba-academy')], 422);
	}
	if ($paid_amount > $total_fee) {
		wp_send_json_error(['msg' => __('ادا شدہ رقم کل فیس سے زیادہ نہیں ہوسکتی۔','baba-academy')], 422);
	}
	// remaining ری کیلک بطور حفاظت
	$remaining_calc = max(0, $total_fee - $paid_amount);
	if ($remaining !== $remaining_calc) $remaining = $remaining_calc;

	// due_date validate
	$dt = wp_datetime_from_string($due_date);
	if (!$dt) {
		wp_send_json_error(['msg' => __('غلط تاریخ۔','baba-academy')], 422);
	}

	// اسکرین شاٹ اپلوڈ (اختیاری)
	$shot_url = null;
	if ( isset($_FILES['screenshot']) && is_array($_FILES['screenshot']) && ! empty($_FILES['screenshot']['name']) ) {
		$file = $_FILES['screenshot'];
		if ( $file['error'] !== UPLOAD_ERR_OK ) {
			wp_send_json_error(['msg' => __('فائل اپلوڈ میں مسئلہ۔','baba-academy')], 422);
		}
		$size_ok = ( (int)$file['size'] <= (UPLOAD_MAX_MB * 1024 * 1024) );
		if ( ! $size_ok ) {
			wp_send_json_error(['msg' => sprintf(__('حد سے بڑی فائل۔ زیادہ سے زیادہ %dMB','baba-academy'), (int) UPLOAD_MAX_MB)], 422);
		}
		$ft = wp_check_filetype_and_ext( $file['tmp_name'], $file['name'], ['jpg|jpeg' => 'image/jpeg', 'png'=>'image/png', 'webp'=>'image/webp'] );
		$mime = isset($ft['type']) ? $ft['type'] : '';
		if ( ! in_array($mime, ['image/jpeg','image/png','image/webp'], true) ) {
			wp_send_json_error(['msg' => __('صرف JPEG/PNG/WEBP فائل منظور ہے۔','baba-academy')], 422);
		}
		require_once ABSPATH . 'wp-admin/includes/file.php';
		$overrides = ['test_form' => false, 'mimes' => ['jpg|jpeg'=>'image/jpeg','png'=>'image/png','webp'=>'image/webp']];
		$move = wp_handle_upload($file, $overrides);
		if (isset($move['error'])) {
			wp_send_json_error(['msg' => __('فائل محفوظ نہ ہوسکی۔','baba-academy')], 500);
		}
		$shot_url = esc_url_raw($move['url']);
	}

	$ok = $wpdb->insert($tbl['admissions'], [
		'student_name'    => $student_name,
		'phone'           => $phone,
		'notes'           => $notes,
		'course_id'       => $course_id,
		'total_fee'       => $total_fee,
		'paid_amount'     => $paid_amount,
		'remaining_amount'=> $remaining,
		'due_date'        => $dt->format('Y-m-d'),
		'screenshot_url'  => $shot_url,
		'status'          => ($remaining === 0 ? 1 : 0),
		'created_at'      => current_time('mysql'),
	], ['%s','%s','%s','%d','%d','%d','%d','%s','%s','%d','%s']);

	if (!$ok) {
		wp_send_json_error(['msg' => __('داخلہ محفوظ نہ ہوا۔','baba-academy')], 500);
	}

	wp_send_json_success(['msg' => __('داخلہ محفوظ ہوگیا۔','baba-academy')]);
}

/**
 * --------------------------------------------------------------------
 * AJAX: داخلہ — لسٹ (Dashboard) + فلٹرز + صفحہ بندی
 * --------------------------------------------------------------------
 */
add_action('wp_ajax_'.NS.'_list_admissions', __NAMESPACE__ . '\\ajax_list_admissions');
function ajax_list_admissions(){
	check_ajax_referer(NONCE_KEY, 'nonce');
	if ( ! current_user_can(CAP) ) wp_send_json_error(['msg' => __('اجازت نہیں','baba-academy')], 403);

	global $wpdb;
	$tbl = table_names();

	$page   = isset($_POST['page']) ? max(1, absint($_POST['page'])) : 1;
	$limit  = 10;
	$offset = ($page - 1) * $limit;

	$course = isset($_POST['course_id']) ? absint($_POST['course_id']) : 0;
	$status = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : '';

	$where  = 'WHERE 1=1';
	$args   = [];

	if ($course > 0) {
		$where .= ' AND a.course_id=%d';
		$args[] = $course;
	}
	if ($status !== '' ) {
		// -1 => overdue (due_date < today AND remaining>0)
		if ($status === '-1') {
			$where .= ' AND a.remaining_amount>0 AND a.due_date < %s';
			$args[] = (new \DateTimeImmutable('today', wp_timezone()))->format('Y-m-d');
		} else {
			$where .= ' AND a.status=%d';
			$args[] = absint($status);
		}
	}

	$sql_count = "SELECT COUNT(*) FROM {$tbl['admissions']} a {$where}";
	$total = (int) $wpdb->get_var($wpdb->prepare($sql_count, $args));

	$sql = "SELECT a.id, a.student_name, a.phone, a.total_fee, a.paid_amount, a.remaining_amount, a.due_date, a.status, a.screenshot_url,
		c.title AS course_title
		FROM {$tbl['admissions']} a
		LEFT JOIN {$tbl['courses']} c ON c.id=a.course_id
		{$where}
		ORDER BY a.id DESC
		LIMIT %d OFFSET %d";
	$args_p = array_merge($args, [$limit, $offset]);
	$rows = $wpdb->get_results($wpdb->prepare($sql, $args_p), ARRAY_A);

	$items = [];
	foreach ($rows as $r) {
		$days  = $r['due_date'] ? days_left_until($r['due_date']) : null;
		$items[] = [
			'id' => (int)$r['id'],
			'student_name' => esc_html($r['student_name']),
			'phone' => esc_html($r['phone']),
			'course' => esc_html($r['course_title'] ?: ''),
			'total_fee' => (int)$r['total_fee'],
			'paid_amount' => (int)$r['paid_amount'],
			'remaining_amount' => (int)$r['remaining_amount'],
			'due_date' => $r['due_date'] ?: null,
			'days_left' => $days,
			'status' => (int)$r['status'],
			'screenshot_url' => $r['screenshot_url'] ? esc_url_raw($r['screenshot_url']) : null,
		];
	}

	wp_send_json_success([
		'items' => $items,
		'page'  => $page,
		'total' => $total,
		'pages' => (int) ceil($total / $limit),
	]);
}

/**
 * --------------------------------------------------------------------
 * سیکیورٹی: کیپبیلٹی چیک بطور شارٹ ہیلپر (JS سے دکھانے/چھپانے میں مدد)
 * --------------------------------------------------------------------
 */
add_action('wp_ajax_'.NS.'_whoami', __NAMESPACE__ . '\\ajax_whoami');
function ajax_whoami(){
	check_ajax_referer(NONCE_KEY, 'nonce');
	wp_send_json_success([
		'can' => current_user_can(CAP)
	]);
}

/**
 * --------------------------------------------------------------------
 * i18n لوڈر (اختیاری)
 * --------------------------------------------------------------------
 */
add_action('plugins_loaded', __NAMESPACE__ . '\\load_textdomain');
function load_textdomain(){
	load_plugin_textdomain('baba-academy', false, dirname(plugin_basename(__FILE__)) . '/languages');
}

/** Part Fix — Optional Screenshot Logic
 * File: plugin-main.php
 * کہاں پیسٹ کریں: آخر میں
 * مقصد: اسکرین شاٹ نہ دینے پر بھی نیا داخلہ محفوظ ہو سکے
 */

add_action('init', function(){
    if (!function_exists('SSM\\BA\\ajax_create_admission')) return;
    // نوٹ: اصل function پہلے سے define ہے؛ صرف if-condition کا اپڈیٹ نیچے دکھایا جا رہا ہے
    // اسے مین کوڈ میں manual replace کریں:

    /*
    پرانی لائن:
    if ( isset($_FILES['screenshot']) && is_array($_FILES['screenshot']) && ! empty($_FILES['screenshot']['name']) ) {

    نئی لائن:
    */
    // if-condition کے آغاز میں replace کریں ⬇️
    // ✅ نیا ورژن:
    // if ( isset($_FILES['screenshot']) && is_array($_FILES['screenshot']) && ! empty($_FILES['screenshot']['name']) && $_FILES['screenshot']['error'] !== UPLOAD_ERR_NO_FILE ) {

    // اب اگر کوئی فائل اپلوڈ نہ کرے تو فارم نارمل طور پر محفوظ ہو جائے گا۔
});
