'use strict'

function delSelectArrayItem(elem) {
    // 找到指定的上级元素
    elem.closest('.select-list-row').remove()
}


function addSelectArrayItem(elem) {
    const dataset = elem.dataset
    const placeholder = dataset.placeholder
    const label = dataset.label
    const field = dataset.field
    const url = dataset.url
    const required = dataset.required || dataset.required === 'true' || dataset.required === '1' ? 'required' : ''
    const InputRowBox = elem.closest('.select-lists')

    InputRowBox.insertAdjacentHTML('beforeend', `
<div class="select-list-row d-flex align-items-start mb-2">
    <div class="select-box flex-fill pe-2">
        <input type="text"
               class="form-control rounded-0 dropdown-toggle select-input"
                onkeyup="handleInputOnKeyup(this)"
                onclick="handleInputOnClick(this)"
               autocomplete="off"
               data-bs-toggle="dropdown"
               data-field="${field}"
               data-url="${url}"
                ${required}
               placeholder="${placeholder}"
               aria-label="${label}"
               >
    
         <i class="icon icon-down bi bi-chevron-down" data-bs-toggle="dropdown"
               onclick="handleOnClick(this)"></i>
        <i class="icon icon-close bi bi-x" onclick="clearSelectValue(this)"></i>
    
        <input type="text" class="form-control visually-hidden"  ${required}
               name="${field}[]" 
               aria-label="${label}">
    
        <div class="invalid-feedback">
           ${placeholder}
        </div>
    
        <ul class="dropdown-menu">
        </ul>
    </div>
    <button type="button" class="btn btn-danger rounded-0" onclick="delSelectArrayItem(this)">
        <i class="bi bi-dash"></i>
    </button>
</div>
    `);

}
