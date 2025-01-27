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
        function copyToClipboard(text) {
            const codeElement = event.target;
            const originalText = codeElement.textContent;
            
            navigator.clipboard.writeText('f.' + text).then(() => {
                // Change appearance to show feedback
                codeElement.textContent = 'Copied!';
                codeElement.style.backgroundColor = '#4CAF50';
                codeElement.style.color = 'white';
                
                // Revert back after 1 second
                setTimeout(() => {
                    codeElement.textContent = originalText;
                    codeElement.style.backgroundColor = '';
                    codeElement.style.color = '';
                }, 1000);
            });
        }

        function showFormFields(formId) {
            if (!formId) {
                document.getElementById('form-fields').innerHTML = '';
                return;
            }

            fetch(`<?php echo esc_url(admin_url('admin-ajax.php')); ?>?action=get_form_fields&form_id=${formId}`)
                .then(response => response.json())
                .then(data => {
                    let html = '<div class="fields-list">';
                    html += '<h3>Form Fields:</h3>';
                    html += '<ul>';
                    
                    for (const [key, field] of Object.entries(data)) {
                        html += `<li><code class="field-code" onclick="copyToClipboard('${key}')" title="Click to copy">${key}</code> - ${field.label}</li>`;
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

        <style>
        .field-code {
            cursor: pointer;
            padding: 2px 4px;
            background: #f5f5f5;
            border-radius: 3px;
            transition: all 0.2s ease;
        }
        .field-code:hover {
            background: #e0e0e0;
        }
        </style>
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
        
        try {
            $formApi = fluentFormApi('forms')->form($form_id);
            
            if (!$formApi) {
                wp_send_json_error('Form not found');
            }

            $fields = $formApi->fields()["fields"];
            $field_data = self::process_fields($fields);

            wp_send_json($field_data);

        } catch (Exception $e) {
            error_log('FluentForm API Error: ' . $e->getMessage());
            wp_send_json_error('Error fetching form fields');
        }
    }

    /**
     * Process fields recursively to handle containers and nested fields
     * 
     * @param array $fields Array of form fields
     * @return array Processed field data
     */
    private static function process_fields($fields) {
        $field_data = [];
        
        // Fields to ignore
        $ignore_elements = ['custom_html', 'form_step', 'additional_info_field', 'additional_info_scripts', 'section_break'];

        foreach ($fields as $field) {
            // Skip ignored elements
            if (in_array($field['element'], $ignore_elements)) {
                continue;
            }

            if ($field['element'] === 'container') {
                // Process container columns
                if (isset($field['columns']) && is_array($field['columns'])) {
                    foreach ($field['columns'] as $column) {
                        if (isset($column['fields']) && is_array($column['fields'])) {
                            // Recursively process fields in each column
                            $field_data = array_merge($field_data, self::process_fields($column['fields']));
                        }
                    }
                }
            } else {
                // For hidden inputs, only check for name
                if ($field['element'] === 'input_hidden') {
                    if (!isset($field["attributes"]['name'])) {
                        continue;
                    }
                    $field_data[$field["attributes"]['name']] = [
                        'label' => '',
                        'type' => $field["element"]
                    ];
                } else {
                    // Process regular field
                    if (!isset($field["attributes"]['name']) || !isset($field["settings"]['label'])) {
                        continue;
                    }
                    $field_data[$field["attributes"]['name']] = [
                        'label' => $field["settings"]['label'],
                        'type' => $field["element"]
                    ];
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