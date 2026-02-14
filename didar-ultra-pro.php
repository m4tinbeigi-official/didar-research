<?php
/*
Plugin Name: سیستم جامع تحقیقات بازار دیدار (نسخه حرفه‌ای)
Description: سامانه جمع‌آوری داده‌های میدانی با فرم چندمرحله‌ای، تقویم شمسی واقعی، خروجی/ورودی اکسل و داشبورد مدیریت پیشرفته.
Version: 3.5.0
Author: تیم فنی دیدار
Text Domain: didar-research
*/

if (!defined('ABSPATH')) exit;

// ==========================================
// ۱. تنظیمات اولیه و دیتابیس
// ==========================================
define('DR_TABLE_NAME', 'didar_research_data');

register_activation_hook(__FILE__, 'dr_plugin_activation');

function dr_plugin_activation() {
    global $wpdb;
    $table_name = $wpdb->prefix . DR_TABLE_NAME;
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        agent_name varchar(100) NOT NULL,
        shop_name varchar(200) NOT NULL,
        visit_date varchar(50) NOT NULL,
        location_gps varchar(100),
        photo_url text,
        full_data longtext,
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}

// ==========================================
// ۲. منوی مدیریت و داشبورد
// ==========================================
add_action('admin_menu', 'dr_add_admin_menu');
function dr_add_admin_menu() {
    add_menu_page(
        'تحقیقات بازار', 
        'گزارشات دیدار', 
        'manage_options', 
        'didar-reports', 
        'dr_render_dashboard', 
        'dashicons-chart-pie', 
        6
    );
}

