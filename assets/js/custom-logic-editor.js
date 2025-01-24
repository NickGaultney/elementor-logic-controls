jQuery(document).ready(function ($) {
    const initCodeMirror = () => {
        $('.elementor-control-custom_logic textarea').each(function () {
            const textarea = $(this);
            if (!textarea.data('codemirror-initialized')) {
                const editor = wp.codeEditor.initialize(textarea[0], {
                    codemirror: {
                        mode: 'javascript', // Set the appropriate mode for your "Logic"
                        lineNumbers: true,
                        theme: 'default', // Use Elementor's theme or specify another
                    },
                });
                textarea.data('codemirror-initialized', true);
            }
        });
    };

    // Trigger initialization on load and when controls are rendered
    $(window).on('elementor:init elementor:preview:loaded', initCodeMirror);
    elementor.hooks.addAction('panel/open_editor', initCodeMirror);
});