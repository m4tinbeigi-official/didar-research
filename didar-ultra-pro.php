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
    // Enqueue Assets for Datepicker
    wp_enqueue_style('dr-font', 'https://cdn.jsdelivr.net/gh/rastikerdar/vazirmatn@v33.003/Vazirmatn-font-face.css');
    wp_enqueue_style('dr-persian-datepicker', 'https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/css/persian-datepicker.min.css', array(), '1.2.0');

    // Load in document <head> to ensure the shortcode inline script can initialize safely.
    wp_register_script('dr-persian-date', 'https://cdn.jsdelivr.net/npm/persian-date@0.1.8/dist/persian-date.min.js', array('jquery'), '0.1.8', false);
    wp_register_script('dr-persian-datepicker', 'https://cdn.jsdelivr.net/npm/persian-datepicker@1.2.0/dist/js/persian-datepicker.min.js', array('jquery', 'dr-persian-date'), '1.2.0', false);
    wp_enqueue_script('dr-persian-date');
    wp_enqueue_script('dr-persian-datepicker');

    $dr_persiandate_shim_js = <<<'JS'
(function(w, $) {
    if (!w || !$ || !w.persianDate) {
        return;
    }

    if (typeof w.persianDate.extend !== 'function' && typeof $.extend === 'function') {
        w.persianDate.extend = $.extend;
    }
})(window, window.jQuery);
JS;
    wp_add_inline_script('dr-persian-datepicker', $dr_persiandate_shim_js, 'before');

    $dr_datepicker_init_js = <<<'JS'
