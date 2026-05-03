<?php
/**
 * Plugin Name: Woo Impex Pro
 * Version: 2.9.9
 * Author: UAServer
 */

if (!defined('ABSPATH')) exit;

define('WIMPEX_VERSION', '2.9.9');
define('WIMPEX_FREE_LIMIT', 50);
define('WIMPEX_PRICE', 599);
define('WIMPEX_CARD', '5457082521749601'); // Вставте свій номер карти ПриватБанку

// ==================== МОНЕТИЗАЦІЯ ====================
function wimpex_has_pro() {
    $key = get_option('wimpex_license_key', '');
    if (empty($key)) return false;
    $lics = get_option('wimpex_licenses', []);
    return isset($lics[$key]) && $lics[$key]['status'] === 'active';
}
function wimpex_get_count() { return (int)get_option('wimpex_operations', 0); }
function wimpex_inc() { update_option('wimpex_operations', wimpex_get_count() + 1); }
function wimpex_can() { return wimpex_has_pro() ? true : wimpex_get_count() < WIMPEX_FREE_LIMIT; }

// ==================== МЕНЮ ====================
add_action('admin_menu', function() {
    $parent = 'edit.php?post_type=product';
    add_submenu_page($parent, 'Woo Impex Pro', '🚀 Woo Impex Pro (' . WIMPEX_VERSION . ')', 'manage_options', 'wimpex', 'wimpex_render_page');
    add_submenu_page(null, 'Ліцензія', 'Ліцензія', 'manage_options', 'wimpex_license', 'wimpex_license_page');
    add_submenu_page($parent, 'Продажі Woo Impex', '💸 Продажі Impex', 'manage_options', 'wimpex_sales', 'wimpex_sales_page');
});

// ==================== ПОЛЯ МАПІНГУ ====================
function wimpex_get_fields() {
    return [
        'post_title'     => 'Назва товару *',
        'post_content'   => 'Повний опис',
        'post_excerpt'   => 'Короткий опис',
        '_sku'           => 'Артикул (SKU)',
        '_regular_price' => 'Ціна *',
        '_sale_price'    => 'Акційна ціна',
        '_stock'         => 'Кількість',
        'product_cat'    => 'Категорії (через /)',
        'product_tag'    => 'Теги',
        'images'         => 'Зображення (головне | галерея)',
        '_weight'        => 'Вага',
        'attribute'      => 'Атрибут (динамічний)',
        'parent_sku'     => 'Артикул батька'
    ];
}

