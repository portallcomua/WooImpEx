<?php
/**
 * Plugin Name: WooImpex Pro
 * Version: 2.0
 * Description: Інтуїтивний імпорт/експорт товарів WooCommerce (прості + варіативні)
 * Author: portallcomua
 * GitHub Plugin URI: https://github.com/portallcomua/wooimpex
 */

if (!defined('ABSPATH')) exit;

define('WIMPEX_VERSION', '2.0');
define('WIMPEX_FREE_LIMIT', 25);
define('WIMPEX_SHOP_URL', 'https://uaserver.pp.ua/product/wooimpex-pro/');

// ==================== АВТООНОВЛЕННЯ ====================
add_filter('pre_set_site_transient_update_plugins', function($transient) {
    if (empty($transient->checked)) return $transient;
    $plugin_slug = plugin_basename(__FILE__);
    $response = wp_remote_get("https://api.github.com/repos/portallcomua/wooimpex/releases/latest");
    if (is_wp_error($response)) return $transient;
    $release = json_decode(wp_remote_retrieve_body($response));
    if (isset($release->tag_name)) {
        $latest = ltrim($release->tag_name, 'v');
        if (version_compare(WIMPEX_VERSION, $latest, '<')) {
            $transient->response[$plugin_slug] = (object) [
                'slug' => dirname($plugin_slug),
                'plugin' => $plugin_slug,
                'new_version' => $latest,
                'url' => $release->html_url,
                'package' => $release->zipball_url,
            ];
        }
    }
    return $transient;
});

// ==================== МОНЕТИЗАЦІЯ ====================
function wimpex_get_count() { return (int) get_option('wimpex_operations', 0); }
function wimpex_inc() { update_option('wimpex_operations', wimpex_get_count() + 1); }
function wimpex_can() { return get_option('wimpex_license') ? true : wimpex_get_count() < WIMPEX_FREE_LIMIT; }
function wimpex_remaining() { return max(0, WIMPEX_FREE_LIMIT - wimpex_get_count()); }
function wimpex_has_pro() { return get_option('wimpex_license', false); }

// ==================== АДМІН МЕНЮ ====================
add_action('admin_menu', function() {
    add_menu_page('WooImpex Pro', 'WooImpex Pro', 'manage_woocommerce', 'wimpex', 'wimpex_render_page', 'dashicons-database-import', 55);
    add_submenu_page('wimpex', 'Ліцензія', '🔑 Ліцензія', 'manage_woocommerce', 'wimpex_license', 'wimpex_license_page');
});

function wimpex_license_page() { ?>
    <div class="wrap"><h1>🔑 Ліцензія WooImpex Pro</h1>
    <?php if (get_option('wimpex_license')): ?>
        <div class="notice notice-success"><p>✅ Активна (варіативні товари доступні)</p></div>
    <?php else: ?>
        <div class="notice notice-warning"><p>⚠️ Безкоштовно: <?php echo wimpex_remaining(); ?> / <?php echo WIMPEX_FREE_LIMIT; ?> (тільки прості товари)</p>
        <form method="post"><?php wp_nonce_field('wimpex_lic'); ?>
            <input name="license_key" placeholder="Ключ"><button type="submit" name="activate_lic">🔑 Активувати</button>
        </form>
        <p><a href="<?php echo WIMPEX_SHOP_URL; ?>" target="_blank">💰 Придбати PRO (599 грн / $29) – варіативні товари + необмежено</a></p>
    <?php endif; ?>
    </div><?php
}

add_action('admin_init', function() {
    if (isset($_POST['activate_lic']) && wp_verify_nonce($_POST['wimpex_lic'], 'wimpex_lic')) {
        if (strlen(trim($_POST['license_key'])) >= 16) update_option('wimpex_license', true);
        else echo '<div class="notice notice-error"><p>❌ Невірний ключ</p></div>';
    }
});

