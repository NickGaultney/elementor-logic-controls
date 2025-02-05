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
    private static $show = false;  // Add class property to track visibility
    private static $functions_declared = false;  // Track if we've declared the functions

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

        // Filter the final content to remove hidden elements
        add_filter('elementor/frontend/the_content', [__CLASS__, 'remove_hidden_elements']);

        // Initialize CodeMirror for PHP editing
        //add_action('elementor/editor/after_enqueue_scripts', [ __CLASS__, 'initialize_codemirror' ] );
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
                'description' => esc_html__('Use s(\'field_name\') to access form fields. Call show(); or hide(); based on your conditions.', 'elementor-logic-controls'),
                'default'     => "if () {\n    show();\n} else {\n    hide();\n}",
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
        // Skip in editor mode
        if (isset($_GET['action']) && 'elementor' === $_GET['action']) {
            return;
        }

        // Only run on "Results" post type
        if (get_post_type() !== 'results') {
            return;
        }

        $settings = $element->get_settings_for_display();

        if (isset($settings['enable_logic']) && 'yes' === $settings['enable_logic'] && !empty($settings['php_snippet'])) {
            $submisison = self::get_submission_data();
            self::$show = false;  // Reset for each element
            
            try {
                // Only declare functions once
                if (!self::$functions_declared) {
                    function show() { \Elementor_Logic_Controls::logic_show(); }
                    function hide() { \Elementor_Logic_Controls::logic_hide(); }
                    function contains($field, ...$values) { 
                        return \Elementor_Logic_Controls::logic_contains($field, ...$values); 
                    }
                    function not_contains($field, ...$values) { 
                        return \Elementor_Logic_Controls::logic_not_contains($field, ...$values); 
                    }
                    function is_empty($field) { 
                        return \Elementor_Logic_Controls::logic_is_empty($field); 
                    }
                    function not_empty($field) { 
                        return \Elementor_Logic_Controls::logic_not_empty($field); 
                    }
                    function s($key) {
                        return \Elementor_Logic_Controls::logic_get_value($key);
                    }
                        
                    self::$functions_declared = true;
                }

                // Add any variables you need to use in the snippet
                $today = (new DateTime())->format("m/d/Y");
                
                // Execute just the user's snippet
                eval($settings['php_snippet']);
                
            } catch (ParseError $e) {
                error_log('Logic Parse Error: ' . $e->getMessage());
                self::$show = false;
            }

            // Add class if element should be hidden
            if (!self::$show) {
                $element->add_render_attribute('_wrapper', 'class', 'pbn_interview_hidden');
            }
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

    /**
     * Remove elements with pbn_interview_hidden class from content
     * 
     * @param string $content The rendered content
     * @return string Modified content
     */
    public static function remove_hidden_elements($content) {
        // Only process on "Results" post type
        if (get_post_type() !== 'results') {
            return $content;
        }

        if (empty($content)) {
            return $content;
        }

        // Load content into DOMDocument
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML(mb_convert_encoding($content, 'HTML-ENTITIES', 'UTF-8'), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        // Find all elements with our class
        $xpath = new \DOMXPath($dom);
        $elements = $xpath->query("//*[contains(@class, 'pbn_interview_hidden')]");

        // Remove each hidden element
        foreach ($elements as $element) {
            $element->parentNode->removeChild($element);
        }

        // Convert back to HTML string
        $content = $dom->saveHTML();

        return $content;
    }

    // Helper functions moved outside of collect_logic_snippets
    public static function logic_show() {
        self::$show = true;
    }
    
    public static function logic_hide() {
        self::$show = false;
    }
    
    public static function logic_contains($field_array, ...$values) {
        return isset($field_array) && 
               is_array($field_array) && 
               !empty(array_intersect($field_array, $values));
    }
    
    public static function logic_not_contains($field_array, ...$values) {
        return isset($field_array) && 
               is_array($field_array) && 
               empty(array_intersect($field_array, $values));
    }
    
    public static function logic_is_empty($field_array) {
        return !isset($field_array) || 
               !is_array($field_array) || 
               empty($field_array);
    }
    
    public static function logic_not_empty($field_array) {
        return isset($field_array) && 
               is_array($field_array) && 
               !empty($field_array);
    }

    public static function logic_get_value($key) {
        return isset(self::$submission_data[$key]) ? self::$submission_data[$key] : null;
    }
}
