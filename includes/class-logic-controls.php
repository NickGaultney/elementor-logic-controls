<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Elementor_Logic_Controls {

    /**
     * Cached submission data
     * @var array|null
     */
    private static $submission_data = null;

    /**
     * Get submission data, fetching only once per page load
     * 
     * @return array|null
     */
    private static function get_submission_data() {
        // Return cached data if already fetched
        if (self::$submission_data !== null) {
            return self::$submission_data;
        }

        // Initialize with empty array
        self::$submission_data = [];

        // Get the results_id from URL
        $results_id = sanitize_text_field($_GET['results_id'] ?? '');

        if ($results_id) {
            try {
                // Decode the results ID and get the entry
                [$decodedEntryId, $decodedFormId] = self::decode_form_id($results_id, "d001dda9-0e66-4b6c-ae3e-609d70780b55");
                
                if ($decodedEntryId && $decodedFormId) {
                    $formApi = fluentFormApi('forms')->entryInstance($decodedFormId);
                    $entry = $formApi->entry($decodedEntryId, false);

                    if ($entry && isset($entry['submission'])) {
                        // Get response from submission object
                        $submission = $entry['submission'];

                        if (isset($submission['attributes']['response']) && is_array($submission['attributes']['response'])) {
                            self::$submission_data = $submission['attributes']['response'];
                        }
                    }
                }
            } catch (Exception $e) {
                error_log('Submission Data Fetch Error: ' . $e->getMessage());
            }
        }

        return self::$submission_data;
    }

    /**
     * Initialize the plugin.
     */
    public static function init() {
        // Add logic controls to Elementor elements.
        add_action( 'elementor/element/after_section_end', [ __CLASS__, 'add_logic_controls' ], 10, 3 );

        // Process logic before rendering
        add_action( 'elementor/frontend/before_render', [ __CLASS__, 'collect_logic_snippets' ] );

        // Initialize CodeMirror for PHP editing
        add_action('elementor/editor/after_enqueue_scripts', [ __CLASS__, 'initialize_codemirror' ] );
    }

    /**
     * Add logic controls to Elementor elements.
     *
     * @param \Elementor\Element_Base $element
     * @param string $section_id
     * @param array $args
     */
    public static function add_logic_controls( $element, $section_id, $args ) {
        if ( 'section_custom_css' !== $section_id ) {
            return;
        }

        $element->start_controls_section(
            'logic_section',
            [
                'tab'   => \Elementor\Controls_Manager::TAB_ADVANCED,
                'label' => esc_html__( 'Logic', 'elementor-logic-controls' ),
            ]
        );

        $element->add_control(
            'enable_logic',
            [
                'label'        => esc_html__( 'Enable Logic', 'elementor-logic-controls' ),
                'type'         => \Elementor\Controls_Manager::SWITCHER,
                'label_on'     => esc_html__( 'Yes', 'elementor-logic-controls' ),
                'label_off'    => esc_html__( 'No', 'elementor-logic-controls' ),
                'return_value' => 'yes',
                'default'      => '',
                'render_type'  => 'none',
            ]
        );

        $element->add_control(
            'php_snippet',
            [
                'label'       => esc_html__('PHP Logic', 'elementor-logic-controls'),
                'type'        => \Elementor\Controls_Manager::TEXTAREA,
                'rows'        => 8,
                'description' => esc_html__('Use $s[\'field_name\'] to access form fields. Call show() or hide() based on your conditions.', 'elementor-logic-controls'),
                'default'     => "if (\$s['field_name'] === 'value') {\n    show();\n} else {\n    hide();\n}",
                'condition'   => [
                    'enable_logic' => 'yes',
                ],
                'render_type' => 'none',
            ]
        );

        $element->add_control(
            'js_snippet',
            [
                'label'       => esc_html__('JS Logic', 'elementor-logic-controls'),
                'type'        => \Elementor\Controls_Manager::TEXTAREA,
                'rows'        => 8,
                'description' => esc_html__('Use $s[\'field_name\'] to access form fields. Call show() or hide() based on your conditions.', 'elementor-logic-controls'),
                'default'     => "",
                'condition'   => [
                    'enable_logic' => 'yes',
                ],
                'render_type' => 'none',
            ]
        );

        $element->end_controls_section();
    }

    /**
     * Process logic snippets before rendering
     */
    public static function collect_logic_snippets($element) {
        if (isset($_GET['action']) && 'elementor' === $_GET['action']) {
            return; // Skip in editor mode
        }

        $settings = $element->get_settings_for_display();

        if (isset($settings['enable_logic']) && 'yes' === $settings['enable_logic'] && !empty($settings['php_snippet'])) {
            $s = self::get_submission_data(); // Use $s as shorthand for submission
            
            try {
                // Create the helper functions in the snippet's scope
                $snippet = '
                    function show() { 
                        $GLOBALS["show"] = true; 
                    }
                    
                    function hide() { 
                        $GLOBALS["show"] = false; 
                    }
                    
                    function contains($field, ...$values) {
                        global $s;
                        do_action("qm/debug", $s);
                        return isset($s[$field]) && is_array($s[$field]) && !empty(array_intersect($s[$field], $values));
                    }
                    
                    function not_contains($field, ...$values) {
                        global $s;
                        return isset($s[$field]) && is_array($s[$field]) && empty(array_intersect($s[$field], $values));
                    }
                    
                    function is_empty($field) {
                        global $s;
                        return !isset($s[$field]) || empty($s[$field]);
                    }
                    
                    function not_empty($field) {
                        global $s;
                        return isset($s[$field]) && !empty($s[$field]);
                    }

                    ' . $settings['php_snippet'];
                
                // Execute the snippet
                eval($snippet);
                
            } catch (ParseError $e) {
                error_log('Logic Parse Error: ' . $e->getMessage());
                $GLOBALS["show"] = true; // Show element if there's an error
            }

            // Prevent rendering if hide() was called
            if (!$GLOBALS["show"]) {
                // This will prevent the element from being rendered at all
                $element->set_render_attribute('_wrapper', 'class', ['elementor-hidden', 'elementor-hidden-rendered']);
                add_filter('elementor/element/get_child_type', function($child_type, $data) use ($element) {
                    if ($data['id'] === $element->get_id()) {
                        return false;
                    }
                    return $child_type;
                }, 10, 2);
                $element->set_settings('enabled', false);
                return false;
            }

            // Clean up global variable
            unset($GLOBALS["show"]);
        }
    }
    
    /**
     * Validates and decodes a results_id back into the original form ID.
     *
     * @param string $resultsId The encoded results_id.
     * @param string $secretKey The same secret key used for encoding.
     * @return int|null The original form ID, or null if validation fails.
     */
    public static function decode_form_id(string $resultsId, string $secretKey): ?array
    {
        // Reverse URL-safe Base64 encoding
        $encoded = strtr($resultsId, '-_', '+/');
    
        // Decode the Base64 data
        $decoded = base64_decode($encoded, true);
    
        // If decoding fails, return null
        if ($decoded === false) {
            return null;
        }
    
        // Split the decoded data to retrieve the form ID and the hash
        $parts = explode(':', $decoded);
    
        // Validate the format
        if (count($parts) !== 3) {
            return null;
        }
    
        [$entryId, $formId, $hash] = $parts;
    
        // Recompute the hash for the form ID using the secret key
        $expectedHash = hash_hmac('sha256', $formId, $secretKey);
    
        // Verify the hash matches
        if (!hash_equals($expectedHash, $hash)) {
            return null;
        }
    
        // Return the form ID as an integer
        return [$entryId, $formId];
    }

    /**
     * Initialize CodeMirror for PHP editing
     */
    public static function initialize_codemirror() {
        wp_enqueue_code_editor(['type' => 'text/x-php']);
        wp_enqueue_script(
            'custom-logic-editor',
            plugin_dir_url(__FILE__) . 'assets/js/custom-logic-editor.js',
            ['jquery', 'elementor-editor'],
            ELC_VERSION,
            true
        );
    }
}