function dr_render_dashboard() {
    global $wpdb;
    $table = $wpdb->prefix . DR_TABLE_NAME;
    
    // Pagination logic
    $per_page = 20;
    $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($page - 1) * $per_page;
    
    $total_items = $wpdb->get_var("SELECT COUNT(id) FROM $table");
    $total_pages = ceil($total_items / $per_page);
    
    $results = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table ORDER BY id DESC LIMIT %d OFFSET %d", $per_page, $offset));
    
    ?>
    <div class="wrap dr-admin-panel" dir="rtl">
        <h1 class="wp-heading-inline">📊 داشبورد مدیریت تحقیقات بازار</h1>
        
        <div class="dr-action-bar">
            <div class="dr-stats">
                <span>کل گزارش‌ها: <strong><?php echo number_format($total_items); ?></strong></span>
            </div>
            <div class="dr-buttons">
                <a href="<?php echo admin_url('admin-post.php?action=dr_export_csv'); ?>" class="button button-primary">📥 دانلود خروجی Excel</a>
                <button class="button button-secondary" onclick="document.getElementById('import-box').classList.toggle('hidden')">📤 ایمپورت اطلاعات</button>
            </div>
        </div>

        <div id="import-box" class="dr-import-box hidden">
            <form action="<?php echo admin_url('admin-post.php'); ?>" method="post" enctype="multipart/form-data">
                <input type="hidden" name="action" value="dr_import_csv">
                <?php wp_nonce_field('dr_import_nonce', 'dr_nonce'); ?>
                <p>فایل CSV را انتخاب کنید:</p>
                <input type="file" name="import_file" accept=".csv" required>
                <button type="submit" class="button button-primary">شروع درون‌ریزی</button>
            </form>
        </div>

        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th width="50">ID</th>
                    <th>نام نماینده</th>
                    <th>نام فروشگاه</th>
                    <th>تاریخ بازدید (شمسی)</th>
                    <th>لوکیشن</th>
                    <th>تصویر</th>
                    <th>عملیات</th>
                </tr>
            </thead>
            <tbody>
                <?php if($results): foreach($results as $row): ?>
                <tr>
                    <td><?php echo $row->id; ?></td>
                    <td><strong><?php echo esc_html($row->agent_name); ?></strong></td>
                    <td><?php echo esc_html($row->shop_name); ?></td>
                    <td><span class="dr-badge"><?php echo esc_html($row->visit_date); ?></span></td>
                    <td>
                        <?php if($row->location_gps): ?>
                            <a href="https://www.google.com/maps?q=<?php echo esc_attr($row->location_gps); ?>" target="_blank" class="dr-gps-link">📍 مشاهده</a>
                        <?php else: echo '-'; endif; ?>
                    </td>
                    <td>
                        <?php if($row->photo_url): ?>
                            <a href="<?php echo esc_url($row->photo_url); ?>" target="_blank">📷 تصویر</a>
                        <?php else: echo '-'; endif; ?>
                    </td>
                    <td>
                        <button class="button button-small" onclick='openDrModal(<?php echo json_encode($row->full_data); ?>)'>جزئیات کامل</button>
                    </td>
                </tr>
                <?php endforeach; else: ?>
                <tr><td colspan="7">هیچ داده‌ای یافت نشد.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <?php if($total_pages > 1): ?>
        <div class="tablenav bottom">
            <div class="tablenav-pages">
                <?php echo paginate_links(array(
                    'base' => add_query_arg('paged', '%#%'),
                    'format' => '',
                    'prev_text' => __('&laquo;'),
                    'next_text' => __('&raquo;'),
                    'total' => $total_pages,
                    'current' => $page
                )); ?>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <div id="drModal" class="dr-modal">
        <div class="dr-modal-content">
            <span class="dr-close" onclick="document.getElementById('drModal').style.display='none'">&times;</span>
            <h2>📋 ریز اطلاعات فرم</h2>
            <div id="drModalBody" class="dr-grid-view"></div>
        </div>
    </div>

    <style>
        .dr-admin-panel { font-family: Tahoma, sans-serif; }
        .dr-action-bar { display: flex; justify-content: space-between; align-items: center; background: #fff; padding: 15px; margin: 20px 0; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
        .dr-import-box { background: #e6f7ff; padding: 15px; border: 1px dashed #1890ff; margin-bottom: 20px; border-radius: 5px; }
        .hidden { display: none; }
        .dr-badge { background: #e6fffa; color: #006d75; padding: 3px 8px; border-radius: 4px; font-size: 11px; border: 1px solid #b5f5ec; }
        .dr-gps-link { text-decoration: none; color: #d32f2f; }
        
        /* Modal */
        .dr-modal { display: none; position: fixed; z-index: 9999; left: 0; top: 0; width: 100%; height: 100%; background-color: rgba(0,0,0,0.5); backdrop-filter: blur(2px); }
        .dr-modal-content { background-color: #fefefe; margin: 5% auto; padding: 25px; border: 1px solid #888; width: 70%; border-radius: 10px; direction: rtl; max-height: 80vh; overflow-y: auto; box-shadow: 0 10px 25px rgba(0,0,0,0.2); }
        .dr-close { float: left; font-size: 28px; font-weight: bold; cursor: pointer; color: #aaa; }
        .dr-close:hover { color: #000; }
        .dr-grid-view { display: grid; grid-template-columns: repeat(auto-fill, minmax(250px, 1fr)); gap: 15px; margin-top: 15px; }
        .dr-item { background: #f9f9f9; padding: 10px; border-radius: 5px; border-right: 3px solid #0073aa; font-size: 13px; }
        .dr-item strong { display: block; margin-bottom: 5px; color: #333; }
    </style>
    <script>
        function openDrModal(data) {
            let html = '';
            // Parse JSON if it's a string
            if(typeof data === 'string') {
                try { data = JSON.parse(data); } catch(e) { console.error(e); }
            }
            
            // Exclude internal fields
            const exclude = ['action', 'dr_nonce', '_wp_http_referer'];
            
            for (const [key, value] of Object.entries(data)) {
                if(!exclude.includes(key) && value) {
                    let val = Array.isArray(value) ? value.join('، ') : value;
                    html += `<div class="dr-item"><strong>${key.replace(/_/g, ' ')}:</strong> ${val}</div>`;
                }
            }
            document.getElementById('drModalBody').innerHTML = html;
            document.getElementById('drModal').style.display = "block";
        }
    </script>
    <?php
}

// ==========================================
// ۳. هندلر خروجی و ورودی (CSV)
// ==========================================
add_action('admin_post_dr_export_csv', 'dr_handle_export_csv');
function dr_handle_export_csv() {
    if (!current_user_can('manage_options')) wp_die('دسترسی غیرمجاز');
    
    global $wpdb;
    $table = $wpdb->prefix . DR_TABLE_NAME;
    $results = $wpdb->get_results("SELECT * FROM $table", ARRAY_A);
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=didar_report_'.date('Y-m-d').'.csv');
    
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF)); // UTF-8 BOM
    
    fputcsv($output, array('ID', 'Agent', 'Shop', 'Solar Date', 'GPS', 'Photo', 'Full JSON', 'Server Time'));
    
    foreach ($results as $row) {
        fputcsv($output, $row);
    }
    fclose($output);
    exit;
}

add_action('admin_post_dr_import_csv', 'dr_handle_import_csv');
function dr_handle_import_csv() {
    if (!current_user_can('manage_options')) wp_die('دسترسی غیرمجاز');
    check_admin_referer('dr_import_nonce', 'dr_nonce');
    
    if(!empty($_FILES['import_file']['tmp_name'])) {
        global $wpdb;
        $table = $wpdb->prefix . DR_TABLE_NAME;
        $file = fopen($_FILES['import_file']['tmp_name'], 'r');
        
        // Skip BOM if exists
        $bom = fread($file, 3);
        if ($bom != "\xEF\xBB\xBF") rewind($file);
        
        fgetcsv($file); // Skip Header
        
        while (($data = fgetcsv($file)) !== FALSE) {
            if(count($data) >= 7) {
                $wpdb->insert($table, [
                    'agent_name' => sanitize_text_field($data[1]),
                    'shop_name'  => sanitize_text_field($data[2]),
                    'visit_date' => sanitize_text_field($data[3]),
                    'location_gps' => sanitize_text_field($data[4]),
                    'photo_url'  => esc_url_raw($data[5]),
                    'full_data'  => $data[6], // JSON string
                    'created_at' => current_time('mysql')
                ]);
            }
        }
        fclose($file);
    }
    wp_redirect(admin_url('admin.php?page=didar-reports'));
    exit;
}

// ==========================================
// ۴. شورت‌کد فرم (Frontend)
// ==========================================
add_shortcode('didar_research_form', 'dr_frontend_form');
function dr_frontend_form() {
    wp_enqueue_style('dr-font', 'https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css');
    wp_enqueue_style('dr-persian-datepicker', 'https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/css/persian-datepicker.min.css', array(), '1.2.0');

    wp_register_script('dr-persian-date', 'https://cdn.jsdelivr.net/npm/persian-date@0.1.8/dist/persian-date.min.js', array('jquery'), '0.1.8', false);
    wp_register_script('dr-persian-datepicker', 'https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/js/persian-datepicker.min.js', array('jquery', 'dr-persian-date'), '1.2.0', false);
    wp_enqueue_script('dr-persian-date');
    wp_enqueue_script('dr-persian-datepicker');

    $dr_persiandate_shim_js = <<<'JS'
(function(w, $) {
    if (!w || !$ || !w.persianDate) return;
    if (typeof w.persianDate.extend !== 'function' && typeof $.extend === 'function') {
        w.persianDate.extend = $.extend;
    }
})(window, window.jQuery);
JS;
    wp_add_inline_script('dr-persian-datepicker', $dr_persiandate_shim_js, 'before');

    $dr_datepicker_init_js = <<<'JS'
jQuery(function($) {
    const $dateInput = $('#p_date_input');
    if (!$dateInput.length) return;
    if (typeof $.fn.persianDatepicker === 'function' && typeof window.persianDate !== 'undefined') {
        try {
            $dateInput.persianDatepicker({
                format: 'YYYY/MM/DD',
                initialValue: true,
                autoClose: true
            });
        } catch (e) {
            $dateInput.prop('readonly', false).attr('placeholder', '1403/01/01');
        }
    } else {
        $dateInput.prop('readonly', false).attr('placeholder', '1403/01/01');
    }
});
JS;
    wp_add_inline_script('dr-persian-datepicker', $dr_datepicker_init_js, 'after');

    ob_start(); ?>

    <style>
        .dr-wrapper { font-family: 'Vazirmatn', sans-serif; direction: rtl; background: #fff; max-width: 1100px; margin: 30px auto; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); overflow: hidden; }
        .dr-head { background: #08084A; color: #fff; padding: 20px 30px; text-align: center; }
        .dr-head h2 { color: #fff; margin: 0; }
        .dr-content { padding: 25px; }
        .dr-section { margin-bottom: 22px; border: 1px solid #eee; border-radius: 10px; padding: 16px; background: #fcfcff; }
        .dr-section h3 { margin: 0 0 12px; color: #08084A; font-size: 1.05rem; }
        .dr-group { margin-bottom: 12px; }
        .dr-label { display: block; margin-bottom: 6px; font-weight: 700; color: #333; }
        .dr-help { font-size: 12px; color: #666; margin-top: 4px; }
        .dr-input, .dr-select, .dr-textarea { width: 100%; box-sizing: border-box; padding: 10px; border: 1px solid #ccc; border-radius: 8px; font-family: 'Vazirmatn'; }
        .dr-textarea { min-height: 90px; }
        .dr-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
        .dr-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 12px; }
        .dr-check-group { display: flex; flex-wrap: wrap; gap: 10px; }
        .dr-check-group label { background: #f0f2f7; padding: 6px 10px; border-radius: 18px; display: inline-flex; align-items: center; gap: 6px; }
        .dr-btn-submit { width: 100%; padding: 12px; background: #00a651; border: none; border-radius: 8px; color: #fff; font-weight: 700; cursor: pointer; }
        .dr-btn-submit:disabled { opacity: 0.7; }
        @media (max-width: 800px) { .dr-grid-2, .dr-grid-3 { grid-template-columns: 1fr; } }
    </style>

    <div class="dr-wrapper">
        <div class="dr-head"><h2>فرم جامع بازدید تحقیقات بازار دیدار</h2></div>
        <div class="dr-content">
            <form id="drForm">
                <input type="hidden" name="sys_gps" id="sys_gps">
                <?php wp_nonce_field('dr_submit_form', 'dr_nonce'); ?>

                <div class="dr-section">
                    <h3>مشخصات بازدید</h3>
                    <div class="dr-grid-3">
                        <div class="dr-group"><label class="dr-label">بازدیدکننده *</label><input type="text" name="agent_name" class="dr-input" required></div>
                        <div class="dr-group"><label class="dr-label">تاریخ (شمسی) *</label><input type="text" name="visit_date" id="p_date_input" class="dr-input" readonly required></div>
                        <div class="dr-group"><label class="dr-label">ساعت *</label><input type="time" name="visit_time" class="dr-input" required></div>
                    </div>
                    <div class="dr-grid-2">
                        <div class="dr-group"><label class="dr-label">شهر / استان *</label><input type="text" name="city_province" class="dr-input" required></div>
                        <div class="dr-group"><label class="dr-label">منطقه *</label>
                            <div class="dr-check-group">
                                <label><input type="radio" name="area_type" value="بازار اصلی" required>بازار اصلی</label>
                                <label><input type="radio" name="area_type" value="بالاشهر">بالاشهر</label>
                                <label><input type="radio" name="area_type" value="سایر پاساژها">سایر پاساژها</label>
                                <label><input type="radio" name="area_type" value="سایر مناطق شهر">سایر مناطق شهر</label>
                            </div>
                        </div>
                    </div>
                    <div class="dr-grid-2">
                        <div class="dr-group"><label class="dr-label">بازار/پاساژ/خیابان *</label><input type="text" name="market_passage_street" class="dr-input" required></div>
                        <div class="dr-group"><label class="dr-label">طبقه / راهرو / پلاک</label><input type="text" name="floor_corridor_plaque" class="dr-input"></div>
                    </div>
                    <div class="dr-grid-3">
                        <div class="dr-group"><label class="dr-label">نام فروشگاه *</label><input type="text" name="shop_name" class="dr-input" required></div>
                        <div class="dr-group"><label class="dr-label">نام مالک/مدیر</label><input type="text" name="owner_manager_name" class="dr-input"></div>
                        <div class="dr-group"><label class="dr-label">شماره تماس</label><input type="text" name="phone_number" class="dr-input"></div>
                    </div>
                    <div class="dr-grid-2">
                        <div class="dr-group"><label class="dr-label">لوکیشن/نزدیک به</label><input type="text" name="location_nearby" class="dr-input" placeholder="مثلاً ورودی، پله‌برقی، سرراهرو"></div>
                        <div class="dr-group"><label class="dr-label">عکس تابلو/ویترین ثبت شد؟</label>
                            <div class="dr-check-group">
                                <label><input type="radio" name="photo_registered" value="بله">بله</label>
                                <label><input type="radio" name="photo_registered" value="خیر">خیر</label>
                            </div>
                        </div>
                    </div>
                    <div class="dr-group"><label class="dr-label">همکاری قبلی با برند بهشتی</label>
                        <div class="dr-check-group">
                            <label><input type="radio" name="previous_coop" value="همکاری مثبت">همکاری مثبت</label>
                            <label><input type="radio" name="previous_coop" value="همکاری">همکاری</label>
                            <label><input type="radio" name="previous_coop" value="همکاری نداشته">همکاری نداشته</label>
                            <label><input type="radio" name="previous_coop" value="آشنایی ندارد">آشنایی ندارد</label>
                        </div>
                    </div>
                </div>

                <div class="dr-section">
                    <h3>1) موقعیت و تیپ فروشگاه</h3>
                    <div class="dr-group"><label class="dr-label">1-الف) جایگاه در مسیر بازار</label><div class="dr-check-group">
                        <label><input type="checkbox" name="route_position[]" value="کریدور اصلی">کریدور اصلی</label>
                        <label><input type="checkbox" name="route_position[]" value="راهروی فرعی">راهروی فرعی</label>
                        <label><input type="checkbox" name="route_position[]" value="بن‌بست/گوشه">بن‌بست/گوشه</label>
                        <label><input type="checkbox" name="route_position[]" value="طبقه بالا">طبقه بالا</label>
                        <label><input type="checkbox" name="route_position[]" value="نزدیک ورودی">نزدیک ورودی</label>
                        <label><input type="checkbox" name="route_position[]" value="نزدیک پله‌برقی/پله">نزدیک پله‌برقی/پله</label>
                        <label><input type="checkbox" name="route_position[]" value="نبش/دوبر">نبش/دوبر</label>
                        <label><input type="checkbox" name="route_position[]" value="داخل پاساژ">داخل پاساژ</label>
                    </div></div>
                    <div class="dr-group"><label class="dr-label">1-ب) کلاس ظاهری (1 تا 5)</label><div class="dr-check-group">
                        <label><input type="radio" name="appearance_class" value="1">1</label><label><input type="radio" name="appearance_class" value="2">2</label><label><input type="radio" name="appearance_class" value="3">3</label><label><input type="radio" name="appearance_class" value="4">4</label><label><input type="radio" name="appearance_class" value="5">5</label>
                    </div></div>
                    <div class="dr-grid-3">
                        <div class="dr-group"><label class="dr-label">عرض دهنه (متر)</label><input type="number" step="0.1" name="shop_width_meter" class="dr-input"></div>
                        <div class="dr-group"><label class="dr-label">عمق (متر)</label><input type="number" step="0.1" name="shop_depth_meter" class="dr-input"></div>
                        <div class="dr-group"><label class="dr-label">مساحت تقریبی (مترمربع)</label><input type="number" step="0.1" name="shop_area_sqm" class="dr-input"></div>
                    </div>
                    <div class="dr-grid-3">
                        <div class="dr-group"><label class="dr-label">تعداد ویترین‌ها</label><input type="number" name="vitrine_count" class="dr-input"></div>
                        <div class="dr-group"><label class="dr-label">طول کل ویترین نمایشی (متر)</label><input type="number" step="0.1" name="vitrine_total_length" class="dr-input"></div>
                        <div class="dr-group"><label class="dr-label">طبقات و ردیف مؤثر نمایش</label><select name="display_rows" class="dr-select"><option value="">انتخاب</option><option>1</option><option>2</option><option>3</option><option>4+</option></select></div>
                    </div>
                    <div class="dr-grid-2">
                        <div class="dr-group"><label class="dr-label">کیفیت نورپردازی</label><select name="lighting_quality" class="dr-select"><option value="">انتخاب</option><option>ضعیف</option><option>متوسط</option><option>عالی</option></select></div>
                        <div class="dr-group"><label class="dr-label">فضای مشاوره</label><select name="consultation_space" class="dr-select"><option value="">انتخاب</option><option>ندارد</option><option>1 ست</option><option>2 ست</option><option>3+</option></select></div>
                    </div>
                </div>

                <div class="dr-section">
                    <h3>2) ظرفیت عملیاتی و نیروی انسانی</h3>
                    <div class="dr-grid-3">
                        <div class="dr-group"><label class="dr-label">تعداد افراد حاضر</label><input type="number" name="present_people_count" class="dr-input"></div>
                        <div class="dr-group"><label class="dr-label">تعداد فروشنده</label><input type="number" name="seller_count" class="dr-input"></div>
                        <div class="dr-group"><label class="dr-label">صندوق/حسابدار</label><input type="number" name="cashier_count" class="dr-input"></div>
                    </div>
                    <div class="dr-grid-3">
                        <div class="dr-group"><label class="dr-label">مدیریت/مالک حاضر بود؟</label><div class="dr-check-group"><label><input type="radio" name="manager_present" value="بله">بله</label><label><input type="radio" name="manager_present" value="خیر">خیر</label></div></div>
                        <div class="dr-group"><label class="dr-label">ریتم کار لحظه‌ای</label><select name="work_rhythm" class="dr-select"><option value="">انتخاب</option><option>خلوت</option><option>معمولی</option><option>شلوغ</option></select></div>
                        <div class="dr-group"><label class="dr-label">سرعت سرویس‌دهی</label><select name="service_speed" class="dr-select"><option value="">انتخاب</option><option>سریع</option><option>متوسط</option><option>کند</option></select></div>
                    </div>
                </div>

                <div class="dr-section">
                    <h3>3) ترافیک و پتانسیل فروش</h3>
                    <div class="dr-grid-2">
                        <div class="dr-group"><label class="dr-label">عبوری جلوی فروشگاه در ۳ دقیقه</label><input type="number" name="passers_3min" class="dr-input"></div>
                        <div class="dr-group"><label class="dr-label">وارد شده به فروشگاه در ۳ دقیقه</label><input type="number" name="entrants_3min" class="dr-input"></div>
                    </div>
                    <div class="dr-grid-2">
                        <div class="dr-group"><label class="dr-label">جذابیت ویترین (1 تا 5)</label><div class="dr-check-group"><label><input type="radio" name="vitrine_attraction" value="1">1</label><label><input type="radio" name="vitrine_attraction" value="2">2</label><label><input type="radio" name="vitrine_attraction" value="3">3</label><label><input type="radio" name="vitrine_attraction" value="4">4</label><label><input type="radio" name="vitrine_attraction" value="5">5</label></div></div>
                        <div class="dr-group"><label class="dr-label">احتمال تبدیل به خرید</label><div class="dr-check-group"><label><input type="radio" name="conversion_probability" value="کم">کم</label><label><input type="radio" name="conversion_probability" value="متوسط">متوسط</label><label><input type="radio" name="conversion_probability" value="زیاد">زیاد</label></div></div>
                    </div>
                </div>

                <div class="dr-section">
                    <h3>4) موجودی ویترین و تخمین وزن</h3>
                    <div class="dr-grid-3">
                        <div class="dr-group"><label class="dr-label">تراکم چیدمان</label><select name="vitrine_density" class="dr-select"><option value="">انتخاب</option><option>کم‌چین</option><option>متوسط</option><option>پرچین</option><option>خیلی پرچین</option></select></div>
                        <div class="dr-group"><label class="dr-label">تخمین وزن کل ویترین</label><select name="vitrine_weight_estimation" class="dr-select"><option value="">انتخاب</option><option>زیر 1kg</option><option>1–3kg</option><option>3–7kg</option><option>7–15kg</option><option>15–30kg</option><option>30–50kg</option><option>50–80kg</option><option>80kg+</option></select></div>
                        <div class="dr-group"><label class="dr-label">تیپ طراحی غالب</label><select name="design_style" class="dr-select"><option value="">انتخاب</option><option>قدیمی و کلاسیک</option><option>مدرن و به‌روز</option><option>عربی (طرح خلیجی)</option><option>محصولات خارجی (ترک و ایتالیایی)</option><option>ترکیبی</option></select></div>
                    </div>
                    <div class="dr-grid-2">
                        <div class="dr-group"><label class="dr-label">رنگ غالب ویترین</label><div class="dr-check-group"><label><input type="checkbox" name="vitrine_color[]" value="زرد">زرد</label><label><input type="checkbox" name="vitrine_color[]" value="رزگلد">رزگلد</label><label><input type="checkbox" name="vitrine_color[]" value="سفید">سفید</label><label><input type="checkbox" name="vitrine_color[]" value="دو رنگ">دو رنگ</label><label><input type="checkbox" name="vitrine_color[]" value="ترکیبی">ترکیبی</label></div></div>
                        <div class="dr-group"><label class="dr-label">ترکیب دسته‌های محصول (۰ تا ۵)</label><textarea name="product_mix_scores" class="dr-textarea" placeholder="مثال: انگشتر=4، گوشواره=3، سرویس=1 ..."></textarea></div>
                    </div>
                    <div class="dr-group"><label class="dr-label">وضعیت بنکداران/تولیدکنندگان شاخص در ویترین</label><textarea name="producers_status" class="dr-textarea" placeholder="نام بنکداری/تولیدی + محصول"></textarea></div>
                </div>

                <div class="dr-section">
                    <h3>5) هویت/برند و کیفیت ارائه</h3>
                    <div class="dr-grid-3">
                        <div class="dr-group"><label class="dr-label">برندها روی ویترین مشخص‌اند؟</label><select name="brands_visible" class="dr-select"><option value="">انتخاب</option><option>بله</option><option>خیر</option><option>تا حدی</option></select></div>
                        <div class="dr-group"><label class="dr-label">نوع تگ/لیبل قیمت</label><select name="price_tag_type" class="dr-select"><option value="">انتخاب</option><option>ساده</option><option>شکیل</option><option>لوگو/برنددار</option><option>نامشخص</option></select></div>
                        <div class="dr-group"><label class="dr-label">بسته‌بندی قابل مشاهده</label><select name="packaging_visibility" class="dr-select"><option value="">انتخاب</option><option>ندارد</option><option>معمولی</option><option>لوکس</option><option>برنددار</option></select></div>
                    </div>
                    <div class="dr-group"><label class="dr-label">نشانه‌های اعتماد</label><div class="dr-check-group">
                        <label><input type="checkbox" name="trust_signs[]" value="مجوز/پروانه قابل مشاهده">مجوز/پروانه قابل مشاهده</label>
                        <label><input type="checkbox" name="trust_signs[]" value="ترازوی مشخص">ترازوی مشخص</label>
                        <label><input type="checkbox" name="trust_signs[]" value="دوربین">دوربین</label>
                        <label><input type="checkbox" name="trust_signs[]" value="فضای VIP/اتاقک">فضای VIP/اتاقک</label>
                        <label><input type="checkbox" name="trust_signs[]" value="نمایش ضمانت/گارانتی">نمایش ضمانت/گارانتی</label>
                        <label><input type="checkbox" name="trust_signs[]" value="نظم و تمیزی بالا">نظم و تمیزی بالا</label>
                    </div></div>
                </div>

                <div class="dr-section">
                    <h3>6) قیمت‌گذاری و اجرت</h3>
                    <div class="dr-grid-3">
                        <div class="dr-group"><label class="dr-label">روی بعضی کارها اجرت/قیمت درج شده؟</label><div class="dr-check-group"><label><input type="radio" name="wage_marked" value="بله">بله</label><label><input type="radio" name="wage_marked" value="خیر">خیر</label></div></div>
                        <div class="dr-group"><label class="dr-label">سطح اجرت متوسط</label><select name="wage_level" class="dr-select"><option value="">انتخاب</option><option>پایین (کمتر از 5)</option><option>متوسط (کمتر از 10)</option><option>بالا (10 و بیشتر)</option></select></div>
                        <div class="dr-group"><label class="dr-label">مدل تسویه</label><select name="settlement_model" class="dr-select"><option value="">انتخاب</option><option>نقدی</option><option>اعتباری</option><option>ترکیبی</option><option>سایر</option></select></div>
                    </div>
                </div>

                <div class="dr-section">
                    <h3>7) رقابت اطراف و همسایگی</h3>
                    <div class="dr-grid-3">
                        <div class="dr-group"><label class="dr-label">تعداد طلافروشی در شعاع ۳۰ متر</label><input type="number" name="nearby_goldshops_30m" class="dr-input"></div>
                        <div class="dr-group"><label class="dr-label">سطح رقابت</label><select name="competition_level" class="dr-select"><option value="">انتخاب</option><option>کم</option><option>متوسط</option><option>زیاد</option></select></div>
                        <div class="dr-group"><label class="dr-label">این فروشگاه نسبت به اطراف</label><select name="relative_strength" class="dr-select"><option value="">انتخاب</option><option>ضعیف‌تر</option><option>مشابه</option><option>قوی‌تر</option></select></div>
                    </div>
                    <div class="dr-group"><label class="dr-label">همسایه‌های مهم</label><div class="dr-check-group">
                        <label><input type="checkbox" name="key_neighbors[]" value="بنکدار/عمده‌فروش">بنکدار/عمده‌فروش</label>
                        <label><input type="checkbox" name="key_neighbors[]" value="تعمیرکار/کارگاه">تعمیرکار/کارگاه</label>
                        <label><input type="checkbox" name="key_neighbors[]" value="صرافی">صرافی</label>
                        <label><input type="checkbox" name="key_neighbors[]" value="پاساژ لوکس">پاساژ لوکس</label>
                        <label><input type="checkbox" name="key_neighbors[]" value="خرده‌فروش معمولی">خرده‌فروش معمولی</label>
                    </div></div>
                </div>

                <div class="dr-section">
                    <h3>8) ارزیابی همکاری از نگاه نماینده</h3>
                    <div class="dr-group"><label class="dr-label">شاخص‌های مثبت</label><div class="dr-check-group">
                        <label><input type="checkbox" name="positive_indicators[]" value="ویترین پر و متنوع">ویترین پر و متنوع</label>
                        <label><input type="checkbox" name="positive_indicators[]" value="ترافیک خوب">ترافیک خوب</label>
                        <label><input type="checkbox" name="positive_indicators[]" value="ظاهر حرفه‌ای و منظم">ظاهر حرفه‌ای و منظم</label>
                        <label><input type="checkbox" name="positive_indicators[]" value="نشانه‌های مشتری لوکس">نشانه‌های مشتری لوکس</label>
                        <label><input type="checkbox" name="positive_indicators[]" value="فروشنده فعال و خوش‌برخورد">فروشنده فعال و خوش‌برخورد</label>
                        <label><input type="checkbox" name="positive_indicators[]" value="چیدمان مطابق ترند روز">چیدمان مطابق ترند روز</label>
                    </div></div>
                    <div class="dr-group"><label class="dr-label">شاخص‌های ریسک</label><div class="dr-check-group">
                        <label><input type="checkbox" name="risk_indicators[]" value="خلوت و کم‌فروش">خلوت و کم‌فروش</label>
                        <label><input type="checkbox" name="risk_indicators[]" value="ویترین خالی/کم‌چین">ویترین خالی/کم‌چین</label>
                        <label><input type="checkbox" name="risk_indicators[]" value="تمرکز شدید روی کارهای خیلی ارزان">تمرکز شدید روی کارهای خیلی ارزان</label>
                        <label><input type="checkbox" name="risk_indicators[]" value="بی‌نظمی/کاهش اعتماد">بی‌نظمی/کاهش اعتماد</label>
                        <label><input type="checkbox" name="risk_indicators[]" value="حساسیت به عکاسی/سؤال">حساسیت به عکاسی/سؤال</label>
                        <label><input type="checkbox" name="risk_indicators[]" value="ریسک اعتباری محتمل">ریسک اعتباری محتمل</label>
                    </div></div>
                </div>

                <div class="dr-section">
                    <h3>9) سوالات مربوط به محصولات طلای دیدار</h3>
                    <div class="dr-grid-3">
                        <div class="dr-group"><label class="dr-label">وزن‌های پرفروش</label><select name="best_selling_weight" class="dr-select"><option value="">انتخاب</option><option>سبک</option><option>متوسط</option><option>سنگین</option><option>ترکیبی</option></select></div>
                        <div class="dr-group"><label class="dr-label">سبک محصول موردنیاز</label><select name="needed_style" class="dr-select"><option value="">انتخاب</option><option>کلاسیک</option><option>مدرن</option><option>عربی (خلیجی)</option><option>سایر</option></select></div>
                        <div class="dr-group"><label class="dr-label">تطابق محصولات دیدار با ویترین (1-5)</label><select name="didar_fit_score" class="dr-select"><option value="">انتخاب</option><option>1</option><option>2</option><option>3</option><option>4</option><option>5</option></select></div>
                    </div>
                    <div class="dr-grid-2">
                        <div class="dr-group"><label class="dr-label">نظر درباره اجرت محصولات (1-5)</label><select name="wage_satisfaction" class="dr-select"><option value="">انتخاب</option><option>1</option><option>2</option><option>3</option><option>4</option><option>5</option></select></div>
                        <div class="dr-group"><label class="dr-label">نظر کلی درباره محصولات دیدار (1-5)</label><select name="products_satisfaction" class="dr-select"><option value="">انتخاب</option><option>1</option><option>2</option><option>3</option><option>4</option><option>5</option></select></div>
                    </div>
                    <div class="dr-group"><label class="dr-label">محصولات پیشنهادی نماینده برای بازدید بعدی</label><textarea name="suggested_products_next_visit" class="dr-textarea"></textarea></div>
                </div>

                <div class="dr-section">
                    <h3>10) جمع‌بندی و اقدام بعدی</h3>
                    <div class="dr-grid-2">
                        <div class="dr-group"><label class="dr-label">امتیاز کلی همکاری (1 تا 10)</label><select name="total_score" class="dr-select"><option value="">انتخاب</option><option>1</option><option>2</option><option>3</option><option>4</option><option>5</option><option>6</option><option>7</option><option>8</option><option>9</option><option>10</option></select></div>
                        <div class="dr-group"><label class="dr-label">پیشنهاد اقدام بعدی</label><div class="dr-check-group">
                            <label><input type="checkbox" name="next_action[]" value="A) هدف فوری برای معرفی دیدار">A) هدف فوری برای معرفی دیدار</label>
                            <label><input type="checkbox" name="next_action[]" value="B) بازدید مجدد در زمانی دیگر">B) بازدید مجدد در زمانی دیگر</label>
                            <label><input type="checkbox" name="next_action[]" value="C) ارسال کاتالوگ/گالری محصول">C) ارسال کاتالوگ/گالری محصول</label>
                            <label><input type="checkbox" name="next_action[]" value="D) نیاز به تماس از سوی امور مشتریان">D) نیاز به تماس از سوی امور مشتریان</label>
                            <label><input type="checkbox" name="next_action[]" value="E) فعلاً کنار گذاشته شود">E) فعلاً کنار گذاشته شود</label>
                            <label><input type="checkbox" name="next_action[]" value="F) نیاز به ارجاع به سرپرست/مدیر فروش">F) نیاز به ارجاع به سرپرست/مدیر فروش</label>
                        </div></div>
                    </div>
                    <div class="dr-group"><label class="dr-label">یادداشت نماینده</label><textarea name="private_note" class="dr-textarea" placeholder="محصولات شاخص، رفتار فروشنده، خدمات طلای دیدار، نکته امنیتی و ..."></textarea></div>
                    <div class="dr-group" style="border: 2px dashed #ccc; padding: 14px; border-radius: 8px;">
                        <label class="dr-label">آپلود عکس فروشگاه/ویترین</label>
                        <input type="file" name="shop_image" accept="image/*">
                    </div>
                </div>

                <button type="submit" class="dr-btn-submit" id="finalSubmit">ثبت نهایی گزارش ✅</button>
            </form>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(position) {
                $('#sys_gps').val(position.coords.latitude + "," + position.coords.longitude);
            });
        }

        $('#drForm').on('submit', function(e) {
            e.preventDefault();

            const requiredFields = this.querySelectorAll('[required]');
            for (const field of requiredFields) {
                if (!field.value) {
                    alert('لطفا فیلدهای ضروری را کامل کنید.');
                    field.focus();
                    return;
                }
            }

            let btn = $('#finalSubmit');
            btn.text('در حال آپلود...').prop('disabled', true);

            let formData = new FormData(this);
            formData.append('action', 'dr_submit_ajax');

            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: formData,
                contentType: false,
                processData: false,
                success: function(response) {
                    if(response.success) {
                        alert('✅ ' + response.data);
                        location.reload();
                    } else {
                        alert('❌ خطا: ' + response.data);
                        btn.text('تلاش مجدد').prop('disabled', false);
                    }
                },
                error: function() {
                    alert('خطای ارتباط با سرور');
                    btn.text('ثبت نهایی گزارش ✅').prop('disabled', false);
                }
            });
        });
    });
    </script>
    <?php
    return ob_get_clean();
}

