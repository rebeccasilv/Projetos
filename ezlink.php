<?php
/**
 * Plugin Name: EzLink - Sistema de Links Inteligente
 * Plugin URI: https://unity-rede.com
 * Description: Plugin para seleção de páginas/posts e integração com painel administrativo externo
 * Version: 3.1.0
 * Author: Rebecca Silva
 * Text Domain: ezlink
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.4
 * Requires PHP: 7.4
 * Network: false
 * License: GPL v2 or later
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Define constants
define('EZLINK_VERSION', '3.1.0');
define('EZLINK_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('EZLINK_PLUGIN_URL', plugin_dir_url(__FILE__));

/**
 * Main EzLink Class
 */
class EzLink {
    
    private static $instance = null;
    
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    private function __construct() {
        $this->init_hooks();
    }
    
    /**
     * Initialize WordPress hooks
     */
    private function init_hooks() {
        add_action('init', array($this, 'init'), 0);
        add_action('admin_menu', array($this, 'admin_menu'));
        add_action('wp_ajax_ezlink_sync_pages', array($this, 'ajax_sync_pages'));
        add_action('wp_ajax_ezlink_select_content', array($this, 'ajax_select_content'));
        add_action('wp_ajax_ezlink_generate_captcha', array($this, 'ajax_generate_captcha'));
        add_action('wp_ajax_nopriv_ezlink_generate_captcha', array($this, 'ajax_generate_captcha'));
        add_action('wp_ajax_ezlink_verify_captcha', array($this, 'ajax_verify_captcha'));
        add_action('wp_ajax_nopriv_ezlink_verify_captcha', array($this, 'ajax_verify_captcha'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
        add_action('template_redirect', array($this, 'handle_redirect_with_captcha'));
        
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Plugin initialization
     */
    public function init() {
        load_plugin_textdomain('ezlink', false, dirname(plugin_basename(__FILE__)) . '/languages');
        $this->create_database_tables();
    }
    
    /**
     * Create database tables
     */
    private function create_database_tables() {
        global $wpdb;
        
        $links_table = $wpdb->prefix . 'ezlink_links';
        $captcha_table = $wpdb->prefix . 'ezlink_captcha';
        $charset_collate = $wpdb->get_charset_collate();
        
        // Tabela de links
        $sql_links = "CREATE TABLE IF NOT EXISTS $links_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            original_url varchar(2083) NOT NULL,
            short_code varchar(50) NOT NULL,
            title varchar(255) DEFAULT '',
            clicks mediumint(9) DEFAULT 0,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            is_active tinyint(1) DEFAULT 1,
            post_id mediumint(9) DEFAULT NULL,
            post_type varchar(20) DEFAULT 'page',
            PRIMARY KEY (id),
            UNIQUE KEY short_code (short_code),
            KEY is_active (is_active),
            KEY created_at (created_at),
            KEY post_id (post_id)
        ) $charset_collate;";
        
        // Tabela de captcha
        $sql_captcha = "CREATE TABLE IF NOT EXISTS $captcha_table (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            session_id varchar(100) NOT NULL,
            question varchar(255) NOT NULL,
            answer varchar(10) NOT NULL,
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            expires_at datetime NOT NULL,
            is_used tinyint(1) DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY session_id (session_id),
            KEY expires_at (expires_at)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql_links);
        dbDelta($sql_captcha);
        
        update_option('ezlink_db_version', '3.1');
    }
    
    /**
     * Add admin menu
     */
    public function admin_menu() {
        add_menu_page(
            'EzLink',
            'EzLink',
            'manage_options',
            'ezlink',
            array($this, 'admin_page'),
            'dashicons-admin-links',
            30
        );
        
        add_submenu_page(
            'ezlink',
            'Dashboard',
            'Dashboard',
            'manage_options',
            'ezlink',
            array($this, 'admin_page')
        );
        
        add_submenu_page(
            'ezlink',
            'Seleção de Conteúdo',
            'Conteúdo',
            'manage_options',
            'ezlink-content',
            array($this, 'content_selection_page')
        );
        
        add_submenu_page(
            'ezlink',
            'Configurações',
            'Configurações',
            'manage_options',
            'ezlink-settings',
            array($this, 'settings_page')
        );
        
        add_submenu_page(
            'ezlink',
            'API Sync',
            'API Sync',
            'manage_options',
            'ezlink-sync',
            array($this, 'sync_page')
        );
    }
    
    /**
     * Admin dashboard page
     */
    public function admin_page() {
        $stats = $this->get_stats();
        $selected_content = $this->get_selected_content_stats();
        ?>
        <div class="wrap ezlink-admin">
            <div class="ezlink-header">
                <h1>EzLink v<?php echo EZLINK_VERSION; ?></h1>
                <p class="subtitle">Sistema de Links Inteligente - Integração com Painel Externo</p>
            </div>
            
            <div class="ezlink-dashboard-grid">
                <div class="ezlink-card">
                    <div class="ezlink-card-header">
                        <h2>Estatísticas Gerais</h2>
                    </div>
                    <div class="ezlink-card-body">
                        <div class="ezlink-stats">
                            <div class="stat-item">
                                <span class="stat-label">Versão:</span>
                                <span class="stat-value"><?php echo EZLINK_VERSION; ?></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Total Links:</span>
                                <span class="stat-value"><?php echo $stats['total_links']; ?></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Total Cliques:</span>
                                <span class="stat-value"><?php echo $stats['total_clicks']; ?></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Uso de Memória:</span>
                                <span class="stat-value"><?php echo $this->get_memory_usage(); ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="ezlink-card">
                    <div class="ezlink-card-header">
                        <h2>Conteúdo Selecionado</h2>
                    </div>
                    <div class="ezlink-card-body">
                        <div class="ezlink-stats">
                            <div class="stat-item">
                                <span class="stat-label">Páginas Disponíveis:</span>
                                <span class="stat-value"><?php echo $selected_content['pages']; ?></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Posts Disponíveis:</span>
                                <span class="stat-value"><?php echo $selected_content['posts']; ?></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Total para Sync:</span>
                                <span class="stat-value"><?php echo $selected_content['total']; ?></span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-label">Captcha:</span>
                                <span class="stat-value"><?php echo get_option('ezlink_captcha_enabled', '0') === '1' ? 'Ativo' : 'Inativo'; ?></span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="ezlink-card">
                    <div class="ezlink-card-header">
                        <h2>Ações Rápidas</h2>
                    </div>
                    <div class="ezlink-card-body">
                        <div class="quick-actions">
                            <a href="<?php echo admin_url('admin.php?page=ezlink-content'); ?>" class="button button-primary">Selecionar Conteúdo</a>
                            <a href="<?php echo admin_url('admin.php?page=ezlink-sync'); ?>" class="button button-secondary">Testar API</a>
                            <a href="<?php echo admin_url('admin.php?page=ezlink-settings'); ?>" class="button">Configurações</a>
                        </div>
                    </div>
                </div>
                
                <div class="ezlink-card full-width">
                    <div class="ezlink-card-header">
                        <h2>Conteúdo Recente</h2>
                    </div>
                    <div class="ezlink-card-body">
                        <div id="recent-content-table">
                            <?php $this->render_recent_content(); ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Content selection page
     */
    public function content_selection_page() {
        if (isset($_POST['submit_selection'])) {
            $this->save_content_selection();
            echo '<div class="notice notice-success"><p>Seleção de conteúdo salva com sucesso!</p></div>';
        }
        
        $selected_pages = get_option('ezlink_selected_pages', array());
        $selected_posts = get_option('ezlink_selected_posts', array());
        ?>
        <div class="wrap ezlink-admin">
            <div class="ezlink-header">
                <h1>Seleção de Conteúdo</h1>
                <p class="subtitle">Escolha quais páginas e posts ficarão disponíveis no painel externo</p>
            </div>
            
            <form method="post" action="">
                <div class="ezlink-selection-grid">
                    <div class="ezlink-card">
                        <div class="ezlink-card-header">
                            <h2>Páginas Disponíveis</h2>
                            <button type="button" id="select-all-pages" class="button button-small">Selecionar Todas</button>
                        </div>
                        <div class="ezlink-card-body">
                            <div class="content-selection-list" style="max-height: 400px; overflow-y: auto;">
                                <?php $this->render_pages_selection($selected_pages); ?>
                            </div>
                        </div>
                    </div>
                    
                    <div class="ezlink-card">
                        <div class="ezlink-card-header">
                            <h2>Posts Disponíveis</h2>
                            <button type="button" id="select-all-posts" class="button button-small">Selecionar Todos</button>
                        </div>
                        <div class="ezlink-card-body">
                            <div class="content-selection-list" style="max-height: 400px; overflow-y: auto;">
                                <?php $this->render_posts_selection($selected_posts); ?>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="ezlink-card">
                    <div class="ezlink-card-header">
                        <h2>Opções de Sincronização</h2>
                    </div>
                    <div class="ezlink-card-body">
                        <table class="form-table">
                            <tr>
                                <th scope="row">Sincronização Automática</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="ezlink_auto_sync" value="1" <?php checked(get_option('ezlink_auto_sync', '0'), '1'); ?>>
                                        Sincronizar automaticamente novo conteúdo
                                    </label>
                                    <p class="description">Novos posts/páginas serão automaticamente incluídos na seleção</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Gerar Links Automaticamente</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="ezlink_auto_generate_links" value="1" <?php checked(get_option('ezlink_auto_generate_links', '0'), '1'); ?>>
                                        Gerar links curtos automaticamente para conteúdo selecionado
                                    </label>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <p class="submit">
                    <input type="submit" name="submit_selection" class="button-primary" value="Salvar Seleção">
                    <button type="button" id="generate-links-btn" class="button button-secondary">Gerar Links para Selecionados</button>
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * Settings page
     */
    public function settings_page() {
        if (isset($_POST['submit'])) {
            $this->save_settings();
            echo '<div class="notice notice-success"><p>Configurações salvas com sucesso!</p></div>';
        }
        
        $api_domain = get_option('ezlink_api_domain', 'https://unity-rede.com');
        $batch_size = get_option('ezlink_batch_size', 50);
        $cache_duration = get_option('ezlink_cache_duration', 300);
        $enable_logging = get_option('ezlink_enable_logging', '0');
        $captcha_enabled = get_option('ezlink_captcha_enabled', '0');
        $captcha_timeout = get_option('ezlink_captcha_timeout', '60');
        ?>
        <div class="wrap ezlink-admin">
            <div class="ezlink-header">
                <h1>Configurações EzLink</h1>
                <p class="subtitle">Configure as opções do plugin</p>
            </div>
            
            <form method="post" action="">
                <div class="ezlink-card">
                    <div class="ezlink-card-header">
                        <h2>API e Integração</h2>
                    </div>
                    <div class="ezlink-card-body">
                        <table class="form-table">
                            <tr>
                                <th scope="row">Domínio da API</th>
                                <td>
                                    <input type="url" name="ezlink_api_domain" value="<?php echo esc_attr($api_domain); ?>" class="regular-text" required>
                                    <p class="description">URL do painel administrativo externo</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Tamanho do Lote</th>
                                <td>
                                    <input type="number" name="ezlink_batch_size" value="<?php echo esc_attr($batch_size); ?>" min="10" max="100" class="small-text">
                                    <p class="description">Número de itens processados por vez</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Duração do Cache</th>
                                <td>
                                    <input type="number" name="ezlink_cache_duration" value="<?php echo esc_attr($cache_duration); ?>" min="60" class="small-text">
                                    <p class="description">Duração do cache em segundos</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div class="ezlink-card">
                    <div class="ezlink-card-header">
                        <h2>Sistema de Captcha</h2>
                    </div>
                    <div class="ezlink-card-body">
                        <table class="form-table">
                            <tr>
                                <th scope="row">Ativar Captcha</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="ezlink_captcha_enabled" value="1" <?php checked($captcha_enabled, '1'); ?>>
                                        Ativar verificação anti-robô com captcha matemático
                                    </label>
                                    <p class="description">Usuários precisarão resolver uma operação matemática simples antes do redirecionamento</p>
                                </td>
                            </tr>
                            <tr>
                                <th scope="row">Timeout do Captcha</th>
                                <td>
                                    <input type="number" name="ezlink_captcha_timeout" value="<?php echo esc_attr($captcha_timeout); ?>" min="30" max="300" class="small-text">
                                    <p class="description">Tempo limite em segundos para resolver o captcha</p>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <div class="ezlink-card">
                    <div class="ezlink-card-header">
                        <h2>Sistema e Debug</h2>
                    </div>
                    <div class="ezlink-card-body">
                        <table class="form-table">
                            <tr>
                                <th scope="row">Ativar Logs</th>
                                <td>
                                    <label>
                                        <input type="checkbox" name="ezlink_enable_logging" value="1" <?php checked($enable_logging, '1'); ?>>
                                        Ativar sistema de logs para debug
                                    </label>
                                </td>
                            </tr>
                        </table>
                    </div>
                </div>
                
                <p class="submit">
                    <input type="submit" name="submit" class="button-primary" value="Salvar Configurações">
                </p>
            </form>
        </div>
        <?php
    }
    
    /**
     * API Sync page
     */
    public function sync_page() {
        $sync_url = get_rest_url(null, 'ezlink/v1/sync-pages');
        $selected_stats = $this->get_selected_content_stats();
        ?>
        <div class="wrap ezlink-admin">
            <div class="ezlink-header">
                <h1>API Sync</h1>
                <p class="subtitle">Informações da API para integração externa</p>
            </div>
            
            <div class="ezlink-card">
                <div class="ezlink-card-header">
                    <h2>Informações da API</h2>
                </div>
                <div class="ezlink-card-body">
                    <div class="api-info">
                        <div class="api-item">
                            <strong>Endpoint URL:</strong>
                            <div class="api-value">
                                <code><?php echo esc_html($sync_url); ?></code>
                                <button type="button" class="button button-small copy-btn" data-copy="<?php echo esc_attr($sync_url); ?>">Copiar</button>
                            </div>
                        </div>
                        <div class="api-item">
                            <strong>Método:</strong>
                            <span class="method-badge">GET</span>
                        </div>
                        <div class="api-item">
                            <strong>Páginas Disponíveis:</strong>
                            <span class="stat-value"><?php echo $selected_stats['total']; ?></span>
                        </div>
                        <div class="api-item">
                            <strong>Status:</strong>
                            <span class="status-active">Operacional</span>
                        </div>
                        <div class="api-item">
                            <strong>Captcha:</strong>
                            <span class="<?php echo get_option('ezlink_captcha_enabled', '0') === '1' ? 'status-active' : 'status-inactive'; ?>">
                                <?php echo get_option('ezlink_captcha_enabled', '0') === '1' ? 'Ativado' : 'Desativado'; ?>
                            </span>
                        </div>
                    </div>
                    
                    <div class="api-actions">
                        <button type="button" id="test-api-btn" class="button button-primary">Testar Endpoint</button>
                        <a href="<?php echo esc_url($sync_url); ?>" target="_blank" class="button button-secondary">Abrir no Navegador</a>
                        <button type="button" id="test-captcha-btn" class="button">Testar Captcha</button>
                    </div>
                    
                    <div id="test-result" class="test-result" style="display: none;"></div>
                </div>
            </div>
            
            <div class="ezlink-card">
                <div class="ezlink-card-header">
                    <h2>Estrutura de Resposta da API</h2>
                </div>
                <div class="ezlink-card-body">
                    <pre><code>{
  "success": true,
  "message": "Sync completado com sucesso",
  "data": {
    "pages": [
      {
        "id": 123,
        "title": "Título da Página",
        "url": "https://site.com/pagina",
        "content": "Resumo do conteúdo...",
        "modified": "2024-01-01 12:00:00",
        "status": "active",
        "type": "page"
      }
    ],
    "total": 15,
    "plugin_version": "<?php echo EZLINK_VERSION; ?>",
    "site_url": "<?php echo get_site_url(); ?>",
    "site_name": "<?php echo get_bloginfo('name'); ?>",
    "captcha_enabled": <?php echo get_option('ezlink_captcha_enabled', '0') === '1' ? 'true' : 'false'; ?>,
    "timestamp": "2024-01-01T12:00:00+00:00"
  }
}</code></pre>
                </div>
            </div>
        </div>
        <?php
    }
    
    /**
     * Register REST API routes
     */
    public function register_rest_routes() {
        register_rest_route('ezlink/v1', '/sync-pages', array(
            'methods' => 'GET',
            'callback' => array($this, 'rest_sync_pages'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('ezlink/v1', '/captcha/generate', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_generate_captcha'),
            'permission_callback' => '__return_true'
        ));
        
        register_rest_route('ezlink/v1', '/captcha/verify', array(
            'methods' => 'POST',
            'callback' => array($this, 'rest_verify_captcha'),
            'permission_callback' => '__return_true'
        ));
    }
    
    /**
     * REST API endpoint for page sync
     */
    public function rest_sync_pages($request) {
        try {
            $pages = $this->get_selected_pages_for_sync();
            
            return new WP_REST_Response(array(
                'success' => true,
                'message' => 'Sync completado com sucesso',
                'data' => array(
                    'pages' => $pages,
                    'total' => count($pages),
                    'plugin_version' => EZLINK_VERSION,
                    'site_url' => get_site_url(),
                    'site_name' => get_bloginfo('name'),
                    'captcha_enabled' => get_option('ezlink_captcha_enabled', '0') === '1',
                    'timestamp' => current_time('c')
                )
            ), 200);
            
        } catch (Exception $e) {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Erro interno do servidor',
                'error' => $e->getMessage(),
                'timestamp' => current_time('c')
            ), 500);
        }
    }
    
    /**
     * Generate captcha via REST API
     */
    public function rest_generate_captcha($request) {
        if (get_option('ezlink_captcha_enabled', '0') !== '1') {
            return new WP_REST_Response(array(
                'success' => false,
                'message' => 'Captcha não está ativado'
            ), 400);
        }
        
        return new WP_REST_Response($this->generate_captcha(), 200);
    }
    
    /**
     * Verify captcha via REST API
     */
    public function rest_verify_captcha($request) {
        $session_id = $request->get_param('session_id');
        $answer = $request->get_param('answer');
        
        $result = $this->verify_captcha($session_id, $answer);
        
        return new WP_REST_Response($result, $result['success'] ? 200 : 400);
    }
    
    /**
     * Get selected pages for sync (only selected content)
     */
    private function get_selected_pages_for_sync() {
        $cache_key = 'ezlink_selected_sync_pages';
        $cached = get_transient($cache_key);
        
        if ($cached !== false) {
            return $cached;
        }
        
        $selected_pages = get_option('ezlink_selected_pages', array());
        $selected_posts = get_option('ezlink_selected_posts', array());
        $pages = array();
        
        // Process selected pages
        if (!empty($selected_pages)) {
            $page_args = array(
                'post_type' => 'page',
                'post_status' => 'publish',
                'post__in' => $selected_pages,
                'posts_per_page' => -1
            );
            
            $page_query = new WP_Query($page_args);
            
            if ($page_query->have_posts()) {
                while ($page_query->have_posts()) {
                    $page_query->the_post();
                    $pages[] = array(
                        'id' => get_the_ID(),
                        'title' => get_the_title(),
                        'url' => get_permalink(),
                        'content' => wp_trim_words(get_the_content(), 20),
                        'modified' => get_the_modified_date('Y-m-d H:i:s'),
                        'status' => 'active',
                        'type' => 'page'
                    );
                }
            }
            wp_reset_postdata();
        }
        
        // Process selected posts
        if (!empty($selected_posts)) {
            $post_args = array(
                'post_type' => 'post',
                'post_status' => 'publish',
                'post__in' => $selected_posts,
                'posts_per_page' => -1
            );
            
            $post_query = new WP_Query($post_args);
            
            if ($post_query->have_posts()) {
                while ($post_query->have_posts()) {
                    $post_query->the_post();
                    $pages[] = array(
                        'id' => get_the_ID(),
                        'title' => get_the_title(),
                        'url' => get_permalink(),
                        'content' => wp_trim_words(get_the_content(), 20),
                        'modified' => get_the_modified_date('Y-m-d H:i:s'),
                        'status' => 'active',
                        'type' => 'post'
                    );
                }
            }
            wp_reset_postdata();
        }
        
        // Cache results
        $cache_duration = get_option('ezlink_cache_duration', 300);
        set_transient($cache_key, $pages, $cache_duration);
        
        return $pages;
    }
    
/**
     * Generate mathematical captcha
     */
    private function generate_captcha() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ezlink_captcha';
        
        // Generate simple math problem
        $num1 = rand(1, 10);
        $num2 = rand(1, 10);
        $operations = ['+', '-'];
        $operation = $operations[array_rand($operations)];
        
        if ($operation === '+') {
            $answer = $num1 + $num2;
            $question = "$num1 + $num2 = ?";
        } else {
            // Ensure positive result for subtraction
            if ($num1 < $num2) {
                $temp = $num1;
                $num1 = $num2;
                $num2 = $temp;
            }
            $answer = $num1 - $num2;
            $question = "$num1 - $num2 = ?";
        }
        
        // Generate unique session ID
        $session_id = 'captcha_' . uniqid() . '_' . time();
        
        // Calculate expiration time
        $timeout = get_option('ezlink_captcha_timeout', '60');
        $expires_at = date('Y-m-d H:i:s', time() + intval($timeout));
        
        // Clean old captchas
        $wpdb->delete(
            $table_name,
            array('expires_at <' => current_time('mysql')),
            array('%s')
        );
        
        // Insert new captcha
        $result = $wpdb->insert(
            $table_name,
            array(
                'session_id' => $session_id,
                'question' => $question,
                'answer' => strval($answer),
                'created_at' => current_time('mysql'),
                'expires_at' => $expires_at,
                'is_used' => 0
            ),
            array('%s', '%s', '%s', '%s', '%s', '%d')
        );
        
        if ($result) {
            return array(
                'success' => true,
                'session_id' => $session_id,
                'question' => $question,
                'expires_in' => intval($timeout),
                'expires_at' => $expires_at
            );
        }
        
        return array(
            'success' => false,
            'message' => 'Erro ao gerar captcha'
        );
    }
    
    /**
     * Verify captcha answer
     */
    private function verify_captcha($session_id, $answer) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ezlink_captcha';
        
        // Find captcha
        $captcha = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE session_id = %s AND is_used = 0 AND expires_at > %s",
            $session_id,
            current_time('mysql')
        ));
        
