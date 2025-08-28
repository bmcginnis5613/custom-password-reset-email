<?php
/*
Plugin Name: Custom Password Reset Email
Description: Customizes the WordPress password reset emails with a configurable logo and styling.
Author: FirstTracks Marketing
Author URI: https://firsttracksmarketing.com/
Version: 1.0.0
*/

if (!defined('ABSPATH')) {
    exit;
}

class CustomPasswordReset {
    
    private $option_name = 'custom_password_reset_settings';
    
    public function __construct() {
        add_action('init', array($this, 'init'));
    }
    
    public function init() {
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        add_filter('plugin_action_links_' . plugin_basename(__FILE__), array($this, 'add_settings_link'));
        $this->init_password_reset();
        add_action('admin_enqueue_scripts', array($this, 'admin_enqueue_scripts'));
    }
    
    public function init_password_reset() {
        add_filter('wp_mail_content_type', function() {
            return 'text/html';
        });
        
        add_filter('retrieve_password_title', array($this, 'custom_password_reset_subject'), 10, 2);
        add_filter('retrieve_password_message', array($this, 'custom_lost_password_email'), 10, 4);
    }
    
    public function add_admin_menu() {
        add_options_page(
            'Custom Password Reset Settings',
            'Password Reset Email',
            'manage_options',
            'custom-password-reset',
            array($this, 'admin_page')
        );
    }
    
    public function register_settings() {
        register_setting('custom_password_reset_settings', $this->option_name, array($this, 'sanitize_settings'));
        
        add_settings_section(
            'general_settings',
            'Email Settings',
            array($this, 'settings_section_callback'),
            'custom-password-reset'
        );
        
        add_settings_field(
            'logo_url',
            'Logo URL',
            array($this, 'logo_url_callback'),
            'custom-password-reset',
            'general_settings'
        );
        
        add_settings_field(
            'logo_max_height',
            'Logo Max Height (px)',
            array($this, 'logo_max_height_callback'),
            'custom-password-reset',
            'general_settings'
        );
        
        add_settings_field(
            'button_color',
            'Button Color',
            array($this, 'button_color_callback'),
            'custom-password-reset',
            'general_settings'
        );
        
        add_settings_field(
            'email_subject',
            'Email Subject',
            array($this, 'email_subject_callback'),
            'custom-password-reset',
            'general_settings'
        );

        add_settings_field(
            'show_ip_address',
            'Show IP Address',
            array($this, 'show_ip_address_callback'),
            'custom-password-reset',
            'general_settings'
        );
    }
    
    public function sanitize_settings($input) {
        $sanitized = array();
        
        if (isset($input['logo_url'])) {
            $sanitized['logo_url'] = esc_url_raw($input['logo_url']);
        }
        
        if (isset($input['logo_max_height'])) {
            $sanitized['logo_max_height'] = absint($input['logo_max_height']);
            if ($sanitized['logo_max_height'] < 1) {
                $sanitized['logo_max_height'] = 60;
            }
        }
        
        if (isset($input['button_color'])) {
            $sanitized['button_color'] = sanitize_hex_color($input['button_color']);
        }
        
        if (isset($input['email_subject'])) {
            $sanitized['email_subject'] = sanitize_text_field($input['email_subject']);
        }

        $sanitized['show_ip_address'] = isset($input['show_ip_address']) ? 1 : 0;
        
        return $sanitized;
    }
    
    public function settings_section_callback() {
        echo '<p>Configure the settings for your custom password reset email.</p>';
    }
    
    public function logo_url_callback() {
        $options = get_option($this->option_name);
        $logo_url = isset($options['logo_url']) ? $options['logo_url'] : '';
        
        echo '<input type="url" id="logo_url" name="' . $this->option_name . '[logo_url]" value="' . esc_attr($logo_url) . '" class="regular-text" />';
        echo '<input type="button" id="upload_logo_button" class="button" value="Upload Logo" />';
        echo '<p class="description">Enter the URL of your logo image or click "Upload Logo" to select from the media library.</p>';
        
        if ($logo_url) {
            echo '<div id="logo_preview" style="margin-top: 10px;">';
            echo '<img src="' . esc_url($logo_url) . '" style="max-height: 60px; border: 1px solid #ddd; padding: 5px;" />';
            echo '</div>';
        }
    }
    