// ==================== СТОРІНКА ПРОДАЖІВ (ПІДТВЕРДЖЕННЯ/ВІДМІНА) ====================
function wimpex_sales_page() {
    // Підтвердження
    if (isset($_POST['approve_sale']) && isset($_POST['transaction_key'])) {
        $trans_key = sanitize_text_field($_POST['transaction_key']);
        $pending = get_option('wimpex_pending_sales', []);
        $licenses = get_option('wimpex_licenses', []);
        
        if (isset($pending[$trans_key])) {
            $sale = $pending[$trans_key];
            
            if (isset($licenses[$sale['license_key']])) {
                $licenses[$sale['license_key']]['status'] = 'active';
                unset($licenses[$sale['license_key']]['expires']);
                update_option('wimpex_licenses', $licenses);
            }
            
            $used = get_option('wimpex_used_transactions', []);
            $used[] = $trans_key;
            update_option('wimpex_used_transactions', $used);
            
            unset($pending[$trans_key]);
            update_option('wimpex_pending_sales', $pending);
            
            wp_mail($sale['email'], '✅ Вашу ліцензію Woo Impex Pro підтверджено', 
                "Вітаємо! Вашу ліцензію підтверджено.\n\nВаш ключ: " . $sale['license_key'] . "\n\nТепер він активний назавжди.\n\nДякуємо за покупку!");
            
            echo '<div class="notice notice-success"><p>✅ Ліцензію підтверджено! Ключ став постійним.</p></div>';
        }
    }
    
    // Відхилення
    if (isset($_POST['reject_sale']) && isset($_POST['transaction_key'])) {
        $trans_key = sanitize_text_field($_POST['transaction_key']);
        $pending = get_option('wimpex_pending_sales', []);
        $licenses = get_option('wimpex_licenses', []);
        
        if (isset($pending[$trans_key])) {
            $sale = $pending[$trans_key];
            
            if (isset($licenses[$sale['license_key']])) {
                unset($licenses[$sale['license_key']]);
                update_option('wimpex_licenses', $licenses);
            }
            
            unset($pending[$trans_key]);
            update_option('wimpex_pending_sales', $pending);
            
            wp_mail($sale['email'], '❌ Запит на ліцензію Woo Impex Pro відхилено', 
                "На жаль, ваш запит на отримання ліцензійного ключа було відхилено.\n\nМожливі причини: недійсна транзакція або недостатня сума.\n\nЗв'яжіться з підтримкою: " . get_option('admin_email'));
            
            echo '<div class="notice notice-warning"><p>⚠️ Ліцензію відхилено та видалено.</p></div>';
        }
    }
    
    $licenses = get_option('wimpex_licenses', []);
    $transactions = get_option('wimpex_used_transactions', []);
    $pending = get_option('wimpex_pending_sales', []);
    ?>
    <div class="wrap">
        <h1>💰 Продажі Woo Impex Pro</h1>
        <div style="display:flex; gap:20px; margin-bottom:20px;">
            <div style="background:#fff3cd; padding:15px; border:1px solid #ffeeba; border-radius:8px; text-align:center; flex:1;">
                <h3>⏳ Очікує перевірки</h3>
                <h2><?php echo count($pending); ?></h2>
                <small>Автоматично деактивуються через 24 години</small>
            </div>
            <div style="background:#d4edda; padding:15px; border:1px solid #c3e6cb; border-radius:8px; text-align:center; flex:1;">
                <h3>✅ Підтверджено</h3>
                <h2><?php echo count($transactions); ?></h2>
            </div>
            <div style="background:#d1ecf1; padding:15px; border:1px solid #bee5eb; border-radius:8px; text-align:center; flex:1;">
                <h3>💰 Дохід (грн)</h3>
                <h2><?php echo count($transactions) * WIMPEX_PRICE; ?></h2>
            </div>
        </div>
        
        <?php if (!empty($pending)): ?>
        <h2>⏳ Очікують перевірки</h2>
        <table class="wp-list-table widefat striped">
            <thead><tr><th>Email</th><th>Транзакція</th><th>Дата</th><th>Ключ</th><th>Дія</th></tr></thead>
            <tbody>
                <?php foreach ($pending as $trans_key => $data): ?>
                <tr>
                    <td><?php echo esc_html($data['email']); ?></td>
                    <td><code><?php echo esc_html($trans_key); ?></code></td>
                    <td><?php echo esc_html($data['date']); ?></td>
                    <td><code><?php echo esc_html($data['license_key']); ?></code></td>
                    <td>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="transaction_key" value="<?php echo esc_attr($trans_key); ?>">
                            <button type="submit" name="approve_sale" class="button button-primary" style="background:#46b450;">✅ Підтвердити</button>
                            <button type="submit" name="reject_sale" class="button" style="background:#dc3232; color:#fff;">❌ Відхилити</button>
                        </form>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
        
        <h2>📋 Підтверджені ліцензії</h2>
        <table class="wp-list-table widefat striped">
            <thead><tr><th>Ключ</th><th>Email</th><th>Транзакція</th><th>Дата</th><th>Статус</th></tr></thead>
            <tbody>
                <?php foreach ($licenses as $key => $data): ?>
                <tr>
                    <td><code><?php echo esc_html($key); ?></code></td>
                    <td><?php echo esc_html($data['email']); ?></td>
                    <td><?php echo esc_html($data['transaction']); ?></td>
                    <td><?php echo esc_html($data['date']); ?></td>
                    <td><?php echo $data['status'] === 'active' ? '✅ активна' : '⏳ тимчасова'; ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php
}