// ==================== ГОЛОВНА СТОРІНКА ====================
function wimpex_render_page() {
    $step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
    $file_id = isset($_GET['file']) ? $_GET['file'] : '';
    $has_pro = wimpex_has_pro();
    ?>
    <div class="wrap" style="max-width:800px; margin:auto; padding:20px;">
        <div style="display:flex; justify-content:space-between;">
            <h1>📦 WooImpex Pro</h1>
            <span style="background:#2271b1; color:#fff; padding:4px 12px; border-radius:20px;">v<?php echo WIMPEX_VERSION; ?></span>
        </div>
        
        <div style="background:<?php echo wimpex_can() ? '#d4edda' : '#f8d7da'; ?>; padding:15px; border-radius:10px; margin-bottom:20px; text-align:center;">
            <?php if (get_option('wimpex_license')): ?>
                ✅ PRO версія активна (варіативні товари доступні)
            <?php else: ?>
                📊 Безкоштовна версія: залишилось <strong><?php echo wimpex_remaining(); ?></strong> з <?php echo WIMPEX_FREE_LIMIT; ?> (тільки прості товари)
                <?php if (wimpex_remaining() == 0): ?>
                    <br><a href="<?php echo admin_url('admin.php?page=wimpex_license'); ?>" style="color:#d9534f;">Придбати ліцензію →</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>
        
        <div style="margin-bottom:15px;">
            <a href="https://uaserver.pp.ua/readme-wooimpex" target="_blank" class="button">📖 Інструкція</a>
            <a href="<?php echo admin_url('admin.php?page=wimpex_license'); ?>" class="button">🔑 Ліцензія</a>
        </div>
        
        <?php if ($step == 2 && $file_id): ?>
            <?php wimpex_mapping_form($file_id, $has_pro); ?>
        <?php else: ?>
            <?php wimpex_upload_form(); ?>
        <?php endif; ?>
    </div>
    <?php
}

function wimpex_upload_form() { ?>
    <form method="post" enctype="multipart/form-data" action="<?php echo admin_url('admin-post.php'); ?>">
        <input type="hidden" name="action" value="wimpex_upload">
        <?php wp_nonce_field('wimpex_upload'); ?>
        <table class="form-table">
            <tr><th>📁 CSV файл</th><td><input type="file" name="csv_file" accept=".csv" required></td></tr>
            <tr><th>🔧 Роздільник</th><td><input type="text" name="delimiter" value="," size="1"></td></tr>
        </table>
        <p class="submit"><input type="submit" class="button-primary" value="📤 ЗАВАНТАЖИТИ ТА АНАЛІЗУВАТИ"></p>
    </form>
    
    <div style="margin-top:30px;">
        <h3>📥 ПРИКЛАДИ CSV ФАЙЛІВ</h3>
        <p><a href="<?php echo plugin_dir_url(__FILE__) . 'sample-simple.csv'; ?>" class="button" download>⬇️ Прості товари (приклад)</a></p>
        <p><a href="<?php echo plugin_dir_url(__FILE__) . 'sample-variable.csv'; ?>" class="button" download>⬇️ Варіативні товари (PRO)</a></p>
    </div>
<?php }

function wimpex_mapping_form($file_id, $has_pro) {
    $headers = get_transient('wimpex_headers_' . $file_id);
    $sample = get_transient('wimpex_sample_' . $file_id);
    $fields = wimpex_get_fields($has_pro);
    ?>
    <h3>🧩 ЗІСТАВТЕ КОЛОНКИ CSV З ПОЛЯМИ ТОВАРУ</h3>
    <div style="display:flex; gap:40px; flex-wrap:wrap;">
        <div style="flex:1; min-width:250px;">
            <h3>📋 КОЛОНКИ CSV</h3>
            <?php foreach ($headers as $idx => $col): ?>
            <div style="margin-bottom:15px; padding:8px; border:1px solid #ddd; border-radius:5px;">
                <strong><?php echo esc_html($col); ?></strong><br>
                <span style="font-size:11px; color:#888;">приклад: <?php echo esc_html(substr($sample[$idx] ?? '', 0, 40)); ?></span><br>
                <select name="map[<?php echo $idx; ?>]" style="width:100%; margin-top:5px;">
                    <option value="">— НЕ ІМПОРТУВАТИ —</option>
                    <?php foreach ($fields as $key => $label): ?>
                    <option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <?php endforeach; ?>
        </div>
        <div style="flex:1; min-width:250px;">
            <h3>🏷️ ПОЛЯ ТОВАРУ (WooCommerce)</h3>
            <?php foreach ($fields as $key => $label): ?>
            <div style="margin:5px 0;">📌 <?php echo esc_html($label); ?> <code><?php echo esc_html($key); ?></code></div>
            <?php endforeach; ?>
        </div>
    </div>
    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
        <input type="hidden" name="action" value="wimpex_import">
        <input type="hidden" name="file" value="<?php echo esc_attr($file_id); ?>">
        <?php wp_nonce_field('wimpex_import'); ?>
        <p class="submit"><input type="submit" class="button-primary" value="🚀 ПОЧАТИ ІМПОРТ"></p>
    </form>
<?php }

