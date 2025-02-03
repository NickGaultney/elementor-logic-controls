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
     * Enqueue required styles and scripts
     */
    public static function enqueue_styles() {
        wp_enqueue_style(
            'logic-assistant-styles',
            ELC_URL . 'assets/css/logic-assistant.css',
            [],
            ELC_VERSION
        );

        wp_enqueue_script(
            'logic-assistant',
            ELC_URL . 'assets/js/logic-assistant.js',
            [],
            ELC_VERSION,
            true
        );

        wp_localize_script('logic-assistant', 'logicAssistantData', [
            'ajaxurl' => admin_url('admin-ajax.php')
        ]);
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
            <select id="form-selector" onchange="logicAssistant.showTools(this.value)">
                <option value="">Select a form</option>
                <?php foreach ($forms as $form): ?>
                    <option value="<?php echo esc_attr($form->id); ?>">
                        <?php echo esc_html($form->title); ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <div id="tool-selector" style="display: none;">
                <div class="tool-buttons">
                    <button onclick="logicAssistant.selectTool('field-list')" class="tool-button active">Field List</button>
                    <button onclick="logicAssistant.selectTool('logic-builder')" class="tool-button">Logic Builder</button>
                </div>
            </div>

            <div id="field-list" class="tool-panel"></div>
            <div id="logic-builder" class="tool-panel" style="display: none;">
                <div class="logic-builder-form">
                    <div id="conditions-container">
                        <div class="condition-group">
                            <div class="condition" data-index="0">
                                <select class="field-selector" onchange="logicAssistant.updateOperators(this)">
                                    <option value="">Select a field</option>
                                </select>
                                
                                <select class="operator-selector" onchange="logicAssistant.updatePreview()">
                                    <option value="">Select an operator</option>
                                </select>
                                
                                <input type="text" class="value-input" placeholder="Enter value" onkeyup="logicAssistant.updatePreview()">
                                
                                <button type="button" class="remove-condition" onclick="logicAssistant.removeCondition(this)" style="display: none;">×</button>
                            </div>
                        </div>
                        
                        <div class="condition-controls">
                            <button type="button" onclick="logicAssistant.addCondition()" class="add-condition">Add Condition</button>
                            <select id="logic-operator" onchange="logicAssistant.updatePreview()">
                                <option value="&&">AND</option>
                                <option value="||">OR</option>
                            </select>
                        </div>
                    </div>

                    <div id="code-preview" class="code-preview">
                        <h4>Generated Code:</h4>
                        <pre><code></code></pre>
                        <button type="button" onclick="logicAssistant.copyGeneratedCode()" class="copy-code">Copy Code</button>
                    </div>
                </div>
            </div>
        </div>
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