        if (!$captcha) {
            return array(
                'success' => false,
                'message' => 'Captcha expirado ou inválido'
            );
        }
        
        // Check answer
        if (trim($answer) === trim($captcha->answer)) {
            // Mark as used
            $wpdb->update(
                $table_name,
                array('is_used' => 1),
                array('id' => $captcha->id),
                array('%d'),
                array('%d')
            );
            
            return array(
                'success' => true,
                'message' => 'Captcha verificado com sucesso'
            );
        }
        
        return array(
            'success' => false,
            'message' => 'Resposta incorreta'
        );
    }
    
    /**
     * Handle redirect with captcha verification
     */
    public function handle_redirect_with_captcha() {
        if (!isset($_GET['ezlink_redirect'])) {
            return;
        }
        
        $short_code = sanitize_text_field($_GET['ezlink_redirect']);
        
        global $wpdb;
        $table_name = $wpdb->prefix . 'ezlink_links';
        
        $link = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE short_code = %s AND is_active = 1",
            $short_code
        ));
        
        if (!$link) {
            wp_die('Link não encontrado ou inativo.');
        }
        
        // Check if captcha is enabled
        if (get_option('ezlink_captcha_enabled', '0') === '1') {
            // Check if captcha was already verified
            if (!isset($_GET['captcha_verified']) || $_GET['captcha_verified'] !== '1') {
                $this->show_captcha_page($short_code, $link);
                return;
            }
        }
        
        // Update click count
        $wpdb->update(
            $table_name,
            array('clicks' => $link->clicks + 1),
            array('id' => $link->id),
            array('%d'),
            array('%d')
        );
        
        // Redirect to original URL
        wp_redirect($link->original_url, 301);
        exit;
    }
    
    /**
     * Show captcha verification page
     */
    private function show_captcha_page($short_code, $link) {
        $site_title = get_bloginfo('name');
        $captcha_data = $this->generate_captcha();
        
        if (!$captcha_data['success']) {
            wp_die('Erro ao gerar captcha. Tente novamente.');
        }
        
        ?>
        <!DOCTYPE html>
        <html <?php language_attributes(); ?>>
        <head>
            <meta charset="<?php bloginfo('charset'); ?>">
            <meta name="viewport" content="width=device-width, initial-scale=1">
            <title>Verificação de Segurança - <?php echo esc_html($site_title); ?></title>
            <style>
                body { 
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    margin: 0;
                    padding: 20px;
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                }
                .captcha-container {
                    background: white;
                    border-radius: 10px;
                    padding: 40px;
                    box-shadow: 0 10px 30px rgba(0,0,0,0.2);
                    text-align: center;
                    max-width: 400px;
                    width: 100%;
                }
                .captcha-title {
                    color: #333;
                    margin-bottom: 20px;
                    font-size: 24px;
                    font-weight: 600;
                }
                .captcha-description {
                    color: #666;
                    margin-bottom: 30px;
                    line-height: 1.5;
                }
                .captcha-question {
                    background: #f8f9fa;
                    border: 2px solid #e9ecef;
                    border-radius: 8px;
                    padding: 20px;
                    margin-bottom: 20px;
                    font-size: 20px;
                    font-weight: bold;
                    color: #333;
                }
                .captcha-input {
                    width: 100%;
                    padding: 15px;
                    border: 2px solid #e9ecef;
                    border-radius: 8px;
                    font-size: 18px;
                    text-align: center;
                    margin-bottom: 20px;
                    box-sizing: border-box;
                }
                .captcha-input:focus {
                    outline: none;
                    border-color: #667eea;
                }
                .captcha-button {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    border: none;
                    padding: 15px 30px;
                    border-radius: 8px;
                    font-size: 16px;
                    font-weight: 600;
                    cursor: pointer;
                    width: 100%;
                    transition: transform 0.2s;
                }
                .captcha-button:hover {
                    transform: translateY(-2px);
                }
                .captcha-button:disabled {
                    opacity: 0.6;
                    cursor: not-allowed;
                    transform: none;
                }
                .error-message {
                    color: #dc3545;
                    margin-top: 15px;
                    padding: 10px;
                    background: #f8d7da;
                    border-radius: 5px;
                    display: none;
                }
                .loading {
                    display: none;
                    margin-top: 15px;
                }
                .timer {
                    color: #666;
                    font-size: 14px;
                    margin-top: 15px;
                }
                @media (max-width: 480px) {
                    .captcha-container { padding: 20px; }
                    .captcha-title { font-size: 20px; }
                }
            </style>
        </head>
        <body>
            <div class="captcha-container">
                <h1 class="captcha-title">🔒 Verificação de Segurança</h1>
                <p class="captcha-description">
                    Para continuar, resolva a operação matemática abaixo.<br>
                    Isso nos ajuda a verificar que você não é um robô.
                </p>
                
                <div class="captcha-question"><?php echo esc_html($captcha_data['question']); ?></div>
                
                <form id="captcha-form">
                    <input type="hidden" id="session-id" value="<?php echo esc_attr($captcha_data['session_id']); ?>">
                    <input type="hidden" id="short-code" value="<?php echo esc_attr($short_code); ?>">
                    
                    <input type="number" id="captcha-answer" class="captcha-input" 
                           placeholder="Digite sua resposta" required autofocus>
                    
                    <button type="submit" class="captcha-button" id="verify-btn">
                        Verificar e Continuar
                    </button>
                </form>
                
                <div class="error-message" id="error-message"></div>
                <div class="loading" id="loading">Verificando...</div>
                <div class="timer" id="timer">Tempo restante: <span id="countdown"><?php echo esc_html($captcha_data['expires_in']); ?></span>s</div>
            </div>
            
            <script>
                // Timer countdown
                let timeLeft = <?php echo intval($captcha_data['expires_in']); ?>;
                const countdown = document.getElementById('countdown');
                const timer = setInterval(() => {
                    timeLeft--;
                    countdown.textContent = timeLeft;
                    if (timeLeft <= 0) {
                        clearInterval(timer);
                        document.getElementById('error-message').style.display = 'block';
                        document.getElementById('error-message').textContent = 'Tempo esgotado. Recarregue a página.';
                        document.getElementById('verify-btn').disabled = true;
                    }
                }, 1000);
                
                // Form submission
                document.getElementById('captcha-form').addEventListener('submit', async function(e) {
                    e.preventDefault();
                    
                    const sessionId = document.getElementById('session-id').value;
                    const shortCode = document.getElementById('short-code').value;
                    const answer = document.getElementById('captcha-answer').value;
                    const errorDiv = document.getElementById('error-message');
                    const loadingDiv = document.getElementById('loading');
                    const verifyBtn = document.getElementById('verify-btn');
                    
                    if (!answer) {
                        errorDiv.style.display = 'block';
                        errorDiv.textContent = 'Por favor, digite uma resposta.';
                        return;
                    }
                    
                    errorDiv.style.display = 'none';
                    loadingDiv.style.display = 'block';
                    verifyBtn.disabled = true;
                    
                    try {
                        const response = await fetch('<?php echo get_rest_url(null, "ezlink/v1/captcha/verify"); ?>', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                            },
                            body: JSON.stringify({
                                session_id: sessionId,
                                answer: answer
                            })
                        });
                        
                        const result = await response.json();
                        
                        if (result.success) {
                            clearInterval(timer);
                            window.location.href = '<?php echo home_url(); ?>/' + shortCode + '?captcha_verified=1';
                        } else {
                            errorDiv.style.display = 'block';
                            errorDiv.textContent = result.message || 'Resposta incorreta. Tente novamente.';
                            document.getElementById('captcha-answer').value = '';
                            document.getElementById('captcha-answer').focus();
                        }
                    } catch (error) {
                        errorDiv.style.display = 'block';
                        errorDiv.textContent = 'Erro de conexão. Tente novamente.';
                    }
                    
                    loadingDiv.style.display = 'none';
                    verifyBtn.disabled = false;
                });
                
                // Auto-focus on input
                document.getElementById('captcha-answer').focus();
            </script>
        </body>
        </html>
        <?php
        exit;
    }
    
    /**
     * AJAX handler for captcha generation
     */
    public function ajax_generate_captcha() {
        check_ajax_referer('ezlink_nonce', 'nonce');
        
        $result = $this->generate_captcha();
        wp_send_json($result);
    }
    
    /**
     * AJAX handler for captcha verification
     */
    public function ajax_verify_captcha() {
        check_ajax_referer('ezlink_nonce', 'nonce');
        
        $session_id = sanitize_text_field($_POST['session_id']);
        $answer = sanitize_text_field($_POST['answer']);
        
        $result = $this->verify_captcha($session_id, $answer);
        wp_send_json($result);
    }
    
    /**
     * AJAX handler for content selection
     */
    public function ajax_select_content() {
        check_ajax_referer('ezlink_nonce', 'nonce');
        
        if (!current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        
        $action_type = sanitize_text_field($_POST['action_type']);
        
        if ($action_type === 'generate_links') {
            $generated = $this->generate_links_for_selected_content();
            wp_send_json_success(array(
                'message' => "Links gerados com sucesso!",
                'generated_count' => $generated['count'],
                'details' => $generated['details']
            ));
        }
        
        wp_send_json_error('Ação não reconhecida');
    }
    
    /**
     * Generate links for selected content
     */
    private function generate_links_for_selected_content() {
        global $wpdb;
        
        $selected_pages = get_option('ezlink_selected_pages', array());
        $selected_posts = get_option('ezlink_selected_posts', array());
        $table_name = $wpdb->prefix . 'ezlink_links';
        $generated_count = 0;
        $details = array();
        
        // Generate links for selected pages
        foreach ($selected_pages as $page_id) {
            $page = get_post($page_id);
            if ($page && $page->post_status === 'publish') {
                // Check if link already exists
                $existing = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $table_name WHERE post_id = %d AND post_type = 'page'",
                    $page_id
                ));
                
                if (!$existing) {
                    $short_code = $this->generate_unique_short_code();
                    $result = $wpdb->insert(
                        $table_name,
                        array(
                            'original_url' => get_permalink($page_id),
                            'short_code' => $short_code,
                            'title' => $page->post_title,
                            'post_id' => $page_id,
                            'post_type' => 'page',
                            'created_at' => current_time('mysql'),
                            'is_active' => 1,
                            'clicks' => 0
                        ),
                        array('%s', '%s', '%s', '%d', '%s', '%s', '%d', '%d')
                    );
                    
                    if ($result) {
                        $generated_count++;
                        $details[] = array(
                            'title' => $page->post_title,
                            'short_code' => $short_code,
                            'type' => 'page'
                        );
                    }
                }
            }
        }
        
        // Generate links for selected posts
        foreach ($selected_posts as $post_id) {
            $post = get_post($post_id);
            if ($post && $post->post_status === 'publish') {
                // Check if link already exists
                $existing = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $table_name WHERE post_id = %d AND post_type = 'post'",
                    $post_id
                ));
                
                if (!$existing) {
                    $short_code = $this->generate_unique_short_code();
                    $result = $wpdb->insert(
                        $table_name,
                        array(
                            'original_url' => get_permalink($post_id),
                            'short_code' => $short_code,
                            'title' => $post->post_title,
                            'post_id' => $post_id,
                            'post_type' => 'post',
                            'created_at' => current_time('mysql'),
                            'is_active' => 1,
                            'clicks' => 0
                        ),
                        array('%s', '%s', '%s', '%d', '%s', '%s', '%d', '%d')
                    );
                    
                    if ($result) {
                        $generated_count++;
                        $details[] = array(
                            'title' => $post->post_title,
                            'short_code' => $short_code,
                            'type' => 'post'
                        );
                    }
                }
            }
        }
        
        return array(
            'count' => $generated_count,
            'details' => $details
        );
    }
    
    /**
     * Generate unique short code
     */
    private function generate_unique_short_code($length = 7) {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ezlink_links';
        $characters = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        
        do {
            $code = '';
            for ($i = 0; $i < $length; $i++) {
                $code .= $characters[wp_rand(0, strlen($characters) - 1)];
            }
            
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_name WHERE short_code = %s",
                $code
            ));
        } while ($exists);
        
        return $code;
    }
    
    /**
     * Save content selection
     */
    private function save_content_selection() {
        $selected_pages = isset($_POST['selected_pages']) ? array_map('intval', $_POST['selected_pages']) : array();
        $selected_posts = isset($_POST['selected_posts']) ? array_map('intval', $_POST['selected_posts']) : array();
        
        update_option('ezlink_selected_pages', $selected_pages);
        update_option('ezlink_selected_posts', $selected_posts);
        update_option('ezlink_auto_sync', isset($_POST['ezlink_auto_sync']) ? '1' : '0');
        update_option('ezlink_auto_generate_links', isset($_POST['ezlink_auto_generate_links']) ? '1' : '0');
        
        // Auto-generate links if option is enabled
        if (isset($_POST['ezlink_auto_generate_links'])) {
            $this->generate_links_for_selected_content();
        }
        
        // Clear cache
        delete_transient('ezlink_selected_sync_pages');
    }
    
    /**
     * Save settings
     */
    private function save_settings() {
        update_option('ezlink_api_domain', sanitize_url($_POST['ezlink_api_domain']));
        update_option('ezlink_batch_size', intval($_POST['ezlink_batch_size']));
        update_option('ezlink_cache_duration', intval($_POST['ezlink_cache_duration']));
        update_option('ezlink_enable_logging', isset($_POST['ezlink_enable_logging']) ? '1' : '0');
        update_option('ezlink_captcha_enabled', isset($_POST['ezlink_captcha_enabled']) ? '1' : '0');
        update_option('ezlink_captcha_timeout', intval($_POST['ezlink_captcha_timeout']));
    }
    
    /**
     * Render pages selection checkboxes
     */
    private function render_pages_selection($selected_pages) {
        $pages = get_pages(array(
            'post_status' => 'publish',
            'sort_column' => 'post_title'
        ));
        
        foreach ($pages as $page) {
            $checked = in_array($page->ID, $selected_pages) ? 'checked' : '';
            echo '<label class="content-item">';
            echo '<input type="checkbox" name="selected_pages[]" value="' . $page->ID . '" ' . $checked . '>';
            echo '<span class="content-title">' . esc_html($page->post_title) . '</span>';
            echo '<span class="content-url">' . esc_html(get_permalink($page->ID)) . '</span>';
            echo '</label>';
        }
    }
    
    /**
     * Render posts selection checkboxes
     */
    private function render_posts_selection($selected_posts) {
        $posts = get_posts(array(
            'post_status' => 'publish',
            'numberposts' => -1,
            'orderby' => 'title',
            'order' => 'ASC'
        ));
        
        foreach ($posts as $post) {
            $checked = in_array($post->ID, $selected_posts) ? 'checked' : '';
            echo '<label class="content-item">';
            echo '<input type="checkbox" name="selected_posts[]" value="' . $post->ID . '" ' . $checked . '>';
            echo '<span class="content-title">' . esc_html($post->post_title) . '</span>';
            echo '<span class="content-url">' . esc_html(get_permalink($post->ID)) . '</span>';
            echo '</label>';
        }
    }
    
    /**
     * Render recent content table
     */
    private function render_recent_content() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ezlink_links';
        $links = $wpdb->get_results(
            "SELECT * FROM $table_name WHERE is_active = 1 ORDER BY created_at DESC LIMIT 10",
            ARRAY_A
        );
        
        if (empty($links)) {
            echo '<p>Nenhum conteúdo disponível ainda. <a href="' . admin_url('admin.php?page=ezlink-content') . '">Selecione conteúdo</a> para começar.</p>';
            return;
        }
        
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr><th>Título</th><th>Tipo</th><th>Short Code</th><th>URL Original</th><th>Cliques</th><th>Criado</th></tr></thead>';
        echo '<tbody>';
        
        foreach ($links as $link) {
            echo '<tr>';
            echo '<td>' . esc_html($link['title']) . '</td>';
            echo '<td>' . ucfirst($link['post_type'] ?? 'custom') . '</td>';
            echo '<td><code>' . esc_html($link['short_code']) . '</code></td>';
            echo '<td><a href="' . esc_url($link['original_url']) . '" target="_blank">' . esc_html($link['original_url']) . '</a></td>';
            echo '<td>' . esc_html($link['clicks']) . '</td>';
            echo '<td>' . esc_html($link['created_at']) . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody></table>';
    }
    
    /**
     * Get plugin statistics
     */
    private function get_stats() {
        global $wpdb;
        
        $table_name = $wpdb->prefix . 'ezlink_links';
        
        $total_links = $wpdb->get_var("SELECT COUNT(*) FROM $table_name WHERE is_active = 1");
        $total_clicks = $wpdb->get_var("SELECT SUM(clicks) FROM $table_name WHERE is_active = 1");
        
        return array(
            'total_links' => intval($total_links),
            'total_clicks' => intval($total_clicks)
        );
    }
    
    /**
     * Get selected content statistics
     */
    private function get_selected_content_stats() {
        $selected_pages = get_option('ezlink_selected_pages', array());
        $selected_posts = get_option('ezlink_selected_posts', array());
        
        return array(
            'pages' => count($selected_pages),
            'posts' => count($selected_posts),
            'total' => count($selected_pages) + count($selected_posts)
        );
    }
    
    /**
     * Get memory usage
     */
    private function get_memory_usage() {
        return size_format(memory_get_usage());
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function admin_enqueue_scripts($hook) {
        if (strpos($hook, 'ezlink') === false) {
            return;
        }
        
        wp_enqueue_script('ezlink-admin', EZLINK_PLUGIN_URL . 'assets/admin.js', array('jquery'), EZLINK_VERSION, true);
        wp_localize_script('ezlink-admin', 'ezlink_ajax', array(
            'ajax_url' => admin_url('admin-ajax.php'),
            'rest_url' => get_rest_url(null, 'ezlink/v1/'),
            'nonce' => wp_create_nonce('ezlink_nonce')
        ));
        
        wp_enqueue_style('ezlink-admin', EZLINK_PLUGIN_URL . 'assets/admin.css', array(), EZLINK_VERSION);
    }
    
    /**
     * Plugin activation
     */
    public function activate() {
        $this->create_database_tables();
        
        // Set default options
        add_option('ezlink_api_domain', 'https://unity-rede.com');
        add_option('ezlink_batch_size', 50);
        add_option('ezlink_cache_duration', 300);
        add_option('ezlink_enable_logging', '0');
        add_option('ezlink_captcha_enabled', '0');
        add_option('ezlink_captcha_timeout', '60');
        add_option('ezlink_selected_pages', array());
        add_option('ezlink_selected_posts', array());
        add_option('ezlink_auto_sync', '0');
        add_option('ezlink_auto_generate_links', '0');
        
        // Create rewrite rules for short links
        add_rewrite_rule('^([a-zA-Z0-9_-]+)/?$', 'index.php?ezlink_redirect=$matches[1]', 'top');
        flush_rewrite_rules();
    }
    
    /**
     * Plugin deactivation
     */
    public function deactivate() {
        // Clean up transients
        delete_transient('ezlink_selected_sync_pages');
        flush_rewrite_rules();
    }
}