function wimpex_get_fields($has_pro = false) {
    $fields = [
        'post_title' => 'Назва товару',
        'post_content' => 'Повний опис',
        'post_excerpt' => 'Короткий опис',
        '_sku' => 'Артикул (SKU)',
        '_regular_price' => 'Ціна',
        '_sale_price' => 'Акційна ціна',
        '_stock' => 'Кількість',
        '_stock_status' => 'Статус складу',
        '_weight' => 'Вага (кг)',
        '_length' => 'Довжина (см)',
        '_width' => 'Ширина (см)',
        '_height' => 'Висота (см)',
        'product_cat' => 'Категорії (через |)',
        'product_tag' => 'Теги (через |)',
        'attributes' => 'Атрибути (назва:значення1,значення2)'
    ];
    
    if ($has_pro) {
        $fields['parent_sku'] = '🔹 SKU батьківського товару (для варіацій)';
        $fields['variation_attributes'] = '🔹 Атрибути варіації (назва:значення)';
        $fields['variation_price'] = '🔹 Ціна варіації';
        $fields['variation_sku'] = '🔹 SKU варіації';
        $fields['variation_stock'] = '🔹 Кількість варіації';
    }
    
    return $fields;
}

// ==================== ЗАВАНТАЖЕННЯ CSV ====================
add_action('admin_post_wimpex_upload', function() {
    if (!current_user_can('manage_woocommerce')) wp_die('Немає прав');
    check_admin_referer('wimpex_upload');
    
    if (empty($_FILES['csv_file']['tmp_name'])) {
        wp_redirect(admin_url('admin.php?page=wimpex&step=1'));
        exit;
    }
    
    $delimiter = isset($_POST['delimiter']) ? $_POST['delimiter'][0] : ',';
    $handle = fopen($_FILES['csv_file']['tmp_name'], 'r');
    $headers = fgetcsv($handle, 0, $delimiter);
    if (!$headers) {
        wp_redirect(admin_url('admin.php?page=wimpex&step=1'));
        exit;
    }
    $headers = array_map('trim', $headers);
    $sample = fgetcsv($handle, 0, $delimiter);
    fclose($handle);
    
    $file_id = uniqid();
    set_transient('wimpex_headers_' . $file_id, $headers, 3600);
    set_transient('wimpex_sample_' . $file_id, $sample, 3600);
    set_transient('wimpex_file_' . $file_id, $_FILES['csv_file']['tmp_name'], 3600);
    
    wp_redirect(admin_url('admin.php?page=wimpex&step=2&file=' . $file_id));
    exit;
});

