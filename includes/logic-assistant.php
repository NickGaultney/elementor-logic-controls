<?php

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly.
}

class Elementor_Logic_Assistant {

    /**
     * Initialize the logic assistant functionality
     */
    public static function init() {
        add_shortcode('logic_assistant', [__CLASS__, 'render_logic_assistant']);
        add_action('wp_enqueue_scripts', [__CLASS__, 'enqueue_styles']);
    }

    /**
     * Enqueue required styles
     */
    public static function enqueue_styles() {
        wp_enqueue_style(
            'logic-assistant-styles',
            ELC_URL . 'assets/css/logic-assistant.css',
            [],
            ELC_VERSION
        );
    }

    /**
     * Render the logic assistant output
     *
     * @param array $atts Shortcode attributes
     * @return string Rendered HTML
     */
    public static function render_logic_assistant($atts) {
        // Check if FluentForm is active
        if (!function_exists('wpFluent')) {
            return '<div class="logic-assistant-error">FluentForm is not active. Please install and activate FluentForm to use this feature.</div>';
        }

        // Get all forms
        $forms = wpFluent()->table('fluentform_forms')
            ->select(['id', 'title'])
            ->orderBy('id', 'DESC')
            ->get();

        if (empty($forms)) {
            return '<div class="logic-assistant-error">No FluentForms found. Please create a form first.</div>';
        }

        ob_start();
        ?>
        <div class="logic-assistant-container">
            <select id="form-selector" onchange="showFormFields(this.value)">
                <option value="">Select a form</option>
                <?php foreach ($forms as $form): ?>
                    <option value="<?php echo esc_attr($form->id); ?>">
                        <?php echo esc_html($form->title); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <div id="form-fields"></div>
        </div>

        <script>
        function showFormFields(formId) {
            if (!formId) {
                document.getElementById('form-fields').innerHTML = '';
                return;
            }

            // Get form fields using AJAX
            fetch(`<?php echo esc_url(admin_url('admin-ajax.php')); ?>?action=get_form_fields&form_id=${formId}`)
                .then(response => response.json())
                .then(data => {
                    let html = '<div class="fields-list">';
                    html += '<h3>Form Fields:</h3>';
                    html += '<ul>';
                    
                    for (const [key, field] of Object.entries(data)) {
                        html += `<li><code>${key}</code> - ${field.label}</li>`;
                    }
                    
                    html += '</ul>';
                    html += '</div>';
                    
                    document.getElementById('form-fields').innerHTML = html;
                })
                .catch(error => {
                    console.error('Error:', error);
                    document.getElementById('form-fields').innerHTML = 
                        '<div class="logic-assistant-error">Error loading form fields</div>';
                });
        }
        </script>
        <?php
        return ob_get_clean();
    }

    /**
     * AJAX handler to get form fields
     */
    public static function get_form_fields() {
        // Verify request
        if (!isset($_GET['form_id']) || !is_numeric($_GET['form_id'])) {
            wp_send_json_error('Invalid form ID');
        }

        $form_id = intval($_GET['form_id']);
        
        // Get form fields using FluentForm API
        $form = wpFluent()->table('fluentform_forms')->find($form_id);
        if (!$form) {
            wp_send_json_error('Form not found');
        }

        $fields = json_decode($form->form_fields, true);
        $field_data = self::extract_field_data($fields['fields']);
        
        wp_send_json($field_data);
    }

    /**
     * Extract field data from form fields array
     *
     * @param array $fields Form fields array
     * @return array Processed field data
     */
    private static function extract_field_data($fields) {
        $field_data = [];
        
        foreach ($fields as $field) {
            if (!isset($field['attributes']['name']) || !isset($field['settings']['label'])) {
                continue;
            }

            $field_data[$field['attributes']['name']] = [
                'label' => $field['settings']['label'],
                'type' => $field['element']
            ];

            // Handle repeater fields
            if (isset($field['columns']) && is_array($field['columns'])) {
                foreach ($field['columns'] as $column) {
                    if (isset($column['fields']) && is_array($column['fields'])) {
                        $field_data = array_merge(
                            $field_data, 
                            self::extract_field_data($column['fields'])
                        );
                    }
                }
            }
        }

        return $field_data;
    }
}

// Initialize the class
add_action('init', ['Elementor_Logic_Assistant', 'init']);
add_action('wp_ajax_get_form_fields', ['Elementor_Logic_Assistant', 'get_form_fields']);
add_action('wp_ajax_nopriv_get_form_fields', ['Elementor_Logic_Assistant', 'get_form_fields']); 