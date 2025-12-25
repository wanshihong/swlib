'use strict'

const selectDropdownMenuItem = (elem, id, text) => {
    const parent = elem.closest('.select-box')
    // 隐藏输入框，显示选中值容器
    const inputField = parent.querySelector('.select-input')
    const selectedValueContainer = parent.querySelector('.selected-value-container')
    const dropdownIcon = parent.querySelector('.select-dropdown-icon')
    
    // 更新选中值容器的文本
    selectedValueContainer.querySelector('.selected-text').textContent = text
    
    // 隐藏输入模式和下拉图标，显示选中值模式
    inputField.style.display = 'none'
    if (dropdownIcon) dropdownIcon.style.display = 'none'
    selectedValueContainer.style.display = 'flex'
    
    // 赋值到隐藏表单
    parent.querySelector('.visually-hidden').value = id
    inputField.value = text

    // 隐藏下拉菜单
    const dropdownMenu = parent.querySelector('.dropdown-menu')
    if (dropdownMenu.classList.contains('show')) {
        dropdownMenu.classList.remove('show')
    }
}

const selectInputGetData = (url, field, value, dropdownMenuElem, showLoading = true) => {
    if (showLoading) {
        loading('show');
    }
    
    fetch(url, {
        method: 'POST',
        body: JSON.stringify({
            fieldName: field,
            keyword: value,
        })
    }).then(res => res.json()).then(data => {
        if (data.errno) {
            alert(data.msg)
            return;
        }
        
        let innerHTML = `
            <li class="dropdown-menu-header">
                <span class="dropdown-menu-title">选择列表</span>
                <button type="button" class="btn-close-dropdown" onclick="closeDropdown(this)">
                    <i class="bi bi-x"></i>
                </button>
            </li>
        `;
        
        if (data.data && data.data.length > 0) {
            // 有数据，显示下拉列表
            innerHTML += data.data.map(item => {
                return `<li onclick="selectDropdownMenuItem(this,'${item.id}','${item.text}')">
                            <a class="dropdown-item" href="javascript:">
                                ${item.text}
                            </a>
                        </li>`
            }).join('')
        } else {
            // 无数据，显示提示
            innerHTML += `<li class="dropdown-no-results"><span class="dropdown-item disabled">没有匹配的数据</span></li>`
        }
        
        dropdownMenuElem.innerHTML = innerHTML;
        
        // 确保下拉菜单显示
        if (!dropdownMenuElem.classList.contains('show')) {
            dropdownMenuElem.classList.add('show')
        }
    }).finally(() => {
        if (showLoading) {
            loading('hide');
        }
    })
}

// 关闭下拉列表
function closeDropdown(elem) {
    const dropdownMenu = elem.closest('.dropdown-menu')
    if (dropdownMenu && dropdownMenu.classList.contains('show')) {
        dropdownMenu.classList.remove('show')
    }
}

// 添加点击空白处关闭下拉菜单
document.addEventListener('click', function(event) {
    const target = event.target
    if (!target.closest('.select-box')) {
        document.querySelectorAll('.select-box .dropdown-menu.show').forEach(menu => {
            menu.classList.remove('show')
        })
    }
})

const selectInputEvent = (elem, isFocus = false) => {
    const dataSet = elem.dataset
    const url = dataSet.url
    const field = dataSet.field
    const value = elem.value
    const parent = elem.closest('.select-box')
    const targetElem = parent.querySelector('.dropdown-menu')
    
    // 如果是聚焦事件且输入框没有值，或者是输入事件，则发送请求
    if ((isFocus && !value) || !isFocus) {
        selectInputGetData(url, field, value, targetElem, !isFocus)
    }

    if (!value) {
        // 赋值到隐藏表单
        parent.querySelector('.visually-hidden').value = ''
    }
}

function clearSelectValue(elem) {
    const parent = elem.closest('.select-box')
    const inputField = parent.querySelector('.select-input')
    const selectedValueContainer = parent.querySelector('.selected-value-container')
    const dropdownIcon = parent.querySelector('.select-dropdown-icon')
    
    // 隐藏选中值模式，显示输入模式和下拉图标
    selectedValueContainer.style.display = 'none'
    inputField.style.display = 'block'
    if (dropdownIcon) dropdownIcon.style.display = 'block'
    
    // 清空输入框值
    inputField.value = ''
    
    // 赋值到隐藏表单
    parent.querySelector('.visually-hidden').value = ''
    
    // 聚焦到输入框
    inputField.focus()
}

// 新增函数：切换选择框图标
function toggleSelectIcons(parent, hasValue) {
    const downIcon = parent.querySelector('.icon-down')
    const closeIcon = parent.querySelector('.icon-close')
    
    if (hasValue) {
        // 有值时，隐藏下拉图标，显示清空图标
        if (downIcon) downIcon.style.display = 'none'
        if (closeIcon) closeIcon.style.display = 'unset'
    } else {
        // 无值时，显示下拉图标，隐藏清空图标
        if (downIcon) downIcon.style.display = 'unset'
        if (closeIcon) closeIcon.style.display = 'none'
    }
}

function showClearBtn() {
    document.querySelectorAll('.select-input').forEach(elem => {
        const parent = elem.closest('.select-box')
        
        // 根据是否有值切换图标状态
        toggleSelectIcons(parent, elem.value)
        
        // 如果有值则添加readonly属性
        if (elem.value) {
            elem.setAttribute('readonly', true)
        } else {
            elem.removeAttribute('readonly')
        }
    })
}

let handleOnKeyupTimer = null

function handleInputOnKeyup(elem) {
    if (handleOnKeyupTimer) {
        clearTimeout(handleOnKeyupTimer);
    }
    handleOnKeyupTimer = setTimeout(() => {
        selectInputEvent(elem, false)
        handleOnKeyupTimer = null;
    }, 300);
}

function handleInputOnFocus(elem) {
    selectInputEvent(elem, true)
}

// 页面加载完成后，初始化所有选择框
document.addEventListener('DOMContentLoaded', function() {
    initializeAllSelectBoxes()
})

// 初始化所有选择框
function initializeAllSelectBoxes() {
    document.querySelectorAll('.select-box').forEach(selectBox => {
        const inputField = selectBox.querySelector('.select-input')
        const hiddenField = selectBox.querySelector('.visually-hidden')
        const dropdownIcon = selectBox.querySelector('.select-dropdown-icon')
        
        // 如果隐藏域有值，说明已经选择了数据
        if (hiddenField && hiddenField.value && inputField) {
            // 隐藏输入框和下拉图标，显示选中值容器
            const selectedValueContainer = selectBox.querySelector('.selected-value-container')
            if (selectedValueContainer) {
                selectedValueContainer.querySelector('.selected-text').textContent = inputField.value
                inputField.style.display = 'none'
                if (dropdownIcon) dropdownIcon.style.display = 'none'
                selectedValueContainer.style.display = 'flex'
            }
        }
    })
}
