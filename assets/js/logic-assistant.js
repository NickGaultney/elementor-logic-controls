window.logicAssistant = {
    currentForm: null,
    currentFields: null,

    showTools: function(formId) {
        this.currentForm = formId;
        if (!formId) {
            document.getElementById('tool-selector').style.display = 'none';
            document.getElementById('field-list').innerHTML = '';
            document.getElementById('logic-builder').style.display = 'none';
            return;
        }

        document.getElementById('tool-selector').style.display = 'block';
        this.selectTool('field-list');
        this.loadFormFields(formId);
    },

    selectTool: function(tool) {
        // Update button states
        document.querySelectorAll('.tool-button').forEach(btn => {
            btn.classList.remove('active');
            if (btn.textContent.toLowerCase().includes(tool)) {
                btn.classList.add('active');
            }
        });

        // Show/hide appropriate panels
        document.getElementById('field-list').style.display = tool === 'field-list' ? 'block' : 'none';
        document.getElementById('logic-builder').style.display = tool === 'logic-builder' ? 'block' : 'none';
    },

    loadFormFields: function(formId) {
        fetch(window.logicAssistantData.ajaxurl + '?action=get_form_fields&form_id=' + formId)
            .then(response => response.json())
            .then(data => {
                this.currentFields = data;
                this.updateFieldList(data);
                this.updateFieldSelector(data);
            })
            .catch(error => {
                console.error('Error:', error);
                document.getElementById('field-list').innerHTML = 
                    '<div class="logic-assistant-error">Error loading form fields</div>';
            });
    },

    updateFieldList: function(data) {
        let html = '<div class="fields-list">';
        html += '<h3>Form Fields:</h3>';
        html += '<ul>';
        
        for (const [key, field] of Object.entries(data)) {
            html += `<li><code class="field-code" onclick="logicAssistant.copyToClipboard('${key}')" title="Click to copy">${key}</code> - ${field.label}</li>`;
        }
        
        html += '</ul>';
        html += '</div>';
        
        document.getElementById('field-list').innerHTML = html;
    },

    updateFieldSelector: function(data) {
        const selector = document.getElementById('field-selector');
        selector.innerHTML = '<option value="">Select a field</option>';
        
        for (const [key, field] of Object.entries(data)) {
            selector.innerHTML += `<option value="${key}">${field.label}</option>`;
        }
    },

    updateOperators: function(fieldSelect) {
        const field = this.currentFields[fieldSelect.value];
        const operatorSelect = document.getElementById('operator-selector');
        operatorSelect.innerHTML = '<option value="">Select an operator</option>';
        
        if (field) {
            if (field.type === 'select' || field.type === 'radio' || field.type === 'checkbox') {
                operatorSelect.innerHTML += `
                    <option value="contains">contains</option>
                    <option value="not_contains">does not contain</option>
                    <option value="is_empty">is empty</option>
                    <option value="not_empty">is not empty</option>
                `;
            } else {
                operatorSelect.innerHTML += `
                    <option value="equals">equals</option>
                    <option value="not_equals">does not equal</option>
                    <option value="is_empty">is empty</option>
                    <option value="not_empty">is not empty</option>
                `;
            }
        }
        
        this.updatePreview();
    },

    updatePreview: function() {
        const field = document.getElementById('field-selector').value;
        const operator = document.getElementById('operator-selector').value;
        const value = document.getElementById('value-input').value;
        
        let code = '';
        if (field && operator) {
            switch(operator) {
                case 'contains':
                    code = `if (contains('${field}', "${value}")) {\n    show();\n} else {\n    hide();\n}`;
                    break;
                case 'not_contains':
                    code = `if (not_contains('${field}', "${value}")) {\n    show();\n} else {\n    hide();\n}`;
                    break;
                case 'equals':
                    code = `if (s('${field}') === "${value}") {\n    show();\n} else {\n    hide();\n}`;
                    break;
                case 'not_equals':
                    code = `if (s('${field}') !== "${value}") {\n    show();\n} else {\n    hide();\n}`;
                    break;
                case 'is_empty':
                    code = `if (is_empty('${field}')) {\n    show();\n} else {\n    hide();\n}`;
                    break;
                case 'not_empty':
                    code = `if (not_empty('${field}')) {\n    show();\n} else {\n    hide();\n}`;
                    break;
            }
        }
        
        document.querySelector('#code-preview code').textContent = code;
    },

    copyToClipboard: function(text) {
        const codeElement = event.target;
        const originalText = codeElement.textContent;
        
        navigator.clipboard.writeText(".s('" + text + "')").then(() => {
            codeElement.textContent = 'Copied!';
            codeElement.style.backgroundColor = '#4CAF50';
            codeElement.style.color = 'white';
            
            setTimeout(() => {
                codeElement.textContent = originalText;
                codeElement.style.backgroundColor = '';
                codeElement.style.color = '';
            }, 1000);
        });
    }
}; 