// ==========================================
// ۵. پردازش فرم (AJAX Handler)
// ==========================================
add_action('wp_ajax_dr_submit_ajax', 'dr_process_submission');
add_action('wp_ajax_nopriv_dr_submit_ajax', 'dr_process_submission');

function dr_process_submission() {
    check_ajax_referer('dr_submit_form', 'dr_nonce');
    
    global $wpdb;
    $table = $wpdb->prefix . DR_TABLE_NAME;
    
    // Handle File Upload
    $photo_url = '';
    if (!empty($_FILES['shop_image']['name'])) {
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        $uploaded = wp_handle_upload($_FILES['shop_image'], array('test_form' => false));
        if ($uploaded && !isset($uploaded['error'])) {
            $photo_url = $uploaded['url'];
        }
    }

    // Prepare JSON Data (Exclude GPS and File from JSON as they have columns)
    $exclude_keys = ['action', 'dr_nonce', 'sys_gps', 'shop_image'];
    $json_data = array();
    foreach ($_POST as $key => $value) {
        if (!in_array($key, $exclude_keys)) {
            $json_data[$key] = is_array($value) ? array_map('sanitize_text_field', $value) : sanitize_text_field($value);
        }
    }

    $result = $wpdb->insert($table, [
        'agent_name' => sanitize_text_field($_POST['agent_name']),
        'shop_name'  => sanitize_text_field($_POST['shop_name']),
        'visit_date' => sanitize_text_field($_POST['visit_date']), // This comes from JS Datepicker (Solar)
        'location_gps' => sanitize_text_field($_POST['sys_gps']),
        'photo_url'  => $photo_url,
        'full_data'  => json_encode($json_data, JSON_UNESCAPED_UNICODE)
    ]);

    if ($result) {
        wp_send_json_success('گزارش با موفقیت ثبت شد.');
    } else {
        wp_send_json_error('خطا در ذخیره دیتابیس.');
    }
}