// ==================== ФОРМА ОТРИМАННЯ КЛЮЧА (ШОРТКОД) ====================
add_shortcode('wimpex_get_license', function() {
    if (isset($_POST['request_license']) && wp_verify_nonce($_POST['_lic'], 'wimpex_lic')) {
        $email = sanitize_email($_POST['email']);
        $transaction = sanitize_text_field($_POST['transaction']);
        
        $used = get_option('wimpex_used_transactions', []);
        if (in_array($transaction, $used)) {
            return '<div class="notice notice-error"><p>❌ Ця транзакція вже використана!</p></div>';
        }
        
        $licenses = get_option('wimpex_licenses', []);
        foreach ($licenses as $key => $lic) {
            if ($lic['email'] === $email && $lic['status'] === 'active') {
                return '<div class="notice notice-warning"><p>⚠️ Для цього email вже є активний ключ!</p></div>';
            }
        }
        
        $license_key = 'WIMPEX-' . strtoupper(substr(md5(uniqid() . $email . $transaction), 0, 16));
        
        $licenses[$license_key] = [
            'key' => $license_key,
            'email' => $email,
            'transaction' => $transaction,
            'date' => current_time('mysql'),
            'status' => 'pending',
            'expires' => strtotime('+24 hours')
        ];
        update_option('wimpex_licenses', $licenses);
        
        $pending = get_option('wimpex_pending_sales', []);
        $pending[$transaction] = [
            'email' => $email,
            'license_key' => $license_key,
            'date' => current_time('mysql')
        ];
        update_option('wimpex_pending_sales', $pending);
        
        wp_mail($email, '✅ Ваш ліцензійний ключ Woo Impex Pro', 
            "Дякуємо за придбання!\n\nВаш ліцензійний ключ: " . $license_key . 
            "\n\n🔑 Активуйте його в адмінці: WooCommerce → Woo Impex → Ліцензія\n\n" .
            "⚠️ Важливо: ключ буде активний 24 години. Після перевірки оплати він стане постійним.");
        
        wp_mail(get_option('admin_email'), '🆕 Новий запит на ліцензію (потрібна перевірка)', 
            "Новий запит!\n\nEmail: {$email}\nТранзакція: {$transaction}\nКлюч: {$license_key}\n\n" .
            "Сума: " . WIMPEX_PRICE . " грн\n\n" .
            "🔍 Перевірте Приват24. Якщо оплата пройшла — підтвердіть в адмінці WooCommerce → Продажі Impex");
        
        return '<div class="notice notice-success"><p>✅ <strong>Ліцензійний ключ згенеровано та надіслано на ' . esc_html($email) . '!</strong></p>
        <p>Ваш ключ: <code>' . $license_key . '</code></p>
        <p>⚠️ Ключ буде активний 24 години. Після перевірки оплати він стане постійним.</p></div>';
    }
    
    ob_start();
    ?>
    <div style="max-width:500px; margin:20px auto; padding:20px; border:1px solid #ddd; border-radius:8px; background:#fff;">
        <h2>🔑 Отримати ліцензійний ключ</h2>
        <p>Оплатіть <strong><?php echo WIMPEX_PRICE; ?> грн</strong> на карту ПриватБанку:<br>
        <code style="font-size:16px;"><?php echo WIMPEX_CARD; ?></code></p>
        <hr>
        <form method="post">
            <?php wp_nonce_field('wimpex_lic', '_lic'); ?>
            <p><input type="email" name="email" placeholder="Ваш email" style="width:100%; padding:8px;" required></p>
            <p><input type="text" name="transaction" placeholder="Номер транзакції з Приват24" style="width:100%; padding:8px;" required></p>
            <p><button type="submit" name="request_license" style="background:#d9534f; color:#fff; padding:10px; border:none; border-radius:5px; cursor:pointer; width:100%;">🔑 Отримати ключ</button></p>
        </form>
        <p><small>Ключ прийде на email одразу. Після перевірки оплати він стане постійним.</small></p>
    </div>
    <?php
    return ob_get_clean();
});