// Initialize the plugin
function ezlink_init() {
    return EzLink::get_instance();
}
add_action('plugins_loaded', 'ezlink_init');

/**
 * Add query vars
 */
function ezlink_query_vars($vars) {
    $vars[] = 'ezlink_redirect';
    return $vars;
}
add_filter('query_vars', 'ezlink_query_vars');

/**
 * Add admin styles inline
 */
function ezlink_admin_styles() {
    if (!isset($_GET['page']) || strpos($_GET['page'], 'ezlink') === false) {
        return;
    }
    ?>
    <style>
    .ezlink-admin {
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }
    
    .ezlink-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 30px;
        border-radius: 10px;
        margin-bottom: 20px;
        text-align: center;
    }
    
    .ezlink-header h1 {
        margin: 0;
        font-size: 2.5em;
        font-weight: 700;
    }
    
    .ezlink-header .subtitle {
        margin: 10px 0 0 0;
        opacity: 0.9;
        font-size: 1.1em;
    }
    
    .ezlink-dashboard-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
    }
    
    .ezlink-selection-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin-bottom: 20px;
    }
    
    .ezlink-card {
        background: white;
        border-radius: 8px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        overflow: hidden;
    }
    
    .ezlink-card.full-width {
        grid-column: 1 / -1;
    }
    
    .ezlink-card-header {
        background: #f8f9fa;
        padding: 20px;
        border-bottom: 1px solid #e9ecef;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .ezlink-card-header h2 {
        margin: 0;
        font-size: 1.2em;
        color: #333;
    }
    
    .ezlink-card-body {
        padding: 20px;
    }
    
    .ezlink-stats {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }
    
    .stat-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 0;
        border-bottom: 1px solid #f0f0f0;
    }
    
    .stat-item:last-child {
        border-bottom: none;
    }
    
    .stat-label {
        font-weight: 600;
        color: #555;
    }
    
    .stat-value {
        font-weight: 500;
        color: #333;
    }
    
    .quick-actions {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    
    .quick-actions .button {
        text-align: center;
    }
    
    .content-selection-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }
    
    .content-item {
        display: flex;
        align-items: center;
        padding: 10px;
        border: 1px solid #e9ecef;
        border-radius: 5px;
        cursor: pointer;
        transition: background-color 0.2s;
    }
    
    .content-item:hover {
        background-color: #f8f9fa;
    }
    
    .content-item input[type="checkbox"] {
        margin-right: 10px;
    }
    
    .content-title {
        font-weight: 600;
        color: #333;
        margin-bottom: 5px;
        display: block;
        width: 100%;
    }
    
    .content-url {
        font-size: 12px;
        color: #666;
        display: block;
        width: 100%;
    }
    
    .api-info {
        margin-bottom: 20px;
    }
    
    .api-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px 0;
        border-bottom: 1px solid #f0f0f0;
    }
    
    .api-item:last-child {
        border-bottom: none;
    }
    
    .api-value {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .api-value code {
        background: #f1f1f1;
        padding: 5px 8px;
        border-radius: 4px;
        font-size: 12px;
        max-width: 200px;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    
    .method-badge {
        background: #28a745;
        color: white;
        padding: 2px 6px;
        border-radius: 3px;
        font-size: 11px;
        font-weight: bold;
    }
    
    .status-active {
        color: #28a745;
        font-weight: 600;
    }
    
    .status-inactive {
        color: #dc3545;
        font-weight: 600;
    }
    
    .api-actions {
        text-align: center;
        margin-bottom: 20px;
    }
    
    .api-actions .button {
        margin: 0 5px;
    }
    
    .test-result {
        margin-top: 20px;
        padding: 15px;
        border-radius: 4px;
    }
    
    .test-result.success {
        background: #d4edda;
        border: 1px solid #c3e6cb;
        color: #155724;
    }
    
    .test-result.error {
        background: #f8d7da;
        border: 1px solid #f5c6cb;
        color: #721c24;
    }
    
    .test-result.loading {
        background: #fff3cd;
        border: 1px solid #ffeaa7;
        color: #856404;
    }
    
    @media (max-width: 768px) {
        .ezlink-dashboard-grid,
        .ezlink-selection-grid {
            grid-template-columns: 1fr;
        }
        
        .quick-actions {
            flex-direction: column;
        }
        
        .api-item {
            flex-direction: column;
            align-items: flex-start;
            gap: 5px;
        }
        
        .api-value {
            width: 100%;
        }
        
        .api-value code {
            max-width: 100%;
        }
    }
    </style>
    <?php
}
add_action('admin_head', 'ezlink_admin_styles');

/**
 * Add admin JavaScript inline
 */
function ezlink_admin_scripts() {
    if (!isset($_GET['page']) || strpos($_GET['page'], 'ezlink') === false) {
        return;
    }
    ?>
    <script>
    jQuery(document).ready(function($) {
        // Copy functionality
        $('.copy-btn').on('click', function() {
            var text = $(this).data('copy');
            var button = $(this);
            
            navigator.clipboard.writeText(text).then(function() {
                var originalText = button.text();
                button.text('Copiado!');
                setTimeout(function() {
                    button.text(originalText);
                }, 2000);
            }).catch(function() {
                // Fallback for older browsers
                var textArea = document.createElement('textarea');
                textArea.value = text;
                document.body.appendChild(textArea);
                textArea.select();
                document.execCommand('copy');
                document.body.removeChild(textArea);
                
                var originalText = button.text();
                button.text('Copiado!');
                setTimeout(function() {
                    button.text(originalText);
                }, 2000);
            });
        });
        
        // Select all pages functionality
        $('#select-all-pages').on('click', function() {
            var button = $(this);
            var checkboxes = $('input[name="selected_pages[]"]');
            var allChecked = checkboxes.length === checkboxes.filter(':checked').length;
            
            checkboxes.prop('checked', !allChecked);
            button.text(allChecked ? 'Selecionar Todas' : 'Desselecionar Todas');
        });
        
        // Select all posts functionality
        $('#select-all-posts').on('click', function() {
            var button = $(this);
            var checkboxes = $('input[name="selected_posts[]"]');
            var allChecked = checkboxes.length === checkboxes.filter(':checked').length;
            
            checkboxes.prop('checked', !allChecked);
            button.text(allChecked ? 'Selecionar Todos' : 'Desselecionar Todos');
        });
        
        // Generate links for selected content
        $('#generate-links-btn').on('click', function(e) {
            e.preventDefault();
            var button = $(this);
            
            button.prop('disabled', true).text('Gerando Links...');
            
            $.post(ajaxurl, {
                action: 'ezlink_select_content',
                action_type: 'generate_links',
                nonce: ezlink_ajax.nonce
            })
            .done(function(response) {
                if (response.success) {
                    alert('Links gerados com sucesso!\n\nTotal: ' + response.data.generated_count + ' links');
                    location.reload();
                } else {
                    alert('Erro: ' + response.data);
                }
            })
            .fail(function() {
                alert('Erro de conexão ao gerar links.');
            })
            .always(function() {
                button.prop('disabled', false).text('Gerar Links para Selecionados');
            });
        });
        
        // Test API endpoint
        $('#test-api-btn').on('click', function() {
            var button = $(this);
            var resultDiv = $('#test-result');
            var apiUrl = ezlink_ajax.rest_url + 'sync-pages';
            
            button.prop('disabled', true);
            resultDiv.removeClass('success error').addClass('loading').show();
            resultDiv.html('<p>Testando endpoint...</p>');
            
            $.get(apiUrl)
                .done(function(response) {
                    resultDiv.removeClass('loading error').addClass('success');
                    var html = '<h4>Teste Bem-sucedido</h4>';
                    html += '<p><strong>Status:</strong> ' + (response.success ? 'Sucesso' : 'Erro') + '</p>';
                    html += '<p><strong>Mensagem:</strong> ' + response.message + '</p>';
                    html += '<p><strong>Total de Páginas:</strong> ' + (response.data ? response.data.total : 0) + '</p>';
                    html += '<p><strong>Versão do Plugin:</strong> ' + (response.data ? response.data.plugin_version : 'N/A') + '</p>';
                    html += '<p><strong>Captcha:</strong> ' + (response.data && response.data.captcha_enabled ? 'Ativado' : 'Desativado') + '</p>';
                    html += '<p><strong>Timestamp:</strong> ' + (response.data ? response.data.timestamp : 'N/A') + '</p>';
                    resultDiv.html(html);
                })
                .fail(function(xhr) {
                    resultDiv.removeClass('loading success').addClass('error');
                    resultDiv.html('<h4>Teste Falhou</h4><p>Erro: ' + xhr.statusText + '</p>');
                })
                .always(function() {
                    button.prop('disabled', false);
                });
        });
        
        // Test captcha functionality
        $('#test-captcha-btn').on('click', function() {
            var button = $(this);
            var resultDiv = $('#test-result');
            
            button.prop('disabled', true);
            resultDiv.removeClass('success error').addClass('loading').show();
            resultDiv.html('<p>Testando sistema de captcha...</p>');
            
            $.post(ezlink_ajax.rest_url + 'captcha/generate', {})
                .done(function(response) {
                    if (response.success) {
                        resultDiv.removeClass('loading error').addClass('success');
                        var html = '<h4>Captcha Gerado com Sucesso</h4>';
                        html += '<p><strong>Pergunta:</strong> ' + response.question + '</p>';
                        html += '<p><strong>Session ID:</strong> ' + response.session_id + '</p>';
                        html += '<p><strong>Expira em:</strong> ' + response.expires_in + ' segundos</p>';
                        resultDiv.html(html);
                    } else {
                        resultDiv.removeClass('loading success').addClass('error');
                        resultDiv.html('<h4>Erro no Captcha</h4><p>' + response.message + '</p>');
                    }
                })
                .fail(function(xhr) {
                    resultDiv.removeClass('loading success').addClass('error');
                    resultDiv.html('<h4>Falha no Teste</h4><p>Erro: ' + xhr.statusText + '</p>');
                })
                .always(function() {
                    button.prop('disabled', false);
                });
        });
    });
    </script>
    <?php
}
add_action('admin_footer', 'ezlink_admin_scripts');

/**
 * Plugin action links
 */
function ezlink_plugin_action_links($links) {
    $plugin_links = array(
        '<a href="' . admin_url('admin.php?page=ezlink-settings') . '">Configurações</a>',
        '<a href="' . admin_url('admin.php?page=ezlink-content') . '">Conteúdo</a>',
        '<a href="' . admin_url('admin.php?page=ezlink-sync') . '">API Sync</a>',
    );
    
    return array_merge($plugin_links, $links);
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'ezlink_plugin_action_links');

/**
 * Add dashboard widget
 */
function ezlink_dashboard_widget() {
    wp_add_dashboard_widget(
        'ezlink_dashboard_widget',
        'EzLink v' . EZLINK_VERSION . ' - Sistema Inteligente',
        'ezlink_dashboard_widget_content'
    );
}
add_action('wp_dashboard_setup', 'ezlink_dashboard_widget');

function ezlink_dashboard_widget_content() {
    $stats = EzLink::get_instance()->get_selected_content_stats();
    $sync_url = get_rest_url(null, 'ezlink/v1/sync-pages');
    $captcha_status = get_option('ezlink_captcha_enabled', '0') === '1' ? 'Ativo' : 'Inativo';
    ?>
    <div style="text-align: center; padding: 10px;">
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 15px; border-radius: 8px; margin-bottom: 15px;">
            <h3 style="margin: 0; color: white;">EzLink - Sistema Inteligente</h3>
        </div>
        
        <div style="background: #f8f9fa; padding: 10px; border-radius: 6px; margin-bottom: 10px;">
            <p style="margin: 0;"><strong>Conteúdo Selecionado:</strong> <?php echo $stats['total']; ?> itens</p>
            <p style="margin: 0;"><strong>Páginas:</strong> <?php echo $stats['pages']; ?> | <strong>Posts:</strong> <?php echo $stats['posts']; ?></p>
            <p style="margin: 0;"><strong>Captcha:</strong> <?php echo $captcha_status; ?></p>
        </div>
        
        <div style="background: #e7f3ff; padding: 10px; border-radius: 6px; margin-bottom: 10px;">
            <p style="margin: 0; color: #004085;"><strong>API Endpoint:</strong></p>
            <small style="color: #004085; word-break: break-all;"><?php echo esc_html($sync_url); ?></small>
        </div>
        
        <div style="display: flex; gap: 5px; justify-content: center; flex-wrap: wrap;">
            <a href="<?php echo admin_url('admin.php?page=ezlink'); ?>" class="button" style="font-size: 11px;">Dashboard</a>
            <a href="<?php echo admin_url('admin.php?page=ezlink-content'); ?>" class="button" style="font-size: 11px;">Conteúdo</a>
            <a href="<?php echo admin_url('admin.php?page=ezlink-sync'); ?>" class="button" style="font-size: 11px;">API</a>
        </div>
    </div>
    <?php
}

/**
 * Add meta box to posts/pages for EzLink info
 */
function ezlink_add_meta_box() {
    add_meta_box(
        'ezlink_meta_box',
        'EzLink v' . EZLINK_VERSION,
        'ezlink_meta_box_content',
        array('post', 'page'),
        'side',
        'default'
    );
}
add_action('add_meta_boxes', 'ezlink_add_meta_box');

function ezlink_meta_box_content($post) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'ezlink_links';
    
    // Check if this post/page has a short link
    $link = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE post_id = %d AND post_type = %s",
        $post->ID,
        $post->post_type
    ));
    
    $selected_pages = get_option('ezlink_selected_pages', array());
    $selected_posts = get_option('ezlink_selected_posts', array());
    $is_selected = ($post->post_type === 'page' && in_array($post->ID, $selected_pages)) ||
                   ($post->post_type === 'post' && in_array($post->ID, $selected_posts));
    ?>
    <div style="text-align: center; padding: 10px;">
        <div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 12px; border-radius: 6px; margin-bottom: 15px;">
            <strong style="color: white;">Sistema EzLink</strong>
        </div>
        
        <?php if ($link): ?>
            <div style="background: #d4edda; border: 1px solid #c3e6cb; color: #155724; padding: 10px; border-radius: 6px; margin-bottom: 10px;">
                <p style="margin: 0; font-size: 12px;"><strong>Link Curto Ativo:</strong></p>
                <code style="font-size: 11px;"><?php echo esc_html($link->short_code); ?></code>
                <p style="margin: 5px 0 0 0; font-size: 11px;">Cliques: <?php echo $link->clicks; ?></p>
            </div>
        <?php endif; ?>
        
        <div style="background: #f8f9fa; padding: 10px; border-radius: 6px; margin-bottom: 10px;">
            <p style="margin: 0; font-size: 11px;">
                <strong>Status:</strong> 
                <?php if ($is_selected): ?>
                    <span style="color: #28a745;">✓ Selecionado para Sync</span>
                <?php else: ?>
                    <span style="color: #dc3545;">Não selecionado</span>
                <?php endif; ?>
            </p>
        </div>
        
        <div style="margin-top: 10px;">
            <a href="<?php echo admin_url('admin.php?page=ezlink-content'); ?>" class="button button-small" style="font-size: 11px;">Gerenciar Seleção</a>
        </div>
    </div>
    <?php
}

