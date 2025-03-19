'use strict'

// 表单提交验证
const forms = document.querySelectorAll('.needs-validation')
if (forms && forms.length > 0) {
    Array.from(forms).forEach(form => {
        form.addEventListener('submit', event => {
            if (!form.checkValidity()) {
                event.preventDefault()
                event.stopPropagation()
            }

            form.classList.add('was-validated')
        }, false)
    })
}