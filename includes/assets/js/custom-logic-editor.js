jQuery(document).ready(function ($) {
    const initCodeMirror = () => {
        $('.elementor-control-php_snippet textarea').each(function () {
            const textarea = $(this);
            if (!textarea.data('codemirror-initialized')) {
                const editor = wp.codeEditor.initialize(textarea[0], {
                    codemirror: {
                        mode: 'php',
                        lineNumbers: true,
                        matchBrackets: true,
                        autoCloseBrackets: true,
                        theme: 'default',
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