/**
 * Auto-sync new content if option is enabled
 */
function ezlink_auto_sync_new_content($post_id) {
    if (get_option('ezlink_auto_sync', '0') !== '1') {
        return;
    }
    
    $post = get_post($post_id);
    if (!$post || $post->post_status !== 'publish') {
        return;
    }
    
    if (!in_array($post->post_type, array('post', 'page'))) {
        return;
    }
    
    // Add to selected content
    $option_key = $post->post_type === 'page' ? 'ezlink_selected_pages' : 'ezlink_selected_posts';
    $selected = get_option($option_key, array());
    
    if (!in_array($post_id, $selected)) {
        $selected[] = $post_id;
        update_option($option_key, $selected);
        
        // Clear cache
        delete_transient('ezlink_selected_sync_pages');
        
        // Auto-generate link if option is enabled
        if (get_option('ezlink_auto_generate_links', '0') === '1') {
            global $wpdb;
            $table_name = $wpdb->prefix . 'ezlink_links';
            
            // Check if link already exists
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_name WHERE post_id = %d AND post_type = %s",
                $post_id,
                $post->post_type
            ));
            
            if (!$existing) {
                $ezlink = EzLink::get_instance();
                $short_code = $ezlink->generate_unique_short_code();
                
                $wpdb->insert(
                    $table_name,
                    array(
                        'original_url' => get_permalink($post_id),
                        'short_code' => $short_code,
                        'title' => $post->post_title,
                        'post_id' => $post_id,
                        'post_type' => $post->post_type,
                        'created_at' => current_time('mysql'),
                        'is_active' => 1,
                        'clicks' => 0
                    ),
                    array('%s', '%s', '%s', '%d', '%s', '%s', '%d', '%d')
                );
            }
        }
    }
}
add_action('publish_post', 'ezlink_auto_sync_new_content');
add_action('publish_page', 'ezlink_auto_sync_new_content');