// ==================== СТОРІНКА ЛІЦЕНЗІЇ ====================
function wimpex_license_page() {
    $has_pro = wimpex_has_pro();
    ?>
    <div class="wrap">
        <h1>🔑 Ліцензія Woo Impex Pro</h1>
        <div style="background:#fff; border:1px solid #ddd; padding:20px; border-radius:8px; max-width:600px;">
            <?php if ($has_pro): ?>
                <div class="notice notice-success"><p>✅ PRO версія активна!</p></div>
                <p>Ваш ключ: <code><?php echo esc_html(get_option('wimpex_license_key')); ?></code></p>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <input type="hidden" name="action" value="wimpex_deactivate_license">
                    <?php wp_nonce_field('wimpex_lic'); ?>
                    <button type="submit" class="button button-secondary">🔓 Деактивувати</button>
                </form>
            <?php else: ?>
                <div class="notice notice-info"><p>⚡ Безкоштовна версія: <strong><?php echo wimpex_get_count(); ?></strong> / <?php echo WIMPEX_FREE_LIMIT; ?> операцій</p></div>
                <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                    <input type="hidden" name="action" value="wimpex_activate_license">
                    <?php wp_nonce_field('wimpex_lic'); ?>
                    <p><input type="text" name="license_key" placeholder="WIMPEX-XXXXXXXXXXXXXXX" style="width:100%;"></p>
                    <button type="submit" class="button button-primary">🔑 Активувати</button>
                </form>
                <hr>
                <p>💳 Придбати PRO: <code><?php echo WIMPEX_CARD; ?></code><br>
                <a href="<?php echo home_url('/get-license'); ?>" target="_blank">Отримати ключ →</a></p>
            <?php endif; ?>
        </div>
    </div>
    <?php
}

// ==================== АКТИВАЦІЯ/ДЕАКТИВАЦІЯ ЛІЦЕНЗІЇ ====================
add_action('admin_post_wimpex_activate_license', function() {
    if (!current_user_can('manage_options')) wp_die('Немає прав');
    check_admin_referer('wimpex_lic');
    $license_key = sanitize_text_field($_POST['license_key']);
    $licenses = get_option('wimpex_licenses', []);
    if (isset($licenses[$license_key]) && $licenses[$license_key]['status'] === 'active') {
        update_option('wimpex_license_key', $license_key);
        wp_redirect(admin_url('edit.php?post_type=product&page=wimpex_license&success=1'));
    } else {
        wp_redirect(admin_url('edit.php?post_type=product&page=wimpex_license&error=1'));
    }
    exit;
});

add_action('admin_post_wimpex_deactivate_license', function() {
    if (!current_user_can('manage_options')) wp_die('Немає прав');
    check_admin_referer('wimpex_lic');
    delete_option('wimpex_license_key');
    wp_redirect(admin_url('edit.php?post_type=product&page=wimpex_license&deactivated=1'));
    exit;
});

// ==================== АВТОМАТИЧНА ДЕАКТИВАЦІЯ ПРОТЕРМІНОВАНИХ ====================
add_action('wp_scheduled_delete', function() {
    $licenses = get_option('wimpex_licenses', []);
    $changed = false;
    foreach ($licenses as $key => $data) {
        if ($data['status'] === 'pending' && isset($data['expires']) && $data['expires'] < time()) {
            unset($licenses[$key]);
            $changed = true;
        }
    }
    if ($changed) update_option('wimpex_licenses', $licenses);
});

