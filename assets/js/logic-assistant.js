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
                this.updateFieldSelectors();
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
            const label = field.label || key;
            html += `<li><code class="field-code" onclick="logicAssistant.copyToClipboard('${key}')" title="Click to copy">${key}</code> - ${label}</li>`;
        }
        
        html += '</ul>';
        html += '</div>';
        
        document.getElementById('field-list').innerHTML = html;
    },

    updateOperators: function(fieldSelect) {
        const field = this.currentFields[fieldSelect.value];
        const operatorSelect = fieldSelect.closest('.condition').querySelector('.operator-selector');
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

    addCondition: function() {
        const container = document.querySelector('.condition-group');
        const newIndex = container.children.length;
        const template = document.querySelector('.condition').cloneNode(true);
        
        template.dataset.index = newIndex;
        template.querySelector('.field-selector').value = '';
        template.querySelector('.operator-selector').innerHTML = '<option value="">Select an operator</option>';
        template.querySelector('.value-input').value = '';
        template.querySelector('.remove-condition').style.display = 'block';
        
        container.appendChild(template);
        this.updateFieldSelectors();
        this.updatePreview();
    },

    removeCondition: function(button) {
        const condition = button.closest('.condition');
        if (document.querySelectorAll('.condition').length > 1) {
            condition.remove();
            this.updatePreview();
        }
    },

    updatePreview: function() {
        const conditions = [];
        const logicOperator = document.getElementById('logic-operator').value;
        
        document.querySelectorAll('.condition').forEach(condition => {
            const field = condition.querySelector('.field-selector').value;
            const operator = condition.querySelector('.operator-selector').value;
            const value = condition.querySelector('.value-input').value;
            
            if (field && operator) {
                let conditionCode = '';
                switch(operator) {
                    case 'contains':
                        conditionCode = `contains('${field}', "${value}")`;
                        break;
                    case 'not_contains':
                        conditionCode = `not_contains('${field}', "${value}")`;
                        break;
                    case 'equals':
                        conditionCode = `s('${field}') === "${value}"`;
                        break;
                    case 'not_equals':
                        conditionCode = `s('${field}') !== "${value}"`;
                        break;
                    case 'is_empty':
                        conditionCode = `is_empty('${field}')`;
                        break;
                    case 'not_empty':
                        conditionCode = `not_empty('${field}')`;
                        break;
                }
                if (conditionCode) {
                    conditions.push(conditionCode);
                }
            }
        });

        let code = '';
        if (conditions.length > 0) {
            code = `if (${conditions.join(` ${logicOperator} `)}) {\n    show();\n} else {\n    hide();\n}`;
        }
        
        document.querySelector('#code-preview code').textContent = code;
    },

    copyGeneratedCode: function() {
        const code = document.querySelector('#code-preview code').textContent;
        navigator.clipboard.writeText(code).then(() => {
            const button = document.querySelector('.copy-code');
            const originalText = button.textContent;
            button.textContent = 'Copied!';
            button.style.backgroundColor = '#28a745';
            
            setTimeout(() => {
                button.textContent = originalText;
                button.style.backgroundColor = '';
            }, 1000);
        });
    },

    updateFieldSelectors: function() {
        document.querySelectorAll('.field-selector').forEach(selector => {
            if (selector.options.length <= 1) {
                selector.innerHTML = '<option value="">Select a field</option>';
                for (const [key, field] of Object.entries(this.currentFields)) {
                    const label = field.label || key;
                    selector.innerHTML += `<option value="${key}">${label}</option>`;
                }
            }
        });
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