/**
 * Cleanup function for old captchas
 */
function ezlink_cleanup_old_captchas() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'ezlink_captcha';
    
    // Delete expired captchas older than 1 hour
    $wpdb->query($wpdb->prepare(
        "DELETE FROM $table_name WHERE expires_at < %s OR created_at < DATE_SUB(NOW(), INTERVAL 1 HOUR)",
        current_time('mysql')
    ));
}

// Schedule cleanup twice daily
if (!wp_next_scheduled('ezlink_cleanup_captchas')) {
    wp_schedule_event(time(), 'twicedaily', 'ezlink_cleanup_captchas');
}
add_action('ezlink_cleanup_captchas', 'ezlink_cleanup_old_captchas');

/**
 * Plugin row meta
 */
function ezlink_plugin_row_meta($links, $file) {
    if ($file === plugin_basename(__FILE__)) {
        $new_links = array(
            '<span style="color: #28a745; font-weight: bold;">✅ v' . EZLINK_VERSION . ' - Sistema Inteligente</span>',
            '<a href="' . admin_url('admin.php?page=ezlink') . '" style="color: #667eea; font-weight: bold;">Dashboard</a>',
            '<a href="' . admin_url('admin.php?page=ezlink-content') . '" style="color: #f093fb; font-weight: bold;">Seleção de Conteúdo</a>',
            '<span style="color: #28a745; font-weight: bold;">🔒 Captcha Anti-Robô</span>'
        );
        return array_merge($links, $new_links);
    }
    return $links;
}
add_filter('plugin_row_meta', 'ezlink_plugin_row_meta', 10, 2);

