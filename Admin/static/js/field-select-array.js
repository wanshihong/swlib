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

    // 复用 select.twig 的完整结构创建新行
    InputRowBox.insertAdjacentHTML('beforeend', `
<div class="select-list-row d-flex align-items-start mb-2">
    <div class="select-box flex-fill pe-2">
        <!-- 输入模式的元素 -->
        <input type="text"
               class="form-control rounded-0 dropdown-toggle select-input"
               autocomplete="off"
               data-bs-toggle="dropdown"
               data-field="${field}"
               data-url="${url}"
               onfocus="handleInputOnFocus(this)"
               onkeyup="handleInputOnKeyup(this)"
               ${required}
               placeholder="${placeholder}"
               aria-label="${label}"
               value="">
               
        <!-- 输入框右侧下拉图标 -->
        <i class="bi bi-chevron-down select-dropdown-icon" data-bs-toggle="dropdown"></i>
               
        <!-- 选中值容器 - 当有值被选中时显示 -->
        <div class="selected-value-container form-control" style="display: none;">
            <span class="selected-text"></span>
            <button type="button" class="btn-clear" onclick="clearSelectValue(this)">
                <i class="bi bi-x"></i>
            </button>
        </div>

        <!-- 隐藏的实际表单值 - 数组形式 -->
        <input type="text" class="form-control visually-hidden" ${required}
               name="${field}[]" value=""
               aria-label="${label}">

        <div class="invalid-feedback">
            请选择${label}
        </div>

        <!-- 下拉菜单 -->
        <ul class="dropdown-menu">
            <!-- 下拉菜单头部 - 关闭按钮 -->
            <li class="dropdown-menu-header">
                <span class="dropdown-menu-title">选择 ${label}</span>
                <button type="button" class="btn-close-dropdown" onclick="closeDropdown(this)">
                    <i class="bi bi-x"></i>
                </button>
            </li>
        </ul>
    </div>
    <button type="button" class="btn btn-danger rounded-0" onclick="delSelectArrayItem(this)">
        <i class="bi bi-dash"></i>
    </button>
</div>
    `);
}
