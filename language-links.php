<?php
/**
 * Plugin Name: Language Links Meta Box
 * Description: Adds a custom meta box to WordPress posts to add language selection links, and displays them at the bottom of each post.
 * Plugin URI:  https://github.com/sushyant/language-links
 * Version: 1.1
 * Author: Sushyant Zavarzadeh
 * Author URI:  https://sushyant.com
 * License: GPL v3
 */

// Custom Database Table on Plugin Activation
function language_links_install() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'language_links';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        post_id BIGINT(20) UNSIGNED NOT NULL,
        language VARCHAR(255) DEFAULT NULL,
        label VARCHAR(255) DEFAULT NULL,
        link VARCHAR(255) DEFAULT NULL,
        PRIMARY KEY (id),
        KEY post_id (post_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);

    // Debugging: Log table creation success or failure
    if ($wpdb->get_var("SHOW TABLES LIKE '{$table_name}'") != $table_name) {
        error_log('Language Links table creation failed.');
    } else {
        error_log('Language Links table created successfully.');
    }
}
register_activation_hook(__FILE__, 'language_links_install');

// Settings Page for Plugin
function language_links_settings_menu() {
    add_menu_page(
        'Language Links Settings',
        'Language Links',
        'manage_options',
        'language-links-settings',
        'language_links_settings_page',
        'dashicons-admin-generic'
    );
}
add_action('admin_menu', 'language_links_settings_menu');

function language_links_enqueue_media_uploader($hook_suffix) {
    if ($hook_suffix === 'toplevel_page_language-links-settings') {
        wp_enqueue_media();
        wp_enqueue_script('language-links-media-uploader', plugin_dir_url(__FILE__) . 'language-links-media-uploader.js', array('jquery'), null, true);
    }
}
add_action('admin_enqueue_scripts', 'language_links_enqueue_media_uploader');