/**
 * Uninstall function
 */
function ezlink_uninstall() {
    global $wpdb;
    
    // Remove database tables
    $links_table = $wpdb->prefix . 'ezlink_links';
    $captcha_table = $wpdb->prefix . 'ezlink_captcha';
    
    $wpdb->query("DROP TABLE IF EXISTS $links_table");
    $wpdb->query("DROP TABLE IF EXISTS $captcha_table");
    
    // Remove options
    delete_option('ezlink_api_domain');
    delete_option('ezlink_batch_size');
    delete_option('ezlink_cache_duration');
    delete_option('ezlink_enable_logging');
    delete_option('ezlink_captcha_enabled');
    delete_option('ezlink_captcha_timeout');
    delete_option('ezlink_selected_pages');
    delete_option('ezlink_selected_posts');
    delete_option('ezlink_auto_sync');
    delete_option('ezlink_auto_generate_links');
    delete_option('ezlink_db_version');
    
    // Clean up transients
    delete_transient('ezlink_selected_sync_pages');
    
    // Clear scheduled events
    wp_clear_scheduled_hook('ezlink_cleanup_captchas');
}
register_uninstall_hook(__FILE__, 'ezlink_uninstall');

/**
 * Clear scheduled events on deactivation
 */
function ezlink_clear_scheduled_events() {
    wp_clear_scheduled_hook('ezlink_cleanup_captchas');
}
register_deactivation_hook(__FILE__, 'ezlink_clear_scheduled_events');