// ==================== ІМПОРТ ====================
add_action('admin_post_wimpex_import', function() {
    if (!current_user_can('manage_woocommerce')) wp_die('Немає прав');
    check_admin_referer('wimpex_import');
    
    if (!wimpex_can()) {
        wp_die('❌ Ліміт безкоштовної версії вичерпано. <a href="'.admin_url('admin.php?page=wimpex_license').'">Придбайте ліцензію</a>');
    }
    
    $file_id = $_POST['file'];
    $tmp_file = get_transient('wimpex_file_' . $file_id);
    $headers = get_transient('wimpex_headers_' . $file_id);
    $mapping = array_map('sanitize_text_field', $_POST['map']);
    $has_pro = wimpex_has_pro();
    
    if (!$tmp_file || !file_exists($tmp_file)) {
        wp_die('Файл не знайдено. Завантажте CSV знову.');
    }
    
    $handle = fopen($tmp_file, 'r');
    fgetcsv($handle); // пропускаємо заголовки
    $delimiter = ',';
    $parents = [];
    $imported = 0;
    
    while (($row = fgetcsv($handle, 0, $delimiter)) !== false) {
        $product_data = [];
        foreach ($mapping as $col_idx => $field) {
            if ($field && isset($row[$col_idx])) {
                $product_data[$field] = $row[$col_idx];
            }
        }
        
        if (empty($product_data['post_title']) && empty($product_data['parent_sku'])) continue;
        
        // Варіативний товар
        if ($has_pro && !empty($product_data['parent_sku'])) {
            $parent_id = $parents[$product_data['parent_sku']] ?? 0;
            if ($parent_id) {
                wimpex_save_variation($parent_id, $product_data);
                $imported++;
            }
        } 
        // Простий товар або батьківський
        else {
            $product_type = (!empty($product_data['variation_sku']) && $has_pro) ? 'variable' : 'simple';
            $id = wimpex_save_product($product_data, $product_type);
            if ($id) {
                $imported++;
                if (!empty($product_data['_sku'])) {
                    $parents[$product_data['_sku']] = $id;
                }
            }
        }
    }
    fclose($handle);
    
    // Очищаємо тимчасові дані
    delete_transient('wimpex_file_' . $file_id);
    delete_transient('wimpex_headers_' . $file_id);
    delete_transient('wimpex_sample_' . $file_id);
    
    wimpex_inc();
    
    echo '<div class="notice notice-success"><p>✅ Імпорт завершено. Додано/оновлено: ' . $imported . ' товарів.</p></div>';
    echo '<p><a href="'.admin_url('admin.php?page=wimpex').'" class="button">⬅ Повернутись</a></p>';
});

function wimpex_save_product($data, $type = 'simple') {
    $sku = $data['_sku'] ?? '';
    $existing_id = $sku ? wc_get_product_id_by_sku($sku) : 0;
    
    $args = [
        'post_type' => 'product',
        'post_status' => 'publish',
        'post_title' => sanitize_text_field($data['post_title'] ?? ''),
        'post_content' => sanitize_textarea_field($data['post_content'] ?? ''),
        'post_excerpt' => sanitize_textarea_field($data['post_excerpt'] ?? ''),
    ];
    
    if ($existing_id) $args['ID'] = $existing_id;
    $id = $existing_id ? wp_update_post($args) : wp_insert_post($args);
    if (!$id) return false;
    
    if ($type === 'variable') {
        $product = new WC_Product_Variable();
        $product->set_id($id);
    } else {
        $product = wc_get_product($id);
    }
    
    $product->set_sku($sku);
    if (isset($data['_regular_price'])) $product->set_regular_price(wc_clean($data['_regular_price']));
    if (isset($data['_sale_price'])) $product->set_sale_price(wc_clean($data['_sale_price']));
    if (isset($data['_stock'])) { $product->set_stock_quantity(wc_clean($data['_stock'])); $product->set_manage_stock(true); }
    if (isset($data['_stock_status'])) $product->set_stock_status(wc_clean($data['_stock_status']));
    if (isset($data['_weight'])) $product->set_weight(wc_clean($data['_weight']));
    if (isset($data['_length'])) $product->set_length(wc_clean($data['_length']));
    if (isset($data['_width'])) $product->set_width(wc_clean($data['_width']));
    if (isset($data['_height'])) $product->set_height(wc_clean($data['_height']));
    
    if (!empty($data['product_cat'])) {
        $cat_ids = [];
        foreach (explode('|', $data['product_cat']) as $cat) {
            $term = term_exists($cat, 'product_cat');
            if (!$term) $term = wp_insert_term($cat, 'product_cat');
            if (!is_wp_error($term)) $cat_ids[] = (int)$term['term_id'];
        }
        wp_set_object_terms($id, $cat_ids, 'product_cat');
    }
    if (!empty($data['product_tag'])) {
        wp_set_object_terms($id, explode('|', $data['product_tag']), 'product_tag');
    }
    if (!empty($data['attributes'])) {
        wimpex_save_attributes($id, $data['attributes']);
    }
    
    $product->save();
    return $id;
}