    public function logo_max_height_callback() {
        $options = get_option($this->option_name);
        $max_height = isset($options['logo_max_height']) ? $options['logo_max_height'] : 60;
        
        echo '<input type="number" id="logo_max_height" name="' . $this->option_name . '[logo_max_height]" value="' . esc_attr($max_height) . '" min="1" max="200" />';
        echo '<p class="description">Maximum height for the logo in pixels.</p>';
    }
    
    public function button_color_callback() {
        $options = get_option($this->option_name);
        $button_color = isset($options['button_color']) ? $options['button_color'] : '#0073aa';
        
        echo '<input type="text" id="button_color" name="' . $this->option_name . '[button_color]" value="' . esc_attr($button_color) . '" class="color-field" />';
        echo '<p class="description">Choose the color for the reset password button.</p>';
    }
    
    public function email_subject_callback() {
        $options = get_option($this->option_name);
        $subject = isset($options['email_subject']) ? $options['email_subject'] : 'Password Reset for: ' . parse_url(site_url(), PHP_URL_HOST);

        echo '<input type="text" id="email_subject" name="' . $this->option_name . '[email_subject]" value="' . esc_attr($subject) . '" class="regular-text" />';
        echo '<p class="description">Enter the subject line for the password reset email.</p>';
    }

    public function show_ip_address_callback() {
        $options = get_option($this->option_name);
        $show_ip = isset($options['show_ip_address']) ? $options['show_ip_address'] : 1;
        
        echo '<input type="checkbox" id="show_ip_address" name="' . $this->option_name . '[show_ip_address]" value="1" ' . checked(1, $show_ip, false) . ' />';
        echo '<label for="show_ip_address">Display the IP address in the password reset email.</label>';
    }
    
    public function admin_enqueue_scripts($hook) {
        if ($hook !== 'settings_page_custom-password-reset') {
            return;
        }
        
        wp_enqueue_media();
        wp_enqueue_script('wp-color-picker');
        wp_enqueue_style('wp-color-picker');
        
        wp_add_inline_script('wp-color-picker', '
            jQuery(document).ready(function($) {
                $(".color-field").wpColorPicker();
                
                $("#upload_logo_button").click(function(e) {
                    e.preventDefault();
                    var mediaUploader = wp.media({
                        title: "Choose Logo",
                        button: {
                            text: "Use this image"
                        },
                        multiple: false
                    });
                    
                    mediaUploader.on("select", function() {
                        var attachment = mediaUploader.state().get("selection").first().toJSON();
                        $("#logo_url").val(attachment.url);
                        $("#logo_preview").html("<img src=\"" + attachment.url + "\" style=\"max-height: 60px; border: 1px solid #ddd; padding: 5px;\" />");
                    });
                    
                    mediaUploader.open();
                });
            });
        ');
    }
    
    public function admin_page() {
        if (isset($_GET['settings-updated']) && $_GET['settings-updated']) {
            add_settings_error('custom_password_reset_messages', 'custom_password_reset_message', 'Settings saved successfully!', 'updated');
        }
        
        settings_errors('custom_password_reset_messages');
        ?>
        <div class="wrap">
            <h1><?php echo esc_html(get_admin_page_title()); ?></h1>
            <form action="options.php" method="post">
                <?php
                settings_fields('custom_password_reset_settings');
                do_settings_sections('custom-password-reset');
                submit_button('Save Settings');
                ?>
            </form>
            
            <div class="card" style="margin-top: 20px;">
                <h2>Email Preview</h2>
                <?php echo $this->get_email_preview(); ?>
            </div>
        </div>
        <?php
    }
    
    public function add_settings_link($links) {
        $settings_link = '<a href="options-general.php?page=custom-password-reset">Settings</a>';
        array_push($links, $settings_link);
        return $links;
    }
    
    public function custom_password_reset_subject($title, $user_login) {
        $options = get_option($this->option_name);
        $site_domain = parse_url(site_url(), PHP_URL_HOST);

        if (!empty($options['email_subject'])) {
            return str_replace('{site_domain}', $site_domain, $options['email_subject']);
        }

        return 'Password Reset for: ' . $site_domain;
    }

    public function custom_lost_password_email($message, $key, $user_login, $user_data) {
        $options = get_option($this->option_name);
        $site_url = site_url();
        $site_name = get_bloginfo('name');
        
        // Generate the reset URL and sanitize it
        $reset_url = network_site_url("wp-login.php?action=rp&key=$key&login=" . rawurlencode($user_login), 'login');
        // Remove any tracking parameters (like UTM tags, if they exist)
        $reset_url = remove_query_arg(['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'], $reset_url);
        
        $logo_url = isset($options['logo_url']) && !empty($options['logo_url']) 
            ? $options['logo_url'] 
            : '';
            
        $logo_max_height = isset($options['logo_max_height']) ? $options['logo_max_height'] : 60;
        $button_color = isset($options['button_color']) ? $options['button_color'] : '#0073aa';
        $show_ip = isset($options['show_ip_address']) ? $options['show_ip_address'] : 1;

        $user_ip = '';
        if ($show_ip) {
            $user_ip = $_SERVER['REMOTE_ADDR'];
            if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
                $user_ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
            } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
                $user_ip = $_SERVER['HTTP_X_REAL_IP'];
            }
        }
        
