<?php
/**
 * Plugin Name: Language Links Meta Box
 * Description: Adds a custom meta box to WordPress posts to add language selection links, and displays them at the desired position in each post.
 * Plugin URI:  https://github.com/sushyant/language-links
 * Version: 1.2
 * Author: Sushyant Zavarzadeh
 * Author URI: https://sushyant.com
 * License: GPL v3
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Language_Links_Meta_Box {

    public static function init() {
        register_activation_hook( __FILE__, array( __CLASS__, 'language_links_install' ) );
        add_action( 'admin_menu', array( __CLASS__, 'language_links_settings_menu' ) );
        add_action( 'admin_enqueue_scripts', array( __CLASS__, 'language_links_enqueue_media_uploader' ) );
        add_action( 'add_meta_boxes', array( __CLASS__, 'add_language_links_meta_box' ) );
        add_action( 'save_post', array( __CLASS__, 'save_language_links_meta_box' ) );
        add_filter( 'the_content', array( __CLASS__, 'append_language_links_to_content' ), 20 );
    }

    // Custom Database Table on Plugin Activation
    public static function language_links_install() {
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

        require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );
        dbDelta( $sql );

        // Debugging: Log table creation success or failure
        if ( $wpdb->get_var( "SHOW TABLES LIKE '{$table_name}'" ) != $table_name ) {
            error_log( 'Language Links table creation failed.' );
        } else {
            error_log( 'Language Links table created successfully.' );
        }
    }

    // Settings Page for Plugin
    public static function language_links_settings_menu() {
        add_menu_page(
            'Language Links Settings',
            'Language Links',
            'manage_options',
            'language-links-settings',
            array( __CLASS__, 'language_links_settings_page' ),
            'dashicons-admin-generic'
        );
    }

    public static function language_links_enqueue_media_uploader( $hook_suffix ) {
        if ( $hook_suffix === 'toplevel_page_language-links-settings' ) {
            wp_enqueue_media();
            wp_enqueue_script(
                'language-links-admin',
                plugin_dir_url( __FILE__ ) . 'language-links-admin.js',
                array( 'jquery' ),
                '1.0',
                true
            );
        }
    }

    public static function language_links_settings_page() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        // Save settings if form is submitted
        if ( isset( $_POST['language_links_settings_submit'] ) ) {
            check_admin_referer( 'language_links_settings_action', 'language_links_settings_nonce' );

            $language_labels = isset( $_POST['language_label'] ) ? array_map( 'sanitize_text_field', $_POST['language_label'] ) : array();
            $language_icons  = isset( $_POST['language_icon'] ) ? array_map( 'esc_url_raw', $_POST['language_icon'] ) : array();

            // Remove empty labels (in case a language was removed)
            $language_labels = array_filter( $language_labels );
            $language_icons  = array_filter( $language_icons );

            // Update the options in the database
            update_option( 'language_links_labels', $language_labels );
            update_option( 'language_links_icons', $language_icons );

            // Save the Language Link Position setting
            if ( isset( $_POST['language_link_position'] ) ) {
                $allowed_positions      = array( 'below', 'before', 'both' );
                $language_link_position = sanitize_text_field( $_POST['language_link_position'] );
                if ( in_array( $language_link_position, $allowed_positions, true ) ) {
                    update_option( 'language_link_position', $language_link_position );
                } else {
                    // Set to default if invalid value
                    update_option( 'language_link_position', 'below' );
                }
            }

            // Clean up any data associated with removed languages
            // Get the previous labels
            $previous_labels = get_option( 'language_links_labels_previous', array() );
            // Find out which labels have been removed
            $removed_labels = array_diff( $previous_labels, $language_labels );

            if ( ! empty( $removed_labels ) ) {
                global $wpdb;
                $table_name = $wpdb->prefix . 'language_links';
                foreach ( $removed_labels as $removed_label ) {
                    // Delete entries from custom table for the removed label
                    $wpdb->delete( $table_name, array( 'label' => $removed_label ), array( '%s' ) );
                }
            }

            // Update the previous labels option for next time
            update_option( 'language_links_labels_previous', $language_labels );
        } else {
            // First time, set the 'language_links_labels_previous' option if not set
            if ( ! get_option( 'language_links_labels_previous' ) ) {
                $initial_labels = get_option( 'language_links_labels', array() );
                update_option( 'language_links_labels_previous', $initial_labels );
            }
        }

        // Retrieve current settings
        $language_labels         = get_option( 'language_links_labels', array() );
        $language_icons          = get_option( 'language_links_icons', array() );
        $language_link_position  = get_option( 'language_link_position', 'below' ); // Default to 'below'

        // Ensure at least one default value exists
        if ( empty( $language_labels ) ) {
            $language_labels = array( 'English Link', 'Persian Link' );
            update_option( 'language_links_labels', $language_labels );
        }
        if ( empty( $language_icons ) ) {
            $language_icons = array( 'https://mrpsychologist.com/up/uk.svg', 'https://mrpsychologist.com/up/ir.svg' );
            update_option( 'language_links_icons', $language_icons );
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'Language Links Settings', 'language-links-meta-box' ); ?></h1>
            <form method="post" id="language-links-form">
                <?php wp_nonce_field( 'language_links_settings_action', 'language_links_settings_nonce' ); ?>
                <div id="language-links-container">
                    <?php foreach ( $language_labels as $index => $label ) : ?>
                        <div class="language-link">
                            <h2><?php printf( esc_html__( 'Language %d', 'language-links-meta-box' ), intval( $index + 1 ) ); ?></h2>
                            <table class="form-table">
                                <tr valign="top">
                                    <th scope="row">
                                        <label><?php esc_html_e( 'Language Field Label:', 'language-links-meta-box' ); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" name="language_label[]" value="<?php echo esc_attr( $label ); ?>" style="width: 100%;" />
                                    </td>
                                </tr>
                                <tr valign="top">
                                    <th scope="row">
                                        <label><?php esc_html_e( 'Language Icon (SVG URL):', 'language-links-meta-box' ); ?></label>
                                    </th>
                                    <td>
                                        <input type="text" name="language_icon[]" class="language_icon" value="<?php echo esc_url( isset( $language_icons[ $index ] ) ? $language_icons[ $index ] : '' ); ?>" style="width: 70%;" />
                                        <button type="button" class="button upload-icon-button" data-target=".language_icon"><?php esc_html_e( 'Upload Icon', 'language-links-meta-box' ); ?></button>
                                    </td>
                                </tr>
                            </table>
                            <button type="button" class="button remove-language-button"><?php esc_html_e( 'Remove Language', 'language-links-meta-box' ); ?></button>
                        </div>
                    <?php endforeach; ?>
                </div>
                <button type="button" id="add-language-button" class="button"><?php esc_html_e( 'Add Another Language', 'language-links-meta-box' ); ?></button>

                <!-- New Language Link Position setting -->
                <h2><?php esc_html_e( 'Language Link Position', 'language-links-meta-box' ); ?></h2>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e( 'Display Position:', 'language-links-meta-box' ); ?></th>
                        <td>
                            <select name="language_link_position">
                                <option value="below" <?php selected( $language_link_position, 'below' ); ?>><?php esc_html_e( 'Below the post', 'language-links-meta-box' ); ?></option>
                                <option value="before" <?php selected( $language_link_position, 'before' ); ?>><?php esc_html_e( 'Before the first paragraph', 'language-links-meta-box' ); ?></option>
                                <option value="both" <?php selected( $language_link_position, 'both' ); ?>><?php esc_html_e( 'Both below the post and before the first paragraph', 'language-links-meta-box' ); ?></option>
                            </select>
                        </td>
                    </tr>
                </table>

                <?php submit_button( __( 'Save Settings', 'language-links-meta-box' ), 'primary', 'language_links_settings_submit' ); ?>
            </form>
        </div>
        <?php
    }

    // Add Custom Meta Box for Language Links
    public static function add_language_links_meta_box() {
        add_meta_box(
            'language_links_meta_box', // Unique ID
            __( 'Language Links', 'language-links-meta-box' ), // Box title
            array( __CLASS__, 'display_language_links_meta_box' ), // Content callback
            'post', // Post type (can change to 'page' if needed)
            'normal', // Context ('normal' for main area instead of sidebar)
            'default' // Priority ('default' to ensure it shows correctly)
        );
    }

    public static function display_language_links_meta_box( $post ) {
        // Add nonce for security
        wp_nonce_field( 'language_links_action', 'language_links_nonce' );

        // Retrieve settings from the options page
        $language_labels = get_option( 'language_links_labels', array() );

        // Retrieve existing values from the custom database table
        global $wpdb;
        $table_name = $wpdb->prefix . 'language_links';
        $links      = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_name WHERE post_id = %d", $post->ID ) );
        $links_map  = array();
        foreach ( $links as $link ) {
            $links_map[ $link->label ] = $link->link;
        }

        echo '<table class="form-table">';
        foreach ( $language_labels as $label ) {
            $link_value = isset( $links_map[ $label ] ) ? esc_url( $links_map[ $label ] ) : '';
            ?>
            <tr>
                <th>
                    <label for="link_label_<?php echo esc_attr( $label ); ?>"><?php echo esc_html( $label ); ?>:</label>
                </th>
                <td>
                    <input type="text" name="links[<?php echo esc_attr( $label ); ?>]" id="link_label_<?php echo esc_attr( $label ); ?>" value="<?php echo esc_attr( $link_value ); ?>" style="width: 100%;" />
                </td>
            </tr>
            <?php
        }
        echo '</table>';
    }

    // Save the custom meta box values
    public static function save_language_links_meta_box( $post_id ) {
        // Security checks
        if ( ! isset( $_POST['language_links_nonce'] ) || ! wp_verify_nonce( $_POST['language_links_nonce'], 'language_links_action' ) ) {
            return $post_id;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return $post_id;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return $post_id;
        }

        global $wpdb;
        $table_name = $wpdb->prefix . 'language_links';

        // Prepare data for insertion/updating
        if ( isset( $_POST['links'] ) ) {
            foreach ( $_POST['links'] as $label => $link ) {
                // Sanitize inputs
                $label = sanitize_text_field( $label );
                $link  = esc_url_raw( $link );

                $existing_link = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table_name WHERE post_id = %d AND label = %s", $post_id, $label ) );
                if ( $existing_link ) {
                    // Update existing link
                    $wpdb->update(
                        $table_name,
                        array(
                            'link' => $link,
                        ),
                        array( 'id' => intval( $existing_link->id ) ),
                        array( '%s' ),
                        array( '%d' )
                    );
                } else {
                    // Insert new link
                    $wpdb->insert(
                        $table_name,
                        array(
                            'post_id'  => $post_id,
                            'label'    => $label,
                            'link'     => $link,
                            'language' => $label,
                        ),
                        array(
                            '%d',
                            '%s',
                            '%s',
                            '%s',
                        )
                    );
                }
            }
        }
    }

    // Append Language Links to Post Content
    public static function append_language_links_to_content( $content ) {
        if ( is_single() && in_the_loop() && is_main_query() && ! post_password_required() ) {
            global $wpdb;
            $table_name = $wpdb->prefix . 'language_links';
            $links      = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $table_name WHERE post_id = %d", get_the_ID() ) );

            // Retrieve current settings for icons and position
            $language_icons         = get_option( 'language_links_icons', array() );
            $language_labels        = get_option( 'language_links_labels', array() );
            $language_link_position = get_option( 'language_link_position', 'below' ); // Default to 'below'

            $icons_map = array_combine( $language_labels, $language_icons );

            if ( $links ) {
                $links_html  = '<div class="language-links" style="display: inline-flex; align-items: center; margin-top: 20px; margin-bottom: 20px;">';
                $links_html .= '<h5 style="margin: 0; padding-right: 10px;">' . esc_html__( 'Choose the language:', 'language-links-meta-box' ) . '&nbsp;&nbsp;</h5>';

                foreach ( $links as $link ) {
                    $label     = isset( $link->label ) ? $link->label : '';
                    $icon      = isset( $icons_map[ $label ] ) ? esc_url( $icons_map[ $label ] ) : '';
                    $link_url  = isset( $link->link ) ? esc_url( $link->link ) : '';
                    $link_label = isset( $link->label ) ? esc_attr( $link->label ) : '';

                    $links_html .= '<a href="' . esc_url( $link_url ) . '" hreflang="' . esc_attr( isset( $link->language ) ? $link->language : '' ) . '" style="margin-right: 10px;">';
                    $links_html .= '<img class="mrl" src="' . esc_url( $icon ) . '" alt="' . esc_attr( $link_label ) . '" width="30px" style="margin-right: 10px;" />';
                    $links_html .= '</a>';
                }

                $links_html .= '</div>';

                // Insert the links based on the selected position
                switch ( $language_link_position ) {
                    case 'before':
                        $content = self::insert_before_paragraph( $links_html, 1, $content );
                        break;
                    case 'both':
                        $content = self::insert_before_paragraph( $links_html, 1, $content );
                        $content .= $links_html;
                        break;
                    case 'below':
                    default:
                        $content .= $links_html;
                        break;
                }
            }
        }
        return $content;
    }

    // function to insert content before a specific paragraph
    private static function insert_before_paragraph( $insertion, $paragraph_id, $content ) {
        $closing_p  = '</p>';
        $paragraphs = explode( $closing_p, $content );

        foreach ( $paragraphs as $index => &$paragraph ) {
            if ( trim( $paragraph ) ) {
                $paragraph .= $closing_p;
            }
            if ( $paragraph_id === $index + 1 ) {
                $paragraph = $insertion . $paragraph;
                break; // Insert only before the specified paragraph
            }
        }

        return implode( '', $paragraphs );
    }
}

Language_Links_Meta_Box::init();
