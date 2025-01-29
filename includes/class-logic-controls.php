<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

class Elementor_Logic_Controls {

    /**
     * Initialize the plugin.
     */
    public static function init() {
        // Add logic controls to Elementor elements.
        add_action( 'elementor/element/after_section_end', [ __CLASS__, 'add_logic_controls' ], 10, 3 );

        // Process and output logic snippets.
        add_action( 'elementor/frontend/before_render', [ __CLASS__, 'collect_logic_snippets' ] );
        add_action( 'wp_footer', [ __CLASS__, 'render_logic_snippets' ] );

        // Enqueue custom script to initialize CodeMirror
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
            'js_snippet',
            [
                'label'       => esc_html__( 'JS Snippet', 'elementor-logic-controls' ),
                'type'        => \Elementor\Controls_Manager::TEXTAREA,
                'rows'        => 8,
                'description' => esc_html__( 'Insert JavaScript code to run for this widget.', 'elementor-logic-controls' ),
                'default'     => "if () {\n  show()\n} else {\n  hide()\n}",
                'condition'   => [
                    'enable_logic' => 'yes',
                ],
                'render_type' => 'none',
            ]
        );

        $element->end_controls_section();
    }

    /**
     * Collect logic snippets from Elementor widgets.
     *
     * @param \Elementor\Element_Base $element The element instance.
     */
    public static function collect_logic_snippets( $element ) {
        if ( isset( $_GET['action'] ) && 'elementor' === $_GET['action'] ) {
            return; // Skip in editor mode.
        }

        $settings = $element->get_settings_for_display();

        if ( isset( $settings['enable_logic'] ) && 'yes' === $settings['enable_logic'] && ! empty( $settings['js_snippet'] ) ) {
            if ( ! isset( $GLOBALS['elc_js_snippets'] ) || ! is_array( $GLOBALS['elc_js_snippets'] ) ) {
                $GLOBALS['elc_js_snippets'] = [];
            }

            $GLOBALS['elc_js_snippets'][] = [
                'id'      => $element->get_id(),
                'snippet' => $settings['js_snippet'],
            ];
        }
    }

    /**
     * Render collected logic snippets in the footer.
     */
    public static function render_logic_snippets() {
        if (empty($GLOBALS['elc_js_snippets'])) {
            return;
        }

        echo "<script>\n";
        
        // Create the initialization function
        echo "function initializeLogicSnippets(submission) {\n";
        
        // Provide utility functions for hide/show
        echo "    function show(id) { \n";
        echo "        var element = document.querySelector('[data-id=\"' + id + '\"]'); \n";
        echo "        if (element) { \n";
        echo "            element.style.display = ''; \n";
        echo "        } \n";
        echo "    } \n\n";

        echo "    function hide(id) { \n";
        echo "        var element = document.querySelector('[data-id=\"' + id + '\"]'); \n";
        echo "        if (element) { \n";
        echo "            element.style.display = 'none'; \n";
        echo "        } \n";
        echo "    } \n\n";

        // Execute logic snippets for each widget
        foreach ($GLOBALS['elc_js_snippets'] as $snippet_data) {
            $widget_id = $snippet_data['id'];
            $snippet = $snippet_data['snippet'];

            echo "    try {\n";
            echo "        (function(s, hide, show) {\n";
            echo "            " . $snippet . "\n";
            echo "        })(submission, function() { hide(\"$widget_id\"); }, function() { show(\"$widget_id\"); });\n";
            echo "    } catch (e) { console.error('Error in widget logic for ID: ${widget_id}', e); }\n";
        }

        echo "}\n\n";

        // Add event listener for custom event
        echo "document.addEventListener('logicDataReady', function(e) {\n";
        echo "    initializeLogicSnippets(e.detail.submission);\n";
        echo "});\n";

        echo "</script>\n";
    }

    public static function initialize_codemirror() {
        wp_enqueue_script(
            'custom-logic-editor',
            plugin_dir_url(__FILE__) . 'assets/js/custom-logic-editor.js',
            ['jquery', 'elementor-editor'],
            '1.0',
            true
        );
    }
}