function language_links_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }

    // Save settings if form is submitted
    if (isset($_POST['language_links_settings_submit'])) {
        check_admin_referer('language_links_settings_action', 'language_links_settings_nonce');

        $language_labels = isset($_POST['language_label']) ? array_map('sanitize_text_field', $_POST['language_label']) : [];
        $language_icons = isset($_POST['language_icon']) ? array_map('esc_url_raw', $_POST['language_icon']) : [];

        // Remove empty labels (in case a language was removed)
        $language_labels = array_filter($language_labels);
        $language_icons = array_filter($language_icons);

        // Update the options in the database
        update_option('language_links_labels', $language_labels);
        update_option('language_links_icons', $language_icons);

        // Clean up any data associated with removed languages
        // Get the previous labels
        $previous_labels = get_option('language_links_labels_previous', []);
        // Find out which labels have been removed
        $removed_labels = array_diff($previous_labels, $language_labels);

        if (!empty($removed_labels)) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'language_links';
            foreach ($removed_labels as $removed_label) {
                // Delete entries from custom table for the removed label
                $wpdb->delete($table_name, array('label' => $removed_label), array('%s'));
            }
        }

        // Update the previous labels option for next time
        update_option('language_links_labels_previous', $language_labels);
    } else {
        // First time, set the 'language_links_labels_previous' option if not set
        if (!get_option('language_links_labels_previous')) {
            $initial_labels = get_option('language_links_labels', []);
            update_option('language_links_labels_previous', $initial_labels);
        }
    }

    // Retrieve current settings
    $language_labels = get_option('language_links_labels', []);
    $language_icons = get_option('language_links_icons', []);

    // Ensure at least one default value exists
    if (empty($language_labels)) {
        $language_labels = ['English Link', 'Persian Link'];
        update_option('language_links_labels', $language_labels);
    }
    if (empty($language_icons)) {
        $language_icons = ['https://mrpsychologist.com/up/uk.svg', 'https://mrpsychologist.com/up/ir.svg'];
        update_option('language_links_icons', $language_icons);
    }
    ?>
    <div class="wrap">
        <h1>Language Links Settings</h1>
        <form method="post" id="language-links-form">
            <?php wp_nonce_field('language_links_settings_action', 'language_links_settings_nonce'); ?>
            <div id="language-links-container">
                <?php foreach ($language_labels as $index => $label) : ?>
                    <div class="language-link">
                        <h2>Language <?php echo $index + 1; ?></h2>
                        <table class="form-table">
                            <tr valign="top">
                                <th scope="row">Language Field Label:</th>
                                <td><input type="text" name="language_label[]" value="<?php echo esc_attr($label); ?>" style="width: 100%;" /></td>
                            </tr>
                            <tr valign="top">
                                <th scope="row">Language Icon (SVG URL):</th>
                                <td>
                                    <input type="text" name="language_icon[]" class="language_icon" value="<?php echo esc_url(isset($language_icons[$index]) ? $language_icons[$index] : ''); ?>" style="width: 70%;" />
                                    <button type="button" class="button upload-icon-button" data-target=".language_icon">Upload Icon</button>
                                </td>
                            </tr>
                        </table>
                        <button type="button" class="button remove-language-button">Remove Language</button>
                    </div>
                <?php endforeach; ?>
            </div>
            <button type="button" id="add-language-button" class="button">Add Another Language</button>
            <?php submit_button('Save Settings', 'primary', 'language_links_settings_submit'); ?>
        </form>
    </div>
    <script>
        jQuery(document).ready(function($) {
            $(document).on('click', '.upload-icon-button', function(e) {
                e.preventDefault();
                var button = $(this);
                var target = button.closest('td').find('.language_icon');

                var frame = wp.media({
                    title: 'Select or Upload an SVG',
                    button: {
                        text: 'Use this SVG'
                    },
                    library: {
                        type: 'image/svg+xml'
                    },
                    multiple: false
                });

                frame.on('select', function() {
                    var attachment = frame.state().get('selection').first().toJSON();
                    target.val(attachment.url);
                });

                frame.open();
            });

            $('#add-language-button').click(function() {
                var languageIndex = $('.language-link').length + 1;
                var newLanguageHtml = `
                <div class="language-link">
                    <h2>Language ${languageIndex}</h2>
                    <table class="form-table">
                        <tr valign="top">
                            <th scope="row">Language Field Label:</th>
                            <td><input type="text" name="language_label[]" value="" style="width: 100%;" /></td>
                        </tr>
                        <tr valign="top">
                            <th scope="row">Language Icon (SVG URL):</th>
                            <td>
                                <input type="text" name="language_icon[]" class="language_icon" value="" style="width: 70%;" />
                                <button type="button" class="button upload-icon-button">Upload Icon</button>
                            </td>
                        </tr>
                    </table>
                    <button type="button" class="button remove-language-button">Remove Language</button>
                </div>`;
                $('#language-links-container').append(newLanguageHtml);
                updateLanguageHeadings();
            });

            $(document).on('click', '.remove-language-button', function() {
                $(this).closest('.language-link').remove();
                updateLanguageHeadings();
            });

            function updateLanguageHeadings() {
                $('.language-link').each(function(index) {
                    $(this).find('h2').text('Language ' + (index + 1));
                });
            }
        });
    </script>
    <?php
}

// Add Custom Meta Box for Language Links
function add_language_links_meta_box() {
    add_meta_box(
        'language_links_meta_box', // Unique ID
        'Language Links',          // Box title
        'display_language_links_meta_box', // Content callback
        'post',                    // Post type (can change to 'page' if needed)
        'normal',                  // Context ('normal' for main area instead of sidebar)
        'default'                  // Priority ('default' to ensure it shows correctly)
    );
}
add_action('add_meta_boxes', 'add_language_links_meta_box');

