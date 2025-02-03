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
        const condition = fieldSelect.closest('.condition');
        const operatorSelect = condition.querySelector('.operator-selector');
        const valueInput = condition.querySelector('.value-input');
        
        operatorSelect.innerHTML = '<option value="">Select an operator</option>';
        
        if (field) {
            if (field.type === 'select' || field.type === 'input_radio') {
                operatorSelect.innerHTML += `
                    <option value="equals">equals</option>
                    <option value="not_equals">does not equal</option>
                    <option value="is_empty">is empty</option>
                    <option value="not_empty">is not empty</option>
                `;
                
                // Convert text input to select if we have options
                if (field.options && field.options.length > 0) {
                    const select = document.createElement('select');
                    select.className = 'value-input';
                    select.innerHTML = '<option value="">Select a value</option>';
                    
                    field.options.forEach(option => {
                        select.innerHTML += `<option value="${option.value}">${option.label}</option>`;
                    });
                    
                    select.onchange = () => this.updatePreview();
                    valueInput.replaceWith(select);
                }
            } else if (field.type === 'checkbox') {
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
                
                // Convert back to text input if it was changed to select
                if (valueInput.tagName === 'SELECT') {
                    const input = document.createElement('input');
                    input.type = 'text';
                    input.className = 'value-input';
                    input.placeholder = 'Enter value';
                    input.onkeyup = () => this.updatePreview();
                    valueInput.replaceWith(input);
                }
            }
        }
        
        this.updatePreview();
    },

    addGroup: function() {
        const container = document.getElementById('conditions-container');
        const groups = container.querySelectorAll('.condition-group');
        const newGroup = groups[0].cloneNode(true);
        const newIndex = groups.length;
        
        newGroup.dataset.group = newIndex;
        newGroup.querySelectorAll('.condition').forEach((condition, idx) => {
            if (idx > 0) condition.remove();
        });
        
        // Reset first condition
        const firstCondition = newGroup.querySelector('.condition');
        firstCondition.dataset.index = '0';
        firstCondition.querySelector('.field-selector').value = '';
        firstCondition.querySelector('.operator-selector').innerHTML = '<option value="">Select an operator</option>';
        firstCondition.querySelector('.value-input').value = '';
        
        // Show remove group button for all except first group
        newGroup.querySelector('.remove-group').style.display = 'block';
        
        container.insertBefore(newGroup, container.querySelector('.global-controls'));
        this.updateFieldSelectors();
        this.updatePreview();
    },

    removeGroup: function(button) {
        const group = button.closest('.condition-group');
        if (document.querySelectorAll('.condition-group').length > 1) {
            group.remove();
            this.updatePreview();
        }
    },

    addCondition: function(button) {
        const group = button.closest('.condition-group');
        const container = group.querySelector('.condition');
        const newCondition = container.cloneNode(true);
        const conditions = group.querySelectorAll('.condition');
        
        newCondition.dataset.index = conditions.length;
        newCondition.querySelector('.field-selector').value = '';
        newCondition.querySelector('.operator-selector').innerHTML = '<option value="">Select an operator</option>';
        
        // Reset value input to text type
        const valueInput = document.createElement('input');
        valueInput.type = 'text';
        valueInput.className = 'value-input';
        valueInput.placeholder = 'Enter value';
        valueInput.onkeyup = () => this.updatePreview();
        newCondition.querySelector('.value-input').replaceWith(valueInput);
        
        newCondition.querySelector('.remove-condition').style.display = 'block';
        
        group.insertBefore(newCondition, group.querySelector('.group-controls'));
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
        const groups = [];
        const globalOperator = document.getElementById('global-operator').value;
        
        document.querySelectorAll('.condition-group').forEach(group => {
            const conditions = [];
            const groupOperator = group.querySelector('.group-operator').value;
            
            group.querySelectorAll('.condition').forEach(condition => {
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
            
            if (conditions.length > 0) {
                if (conditions.length === 1) {
                    groups.push(conditions[0]);
                } else {
                    groups.push(`(${conditions.join(` ${groupOperator} `)})`);
                }
            }
        });

        let code = '';
        if (groups.length > 0) {
            // Always wrap in parentheses when there are multiple groups
            const groupsCode = groups.length > 1 ? 
                `(${groups.join(` ${globalOperator} `)})` : 
                groups[0];
            code = `if (${groupsCode}) {\n    show();\n} else {\n    hide();\n}`;
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