function wimpex_save_variation($parent_id, $data) {
    $variation = new WC_Product_Variation();
    $variation->set_parent_id($parent_id);
    if (!empty($data['variation_sku'])) $variation->set_sku($data['variation_sku']);
    if (!empty($data['variation_price'])) $variation->set_regular_price(wc_clean($data['variation_price']));
    if (!empty($data['variation_stock'])) { $variation->set_stock_quantity(wc_clean($data['variation_stock'])); $variation->set_manage_stock(true); }
    
    if (!empty($data['variation_attributes'])) {
        $attrs = [];
        $pairs = explode('|', $data['variation_attributes']);
        foreach ($pairs as $pair) {
            $parts = explode(':', $pair, 2);
            if (count($parts) === 2) {
                $attrs[sanitize_title($parts[0])] = $parts[1];
            }
        }
        $variation->set_attributes($attrs);
    }
    $variation->save();
}

function wimpex_save_attributes($pid, $raw) {
    $pairs = explode('|', $raw);
    $attrs = [];
    foreach ($pairs as $pair) {
        $parts = explode(':', $pair, 2);
        if (count($parts) !== 2) continue;
        $name = sanitize_text_field($parts[0]);
        $values = array_map('trim', explode(',', $parts[1]));
        $slug = wc_sanitize_taxonomy_name($name);
        $tax = 'pa_' . $slug;
        
        if (!taxonomy_exists($tax)) {
            wc_create_attribute(['name' => $name, 'slug' => $slug, 'type' => 'select']);
            register_taxonomy($tax, ['product']);
        }
        
        $term_ids = [];
        foreach ($values as $val) {
            $term = term_exists($val, $tax);
            if (!$term) $term = wp_insert_term($val, $tax);
            if (!is_wp_error($term)) $term_ids[] = (int)$term['term_id'];
        }
        wp_set_object_terms($pid, $term_ids, $tax);
        $attrs[] = [
            'name' => $tax,
            'value' => implode(', ', $values),
            'is_visible' => 1,
            'is_taxonomy' => 1
        ];
    }
    update_post_meta($pid, '_product_attributes', $attrs);
}

// ==================== ЕКСПОРТ ====================
add_action('admin_post_wimpex_export', function() {
    if (!current_user_can('manage_woocommerce')) wp_die('Немає прав');
    check_admin_referer('wimpex_export');
    
    $products = wc_get_products(['limit' => -1]);
    $headers = ['post_title', '_sku', '_regular_price', '_sale_price', '_stock', 'product_cat', 'product_tag'];
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="wooimpex_export_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, $headers);
    
    foreach ($products as $p) {
        fputcsv($out, [
            $p->get_name(),
            $p->get_sku(),
            $p->get_regular_price(),
            $p->get_sale_price(),
            $p->get_stock_quantity(),
            implode('|', wp_get_post_terms($p->get_id(), 'product_cat', ['fields'=>'names'])),
            implode('|', wp_get_post_terms($p->get_id(), 'product_tag', ['fields'=>'names'])),
        ]);
    }
    exit;
});

// ==================== СТВОРЕННЯ ФАЙЛІВ-ПРИКЛАДІВ ====================
register_activation_hook(__FILE__, function() {
    $sample_simple = "post_title,_sku,_regular_price,product_cat\n\"Навушники JBL\",JBL-001,1299,\"Електроніка|Аудіо\"\n\"Мишка Logitech\",LOG-002,899,\"Електроніка|Комп'ютери\"";
    $sample_variable = "post_title,_sku,_regular_price,parent_sku,variation_attributes,variation_price,variation_sku\n\"Футболка Adidas\",AD-TEE-MAIN,1200,,,\n,,,\"AD-TEE-MAIN\",\"Розмір:S|Колір:червоний\",1299,AD-TEE-S-RED\n,,,\"AD-TEE-MAIN\",\"Розмір:M|Колір:синій\",1299,AD-TEE-M-BLU";
    
    file_put_contents(plugin_dir_path(__FILE__) . 'sample-simple.csv', $sample_simple);
    file_put_contents(plugin_dir_path(__FILE__) . 'sample-variable.csv', $sample_variable);
});
?>