        ob_start();
        ?>
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: auto; border: 1px solid #ddd; padding: 20px;">
            <?php if ($logo_url): ?>
            <div style="text-align: center; margin-bottom: 30px;">
                <img src="<?php echo esc_url($logo_url); ?>" 
                    alt="<?php echo esc_attr($site_name); ?>" 
                    style="height: <?php echo esc_attr($logo_max_height); ?>px; max-height: <?php echo esc_attr($logo_max_height); ?>px; width: auto; display: block; margin: 0 auto;" 
                    height="<?php echo esc_attr($logo_max_height); ?>"
                    width="auto">
            </div>
            <?php endif; ?>
            <p style="font-size: 16px; color: #555;">
                We received a request to reset the password for your account associated with <strong><?php echo esc_html($user_login); ?></strong>.
                If you did not request a password reset, please ignore this email.
            </p>
            <p style="font-size: 16px; color: #555;">
                Click the button below to reset your password:
            </p>
            <p style="text-align: center; margin: 30px 0;">
                <a href="<?php echo esc_url($reset_url); ?>" data-pm-no-track style="background-color: <?php echo esc_attr($button_color); ?>; color: #fff; padding: 12px 25px; border-radius: 5px; text-decoration: none; font-size: 16px;">
                    Reset Password
                </a>
            </p>
            <p style="font-size: 16px; color: #555;">
                If the button above doesn't work, copy and paste this link into your browser:<br>
                <a href="<?php echo esc_url($reset_url); ?>" data-pm-no-track><?php echo esc_html($reset_url); ?></a>
            </p>
            <?php if ($show_ip && $user_ip): ?>
            <p style="font-size: 16px; color: #555; margin-top: 30px;">
                This request was made from IP address: <?php echo esc_html($user_ip); ?>
            </p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
    
    private function get_email_preview() {
        $options = get_option($this->option_name);
        $site_url = site_url();
        $site_name = get_bloginfo('name');
        
        $logo_url = isset($options['logo_url']) && !empty($options['logo_url']) 
            ? $options['logo_url'] 
            : '';
            
        $logo_max_height = isset($options['logo_max_height']) ? $options['logo_max_height'] : 60;
        $button_color = isset($options['button_color']) ? $options['button_color'] : '#0073aa';
        $show_ip = isset($options['show_ip_address']) ? $options['show_ip_address'] : 1;
        
        ob_start();
        ?>
        <div style="font-family: Arial, sans-serif; max-width: 600px; margin: auto; border: 1px solid #ddd; padding: 20px; background: #f9f9f9;">
            <?php if ($logo_url): ?>
            <div style="text-align: center; margin-bottom: 30px;">
                <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr($site_name); ?>" style="max-height: <?php echo esc_attr($logo_max_height); ?>px;">
            </div>
            <?php endif; ?>
            <p style="font-size: 16px; color: #555;">
                We received a request to reset the password for your account associated with <strong>sample_user</strong>.
                If you did not request a password reset, please ignore this email.
            </p>
            <p style="font-size: 16px; color: #555;">
                Click the button below to reset your password:
            </p>
            <p style="text-align: center; margin: 30px 0;">
                <a href="#" style="background-color: <?php echo esc_attr($button_color); ?>; color: #fff; padding: 12px 25px; border-radius: 5px; text-decoration: none; font-size: 16px;">
                    Reset Password
                </a>
            </p>
            <p style="font-size: 16px; color: #555;">
                If the button above doesn't work, copy and paste this link into your browser:<br>
                <a href="#">https://yoursite.com/wp-login.php?action=rp&key=sample&login=sample_user</a>
            </p>
            <?php if ($show_ip): ?>
            <p style="font-size: 16px; color: #555; margin-top: 30px;">
                This request was made from IP address: 192.168.1.1
            </p>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}

new CustomPasswordReset();