// ==================== ІНТЕРФЕЙС ІМПОРТУ ====================
function wimpex_render_page() {
    $step = isset($_GET['step']) ? (int)$_GET['step'] : 1;
    $fid = isset($_GET['file']) ? sanitize_text_field($_GET['file']) : '';
    $is_pro = wimpex_has_pro();

    if (isset($_GET['imported'])) {
        $added = (int)$_GET['added'];
        $updated = (int)$_GET['updated'];
        echo "<div class='updated notice is-dismissible'><p>✅ Імпорт завершено! Додано: <strong>$added</strong>, Оновлено: <strong>$updated</strong>.</p></div>";
    }
    ?>
    <div class="wrap">
        <h1>⚡ Woo Impex Pro <small>v<?php echo WIMPEX_VERSION; ?></small></h1>
        <div style="background:#fff; border:1px solid #ccd0d4; padding:15px; border-radius:8px; margin-bottom:20px; border-left: 4px solid <?php echo $is_pro ? '#46b450' : '#ffb900'; ?>;">
            <div style="display:flex; justify-content:space-between; align-items:center;">
                <div><h2 style="margin:0;"><?php echo $is_pro ? '💎 PRO Версія активована' : '🎁 Безкоштовна версія'; ?></h2>
                <p style="margin:5px 0 0;">Операцій: <strong><?php echo wimpex_get_count(); ?></strong> з <?php echo $is_pro ? '∞' : WIMPEX_FREE_LIMIT; ?></p></div>
                <div>
                    <a href="<?php echo admin_url('edit.php?post_type=product&page=wimpex_license'); ?>" class="button <?php echo $is_pro ? '' : 'button-primary'; ?>"><?php echo $is_pro ? 'Керувати ліцензією' : '🔑 Активувати PRO'; ?></a>
                    <a href="<?php echo home_url('/get-license'); ?>" class="button" target="_blank">💰 Придбати PRO</a>
                </div>
            </div>
        </div>
        <div style="background:#fff; border:1px solid #ccd0d4; padding:15px; border-radius:8px; margin-bottom:20px;">
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" style="display:inline;">
                <input type="hidden" name="action" value="wimpex_export_csv">
                <?php wp_nonce_field('wimpex_export_csv'); ?>
                <input type="submit" class="button" value="📤 Експортувати всі товари">
            </form>
        </div>
        <?php if ($step == 2 && $fid): ?>
            <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                <input type="hidden" name="action" value="wimpex_start_import">
                <input type="hidden" name="fid" value="<?php echo $fid; ?>">
                <?php wp_nonce_field('wimpex_start_import'); ?>
                <table class="widefat striped"><thead></td><th>Колонка у файлі</th><th>Поле магазину</th></tr></thead>
                <tbody><?php 
                    $headers = get_transient('wimpex_h_'.$fid);
                    foreach ($headers as $i => $col): 
                        $c_clean = mb_strtolower(preg_replace('/[^а-яёa-z0-9]/ui', '', $col));
                ?>
                <tr><td><strong><?php echo esc_html($col); ?></strong></td>
                <td><select name="map[<?php echo $i; ?>]"><option value="">-- Пропустити --</option>
                <?php foreach (wimpex_get_fields() as $k => $v): 
                    $v_clean = mb_strtolower(preg_replace('/[^а-яёa-z0-9]/ui', '', $v));
                    $sel = (strpos($c_clean, $v_clean) !== false || $c_clean == $v_clean) ? 'selected' : '';
                ?><option value="<?php echo $k; ?>" <?php echo $sel; ?>><?php echo $v; ?></option><?php endforeach; ?>
                </select></td></tr><?php endforeach; ?>
                </tbody></table>
                <?php submit_button('🚀 ПОЧАТИ ІМПОРТ'); ?>
            </form>
        <?php else: ?>
            <div style="background:#fff; border:1px solid #ccd0d4; padding:20px; border-radius:8px;">
                <form method="post" enctype="multipart/form-data" action="<?php echo admin_url('admin-post.php'); ?>">
                    <input type="hidden" name="action" value="wimpex_upload_file">
                    <?php wp_nonce_field('wimpex_upload_file'); ?>
                    <p><input type="file" name="csv" accept=".csv" required></p>
                    <?php submit_button('Завантажити та перейти до мапінгу'); ?>
                </form>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

// ==================== ЛОГІКА ІМПОРТУ ТА ЕКСПОРТУ ====================
add_action('admin_post_wimpex_export_csv', function() {
    check_admin_referer('wimpex_export_csv');
    if (!current_user_can('manage_woocommerce')) return;
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="products_export_'.date('Y-m-d').'.csv"');
    $output = fopen('php://output', 'w');
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    $fields = wimpex_get_fields();
    fputcsv($output, array_values($fields), ',');
    $products = wc_get_products(['limit' => -1]);
    foreach ($products as $p) {
        $cats = wp_get_post_terms($p->get_id(), 'product_cat', ['fields' => 'names']);
        fputcsv($output, [
            $p->get_name(), $p->get_description(), $p->get_short_description(), $p->get_sku(),
            $p->get_regular_price(), $p->get_sale_price(), $p->get_stock_quantity(),
            implode('/', $cats), '', '', $p->get_weight(), '', ''
        ], ',');
    }
    fclose($output);
    exit;
});

add_action('admin_post_wimpex_upload_file', function() {
    check_admin_referer('wimpex_upload_file');
    $f = $_FILES['csv']['tmp_name'];
    $h = fopen($f, 'r');
    $l = fgets($h); rewind($h);
    $d = (strpos($l, ',') !== false) ? ',' : ';';
    $headers = fgetcsv($h, 0, $d);
    fclose($h);
    $fid = uniqid();
    $path = wp_upload_dir()['basedir'].'/wimpex_'.$fid.'.csv';
    move_uploaded_file($f, $path);
    set_transient('wimpex_h_'.$fid, $headers, HOUR_IN_SECONDS);
    set_transient('wimpex_p_'.$fid, $path, HOUR_IN_SECONDS);
    set_transient('wimpex_d_'.$fid, $d, HOUR_IN_SECONDS);
    wp_redirect(admin_url('edit.php?post_type=product&page=wimpex&step=2&file='.$fid));
});

add_action('admin_post_wimpex_start_import', function() {
    check_admin_referer('wimpex_start_import');
    if (!wimpex_can()) wp_die('Ліміт!');
    require_once(ABSPATH . 'wp-admin/includes/media.php');
    require_once(ABSPATH . 'wp-admin/includes/file.php');
    require_once(ABSPATH . 'wp-admin/includes/image.php');
    $fid = $_POST['fid'];
    $path = get_transient('wimpex_p_'.$fid);
    $d = get_transient('wimpex_d_'.$fid);
    $map = $_POST['map'];
    $added = 0; $updated = 0;
    $h = fopen($path, 'r');
    $headers = fgetcsv($h, 0, $d);
    while (($row = fgetcsv($h, 0, $d)) !== false) {
        $raw = [];
        foreach ($map as $i => $f) { if($f) $raw[$f][$i] = $row[$i]; }
        $sku = isset($raw['_sku']) ? current($raw['_sku']) : '';
        if (empty($sku) && empty($raw['post_title'])) continue;
        $pid = $sku ? wc_get_product_id_by_sku($sku) : 0;
        $is_new = !$pid;
        $product = $pid ? wc_get_product($pid) : new WC_Product_Simple();
        if(isset($raw['post_title'])) $product->set_name(current($raw['post_title']));
        if(isset($raw['_sku'])) $product->set_sku($sku);
        if(isset($raw['_regular_price'])) $product->set_regular_price(str_replace(',', '.', current($raw['_regular_price'])));
        if(isset($raw['_stock'])) { $product->set_manage_stock(true); $product->set_stock_quantity(current($raw['_stock'])); }
        $product_id = $product->save();
        $is_new ? $added++ : $updated++;
        if (isset($raw['images'])) {
            $urls = explode('|', current($raw['images']));
            $gallery_ids = [];
            foreach ($urls as $idx => $url) {
                $url = trim($url);
                if (filter_var($url, FILTER_VALIDATE_URL)) {
                    $img_id = media_sideload_image($url, $product_id, null, 'id');
                    if (!is_wp_error($img_id)) {
                        ($idx === 0) ? set_post_thumbnail($product_id, $img_id) : $gallery_ids[] = $img_id;
                    }
                }
            }
            if (!empty($gallery_ids)) $product->set_gallery_image_ids($gallery_ids);
            $product->save();
        }
        if (isset($raw['attribute'])) {
            $attributes = [];
            foreach ($raw['attribute'] as $idx => $val) {
                $name = $headers[$idx];
                $attribute = new WC_Product_Attribute();
                $attribute->set_name($name);
                $attribute->set_options(explode('|', $val));
                $attribute->set_visible(true);
                $attribute->set_variation(false);
                $attributes[] = $attribute;
            }
            $product->set_attributes($attributes);
            $product->save();
        }
        if (isset($raw['product_cat'])) wp_set_object_terms($product_id, explode('/', current($raw['product_cat'])), 'product_cat');
        wimpex_inc();
    }
    fclose($h);
    wp_redirect(admin_url("edit.php?post_type=product&page=wimpex&imported=1&added=$added&updated=$updated"));
    exit;
});

// ==================== СТВОРЕННЯ СТОРІНКИ ПРИ АКТИВАЦІЇ ====================
register_activation_hook(__FILE__, function() {
    if (!get_page_by_path('get-license')) {
        wp_insert_post([
            'post_title' => 'Отримати ліцензійний ключ',
            'post_name' => 'get-license',
            'post_content' => '[wimpex_get_license]',
            'post_status' => 'publish',
            'post_type' => 'page'
        ]);
    }
});
?>