function display_language_links_meta_box($post) {
    // Add nonce for security
    wp_nonce_field('language_links_action', 'language_links_nonce');

    // Retrieve settings from the options page
    $language_labels = get_option('language_links_labels', []);

    // Retrieve existing values from the custom database table
    global $wpdb;
    $table_name = $wpdb->prefix . 'language_links';
    $links = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE post_id = %d", $post->ID));
    $links_map = [];
    foreach ($links as $link) {
        $links_map[$link->label] = $link->link;
    }

    foreach ($language_labels as $label) {
        $link_value = isset($links_map[$label]) ? esc_url($links_map[$label]) : '';
        ?>
        <p>
            <label for="link_label_<?php echo esc_attr($label); ?>"><?php echo esc_html($label); ?>:</label><br>
            <input type="text" name="links[<?php echo esc_attr($label); ?>]" id="link_label_<?php echo esc_attr($label); ?>" value="<?php echo $link_value; ?>" style="width: 100%;" />
        </p>
        <?php
    }
}

// Save the custom meta box values
function save_language_links_meta_box($post_id) {
    // Security checks
    if (!isset($_POST['language_links_nonce']) || !wp_verify_nonce($_POST['language_links_nonce'], 'language_links_action')) {
        return $post_id;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return $post_id;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return $post_id;
    }

    global $wpdb;
    $table_name = $wpdb->prefix . 'language_links';

    // Prepare data for insertion/updating
    if (isset($_POST['links'])) {
        foreach ($_POST['links'] as $label => $link) {
            $existing_link = $wpdb->get_row($wpdb->prepare("SELECT * FROM $table_name WHERE post_id = %d AND label = %s", $post_id, $label));
            if ($existing_link) {
                // Update existing link
                $wpdb->update(
                    $table_name,
                    [
                        'link' => esc_url_raw($link)
                    ],
                    [ 'id' => intval($existing_link->id) ],
                    [ '%s' ],
                    [ '%d' ]
                );
            } else {
                // Insert new link
                $wpdb->insert(
                    $table_name,
                    [
                        'post_id' => $post_id,
                        'label' => $label,
                        'link' => esc_url_raw($link),
                        'language' => $label
                    ],
                    [
                        '%d', '%s', '%s', '%s'
                    ]
                );
            }
        }
    }
}
add_action('save_post', 'save_language_links_meta_box');

// Append Language Links to Post Content
function append_language_links_to_content($content) {
    if (is_single() && in_the_loop() && is_main_query() && !post_password_required()) {
        global $wpdb;
        $table_name = $wpdb->prefix . 'language_links';
        $links = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name WHERE post_id = %d", get_the_ID()));

        // Retrieve current settings for icons
        $language_icons = get_option('language_links_icons', []);
        $language_labels = get_option('language_links_labels', []);
        $icons_map = array_combine($language_labels, $language_icons);

        if ($links) {
            $links_html = '<div class="language-links" style="display: inline-flex; align-items: center; margin-top: 15px;">';
            $links_html .= '<h5 style="margin: 0; padding-right: 10px;">Choose the language:&nbsp;&nbsp;</h5>';

            foreach ($links as $link) {
                $label = isset($link->label) ? $link->label : '';
                $icon = isset($icons_map[$label]) ? esc_url($icons_map[$label]) : '';
                $link_url = isset($link->link) ? esc_url($link->link) : '';
                $link_label = isset($link->label) ? esc_attr($link->label) : '';

                $links_html .= '<a href="' . $link_url . '" hreflang="' . (isset($link->language) ? esc_attr($link->language) : '') . '" style="margin-right: 10px;">
                                    <img class="mrl" src="' . esc_url($icon) . '" alt="' . $link_label . '" width="30px" style="margin-right: 10px;" />
                                </a>';
            }

            $links_html .= '</div>';
            $content .= $links_html;
        }
    }
    return $content;
}
add_filter('the_content', 'append_language_links_to_content');
