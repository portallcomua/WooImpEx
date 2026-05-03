<?php
/**
 * Plugin Name: WooImpex Pro
 * Plugin URI: https://uaserver.pp.ua/
 * Description: Професійний інструмент для імпорту товарів з CSV в WooCommerce. Підтримує прості та варіативні товари, зображення, галерею, атрибути. Автоматичне зіставлення колонок.
 * Version: 2.0.1
 * Author: UAServer
 * Author URI: https://uaserver.pp.ua/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: wooimpex
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.0
 * WC requires at least: 4.0
 * WC tested up to: 9.0
 */

// Запобігаємо прямому доступу
if (!defined('ABSPATH')) {
    exit;
}

// Перевірка наявності WooCommerce
if (!class_exists('WooCommerce')) {
    add_action('admin_notices', function() {
        echo '<div class="error"><p><strong>WooImpex Pro</strong> потребує встановленого та активованого плагіну <strong>WooCommerce</strong>.</p></div>';
    });
    return;
}

// Головний клас плагіна
class WooImpexPro {
    
    private $woo_fields = [];
    private $plugin_version = '2.0.1';
    
    public function __construct() {
        $this->init_woo_fields();
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_post_wooimpex_upload_csv', [$this, 'handle_upload']);
        add_action('admin_post_wooimpex_import', [$this, 'handle_import']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
        add_action('admin_init', [$this, 'maybe_create_sample_files']);
    }
    
    /**
     * Створення файлів-прикладів при активації/оновленні
     */
    public function maybe_create_sample_files() {
        $sample_files = [
            'sample-products.csv' => '"Назва товару","Повний опис","Короткий опис","Артикул (SKU)","Ціна","Акційна ціна","Кількість","Статус складу","Вага (кг)","Довжина (см)","Ширина (см)","Висота (см)","Категорії (через /)","Теги (через ,)","Зображення (URL)","Галерея (URL через |)"
"Навушники JBL Tune 510BT","Бездротові навушники JBL з чистим звуком до 40 годин. Підтримка Bluetooth 5.0.","JBL Tune 510BT — якісний звук","JBL-510BT","1299","","50","instock","0.2","16","18","7","Електроніка/Аудіо/Навушники","бездротові,jbl","https://example.com/images/jbl-510bt.jpg","https://example.com/images/jbl-510bt-1.jpg|https://example.com/images/jbl-510bt-2.jpg"
"Мишка Logitech MX Master 3S","Ергономічна бездротова мишка для професіоналів. Безшумні кліки, 8000 DPI.","Logitech MX Master 3S — тихі кліки","LOG-MX3S","3899","3499","25","instock","0.15","12","8","4","Електроніка/Комп\'ютери/Мишки","логітек,ергономічна","https://example.com/images/logitech-mx3s.jpg","https://example.com/images/logitech-mx3s-1.jpg"',
            
            'sample-variable.csv' => '"Назва товару","Повний опис","Артикул (SKU)","Ціна","Кількість","Категорії (через /)","Атрибут:Розмір","Атрибут:Колір","Тип запису","Батьківський SKU"
"Футболка поло Premium","Якісна бавовняна футболка поло для повсякденного носіння.","POLO-PREMIUM","599","100","Одяг/Чоловікам/Футболки","S,M,L,XL","червоний,синій,чорний","parent",
"Футболка поло Premium - S червоний","","POLO-PREMIUM-S-RED","","15","","S","червоний","variation","POLO-PREMIUM"
"Футболка поло Premium - S синій","","POLO-PREMIUM-S-BLUE","","15","","S","синій","variation","POLO-PREMIUM"
"Футболка поло Premium - S чорний","","POLO-PREMIUM-S-BLACK","","15","","S","чорний","variation","POLO-PREMIUM"
"Футболка поло Premium - M червоний","","POLO-PREMIUM-M-RED","","15","","M","червоний","variation","POLO-PREMIUM"
"Футболка поло Premium - M синій","","POLO-PREMIUM-M-BLUE","","15","","M","синій","variation","POLO-PREMIUM"
"Футболка поло Premium - M чорний","","POLO-PREMIUM-M-BLACK","","15","","M","чорний","variation","POLO-PREMIUM"
"Футболка поло Premium - L червоний","","POLO-PREMIUM-L-RED","","15","","L","червоний","variation","POLO-PREMIUM"
"Футболка поло Premium - L синій","","POLO-PREMIUM-L-BLUE","","15","","L","синій","variation","POLO-PREMIUM"
"Футболка поло Premium - L чорний","","POLO-PREMIUM-L-BLACK","","15","","L","чорний","variation","POLO-PREMIUM"'
        ];
        
        $plugin_dir = plugin_dir_path(__FILE__);
        
        foreach ($sample_files as $filename => $content) {
            $file_path = $plugin_dir . $filename;
            if (!file_exists($file_path)) {
                file_put_contents($file_path, $content);
            }
        }
    }
    
    /**
     * Визначення всіх полів WooCommerce
     */
    private function init_woo_fields() {
        $this->woo_fields = [
            'post_title' => [
                'label' => 'Назва товару',
                'required' => true,
                'meta_key' => false,
                'description' => 'Заголовок товару'
            ],
            'post_content' => [
                'label' => 'Повний опис',
                'required' => false,
                'meta_key' => false,
                'description' => 'Детальний опис товару'
            ],
            'post_excerpt' => [
                'label' => 'Короткий опис',
                'required' => false,
                'meta_key' => false,
                'description' => 'Короткий опис/анонс'
            ],
            '_sku' => [
                'label' => 'Артикул (SKU)',
                'required' => false,
                'meta_key' => true,
                'description' => 'Унікальний артикул'
            ],
            'regular_price' => [
                'label' => 'Ціна',
                'required' => true,
                'meta_key' => true,
                'description' => 'Звичайна ціна'
            ],
            'sale_price' => [
                'label' => 'Акційна ціна',
                'required' => false,
                'meta_key' => true,
                'description' => 'Ціна зі знижкою'
            ],
            'stock' => [
                'label' => 'Кількість',
                'required' => false,
                'meta_key' => true,
                'description' => 'Кількість на складі'
            ],
            'stock_status' => [
                'label' => 'Статус складу',
                'required' => false,
                'meta_key' => true,
                'options' => ['instock' => 'В наявності', 'outofstock' => 'Немає в наявності'],
                'description' => 'instock або outofstock'
            ],
            'weight' => [
                'label' => 'Вага (кг)',
                'required' => false,
                'meta_key' => true,
                'description' => 'Вага в кілограмах'
            ],
            'length' => [
                'label' => 'Довжина (см)',
                'required' => false,
                'meta_key' => true,
                'description' => 'Довжина в сантиметрах'
            ],
            'width' => [
                'label' => 'Ширина (см)',
                'required' => false,
                'meta_key' => true,
                'description' => 'Ширина в сантиметрах'
            ],
            'height' => [
                'label' => 'Висота (см)',
                'required' => false,
                'meta_key' => true,
                'description' => 'Висота в сантиметрах'
            ],
            'product_cat' => [
                'label' => 'Категорії (через /)',
                'required' => false,
                'meta_key' => false,
                'description' => 'Вкладені категорії через /'
            ],
            'product_tag' => [
                'label' => 'Теги (через ,)',
                'required' => false,
                'meta_key' => false,
                'description' => 'Теги через кому'
            ],
            'image' => [
                'label' => 'Зображення (URL)',
                'required' => false,
                'meta_key' => false,
                'description' => 'Пряме посилання на головне фото'
            ],
            'gallery' => [
                'label' => 'Галерея (URL через |)',
                'required' => false,
                'meta_key' => false,
                'description' => 'Посилання на додаткові фото через |'
            ]
        ];
    }
    
    /**
     * Додавання сторінки в адмінку
     */
    public function add_admin_menu() {
        add_submenu_page(
            'woocommerce',
            'WooImpex Pro - Імпорт товарів',
            'WooImpex Pro',
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
            .wooimpex-wrap { margin: 20px 20px 0 0; }
            .wooimpex-header { margin-bottom: 20px; }
            .wooimpex-version { color: #777; font-size: 12px; margin-left: 10px; }
            .wooimpex-card { 
                background: #fff; 
                border: 1px solid #ccd0d4; 
                border-radius: 8px; 
                padding: 25px; 
                margin-bottom: 25px; 
                box-shadow: 0 1px 3px rgba(0,0,0,.05);
            }
            .wooimpex-card h2 { 
                margin-top: 0; 
                margin-bottom: 20px;
                padding-bottom: 10px;
                border-bottom: 2px solid #007cba;
                color: #1d2327;
            }
            .wooimpex-card h3 {
                margin-top: 0;
                margin-bottom: 15px;
                color: #1d2327;
            }
            .wooimpex-mapping-table { 
                width: 100%; 
                border-collapse: collapse; 
                background: #fff;
                margin: 20px 0;
                border-radius: 8px;
                overflow: hidden;
            }
            .wooimpex-mapping-table th, 
            .wooimpex-mapping-table td { 
                padding: 14px 12px; 
                border: 1px solid #e5e5e5; 
                text-align: left; 
                vertical-align: middle;
            }
            .wooimpex-mapping-table th { 
                background: #f6f7f7; 
                font-weight: 600;
            }
            .wooimpex-mapping-table tr:hover td {
                background: #f9f9f9;
            }
            .wooimpex-preview { 
                background: #fff; 
                border: 1px solid #ccd0d4;
                border-radius: 8px;
                padding: 20px; 
                margin-top: 20px;
                overflow: auto;
                max-height: 450px;
            }
            .wooimpex-preview h3 {
                margin-top: 0;
                margin-bottom: 15px;
            }
            .wooimpex-preview table {
                margin: 0;
                width: 100%;
            }
            .wooimpex-badge {
                background: #007cba;
                color: #fff;
                padding: 2px 8px;
                border-radius: 4px;
                font-size: 10px;
                margin-left: 8px;
            }
            .wooimpex-badge-required {
                background: #d63638;
            }
            .wooimpex-button-group {
                margin-top: 25px;
                display: flex;
                gap: 15px;
                align-items: center;
                flex-wrap: wrap;
            }
            .wooimpex-code {
                background: #f6f7f7;
                padding: 15px;
                border-left: 4px solid #007cba;
                font-family: monospace;
                overflow-x: auto;
                font-size: 13px;
                border-radius: 4px;
            }
            .wooimpex-sample-list {
                display: flex;
                gap: 20px;
                flex-wrap: wrap;
                margin: 15px 0;
            }
            .wooimpex-sample-item {
                background: #f6f7f7;
                padding: 12px 20px;
                border-radius: 8px;
                display: flex;
                align-items: center;
                gap: 12px;
            }
            .wooimpex-footer {
                text-align: center;
                margin-top: 30px;
                padding: 20px;
                color: #777;
                font-size: 12px;
                border-top: 1px solid #ddd;
            }
        ');
    }
    
    /**
     * Автоматичне зіставлення колонок
     */
    private function auto_match_columns($csv_headers) {
        $matches = [];
        
        foreach ($csv_headers as $header) {
            $clean_header = trim($header);
            $found = false;
            
            foreach ($this->woo_fields as $field_key => $field_info) {
                if ($clean_header === $field_info['label']) {
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
     * Отримання URL для скачування прикладів
     */
    private function get_sample_url($filename) {
        $file_path = plugin_dir_path(__FILE__) . $filename;
        
        if (file_exists($file_path)) {
            return plugin_dir_url(__FILE__) . $filename;
        }
        
        return '#';
    }
    
    /**
     * Перевірка чи існують файли-приклади
     */
    private function has_sample_files() {
        $plugin_dir = plugin_dir_path(__FILE__);
        return file_exists($plugin_dir . 'sample-products.csv') && 
               file_exists($plugin_dir . 'sample-variable.csv');
    }
    
    /**
     * Рендер сторінки імпорту
     */
    public function render_admin_page() {
        $csv_data = get_transient('wooimpex_csv_data');
        $csv_headers = get_transient('wooimpex_csv_headers');
        $auto_matches = $csv_headers ? $this->auto_match_columns($csv_headers) : [];
        
        ?>
        <div class="wrap wooimpex-wrap">
            <div class="wooimpex-header">
                <h1>🚀 WooImpex Pro <span class="wooimpex-version">v<?php echo $this->plugin_version; ?></span></h1>
                <p>Професійний інструмент для імпорту товарів з CSV у WooCommerce</p>
            </div>
            
            <?php if (isset($_GET['imported']) && $_GET['imported'] > 0): ?>
                <div class="notice notice-success is-dismissible">
                    <p>✅ <strong>Успішно імпортовано <?php echo intval($_GET['imported']); ?> товарів!</strong></p>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_GET['errors'])): ?>
                <div class="notice notice-error is-dismissible">
                    <p>⚠️ <strong>Помилки при імпорті:</strong><br><?php echo esc_html(str_replace('|', '<br>', urldecode($_GET['errors']))); ?></p>
                </div>
            <?php endif; ?>
            
            <?php if (!$csv_data): ?>
                <!-- КРОК 1: ЗАВАНТАЖЕННЯ CSV -->
                <div class="wooimpex-card">
                    <h2>📂 Крок 1: Завантажте CSV файл</h2>
                    
                    <form method="post" enctype="multipart/form-data" action="<?php echo admin_url('admin-post.php'); ?>">
                        <input type="hidden" name="action" value="wooimpex_upload_csv">
                        <?php wp_nonce_field('wooimpex_upload', 'wooimpex_nonce'); ?>
                        
                        <table class="form-table">
                            <tr>
                                <th scope="row">CSV файл:</th>
                                <td>
                                    <input type="file" name="csv_file" accept=".csv" style="padding: 6px;" required>
                                    <p class="description">Виберіть CSV файл з товарами для імпорту</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Оновлення товарів:</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="update_existing" value="1">
                                        <strong>🔄 Оновити існуючі товари</strong>
                                    </label>
                                    <p class="description">Якщо товар з таким SKU вже існує, він буде оновлений</p>
                                </td>
                            </tr>
                        </table>
                        
                        <div class="wooimpex-button-group">
                            <?php submit_button('📤 Завантажити CSV', 'primary', 'upload_csv', false); ?>
                        </div>
                    </form>
                </div>
                
                <!-- ПРИКЛАДИ CSV -->
                <?php if ($this->has_sample_files()): ?>
                <div class="wooimpex-card">
                    <h2>📥 Скачати приклади CSV файлів</h2>
                    <p>Використовуйте ці шаблони для швидкого старту. Вони вже містять правильні назви колонок та тестові дані.</p>
                    
                    <div class="wooimpex-sample-list">
                        <div class="wooimpex-sample-item">
                            <span style="font-size: 24px;">📄</span>
                            <div>
                                <strong>Простий товар</strong>
                                <div><a href="<?php echo esc_url($this->get_sample_url('sample-products.csv')); ?>" class="button button-small" download>⬇️ Завантажити</a></div>
                            </div>
                        </div>
                        <div class="wooimpex-sample-item">
                            <span style="font-size: 24px;">🔄</span>
                            <div>
                                <strong>Варіативний товар</strong>
                                <div><a href="<?php echo esc_url($this->get_sample_url('sample-variable.csv')); ?>" class="button button-small" download>⬇️ Завантажити</a></div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- ДОВІДКА -->
                <div class="wooimpex-card">
                    <h2>ℹ️ Як підготувати CSV файл</h2>
                    
                    <p><strong>✅ Правильні назви колонок (вони повинні збігатися точно):</strong></p>
                    <div class="wooimpex-code">
                        Назва товару, Повний опис, Короткий опис, Артикул (SKU), Ціна, Акційна ціна, Кількість, Статус складу, Вага (кг), Довжина (см), Ширина (см), Висота (см), Категорії (через /), Теги (через ,), Зображення (URL), Галерея (URL через |)
                    </div>
                    
                    <p style="margin-top: 20px;"><strong>📌 Важливі правила:</strong></p>
                    <ul style="list-style-type: disc; margin-left: 20px;">
                        <li>Роздільник колонок — <strong>кома (,)</strong></li>
                        <li>Файл має бути в кодуванні <strong>UTF-8</strong></li>
                        <li>Обов'язкові поля: <strong>Назва товару</strong> та <strong>Ціна</strong></li>
                        <li>Категорії вкладаються через <strong>слеш (/)</strong>, наприклад: <code>Електроніка/Аудіо/Навушники</code></li>
                        <li>Кілька тегів розділяються через <strong>кому (,)</strong></li>
                        <li>Кілька зображень в галереї розділяються через <strong>вертикальну риску (|)</strong></li>
                        <li>Текст, який містить коми, беріть у <strong>подвійні лапки</strong></li>
                    </ul>
                    
                    <p style="margin-top: 15px;"><strong>⚡ Швидкий приклад:</strong></p>
                    <div class="wooimpex-code">
                        Назва товару,Артикул (SKU),Ціна,Категорії (через /)<br>
                        Навушники JBL,JBL-001,1299,Електроніка/Аудіо
                    </div>
                </div>
                
            <?php else: ?>
                <!-- КРОК 2: ЗІСТАВЛЕННЯ КОЛОНОК -->
                <div class="wooimpex-card">
                    <h2>🔧 Крок 2: Зіставте колонки CSV з полями товару</h2>
                    <p>✅ <strong>Плагін автоматично підібрав відповідності</strong> за назвами колонок. Перевірте та натисніть "Почати імпорт".</p>
                    
                    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>">
                        <input type="hidden" name="action" value="wooimpex_import">
                        <?php wp_nonce_field('wooimpex_import', 'wooimpex_nonce'); ?>
                        
                        <table class="wooimpex-mapping-table">
                            <thead>
                                <tr>
                                    <th width="30%">📋 Колонка CSV</th>
                                    <th width="40%">🎯 Поле WooCommerce</th>
                                    <th width="30%">📝 Приклад значення</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($csv_headers as $index => $header): 
                                    $sample_value = isset($csv_data[1][$index]) ? esc_html(mb_substr($csv_data[1][$index], 0, 100)) : '';
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
                                                        <?php if ($field_info['required']): ?>
                                                            <span class="wooimpex-badge wooimpex-badge-required">обовʼязкове</span>
                                                        <?php endif; ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                            <?php if (isset($this->woo_fields[$selected]['description'])): ?>
                                                <p class="description" style="margin: 5px 0 0;"><?php echo esc_html($this->woo_fields[$selected]['description']); ?></p>
                                            <?php endif; ?>
                                        </td>
                                        <td style="color: #666; font-size: 13px; word-break: break-word; background: #f9f9f9;">
                                            <?php echo $sample_value ?: '(порожньо)'; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        
                        <p><span class="wooimpex-badge wooimpex-badge-required">обовʼязкове</span> — поля, які потрібно заповнити обов'язково</p>
                        
                        <input type="hidden" name="update_existing" value="<?php echo get_transient('wooimpex_update_existing') === '1' ? '1' : '0'; ?>">
                        
                        <div class="wooimpex-button-group">
                            <?php submit_button('🚀 Почати імпорт', 'primary', 'start_import', false); ?>
                            <a href="<?php echo admin_url('admin.php?page=wooimpex&reset=1'); ?>" class="button">
                                ↺ Завантажити інший файл
                            </a>
                        </div>
                    </form>
                </div>
                
                <!-- ПОПЕРЕДНІЙ ПЕРЕГЛЯД -->
                <div class="wooimpex-preview">
                    <h3>👁️ Попередній перегляд даних (перші 3 рядки)</h3>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <?php foreach ($csv_headers as $header): ?>
                                    <th><?php echo esc_html(mb_substr($header, 0, 25)); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php for ($i = 1; $i <= min(3, count($csv_data) - 1); $i++): ?>
                                <tr>
                                    <?php foreach ($csv_data[$i] as $cell): ?>
                                        <td><?php echo esc_html(mb_substr($cell, 0, 60) . (mb_strlen($cell) > 60 ? '…' : '')); ?>比较少
                                    <?php endforeach; ?>
                                </tr>
                            <?php endfor; ?>
                        </tbody>
                    </table>
                    <?php if (count($csv_data) > 4): ?>
                        <p style="margin: 10px 0 0; color: #777;">... та ще <?php echo count($csv_data) - 4; ?> рядків</p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            
            <div class="wooimpex-footer">
                <p>WooImpex Pro v<?php echo $this->plugin_version; ?> | Developed by <a href="https://uaserver.pp.ua/" target="_blank">UAServer</a> | <a href="#" target="_blank">Документація</a></p>
            </div>
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
        
        if (empty($csv_data) || count($csv_data) < 2) {
            wp_die('Не вдалося прочитати CSV файл або файл порожній');
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
                // Очищаємо кожне значення
                $row = array_map('trim', $row);
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
                if ($value === '' && $field_key !== 'stock_status') {
                    continue;
                }
                
                $is_meta = isset($this->woo_fields[$field_key]['meta_key']) && $this->woo_fields[$field_key]['meta_key'];
                
                if ($is_meta) {
                    $meta_data[$field_key] = $value;
                } else {
                    $product_data[$field_key] = $value;
                }
            }
            
            // Перевірка обов'язкових полів
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
                update_post_meta($product_id, '_regular_price', floatval($meta_value));
                update_post_meta($product_id, '_price', floatval($meta_value));
            } elseif ($meta_key === 'sale_price') {
                update_post_meta($product_id, '_sale_price', floatval($meta_value));
                update_post_meta($product_id, '_price', floatval($meta_value));
            } elseif ($meta_key === 'stock') {
                update_post_meta($product_id, '_stock', intval($meta_value));
                update_post_meta($product_id, '_manage_stock', 'yes');
            } elseif ($meta_key === 'stock_status') {
                $status = ($meta_value === 'instock' || $meta_value === 'outofstock') ? $meta_value : 'instock';
                update_post_meta($product_id, '_stock_status', $status);
            } elseif (in_array($meta_key, ['weight', 'length', 'width', 'height'])) {
                update_post_meta($product_id, '_' . $meta_key, floatval($meta_value));
            }
        }
        
        // Категорії
        if (!empty($product_data['product_cat'])) {
            $categories = explode('/', $product_data['product_cat']);
            $term_ids = [];
            $parent_id = 0;
            
            foreach ($categories as $cat_name) {
                $cat_name = trim($cat_name);
                if (empty($cat_name)) continue;
                
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
            $tags = array_filter($tags);
            if (!empty($tags)) {
                wp_set_object_terms($product_id, $tags, 'product_tag');
            }
        }
        
        // Зображення
        if (!empty($product_data['image'])) {
            $image_id = $this->upload_image_from_url($product_data['image'], $product_id);
            if ($image_id && !is_wp_error($image_id)) {
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
                    if ($image_id && !is_wp_error($image_id)) {
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

// При активації плагіна
register_activation_hook(__FILE__, function() {
    $plugin = new WooImpexPro();
    $plugin->maybe_create_sample_files();
});