/**
 * Log function for debugging
 */
function ezlink_log($message) {
    if (get_option('ezlink_enable_logging', '0') === '1' && defined('WP_DEBUG_LOG') && WP_DEBUG_LOG) {
        error_log('EzLink v' . EZLINK_VERSION . ': ' . $message);
    }
}

// Log plugin initialization
ezlink_log('Plugin inicializado com sucesso - Sistema de Links Inteligente com Captcha Anti-Robô');

/**
 * HOOKS DE COMPATIBILIDADE COM APIs EXTERNAS
 */

// Hook para integração com tracker.php
add_action('wp_ajax_ezlink_external_track', function() {
    $short_code = sanitize_text_field($_POST['short_code'] ?? '');
    
    if (empty($short_code)) {
        wp_send_json_error('Short code é obrigatório');
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'ezlink_links';
    
    $link = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE short_code = %s AND is_active = 1",
        $short_code
    ));
    
    if ($link) {
        // Update click count
        $wpdb->update(
            $table_name,
            array('clicks' => $link->clicks + 1),
            array('id' => $link->id),
            array('%d'),
            array('%d')
        );
        
        wp_send_json_success(array(
            'url' => $link->original_url,
            'title' => $link->title,
            'clicks' => $link->clicks + 1
        ));
    }
    
    wp_send_json_error('Link não encontrado');
});

// Hook para integração com sequencia.php
add_action('wp_ajax_nopriv_ezlink_external_sequence', function() {
    $pages = EzLink::get_instance()->get_selected_pages_for_sync();
    
    $sequence = array();
    foreach ($pages as $page) {
        $sequence[] = array(
            'id' => $page['id'],
            'nome' => $page['title'],
            'url' => $page['url'],
            'tipo' => $page['type']
        );
    }
    
    wp_send_json_success(array(
        'sequencia_ativa' => array(
            'nome' => 'Sequência EzLink',
            'paginas' => $sequence,
            'total' => count($sequence)
        )
    ));
});

// Hook para integração com link-final.php  
add_action('wp_ajax_nopriv_ezlink_external_final', function() {
    $dest = sanitize_text_field($_GET['dest'] ?? '');
    
    if (empty($dest)) {
        wp_send_json_error('Dest é obrigatório');
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'ezlink_links';
    
    $link = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE short_code = %s AND is_active = 1",
        $dest
    ));
    
    if ($link) {
        wp_send_json_success(array(
            'url_destino' => $link->original_url,
            'final_url' => $link->original_url,
            'titulo' => $link->title,
            'botao_texto' => 'ACESSAR AGORA'
        ));
    }
    
    wp_send_json_error('Link não encontrado');
});

/**
 * FUNCIONALIDADE ADICIONAL: Método público para gerar link curto via código
 */
function ezlink_create_short_link($url, $title = '', $custom_code = '') {
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'ezlink_links';
    
    $ezlink = EzLink::get_instance();
    $short_code = !empty($custom_code) ? $custom_code : $ezlink->generate_unique_short_code();
    
    // Check if custom code already exists
    if (!empty($custom_code)) {
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE short_code = %s",
            $custom_code
        ));
        
        if ($existing) {
            return false;
        }
    }
    
    $result = $wpdb->insert(
        $table_name,
        array(
            'original_url' => $url,
            'short_code' => $short_code,
            'title' => $title ?: 'Link Personalizado',
            'created_at' => current_time('mysql'),
            'is_active' => 1,
            'clicks' => 0
        ),
        array('%s', '%s', '%s', '%s', '%d', '%d')
    );
    
    if ($result) {
        return array(
            'success' => true,
            'short_code' => $short_code,
            'short_url' => home_url($short_code),
            'original_url' => $url,
            'title' => $title ?: 'Link Personalizado'
        );
    }
    
    return false;
}

/**
 * FUNCIONALIDADE ADICIONAL: Obter estatísticas de um link específico
 */
function ezlink_get_link_stats($short_code) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'ezlink_links';
    
    $link = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE short_code = %s",
        $short_code
    ));
    
    if ($link) {
        return array(
            'success' => true,
            'short_code' => $link->short_code,
            'title' => $link->title,
            'original_url' => $link->original_url,
            'clicks' => intval($link->clicks),
            'created_at' => $link->created_at,
            'is_active' => intval($link->is_active) === 1
        );
    }
    
    return false;
}

/**
 * FUNCIONALIDADE ADICIONAL: Listar todos os links ativos
 */
function ezlink_get_all_active_links() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'ezlink_links';
    
    $links = $wpdb->get_results(
        "SELECT * FROM $table_name WHERE is_active = 1 ORDER BY created_at DESC",
        ARRAY_A
    );
    
    return $links;
}

/**
 * INTEGRAÇÃO COM APIS EXTERNAS - Endpoint para verificar status do plugin
 */
add_action('wp_ajax_nopriv_ezlink_plugin_status', function() {
    $stats = EzLink::get_instance()->get_selected_content_stats();
    
    wp_send_json_success(array(
        'plugin_active' => true,
        'version' => EZLINK_VERSION,
        'captcha_enabled' => get_option('ezlink_captcha_enabled', '0') === '1',
        'content_selected' => $stats['total'],
        'pages_selected' => $stats['pages'],
        'posts_selected' => $stats['posts'],
        'auto_sync' => get_option('ezlink_auto_sync', '0') === '1',
        'auto_generate_links' => get_option('ezlink_auto_generate_links', '0') === '1',
        'api_endpoint' => get_rest_url(null, 'ezlink/v1/sync-pages'),
        'site_url' => get_site_url(),
        'site_name' => get_bloginfo('name')
    ));
});

/**
 * SHORTCODE para exibir link curto no conteúdo
 */
function ezlink_shortcode($atts) {
    $atts = shortcode_atts(array(
        'id' => '',
        'code' => '',
        'text' => 'Clique aqui',
        'class' => 'ezlink-button'
    ), $atts);
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'ezlink_links';
    
    $link = null;
    
    if (!empty($atts['id'])) {
        $link = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE id = %d AND is_active = 1",
            $atts['id']
        ));
    } elseif (!empty($atts['code'])) {
        $link = $wpdb->get_row($wpdb->prepare(
            "SELECT * FROM $table_name WHERE short_code = %s AND is_active = 1",
            $atts['code']
        ));
    }
    
    if ($link) {
        $short_url = home_url($link->short_code);
        return '<a href="' . esc_url($short_url) . '" class="' . esc_attr($atts['class']) . '">' . esc_html($atts['text']) . '</a>';
    }
    
    return '<span class="ezlink-error">Link não encontrado</span>';
}
add_shortcode('ezlink', 'ezlink_shortcode');

/**
 * WIDGET para exibir estatísticas no frontend
 */
class EzLink_Stats_Widget extends WP_Widget {
    
    public function __construct() {
        parent::__construct(
            'ezlink_stats_widget',
            'EzLink - Estatísticas',
            array('description' => 'Exibe estatísticas do sistema EzLink')
        );
    }
    
    public function widget($args, $instance) {
        $title = !empty($instance['title']) ? $instance['title'] : 'EzLink Stats';
        $show_captcha = !empty($instance['show_captcha']);
        
        echo $args['before_widget'];
        echo $args['before_title'] . apply_filters('widget_title', $title) . $args['after_title'];
        
        $stats = EzLink::get_instance()->get_stats();
        $content_stats = EzLink::get_instance()->get_selected_content_stats();
        
        echo '<div class="ezlink-widget-stats">';
        echo '<p><strong>Links Ativos:</strong> ' . $stats['total_links'] . '</p>';
        echo '<p><strong>Total Cliques:</strong> ' . $stats['total_clicks'] . '</p>';
        echo '<p><strong>Conteúdo Sync:</strong> ' . $content_stats['total'] . '</p>';
        
        if ($show_captcha) {
            $captcha_status = get_option('ezlink_captcha_enabled', '0') === '1' ? 'Ativo' : 'Inativo';
            echo '<p><strong>Captcha:</strong> ' . $captcha_status . '</p>';
        }
        
        echo '</div>';
        
        echo $args['after_widget'];
    }
    
    public function form($instance) {
        $title = !empty($instance['title']) ? $instance['title'] : 'EzLink Stats';
        $show_captcha = !empty($instance['show_captcha']);
        ?>
        <p>
            <label for="<?php echo $this->get_field_id('title'); ?>">Título:</label>
            <input class="widefat" id="<?php echo $this->get_field_id('title'); ?>" 
                   name="<?php echo $this->get_field_name('title'); ?>" type="text" 
                   value="<?php echo esc_attr($title); ?>">
        </p>
        <p>
            <input class="checkbox" type="checkbox" <?php checked($show_captcha); ?> 
                   id="<?php echo $this->get_field_id('show_captcha'); ?>" 
                   name="<?php echo $this->get_field_name('show_captcha'); ?>">
            <label for="<?php echo $this->get_field_id('show_captcha'); ?>">Mostrar status do Captcha</label>
        </p>
        <?php
    }
    
    public function update($new_instance, $old_instance) {
        $instance = array();
        $instance['title'] = (!empty($new_instance['title'])) ? strip_tags($new_instance['title']) : '';
        $instance['show_captcha'] = (!empty($new_instance['show_captcha'])) ? 1 : 0;
        return $instance;
    }
}

// Registrar widget
add_action('widgets_init', function() {
    register_widget('EzLink_Stats_Widget');
});

/**
 * COMPATIBILIDADE COM WP-CLI
 */
