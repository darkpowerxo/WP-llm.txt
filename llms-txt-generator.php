<?php
/**
 * Plugin Name: LLMs.txt Generator
 * Plugin URI: https://theproject1.com/llm-txt-generator
 * Description: Generates LLMs.txt files in markdown format for each language detected by Polylang
 * Version: 1.0.0
 * Author: darkpowerxo
 * Author URI: https://theproject1.com/
 * Text Domain: llm-txt-generator
 * Domain Path: /languages
 * License: gL v3 or later
 * License URI: http://www.gnu.org/licenses/gpl-3.0.txt
 * Requires at least: 5.0
 * Requires PHP: 7.2
 *
 * @package PolylangLLMsGenerator
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Define plugin constants
define('PLLG_VERSION', '1.0.0');
define('PLLG_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PLLG_PLUGIN_URL', plugin_dir_url(__FILE__));
define('PLLG_PLUGIN_BASENAME', plugin_basename(__FILE__));

class Polylang_LLMs_Generator {
    
    /**
     * Instance of this class
     *
     * @var object
     */
    private static $instance = null;
    
    /**
     * Last generation timestamps for each language
     *
     * @var array
     */
    private $last_generated = array();
    
    /**
     * Plugin constructor
     */
    private function __construct() {
        // Check if Polylang is active
        add_action('admin_init', array($this, 'check_polylang_dependency'));
        
        // Initialize plugin
        add_action('plugins_loaded', array($this, 'init'));
        
        // Register activation and deactivation hooks
        register_activation_hook(__FILE__, array($this, 'activate'));
        register_deactivation_hook(__FILE__, array($this, 'deactivate'));
    }
    
    /**
     * Get singleton instance
     *
     * @return Polylang_LLMs_Generator
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Initialize plugin
     */
    public function init() {
        // Check if Polylang is active
        if (!$this->is_polylang_active()) {
            return;
        }
        
        // Load plugin text domain
        load_plugin_textdomain('llm-txt-generator', false, dirname(plugin_basename(__FILE__)) . '/languages');
        
        // Get saved last generation times
        $this->last_generated = get_option('pllg_last_generated', array());
        
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Register settings
        add_action('admin_init', array($this, 'register_settings'));
        
        // Add AJAX handlers
        add_action('wp_ajax_pllg_generate_llms_files', array($this, 'ajax_generate_llms_files'));
        
        // Add scheduled event hook
        add_action('pllg_scheduled_generation', array($this, 'generate_llms_files'));
        
        // Add rewrite rules for llms.txt files
        add_action('init', array($this, 'add_rewrite_rules'));
        
        // Add template redirect for llms.txt files
        add_action('template_redirect', array($this, 'serve_llms_file'));
    }
    
    /**
     * Activation hook callback
     */
    public function activate() {
        // Set default options
        update_option('pllg_schedule', 'daily');
        update_option('pllg_last_generated', array());
        
        // Schedule first generation
        if (!wp_next_scheduled('pllg_scheduled_generation')) {
            wp_schedule_event(time(), 'daily', 'pllg_scheduled_generation');
        }
        
        // Flush rewrite rules
        $this->add_rewrite_rules();
        flush_rewrite_rules();
    }
    
    /**
     * Deactivation hook callback
     */
    public function deactivate() {
        // Clear scheduled event
        $timestamp = wp_next_scheduled('pllg_scheduled_generation');
        if ($timestamp) {
            wp_unschedule_event($timestamp, 'pllg_scheduled_generation');
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
    
    /**
     * Check if Polylang is active
     *
     * @return bool
     */
    private function is_polylang_active() {
        return (
            function_exists('is_plugin_active') && is_plugin_active('polylang/polylang.php') || 
            function_exists('is_plugin_active') && is_plugin_active('polylang-pro/polylang.php') ||
            function_exists('pll_languages_list')
        );
    }
    
    /**
     * Check Polylang dependency
     */
    public function check_polylang_dependency() {
        if (!$this->is_polylang_active()) {
            add_action('admin_notices', function() {
                echo '<div class="error"><p>' . 
                    __('Polylang LLMs Generator requires Polylang plugin to be installed and activated.', 'llm-txt-generator') . 
                    '</p></div>';
            });
        }
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_options_page(
            __('Polylang LLMs Generator', 'llm-txt-generator'),
            __('LLMs Generator', 'llm-txt-generator'),
            'manage_options',
            'llm-txt-generator',
            array($this, 'render_admin_page')
        );
    }
    
    /**
     * Register settings
     */
    public function register_settings() {
        register_setting('pllg_settings', 'pllg_schedule');
        register_setting('pllg_settings', 'pllg_last_generated');
    }
    
    /**
     * Render admin page
     */
    public function render_admin_page() {
        // Check if user has proper permissions
        if (!current_user_can('manage_options')) {
            return;
        }
        
        // Get active tab
        $active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'settings';
        
        ?>
        <div class="wrap">
            <h1><?php echo esc_html__('Polylang LLMs Generator', 'llm-txt-generator'); ?></h1>
            
            <h2 class="nav-tab-wrapper">
                <a href="?page=llm-txt-generator&tab=settings" class="nav-tab <?php echo $active_tab == 'settings' ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html__('Settings', 'llm-txt-generator'); ?>
                </a>
                <a href="?page=llm-txt-generator&tab=generate" class="nav-tab <?php echo $active_tab == 'generate' ? 'nav-tab-active' : ''; ?>">
                    <?php echo esc_html__('Generate LLMs.txt', 'llm-txt-generator'); ?>
                </a>
            </h2>
            
            <div class="tab-content">
                <?php
                switch ($active_tab) {
                    case 'generate':
                        $this->render_generate_tab();
                        break;
                    case 'settings':
                    default:
                        $this->render_settings_tab();
                        break;
                }
                ?>
            </div>
        </div>
        <?php
    }
    
    /**
     * Render settings tab
     */
    private function render_settings_tab() {
        $schedule = get_option('pllg_schedule', 'daily');
        ?>
        <form method="post" action="options.php">
            <?php settings_fields('pllg_settings'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row"><?php echo esc_html__('Schedule LLMs.txt Generation', 'llm-txt-generator'); ?></th>
                    <td>
                        <select name="pllg_schedule" id="pllg_schedule">
                            <option value="daily" <?php selected($schedule, 'daily'); ?>><?php echo esc_html__('Daily', 'llm-txt-generator'); ?></option>
                            <option value="weekly" <?php selected($schedule, 'weekly'); ?>><?php echo esc_html__('Weekly', 'llm-txt-generator'); ?></option>
                        </select>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(__('Save Settings', 'llm-txt-generator')); ?>
        </form>
        
        <h2><?php echo esc_html__('Generated LLMs.txt Files', 'llm-txt-generator'); ?></h2>
        
        <table class="widefat fixed" cellspacing="0">
            <thead>
                <tr>
                    <th id="language"><?php echo esc_html__('Language', 'llm-txt-generator'); ?></th>
                    <th id="last_generated"><?php echo esc_html__('Last Generated', 'llm-txt-generator'); ?></th>
                    <th id="link"><?php echo esc_html__('LLMs.txt Link', 'llm-txt-generator'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                // Check if Polylang is active
                if (!function_exists('pll_languages_list')) {
                    echo '<tr><td colspan="3">' . esc_html__('Polylang plugin is not active.', 'llm-txt-generator') . '</td></tr>';
                    return;
                }
                
                // Get all languages
                $languages = pll_languages_list(array('fields' => 'slug'));
                
                if (empty($languages)) {
                    echo '<tr><td colspan="3">' . esc_html__('No languages defined in Polylang.', 'llm-txt-generator') . '</td></tr>';
                    return;
                }
                
                foreach ($languages as $lang_slug) {
                    $language_details = PLL()->model->get_language($lang_slug);
                    $language_name = $language_details->name;
                    
                    $last_generated_time = isset($this->last_generated[$lang_slug]) ? $this->last_generated[$lang_slug] : false;
                    $formatted_time = $last_generated_time ? date_i18n(get_option('date_format') . ' ' . get_option('time_format'), $last_generated_time) : __('Not generated yet', 'llm-txt-generator');
                    
                    // Generate the URL for the language
                    $url = $lang_slug === pll_default_language() ? 
                        home_url('/llms.txt') : 
                        home_url('/' . $lang_slug . '/llms.txt');
                    
                    echo '<tr>';
                    echo '<td>' . esc_html($language_name) . ' (' . esc_html($lang_slug) . ')</td>';
                    echo '<td>' . esc_html($formatted_time) . '</td>';
                    echo '<td><a href="' . esc_url($url) . '" target="_blank">' . esc_html($url) . '</a></td>';
                    echo '</tr>';
                }
                ?>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * Render generate tab
     */
    private function render_generate_tab() {
        ?>
        <div class="card">
            <h2><?php echo esc_html__('Generate LLMs.txt Files Manually', 'llm-txt-generator'); ?></h2>
            <p><?php echo esc_html__('Click the button below to generate LLMs.txt files for all languages.', 'llm-txt-generator'); ?></p>
            <p>
                <button type="button" id="pllg-generate-button" class="button button-primary">
                    <?php echo esc_html__('Generate Now', 'llm-txt-generator'); ?>
                </button>
                <span class="spinner" id="pllg-spinner" style="float: none; margin-top: 0;"></span>
            </p>
            <div id="pllg-message"></div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#pllg-generate-button').on('click', function() {
                var $button = $(this);
                var $spinner = $('#pllg-spinner');
                var $message = $('#pllg-message');
                
                $button.prop('disabled', true);
                $spinner.addClass('is-active');
                $message.html('');
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: {
                        action: 'pllg_generate_llms_files',
                        nonce: '<?php echo wp_create_nonce('pllg_generate_llms_files'); ?>'
                    },
                    success: function(response) {
                        if (response.success) {
                            $message.html('<div class="notice notice-success inline"><p>' + response.data.message + '</p></div>');
                        } else {
                            $message.html('<div class="notice notice-error inline"><p>' + response.data.message + '</p></div>');
                        }
                    },
                    error: function() {
                        $message.html('<div class="notice notice-error inline"><p><?php echo esc_js(__('An error occurred while generating LLMs.txt files.', 'llm-txt-generator')); ?></p></div>');
                    },
                    complete: function() {
                        $button.prop('disabled', false);
                        $spinner.removeClass('is-active');
                    }
                });
            });
        });
        </script>
        <?php
    }
    
    /**
     * AJAX handler for generating LLMs.txt files
     */
    public function ajax_generate_llms_files() {
        // Check nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'pllg_generate_llms_files')) {
            wp_send_json_error(array('message' => __('Security check failed.', 'llm-txt-generator')));
        }
        
        // Check permissions
        if (!current_user_can('manage_options')) {
            wp_send_json_error(array('message' => __('You do not have permission to perform this action.', 'llm-txt-generator')));
        }
        
        // Generate the files
        $result = $this->generate_llms_files();
        
        if ($result) {
            wp_send_json_success(array('message' => __('LLMs.txt files have been generated successfully.', 'llm-txt-generator')));
        } else {
            wp_send_json_error(array('message' => __('Failed to generate LLMs.txt files.', 'llm-txt-generator')));
        }
    }
    
    /**
     * Generate LLMs.txt files for all languages
     *
     * @return bool True on success, false on failure
     */
    public function generate_llms_files() {
        // Check if Polylang is active
        if (!function_exists('pll_languages_list')) {
            return false;
        }
        
        // Get all languages
        $languages = pll_languages_list(array('fields' => 'slug'));
        
        if (empty($languages)) {
            return false;
        }
        
        // Update schedule if option has changed
        $schedule = get_option('pllg_schedule', 'daily');
        $current_schedule = wp_get_schedule('pllg_scheduled_generation');
        
        if ($current_schedule !== $schedule) {
            // Clear existing scheduled event
            $timestamp = wp_next_scheduled('pllg_scheduled_generation');
            if ($timestamp) {
                wp_unschedule_event($timestamp, 'pllg_scheduled_generation');
            }
            
            // Schedule new event
            wp_schedule_event(time(), $schedule, 'pllg_scheduled_generation');
        }
        
        // Generate files for each language
        foreach ($languages as $lang_slug) {
            $this->generate_llms_file_for_language($lang_slug);
        }
        
        return true;
    }
    
    /**
     * Generate LLMs.txt file for a specific language
     *
     * @param string $lang_slug Language slug
     * @return bool True on success, false on failure
     */
    private function generate_llms_file_for_language($lang_slug) {
        // Set the current language
        if (function_exists('pll_switch_to_locale')) {
            pll_switch_to_locale($lang_slug);
        }
        
        // Generate content
        $content = $this->generate_llms_content($lang_slug);
        
        if (empty($content)) {
            return false;
        }
        
        // Get upload directory
        $upload_dir = wp_upload_dir();
        
        // Create directory if it doesn't exist
        $llms_dir = $upload_dir['basedir'] . '/llms-txt';
        if (!file_exists($llms_dir)) {
            wp_mkdir_p($llms_dir);
        }
        
        // Create .htaccess file to protect the directory
        if (!file_exists($llms_dir . '/.htaccess')) {
            $htaccess_content = "# Disable directory browsing\n";
            $htaccess_content .= "Options -Indexes\n";
            $htaccess_content .= "# Allow access to .txt files\n";
            $htaccess_content .= "<Files ~ \"\\.txt$\">\n";
            $htaccess_content .= "    Allow from all\n";
            $htaccess_content .= "</Files>\n";
            file_put_contents($llms_dir . '/.htaccess', $htaccess_content);
        }
        
        // Save the file
        $filename = $llms_dir . '/llms-' . $lang_slug . '.txt';
        $result = file_put_contents($filename, $content);
        
        if ($result === false) {
            return false;
        }
        
        // Update last generated time
        $this->last_generated[$lang_slug] = time();
        update_option('pllg_last_generated', $this->last_generated);
        
        // Reset language if necessary
        if (function_exists('pll_restore_locale')) {
            pll_restore_locale();
        }
        
        return true;
    }
    
    /**
     * Generate LLMs.txt content for a specific language
     *
     * @param string $lang_slug Language slug
     * @return string Generated content
     */
    private function generate_llms_content($lang_slug) {
        // Get site info
        $site_title = get_bloginfo('name');
        $site_tagline = get_bloginfo('description');
        
        // Get homepage content
        $home_page_id = get_option('page_on_front');
        $home_page_summary = '';
        
        if ($home_page_id) {
            // Check if home page is translated in current language
            $home_page_id = pll_get_post($home_page_id, $lang_slug);
            
            if ($home_page_id) {
                $home_page = get_post($home_page_id);
                
                // Clean page builder content
                $home_page_content = $this->clean_page_builder_content($home_page->post_content);
                
                // If after cleaning we have no content, try to use the excerpt or title
                if (empty($home_page_content)) {
                    if (!empty($home_page->post_excerpt)) {
                        $home_page_content = $home_page->post_excerpt;
                    } else {
                        $home_page_content = "Homepage: " . $home_page->post_title;
                    }
                }
                
                $home_page_summary = wp_trim_words($home_page_content, 100);
            }
        }
        
        // Start building content
        $content = "# {$site_title}\n";
        $content .= "> {$site_tagline}\n\n";
        
        if (!empty($home_page_summary)) {
            $content .= "{$home_page_summary}\n\n";
        }
        
        // Add menu items
        $content .= "## Menu\n";
        
        // Get menu locations
        $locations = get_nav_menu_locations();
        
        if (!empty($locations)) {
            $primary_menu_id = 0;
            
            // Try to find the primary menu
            foreach ($locations as $location => $menu_id) {
                if (strpos($location, 'primary') !== false || strpos($location, 'main') !== false || strpos($location, 'header') !== false) {
                    $primary_menu_id = $menu_id;
                    break;
                }
            }
            
            // If primary menu not found, take the first menu
            if (!$primary_menu_id && !empty($locations)) {
                $primary_menu_id = reset($locations);
            }
            
            if ($primary_menu_id) {
                // Get menu items for the current language
                $menu_items = wp_get_nav_menu_items($primary_menu_id);
                
                if (!empty($menu_items)) {
                    $menu_structure = array();
                    
                    // First pass: collect all items
                    foreach ($menu_items as $item) {
                        // Check if this menu item is for the current language
                        if (function_exists('pll_get_post_language') && 
                            $item->object_id && 
                            $item->object !== 'custom' && 
                            pll_get_post_language($item->object_id) !== $lang_slug) {
                            continue;
                        }
                        
                        $menu_structure[$item->ID] = array(
                            'ID' => $item->ID,
                            'title' => $item->title,
                            'url' => $item->url,
                            'parent' => $item->menu_item_parent,
                            'children' => array(),
                            'summary' => !empty($item->description) ? wp_trim_words(strip_tags($item->description), 20) : "Menu link to " . $item->title
                        );
                    }
                    
                    // Second pass: build the tree
                    foreach ($menu_structure as $id => $item) {
                        if (!empty($item['parent']) && isset($menu_structure[$item['parent']])) {
                            $menu_structure[$item['parent']]['children'][] = $id;
                        }
                    }
                    
                    // Third pass: generate markdown
                    foreach ($menu_structure as $id => $item) {
                        if (empty($item['parent'])) {
                            $content .= " - [{$item['title']}]({$item['url']}): {$item['summary']}\n";
                            
                            foreach ($item['children'] as $child_id) {
                                $child = $menu_structure[$child_id];
                                $content .= " -- [{$child['title']}]({$child['url']}): {$child['summary']}\n";
                            }
                        }
                    }
                } else {
                    $content .= " - No menu items found for this language\n";
                }
            } else {
                $content .= " - No menu defined\n";
            }
        } else {
            $content .= " - No menu defined\n";
        }
        
        $content .= "\n";
        
        // Get pages
        $content .= "## Pages\n";
        
        $pages_args = array(
            'post_type' => 'page',
            'posts_per_page' => -1,
            'post_status' => 'publish',
            'lang' => $lang_slug,
        );
        
        $pages = get_posts($pages_args);
        
        if (!empty($pages)) {
            foreach ($pages as $page) {
                $page_url = get_permalink($page->ID);
                
                // Clean page builder content
                $page_content = $this->clean_page_builder_content($page->post_content);
                
                // If after cleaning we have no content, try to use the excerpt or title
                if (empty($page_content)) {
                    if (!empty($page->post_excerpt)) {
                        $page_content = $page->post_excerpt;
                    } else {
                        $page_content = "Page about " . $page->post_title;
                    }
                }
                
                $page_summary = wp_trim_words($page_content, 50);
                $content .= "- [{$page->post_title}]({$page_url}): {$page_summary}\n";
            }
        } else {
            $content .= "- No pages found for this language\n";
        }
        
        $content .= "\n";
        
        // Get latest blog posts
        $content .= "## Blog\n";
        
        $posts_args = array(
            'post_type' => 'post',
            'posts_per_page' => 5,
            'post_status' => 'publish',
            'lang' => $lang_slug,
        );
        
        $posts = get_posts($posts_args);
        
        if (!empty($posts)) {
            foreach ($posts as $post) {
                $post_url = get_permalink($post->ID);
                
                // Clean page builder content
                $post_content = $this->clean_page_builder_content($post->post_content);
                
                // If after cleaning we have no content, try to use the excerpt or title
                if (empty($post_content)) {
                    if (!empty($post->post_excerpt)) {
                        $post_content = $post->post_excerpt;
                    } else {
                        $post_content = "Blog post about " . $post->post_title;
                    }
                }
                
                $post_summary = wp_trim_words($post_content, 50);
                $content .= "- [{$post->post_title}]({$post_url}): {$post_summary}\n";
            }
        } else {
            $content .= "- No blog posts found for this language\n";
        }
        
        return $content;
    }

    /**
     * Clean page builder content to extract meaningful text
     *
     * @param string $content Raw post content
     * @return string Cleaned content with only meaningful text
     */
    private function clean_page_builder_content($content) {
        // If content is empty, return empty string
        if (empty($content)) {
            return '';
        }
        
        // Remove all shortcodes first
        $content = preg_replace('/\[[^\]]+\]/', ' ', $content);
        
        // Remove HTML tags
        $content = strip_tags($content);
        
        // Remove HTML entities
        $content = html_entity_decode($content);
        
        // Remove multiple spaces, tabs, and newlines
        $content = preg_replace('/\s+/', ' ', $content);
        
        // Remove control characters
        $content = preg_replace('/[\x00-\x1F\x7F]/', '', $content);
        
        // Trim leading/trailing whitespace
        $content = trim($content);
        
        // If the remaining content is too short (less than 10 chars), it's probably not useful
        if (strlen($content) < 10) {
            return '';
        }
        
        return $content;
    }

    /**
     * Alternative way to get page content for page builders
     * 
     * @param int $post_id Post ID
     * @return string Extracted content
     */
    private function get_alternative_content($post_id) {
        // Try to get meta fields that might contain actual text content
        // This is specific to different page builders
        
        // WPBakery / Visual Composer
        $vc_content = get_post_meta($post_id, '_wpb_shortcodes_custom_css', true);
        if (!empty($vc_content)) {
            return $this->clean_page_builder_content($vc_content);
        }
        
        // Elementor
        $elementor_content = get_post_meta($post_id, '_elementor_data', true);
        if (!empty($elementor_content)) {
            // Try to extract text from Elementor JSON
            if (is_string($elementor_content) && $this->is_json($elementor_content)) {
                $elementor_data = json_decode($elementor_content, true);
                return $this->extract_text_from_elementor($elementor_data);
            }
        }
        
        // Divi
        $divi_content = get_post_meta($post_id, '_et_pb_use_builder', true);
        if ($divi_content === 'on') {
            $et_content = get_post_meta($post_id, '_et_pb_post_content_layout', true);
            if (!empty($et_content)) {
                return $this->clean_page_builder_content($et_content);
            }
        }
        
        // Beaver Builder
        $beaver_content = get_post_meta($post_id, '_fl_builder_data', true);
        if (!empty($beaver_content) && is_array($beaver_content)) {
            $text = '';
            foreach ($beaver_content as $node) {
                if (isset($node->settings->text)) {
                    $text .= ' ' . $node->settings->text;
                } elseif (isset($node->settings->content)) {
                    $text .= ' ' . $node->settings->content;
                }
            }
            return $this->clean_page_builder_content($text);
        }
        
        // Try to get post excerpt as fallback
        $post = get_post($post_id);
        if (!empty($post->post_excerpt)) {
            return $post->post_excerpt;
        }
        
        // Return empty string if nothing found
        return '';
    }

    /**
     * Check if a string is valid JSON
     *
     * @param string $string String to check
     * @return bool True if valid JSON, false otherwise
     */
    private function is_json($string) {
        json_decode($string);
        return (json_last_error() === JSON_ERROR_NONE);
    }

    /**
     * Extract text from Elementor data
     *
     * @param array $elementor_data Elementor data array
     * @return string Extracted text
     */
    private function extract_text_from_elementor($elementor_data) {
        $text = '';
        
        if (!is_array($elementor_data)) {
            return $text;
        }
        
        foreach ($elementor_data as $element) {
            // Check for heading elements
            if (isset($element['settings']['title'])) {
                $text .= ' ' . $element['settings']['title'];
            }
            
            // Check for text editor elements
            if (isset($element['settings']['editor'])) {
                $text .= ' ' . strip_tags($element['settings']['editor']);
            }
            
            // Check for inner elements
            if (isset($element['elements']) && is_array($element['elements'])) {
                $text .= ' ' . $this->extract_text_from_elementor($element['elements']);
            }
        }
        
        return $text;
    }
    /**
     * Add rewrite rules for llms.txt files
     */
    public function add_rewrite_rules() {
        // Check if Polylang is active
        if (!function_exists('pll_languages_list')) {
            return;
        }
        
        // Add rewrite rule for the default language (root)
        add_rewrite_rule(
            '^llms\.txt$',
            'index.php?pllg_llms=1&pllg_lang=' . pll_default_language(),
            'top'
        );
        
        // Add rewrite rules for other languages
        $languages = pll_languages_list(array('fields' => 'slug'));
        foreach ($languages as $lang_slug) {
            add_rewrite_rule(
                '^' . $lang_slug . '/llms\.txt$',
                'index.php?pllg_llms=1&pllg_lang=' . $lang_slug,
                'top'
            );
        }
        
        // Add query vars
        add_filter('query_vars', function($vars) {
            $vars[] = 'pllg_llms';
            $vars[] = 'pllg_lang';
            return $vars;
        });
    }
    
    /**
     * Serve the LLMs.txt file
     */
    public function serve_llms_file() {
        global $wp_query;
        
        if (isset($wp_query->query_vars['pllg_llms']) && $wp_query->query_vars['pllg_llms'] == '1' && isset($wp_query->query_vars['pllg_lang'])) {
            $lang_slug = $wp_query->query_vars['pllg_lang'];
            
            // Get upload directory
            $upload_dir = wp_upload_dir();
            $filename = $upload_dir['basedir'] . '/llms-txt/llms-' . $lang_slug . '.txt';
            
            // Check if file exists
            if (file_exists($filename)) {
                header('Content-Type: text/plain');
                header('Content-Disposition: inline; filename="llms.txt"');
                readfile($filename);
                exit;
            } else {
                // Generate file if it doesn't exist
                $this->generate_llms_file_for_language($lang_slug);
                
                if (file_exists($filename)) {
                    header('Content-Type: text/plain');
                    header('Content-Disposition: inline; filename="llms.txt"');
                    readfile($filename);
                    exit;
                } else {
                    // File couldn't be generated, return 404
                    $wp_query->set_404();
                    status_header(404);
                    return;
                }
            }
        }
    }
}

// Initialize the plugin
Polylang_LLMs_Generator::get_instance();
