'use strict'

function textArrayDelItem(elem) {
    // 找到指定的上级元素
    elem.closest('.textArrayItem').remove()
}


function addTextInput(elem) {
    const dataset = elem.dataset
    const placeholder = dataset.placeholder
    const name = dataset.name
    const label = dataset.label
    const required = dataset.required || dataset.required==='true' || dataset.required==='1' ? 'required' : ''
    const InputRowBox = elem.closest('.inputs')

    InputRowBox.insertAdjacentHTML('beforeend', `
<div class="d-flex mb-2 textArrayItem">
    <input type="text" class="form-control rounded-0 me-2"
           name="${name}[]"
           ${required}
           placeholder="${placeholder}" aria-label="${label}"
    >
     <div class="invalid-feedback">${placeholder}</div>
    <button type="button" class="btn btn-danger rounded-0" onclick="textArrayDelItem(this)">
        <i class="bi bi-dash"></i>
    </button>
</div>
    `);

}