if (defined('WP_CLI') && WP_CLI) {
    class EzLink_CLI_Command extends WP_CLI_Command {
        
        /**
         * Gera um link curto via WP-CLI
         * 
         * ## OPTIONS
         * 
         * <url>
         * : URL para encurtar
         * 
         * [--title=<title>]
         * : Título do link
         * 
         * [--code=<code>]
         * : Código personalizado
         * 
         * ## EXAMPLES
         * 
         *     wp ezlink create "https://example.com" --title="Meu Site"
         *     wp ezlink create "https://example.com" --code="meusite"
         */
        public function create($args, $assoc_args) {
            $url = $args[0];
            $title = $assoc_args['title'] ?? '';
            $code = $assoc_args['code'] ?? '';
            
            $result = ezlink_create_short_link($url, $title, $code);
            
            if ($result) {
                WP_CLI::success("Link criado: {$result['short_url']}");
                WP_CLI::line("Short Code: {$result['short_code']}");
            } else {
                WP_CLI::error("Falha ao criar link");
            }
        }
        
        /**
         * Lista todos os links ativos
         */
        public function list() {
            $links = ezlink_get_all_active_links();
            
            if (empty($links)) {
                WP_CLI::warning("Nenhum link ativo encontrado");
                return;
            }
            
            $table_data = array();
            foreach ($links as $link) {
                $table_data[] = array(
                    'ID' => $link['id'],
                    'Short Code' => $link['short_code'],
                    'Title' => $link['title'],
                    'Clicks' => $link['clicks'],
                    'Created' => $link['created_at']
                );
            }
            
            WP_CLI\Utils\format_items('table', $table_data, array('ID', 'Short Code', 'Title', 'Clicks', 'Created'));
        }
        
        /**
         * Mostra estatísticas do plugin
         */
        public function stats() {
            $stats = EzLink::get_instance()->get_stats();
            $content_stats = EzLink::get_instance()->get_selected_content_stats();
            
            WP_CLI::line("=== EzLink v" . EZLINK_VERSION . " - Estatísticas ===");
            WP_CLI::line("Links Ativos: " . $stats['total_links']);
            WP_CLI::line("Total Cliques: " . $stats['total_clicks']);
            WP_CLI::line("Conteúdo Selecionado: " . $content_stats['total']);
            WP_CLI::line("Páginas: " . $content_stats['pages']);
            WP_CLI::line("Posts: " . $content_stats['posts']);
            WP_CLI::line("Captcha: " . (get_option('ezlink_captcha_enabled', '0') === '1' ? 'Ativo' : 'Inativo'));
        }
    }
    
    WP_CLI::add_command('ezlink', 'EzLink_CLI_Command');
}

/**
 * HOOKS DE INTEGRAÇÃO COM THEMES
 */

// Hook para themes adicionarem botões personalizados
add_action('ezlink_after_captcha', function($link_data) {
    // Permite que themes adicionem conteúdo após verificação do captcha
    do_action('ezlink_theme_after_captcha', $link_data);
});

// Hook para customizar a página de captcha
add_filter('ezlink_captcha_page_style', function($styles) {
    // Permite que themes customizem o CSS da página de captcha
    return apply_filters('ezlink_theme_captcha_style', $styles);
});

/**
 * FUNCIONALIDADES DE BACKUP E RESTORE
 */
function ezlink_export_data() {
    if (!current_user_can('manage_options')) {
        return false;
    }
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'ezlink_links';
    
    $links = $wpdb->get_results("SELECT * FROM $table_name", ARRAY_A);
    $settings = array(
        'selected_pages' => get_option('ezlink_selected_pages', array()),
        'selected_posts' => get_option('ezlink_selected_posts', array()),
        'captcha_enabled' => get_option('ezlink_captcha_enabled', '0'),
        'auto_sync' => get_option('ezlink_auto_sync', '0'),
        'auto_generate_links' => get_option('ezlink_auto_generate_links', '0')
    );
    
    return array(
        'version' => EZLINK_VERSION,
        'export_date' => current_time('mysql'),
        'links' => $links,
        'settings' => $settings
    );
}

// Adicionar página de backup no admin
add_action('admin_menu', function() {
    add_submenu_page(
        'ezlink',
        'Backup & Restore',
        'Backup',
        'manage_options',
        'ezlink-backup',
        function() {
            if (isset($_POST['export_data'])) {
                $data = ezlink_export_data();
                header('Content-Type: application/json');
                header('Content-Disposition: attachment; filename="ezlink-backup-' . date('Y-m-d-H-i-s') . '.json"');
                echo json_encode($data, JSON_PRETTY_PRINT);
                exit;
            }
            ?>
            <div class="wrap ezlink-admin">
                <div class="ezlink-header">
                    <h1>Backup & Restore</h1>
                    <p class="subtitle">Faça backup ou restaure dados do EzLink</p>
                </div>
                
                <div class="ezlink-card">
                    <div class="ezlink-card-header">
                        <h2>Exportar Dados</h2>
                    </div>
                    <div class="ezlink-card-body">
                        <p>Faça download de todos os seus links e configurações em formato JSON.</p>
                        <form method="post">
                            <input type="submit" name="export_data" class="button button-primary" value="Exportar Dados">
                        </form>
                    </div>
                </div>
                
                <div class="ezlink-card">
                    <div class="ezlink-card-header">
                        <h2>Informações do Sistema</h2>
                    </div>
                    <div class="ezlink-card-body">
                        <?php
                        $stats = EzLink::get_instance()->get_stats();
                        $content_stats = EzLink::get_instance()->get_selected_content_stats();
                        ?>
                        <p><strong>Versão do Plugin:</strong> <?php echo EZLINK_VERSION; ?></p>
                        <p><strong>Total de Links:</strong> <?php echo $stats['total_links']; ?></p>
                        <p><strong>Conteúdo Selecionado:</strong> <?php echo $content_stats['total']; ?> itens</p>
                        <p><strong>Base de Dados:</strong> <?php echo $GLOBALS['wpdb']->prefix; ?>ezlink_links</p>
                    </div>
                </div>
            </div>
            <?php
        }
    );
});

/**
 * LOGS DE ATIVIDADE DETALHADOS
 */
function ezlink_log_activity($action, $details = array()) {
    if (get_option('ezlink_enable_logging', '0') !== '1') {
        return;
    }
    
    $log_entry = array(
        'timestamp' => current_time('mysql'),
        'action' => $action,
        'details' => $details,
        'user_id' => get_current_user_id(),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    );
    
    ezlink_log('ACTIVITY: ' . json_encode($log_entry));
}

/**
 * SYSTEM HEALTH CHECK
 */
function ezlink_health_check() {
    $health = array(
        'plugin_version' => EZLINK_VERSION,
        'wordpress_version' => get_bloginfo('version'),
        'php_version' => PHP_VERSION,
        'database_status' => 'OK',
        'tables_exist' => array(),
        'captcha_functional' => false,
        'api_accessible' => false,
        'memory_usage' => memory_get_usage(true),
        'timestamp' => current_time('c')
    );
    
    global $wpdb;
    
    // Check tables
    $tables = array('ezlink_links', 'ezlink_captcha');
    foreach ($tables as $table) {
        $table_name = $wpdb->prefix . $table;
        $exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
        $health['tables_exist'][$table] = $exists;
    }
    
    // Check captcha functionality
    try {
        $ezlink = EzLink::get_instance();
        $captcha_result = $ezlink->generate_captcha();
        $health['captcha_functional'] = $captcha_result['success'];
    } catch (Exception $e) {
        $health['captcha_functional'] = false;
        $health['captcha_error'] = $e->getMessage();
    }
    
    // Check API accessibility
    try {
        $api_url = get_rest_url(null, 'ezlink/v1/sync-pages');
        $response = wp_remote_get($api_url);
        $health['api_accessible'] = !is_wp_error($response) && wp_remote_retrieve_response_code($response) === 200;
    } catch (Exception $e) {
        $health['api_accessible'] = false;
        $health['api_error'] = $e->getMessage();
    }
    
    return $health;
}

// Adicionar endpoint para health check
add_action('rest_api_init', function() {
    register_rest_route('ezlink/v1', '/health', array(
        'methods' => 'GET',
        'callback' => function() {
            return new WP_REST_Response(ezlink_health_check(), 200);
        },
        'permission_callback' => '__return_true'
    ));
});

/**
 * INTEGRAÇÃO FINAL COM APIS EXTERNAS
 */

// Garantir compatibilidade total com tracker.php
add_action('init', function() {
    if (isset($_GET['ezlink_api_call']) && $_GET['ezlink_api_call'] === 'tracker_compatible') {
        $action = $_GET['action'] ?? '';
        $dest = $_GET['dest'] ?? '';
        
        if ($action === 'count_click' && !empty($dest)) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'ezlink_links';
            
            $link = $wpdb->get_row($wpdb->prepare(
                "SELECT * FROM $table_name WHERE short_code = %s AND is_active = 1",
                $dest
            ));
            
            if ($link) {
                $wpdb->update(
                    $table_name,
                    array('clicks' => $link->clicks + 1),
                    array('id' => $link->id),
                    array('%d'),
                    array('%d')
                );
                
                wp_send_json(array(
                    'success' => true,
                    'url_destino' => $link->original_url,
                    'click_count' => $link->clicks + 1,
                    'message' => 'Clique registrado com sucesso!'
                ));
            }
            
            wp_send_json(array(
                'success' => false,
                'message' => 'Link não encontrado'
            ));
        }
        
        wp_send_json(array(
            'success' => false,
            'message' => 'Ação não especificada'
        ));
    }
});

/**
 * CONCLUSÃO E INICIALIZAÇÃO FINAL
 */

// Log de carregamento completo
ezlink_log('Plugin EzLink v' . EZLINK_VERSION . ' carregado completamente - Todas as funcionalidades ativas');

// Verificação de integridade na inicialização
add_action('admin_init', function() {
    if (get_option('ezlink_db_version') !== '3.1') {
        $ezlink = EzLink::get_instance();
        $ezlink->create_database_tables();
    }
});

// Log de carregamento final
ezlink_log('Plugin EzLink v' . EZLINK_VERSION . ' inicializado com todas as funcionalidades ativas');

?>