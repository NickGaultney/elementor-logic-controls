window.logicAssistant = {
    copyToClipboard: function(text) {
        const codeElement = event.target;
        const originalText = codeElement.textContent;
        
        navigator.clipboard.writeText("s('" + text + "')").then(() => {
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
    },

    showFormFields: function(formId) {
        if (!formId) {
            document.getElementById('form-fields').innerHTML = '';
            return;
        }

        fetch(window.logicAssistantData.ajaxurl + '?action=get_form_fields&form_id=' + formId)
            .then(response => response.json())
            .then(data => {
                let html = '<div class="fields-list">';
                html += '<h3>Form Fields:</h3>';
                html += '<ul>';
                
                for (const [key, field] of Object.entries(data)) {
                    html += `<li><code class="field-code" onclick="logicAssistant.copyToClipboard('${key}')" title="Click to copy">${key}</code> - ${field.label}</li>`;
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
}; 