jQuery(function($) {
    const $dateInput = $('#p_date_input');
    if (!$dateInput.length) {
        return;
    }

    if (typeof $.fn.persianDatepicker === 'function' && typeof window.persianDate !== 'undefined') {
        try {
            $dateInput.persianDatepicker({
                format: 'YYYY/MM/DD',
                initialValue: true,
                autoClose: true
            });
        } catch (e) {
            $dateInput.prop('readonly', false).attr('placeholder', '1403/01/01');
            console.error('Datepicker initialization failed. Falling back to manual input.', e);
        }
    } else {
        $dateInput.prop('readonly', false).attr('placeholder', '1403/01/01');
        console.error('persianDatepicker is not loaded. Falling back to manual input.');
    }
});
JS;
    wp_add_inline_script('dr-persian-datepicker', $dr_datepicker_init_js, 'after');

    ob_start(); ?>
    
    <style>
        :root { --dr-primary: #08084A; --dr-accent: #00a651; --dr-bg: #f5f7fa; }
        .dr-wrapper { font-family: 'Vazirmatn', sans-serif; direction: rtl; background: #fff; max-width: 900px; margin: 30px auto; border-radius: 12px; box-shadow: 0 10px 30px rgba(0,0,0,0.08); overflow: hidden; }
        .dr-head { background: var(--dr-primary); color: #fff; padding: 20px 30px; text-align: center; }
        .dr-head h2 { color: #fff; margin: 0; font-size: 1.5rem; }
        
        /* Steps */
        .dr-progress { display: flex; justify-content: space-between; padding: 20px 40px; background: #fafafa; border-bottom: 1px solid #eee; }
        .dr-step-dot { width: 12px; height: 12px; background: #ddd; border-radius: 50%; transition: 0.3s; position: relative; }
        .dr-step-dot.active { background: var(--dr-accent); transform: scale(1.3); }
        .dr-step-dot.completed { background: var(--dr-primary); }
        
        .dr-content { padding: 30px; }
        .dr-step-section { display: none; animation: fadeIn 0.5s ease; }
        .dr-step-section.active { display: block; }
        
        /* Inputs */
        .dr-group { margin-bottom: 20px; }
        .dr-label { display: block; margin-bottom: 8px; font-weight: bold; font-size: 0.9rem; color: #444; }
        .dr-input, .dr-select, .dr-textarea { width: 100%; padding: 12px; border: 1px solid #ccc; border-radius: 8px; font-family: 'Vazirmatn'; box-sizing: border-box; transition: 0.2s; }
        .dr-input:focus { border-color: var(--dr-primary); outline: none; box-shadow: 0 0 0 3px rgba(8,8,74,0.1); }
        
        /* Grid Layout */
        .dr-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; }
        .dr-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 15px; }
        
        /* Radio/Check Buttons */
        .dr-check-group { display: flex; flex-wrap: wrap; gap: 10px; }
        .dr-check-label { background: #f0f2f5; padding: 8px 16px; border-radius: 20px; cursor: pointer; border: 1px solid transparent; transition: 0.2s; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; }
        .dr-check-label:hover { background: #e1e4e8; }
        .dr-check-label input:checked + span { font-weight: bold; }
        input[type="radio"]:checked + .dr-check-label, input[type="radio"]:checked { accent-color: var(--dr-accent); }
        
        /* Buttons */
        .dr-btns { display: flex; gap: 15px; margin-top: 30px; }
        .dr-btn { padding: 12px 25px; border: none; border-radius: 8px; cursor: pointer; font-family: 'Vazirmatn'; font-weight: bold; font-size: 1rem; flex: 1; transition: 0.3s; }
        .dr-btn-next { background: var(--dr-primary); color: #fff; }
        .dr-btn-prev { background: #e0e0e0; color: #333; }
        .dr-btn-submit { background: var(--dr-accent); color: #fff; }
        .dr-btn:hover { opacity: 0.9; transform: translateY(-2px); }
        
        @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
        @media (max-width: 600px) { .dr-grid-2, .dr-grid-3 { grid-template-columns: 1fr; } .dr-btns { flex-direction: column-reverse; } }
    </style>

    <div class="dr-wrapper">
        <div class="dr-head">
            <h2>📝 فرم تحقیقات بازار دیدار</h2>
        </div>
        
        <div class="dr-progress">
            <div class="dr-step-dot active" id="dot-1"></div>
            <div class="dr-step-dot" id="dot-2"></div>
            <div class="dr-step-dot" id="dot-3"></div>
            <div class="dr-step-dot" id="dot-4"></div>
        </div>

        <div class="dr-content">
            <form id="drForm">
                <input type="hidden" name="sys_gps" id="sys_gps">
                <?php wp_nonce_field('dr_submit_form', 'dr_nonce'); ?>

                <div id="step-1" class="dr-step-section active">
                    <h3>اطلاعات پایه و موقعیت</h3>
                    <div class="dr-grid-2">
                        <div class="dr-group">
                            <label class="dr-label">نام نماینده *</label>
                            <input type="text" name="agent_name" class="dr-input" required>
                        </div>
                        <div class="dr-group">
                            <label class="dr-label">تاریخ بازدید (شمسی) *</label>
                            <input type="text" name="visit_date" id="p_date_input" class="dr-input" readonly required>
                        </div>
                    </div>
                    
                    <div class="dr-grid-2">
                        <div class="dr-group">
                            <label class="dr-label">نام فروشگاه *</label>
                            <input type="text" name="shop_name" class="dr-input" required>
                        </div>
                        <div class="dr-group">
                            <label class="dr-label">بازه زمانی مراجعه *</label>
                            <select name="visit_time_period" class="dr-select" required>
                                <option value="">انتخاب کنید</option>
                                <option value="صبح">صبح</option>
                                <option value="ظهر">ظهر</option>
                                <option value="شب">شب</option>
                            </select>
                        </div>
                    </div>

                    <div class="dr-group">
                        <label class="dr-label">شهر / منطقه</label>
                        <input type="text" name="location_city" class="dr-input" placeholder="مثال: تهران - بازار بزرگ">
                    </div>
                    
                    <div class="dr-group">
                        <label class="dr-label">جایگاه فروشگاه:</label>
                        <div class="dr-check-group">
                            <?php 
                            $locs = ['بر خیابان اصلی', 'داخل پاساژ', 'طبقه همکف', 'طبقه بالا', 'گوشه/دنج', 'پر تردد'];
                            foreach($locs as $l) echo "<label class='dr-check-label'><input type='checkbox' name='shop_loc[]' value='$l'> $l</label>";
                            ?>
                        </div>
                    </div>

                    <div class="dr-btns">
                        <button type="button" class="dr-btn dr-btn-next" onclick="drNextStep(2)">مرحله بعدی</button>
                    </div>
                </div>

                <div id="step-2" class="dr-step-section">
                    <h3>تحلیل ترافیک و ویترین</h3>
                    <div class="dr-grid-3">
                        <div class="dr-group">
                            <label class="dr-label">تعداد پرسنل</label>
                            <input type="number" name="staff_count" class="dr-input">
                        </div>
                        <div class="dr-group">
                            <label class="dr-label">تراکم ویترین</label>
                            <select name="vitrine_density" class="dr-select">
                                <option value="خلوت">خلوت</option>
                                <option value="متوسط">متوسط</option>
                                <option value="شلوغ">شلوغ (پرکار)</option>
                            </select>
                        </div>
                        <div class="dr-group">
                            <label class="dr-label">نوع محصول غالب</label>
                            <select name="product_type" class="dr-select">
                                <option value="طلای ساخته">طلای ساخته</option>
                                <option value="سکه و شمش">سکه و شمش</option>
                                <option value="جواهر">جواهر / سنگ</option>
                            </select>
                        </div>
                    </div>

                    <div class="dr-group">
                        <label class="dr-label">میزان پاخور (در ۵ دقیقه):</label>
                        <div class="dr-check-group">
                            <label class="dr-check-label"><input type="radio" name="footfall" value="کم"> زیر ۵ نفر</label>
                            <label class="dr-check-label"><input type="radio" name="footfall" value="متوسط"> ۵ تا ۲۰ نفر</label>
                            <label class="dr-check-label"><input type="radio" name="footfall" value="زیاد"> بالای ۲۰ نفر</label>
                        </div>
                    </div>

                    <div class="dr-btns">
                        <button type="button" class="dr-btn dr-btn-prev" onclick="drNextStep(1)">قبلی</button>
                        <button type="button" class="dr-btn dr-btn-next" onclick="drNextStep(3)">مرحله بعدی</button>
                    </div>
                </div>

                <div id="step-3" class="dr-step-section">
                    <h3>تحلیل رفتار و برند</h3>
                    
                    <div class="dr-group">
                        <label class="dr-label">نقاط قوت (انتخاب چند مورد):</label>
                        <div class="dr-check-group">
                            <?php 
                            $strengths = ['برخورد حرفه‌ای', 'ویترین مدرن', 'لوکیشن عالی', 'قدمت بالا', 'تنوع زیاد'];
                            foreach($strengths as $s) echo "<label class='dr-check-label'><input type='checkbox' name='strengths[]' value='$s'> $s</label>";
                            ?>
                        </div>
                    </div>

                    <div class="dr-group">
                        <label class="dr-label">امتیاز کلی به فروشگاه (۱ تا ۱۰):</label>
                        <input type="range" name="total_score" min="1" max="10" value="5" oninput="this.nextElementSibling.value = this.value" style="width:100%">
                        <output>5</output>
                    </div>

                    <div class="dr-group">
                        <label class="dr-label">یادداشت محرمانه نماینده:</label>
                        <textarea name="private_note" class="dr-textarea" rows="4"></textarea>
                    </div>

                    <div class="dr-btns">
                        <button type="button" class="dr-btn dr-btn-prev" onclick="drNextStep(2)">قبلی</button>
                        <button type="button" class="dr-btn dr-btn-next" onclick="drNextStep(4)">مرحله بعدی</button>
                    </div>
                </div>

                <div id="step-4" class="dr-step-section">
                    <h3>تصویر و ارسال</h3>
                    
                    <div class="dr-group" style="border: 2px dashed #ccc; padding: 20px; text-align: center; border-radius: 8px;">
                        <label class="dr-label">📷 آپلود عکس فروشگاه/ویترین</label>
                        <input type="file" name="shop_image" accept="image/*">
                        <p style="font-size:0.8rem; color:#888;">حداکثر حجم: ۵ مگابایت</p>
                    </div>

                    <div class="dr-btns">
                        <button type="button" class="dr-btn dr-btn-prev" onclick="drNextStep(3)">قبلی</button>
                        <button type="submit" class="dr-btn dr-btn-submit" id="finalSubmit">ثبت نهایی گزارش ✅</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        // 1. Get GPS
        if (navigator.geolocation) {
            navigator.geolocation.getCurrentPosition(function(position) {
                $('#sys_gps').val(position.coords.latitude + "," + position.coords.longitude);
            });
        }

        // 2. Form Submission
        $('#drForm').on('submit', function(e) {
            e.preventDefault();
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

    function drNextStep(step) {
        // Simple Validation
        if(step > 1) {
            let currentStep = step - 1;
            let inputs = document.querySelectorAll(`#step-${currentStep} input[required], #step-${currentStep} select[required], #step-${currentStep} textarea[required]`);
            for(let input of inputs) {
                if(!input.value) {
                    alert('لطفا فیلدهای ضروری را پر کنید.');
                    input.focus();
                    return;
                }
            }
        }
        
        // UI Updates
        jQuery('.dr-step-section').removeClass('active');
        jQuery('#step-' + step).addClass('active');
        
        jQuery('.dr-step-dot').removeClass('active');
        for(let i=1; i<=step; i++) {
            jQuery('#dot-' + i).addClass('active'); 
            if(i < step) jQuery('#dot-' + i).addClass('completed');
        }
        
        // Scroll to top
        window.scrollTo({ top: jQuery('.dr-wrapper').offset().top - 50, behavior: 'smooth' });
    }
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
