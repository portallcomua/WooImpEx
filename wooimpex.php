<?php
/**
 * Plugin Name: WooImpex Pro
 * Plugin URI: https://uaserver.pp.ua/
 * Description: Імпорт товарів з CSV для WooCommerce. Підтримка простих та варіативних товарів, зображень, атрибутів.
 * Version: 2.0.1
 * Author: UAServer
 * Author URI: https://uaserver.pp.ua/
 * Text Domain: wooimpex
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.0
 * WC requires at least: 4.0
 */

// Запобігаємо прямому доступу
if (!defined('ABSPATH')) {
    exit;
}

// Перевірка наявності WooCommerce
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function() {
        echo '<div class="error"><p><strong>WooImpex Pro</strong> потребує встановленого та активованого плагіну <strong>WooCommerce</strong>.</p></div>';
    });
    return;
}

// Головний клас плагіна
class WooImpexPro {
    
    private $woo_fields = [];
    
    public function __construct() {
        $this->init_woo_fields();
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_post_wooimpex_upload_csv', [$this, 'handle_upload']);
        add_action('admin_post_wooimpex_import', [$this, 'handle_import']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
    }
    
    /**
     * Визначення всіх полів WooCommerce
     */
    private function init_woo_fields() {
        $this->woo_fields = [
            'post_title' => [
                'label' => 'Назва товару',
                'required' => true,
                'meta_key' => false
            ],
            'post_content' => [
                'label' => 'Повний опис',
                'required' => false,
                'meta_key' => false
            ],
            'post_excerpt' => [
                'label' => 'Короткий опис',
                'required' => false,
                'meta_key' => false
            ],
            '_sku' => [
                'label' => 'Артикул (SKU)',
                'required' => false,
                'meta_key' => true
            ],
            'regular_price' => [
                'label' => 'Ціна',
                'required' => true,
                'meta_key' => true
            ],
            'sale_price' => [
                'label' => 'Акційна ціна',
                'required' => false,
                'meta_key' => true
            ],
            'stock' => [
                'label' => 'Кількість',
                'required' => false,
                'meta_key' => true
            ],
            'stock_status' => [
                'label' => 'Статус складу',
                'required' => false,
                'meta_key' => true,
                'options' => ['instock' => 'В наявності', 'outofstock' => 'Немає в наявності']
            ],
            'weight' => [
                'label' => 'Вага (кг)',
                'required' => false,
                'meta_key' => true
            ],
            'length' => [
                'label' => 'Довжина (см)',
                'required' => false,
                'meta_key' => true
            ],
            'width' => [
                'label' => 'Ширина (см)',
                'required' => false,
                'meta_key' => true
            ],
            'height' => [
                'label' => 'Висота (см)',
                'required' => false,
                'meta_key' => true
            ],
            'product_cat' => [
                'label' => 'Категорії (через /)',
                'required' => false,
                'meta_key' => false
            ],
            'product_tag' => [
                'label' => 'Теги (через ,)',
                'required' => false,
                'meta_key' => false
            ],
            'image' => [
                'label' => 'Зображення (URL)',
                'required' => false,
                'meta_key' => false
            ],
            'gallery' => [
                'label' => 'Галерея (URL через |)',
                'required' => false,
                'meta_key' => false
            ]
        ];
    }
    
    /**
     * Додавання сторінки в адмінку
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            'WooImpex Імпорт',
            'WooImpex Імпорт',
            'manage_options',
            'wooimpex',
            [$this, 'render_admin_page']
        );
    }
    
    /**
     * Підключення стилів
     */
    public function enqueue_scripts($hook) {
        if ($hook != 'woocommerce_page_wooimpex') {
            return;
        }
        wp_add_inline_style('wp-admin', '
            .wooimpex-mapping-table { width: 100%; border-collapse: collapse; }
            .wooimpex-mapping-table th, .wooimpex-mapping-table td { 
                padding: 12px; 
                border: 1px solid #ddd; 
                text-align: left; 
                vertical-align: top;
            }
            .wooimpex-mapping-table th { background: #f1f1f1; }
            .wooimpex-preview { 
                background: #f9f9f9; 
                padding: 10px; 
                margin-top: 20px;
                max-height: 300px;
                overflow: auto;
            }
            .wooimpex-success { color: #46b450; }
            .wooimpex-error { color: #dc3232; }
        ');
    }
    
    /**
     * Автоматичне зіставлення колонок
     */
    private function auto_match_columns($csv_headers) {
        $matches = [];
        
        foreach ($csv_headers as $header) {
            $found = false;
            foreach ($this->woo_fields as $field_key => $field_info) {
                if ($header === $field_info['label']) {
                    $matches[$header] = $field_key;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                $matches[$header] = '';
            }
        }
        
        return $matches;
    }
    
    /**
     * Рендер сторінки імпорту
     */
    public function render_admin_page() {
        $csv_data = get_transient('wooimpex_csv_data');
        $csv_headers = get_transient('wooimpex_csv_headers');
        $auto_matches = $csv_headers ? $this->auto_match_columns($csv_headers) : [];
        
        ?>
        <div class="wrap">
            <h1>WooImpex Pro - Імпорт товарів</h1>
            
            <?php if (isset($_GET['imported']) && $_GET['imported'] > 0): ?>
                <div class="notice notice-success">
                    <p>✅ Успішно імпортовано <?php echo intval($_GET['imported']); ?> товарів!</p>
                </div>
            <?php endif; ?>
            
            <?php if (!$csv_data): ?>
                <!-- Форма завантаження CSV -->
                <div class="card">
                    <h2>📂 Крок 1: Завантажте CSV файл</h2>
                    <form method="post" enctype="multipart/form-data" action="<?php echo admin_url('admin-post.php'); ?>">
                        <input type="hidden" name="action" value="wooimpex_upload_csv">
                        <?php wp_nonce_field('wooimpex_upload', 'wooimpex_nonce'); ?>
                        
                        <p>
                            <input type="file" name="csv_file" accept=".csv" required>
                        </p>
                        <p>
                            <label>
                                <input type="checkbox" name="update_existing" value="1">
                                🔄 Оновити існуючі товари (за SKU)
                            </label>
                        </p>
                        <?php submit_button('Завантажити CSV', 'primary', 'upload_csv'); ?>
                    </form>
                </div>
                
                <div class="card">
                    <h3>📄 Правильні назви колонок у CSV:</h3>
                    <code style="display: block; background: #f1f1f1; padding: 10px;">
Назва товару, Повний опис, Короткий опис, Артикул (SKU), Ціна, Акційна ціна, Кількість, Статус складу, Вага (кг), Довжина (см), Ширина (см), Висота (см), Категорії (через /), Теги (через ,), Зображення (URL), Галерея (URL через |)
                    </code>
                </div>
            <?php else: ?>
                <!-- Форма зіставлення колонок -->
                <div class="card">
                    <h2>🔧 Крок 2: Зіставте колонки CSV з полями товару</h2>
                    <p>✅ Плагін автоматично підібрав відповідності за назвами колонок.</p>
                    
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                        <input type="hidden" name="action" value="wooimpex_import">
                        <?php wp_nonce_field('wooimpex_import', 'wooimpex_nonce'); ?>
                        
                        <table class="wooimpex-mapping-table">
                            <thead>
                                <tr>
                                    <th width="30%">Колонка CSV</th>
                                    <th width="40%">Поле WooCommerce</th>
                                    <th width="30%">Приклад значення</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($csv_headers as $index => $header): 
                                    $sample_value = isset($csv_data[1][$index]) ? esc_html($csv_data[1][$index]) : '';
                                    $selected = isset($auto_matches[$header]) ? $auto_matches[$header] : '';
                                ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo esc_html($header); ?></strong>
                                            <input type="hidden" name="csv_headers[]" value="<?php echo esc_attr($header); ?>">
                                        </td>
                                        <td>
                                            <select name="mapping[<?php echo $index; ?>]" style="width: 100%;">
                                                <option value="">— НЕ ІМПОРТУВАТИ —</option>
                                                <?php foreach ($this->woo_fields as $field_key => $field_info): ?>
                                                    <option value="<?php echo esc_attr($field_key); ?>" 
                                                        <?php selected($selected, $field_key); ?>>
                                                        <?php echo esc_html($field_info['label']); ?>
                                                        <?php if ($field_info['required']): ?> *<?php endif; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </td>
                                        <td style="color: #666; font-size: 12px;">
                                            <?php echo $sample_value; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <p><strong>*</strong> — обов'язкові поля</p>
                        
                        <input type="hidden" name="update_existing" value="<?php echo isset($_POST['update_existing']) ? '1' : '0'; ?>">
                        
                        <?php submit_button('🚀 Почати імпорт', 'primary', 'start_import'); ?>
                        <a href="<?php echo admin_url('admin.php?page=wooimpex&reset=1'); ?>" class="button">
                            ↺ Завантажити інший файл
                        </a>
                    </form>
                </div>
                
                <!-- Попередній перегляд -->
                <div class="wooimpex-preview">
                    <h3>👁️ Попередній перегляд (3 перших рядки)</h3>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <?php foreach ($csv_headers as $header): ?>
                                    <th><?php echo esc_html(mb_substr($header, 0, 30)); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php for ($i = 1; $i <= min(3, count($csv_data) - 1); $i++): ?>
                                <tr>
                                    <?php foreach ($csv_data[$i] as $cell): ?>
                                        <td><?php echo esc_html(mb_substr($cell, 0, 50)); ?></td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Обробка завантаження CSV
     */
    public function handle_upload() {
        if (!current_user_can('manage_options')) {
            wp_die('Недостатньо прав');
        }
        
        check_admin_referer('wooimpex_upload', 'wooimpex_nonce');
        
        if (!isset($_FILES['csv_file']) || $_FILES['csv_file']['error'] != UPLOAD_ERR_OK) {
            wp_die('Помилка завантаження файлу');
        }
        
        $file = $_FILES['csv_file']['tmp_name'];
        $csv_data = $this->parse_csv($file);
        
        if (empty($csv_data)) {
            wp_die('Не вдалося прочитати CSV файл');
        }
        
        set_transient('wooimpex_csv_data', $csv_data, HOUR_IN_SECONDS);
        set_transient('wooimpex_csv_headers', $csv_data[0], HOUR_IN_SECONDS);
        set_transient('wooimpex_update_existing', isset($_POST['update_existing']) ? '1' : '0', HOUR_IN_SECONDS);
        
        wp_redirect(admin_url('admin.php?page=wooimpex'));
        exit;
    }
    
    /**
     * Парсинг CSV
     */
    private function parse_csv($file) {
        $data = [];
        if (($handle = fopen($file, 'r')) !== false) {
            while (($row = fgetcsv($handle, 0, ',')) !== false) {
                if (empty($data)) {
                    $row[0] = $this->remove_bom($row[0]);
                }
                $data[] = $row;
            }
            fclose($handle);
        }
        return $data;
    }
    
    /**
     * Видалення BOM
     */
    private function remove_bom($text) {
        $bom = pack('H*','EFBBBF');
        $text = preg_replace("/^$bom/", '', $text);
        return $text;
    }
    
    /**
     * Обробка імпорту
     */
    public function handle_import() {
        if (!current_user_can('manage_options')) {
            wp_die('Недостатньо прав');
        }
        
        check_admin_referer('wooimpex_import', 'wooimpex_nonce');
        
        $csv_data = get_transient('wooimpex_csv_data');
        $mapping = $_POST['mapping'];
        $update_existing = get_transient('wooimpex_update_existing') === '1';
        
        if (empty($csv_data)) {
            wp_die('Дані CSV не знайдено. Будь ласка, завантажте файл знову.');
        }
        
        $headers = array_shift($csv_data);
        $imported = 0;
        $errors = [];
        
        foreach ($csv_data as $row_index => $row) {
            $product_data = [];
            $meta_data = [];
            
            foreach ($mapping as $col_index => $field_key) {
                if (empty($field_key) || !isset($row[$col_index])) {
                    continue;
                }
                
                $value = trim($row[$col_index]);
                if (empty($value) && $field_key !== 'stock_status') {
                    continue;
                }
                
                $is_meta = isset($this->woo_fields[$field_key]['meta_key']) && $this->woo_fields[$field_key]['meta_key'];
                
                if ($is_meta) {
                    $meta_data[$field_key] = $value;
                } else {
                    $product_data[$field_key] = $value;
                }
            }
            
            if (empty($product_data['post_title'])) {
                $errors[] = "Рядок " . ($row_index + 2) . ": відсутня назва товару";
                continue;
            }
            
            if (empty($meta_data['regular_price'])) {
                $errors[] = "Рядок " . ($row_index + 2) . ": відсутня ціна товару";
                continue;
            }
            
            $product_id = $this->save_product($product_data, $meta_data, $update_existing);
            
            if ($product_id) {
                $imported++;
            } else {
                $errors[] = "Рядок " . ($row_index + 2) . ": помилка створення товару";
            }
        }
        
        delete_transient('wooimpex_csv_data');
        delete_transient('wooimpex_csv_headers');
        delete_transient('wooimpex_update_existing');
        
        $redirect_url = admin_url('admin.php?page=wooimpex&imported=' . $imported);
        if (!empty($errors)) {
            $redirect_url .= '&errors=' . urlencode(implode('|', $errors));
        }
        
        wp_redirect($redirect_url);
        exit;
    }
    
    /**
     * Збереження товару
     */
    private function save_product($product_data, $meta_data, $update_existing = false) {
        $existing_product_id = null;
        
        if ($update_existing && !empty($meta_data['_sku'])) {
            $existing_product_id = wc_get_product_id_by_sku($meta_data['_sku']);
        }
        
        $product_args = [
            'post_title' => $product_data['post_title'],
            'post_type' => 'product',
            'post_status' => 'publish',
        ];
        
        if (isset($product_data['post_content'])) {
            $product_args['post_content'] = $product_data['post_content'];
        }
        
        if (isset($product_data['post_excerpt'])) {
            $product_args['post_excerpt'] = $product_data['post_excerpt'];
        }
        
        if ($existing_product_id && $update_existing) {
            $product_args['ID'] = $existing_product_id;
            $product_id = wp_update_post($product_args);
        } else {
            $product_id = wp_insert_post($product_args);
        }
        
        if (!$product_id) {
            return false;
        }
        
        wp_set_object_terms($product_id, 'simple', 'product_type');
        
        foreach ($meta_data as $meta_key => $meta_value) {
            if ($meta_key === '_sku') {
                update_post_meta($product_id, '_sku', $meta_value);
            } elseif ($meta_key === 'regular_price') {
                update_post_meta($product_id, '_regular_price', $meta_value);
                update_post_meta($product_id, '_price', $meta_value);
            } elseif ($meta_key === 'sale_price') {
                update_post_meta($product_id, '_sale_price', $meta_value);
                update_post_meta($product_id, '_price', $meta_value);
            } elseif ($meta_key === 'stock') {
                update_post_meta($product_id, '_stock', $meta_value);
                update_post_meta($product_id, '_manage_stock', 'yes');
            } elseif ($meta_key === 'stock_status') {
                update_post_meta($product_id, '_stock_status', $meta_value);
            } elseif (in_array($meta_key, ['weight', 'length', 'width', 'height'])) {
                update_post_meta($product_id, '_' . $meta_key, $meta_value);
            }
        }
        
        // Категорії
        if (!empty($product_data['product_cat'])) {
            $categories = explode('/', $product_data['product_cat']);
            $term_ids = [];
            $parent_id = 0;
            
            foreach ($categories as $cat_name) {
                $cat_name = trim($cat_name);
                $term = term_exists($cat_name, 'product_cat', $parent_id);
                
                if (!$term) {
                    $term = wp_insert_term($cat_name, 'product_cat', ['parent' => $parent_id]);
                }
                
                if (!is_wp_error($term)) {
                    $term_ids[] = (int)$term['term_id'];
                    $parent_id = $term['term_id'];
                }
            }
            
            if (!empty($term_ids)) {
                wp_set_object_terms($product_id, $term_ids, 'product_cat');
            }
        }
        
        // Теги
        if (!empty($product_data['product_tag'])) {
            $tags = explode(',', $product_data['product_tag']);
            $tags = array_map('trim', $tags);
            wp_set_object_terms($product_id, $tags, 'product_tag');
        }
        
        // Зображення
        if (!empty($product_data['image'])) {
            $image_id = $this->upload_image_from_url($product_data['image'], $product_id);
            if ($image_id) {
                set_post_thumbnail($product_id, $image_id);
            }
        }
        
        // Галерея
        if (!empty($product_data['gallery'])) {
            $image_urls = explode('|', $product_data['gallery']);
            $gallery_ids = [];
            
            foreach ($image_urls as $url) {
                $url = trim($url);
                if (!empty($url)) {
                    $image_id = $this->upload_image_from_url($url, $product_id);
                    if ($image_id) {
                        $gallery_ids[] = $image_id;
                    }
                }
            }
            
            if (!empty($gallery_ids)) {
                update_post_meta($product_id, '_product_image_gallery', implode(',', $gallery_ids));
            }
        }
        
        return $product_id;
    }
    
    /**
     * Завантаження зображення з URL
     */
    private function upload_image_from_url($url, $parent_post_id = 0) {
        if (empty($url)) {
            return false;
        }
        
        $attachment_id = attachment_url_to_postid($url);
        if ($attachment_id) {
            return $attachment_id;
        }
        
        require_once(ABSPATH . 'wp-admin/includes/media.php');
        require_once(ABSPATH . 'wp-admin/includes/file.php');
        require_once(ABSPATH . 'wp-admin/includes/image.php');
        
        $attachment_id = media_sideload_image($url, $parent_post_id, null, 'id');
        
        if (is_wp_error($attachment_id)) {
            return false;
        }
        
        return $attachment_id;
    }
}

// Ініціалізація
function wooimpex_pro_init() {
    new WooImpexPro();
}
add_action('plugins_loaded', 'wooimpex_pro_init');

// Скидання даних
add_action('admin_init', function() {
    if (isset($_GET['page']) && $_GET['page'] === 'wooimpex' && isset($_GET['reset'])) {
        delete_transient('wooimpex_csv_data');
        delete_transient('wooimpex_csv_headers');
        delete_transient('wooimpex_update_existing');
        wp_redirect(admin_url('admin.php?page=wooimpex'));
        exit;
    }
});
