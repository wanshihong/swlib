'use strict'

const selectDropdownMenuItem = (elem, id, text) => {
    const parent = elem.closest('.select-box')
    // 赋值到显示表单
    parent.querySelector('input[type="text"]').value = text
    // 赋值到隐藏表单
    parent.querySelector('.visually-hidden').value = id
    showClearBtn()
}

const selectInputGetData = (url, field, value, dropdownMenuElem) => {
    loading('show');
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
        dropdownMenuElem.innerHTML = data.data.map(item => {
            return `<li onclick="selectDropdownMenuItem(this,'${item.id}','${item.text}')">
                        <a class="dropdown-item" href="javascript:">
                            ${item.text}
                        </a>
                    </li>`
        }).join('')

    }).finally(() => {
        loading('hide');
    })
}


const selectInputEvent = (elem) => {
    const dataSet = elem.dataset
    const url = dataSet.url
    const field = dataSet.field
    const value = elem.value
    const targetElem = elem.closest('.select-box').querySelector('.dropdown-menu')
    selectInputGetData(url, field, value, targetElem)

    if (!value) {
        const parent = elem.closest('.select-box')
        // 赋值到隐藏表单
        parent.querySelector('.visually-hidden').value = ''
    }
}

function clearSelectValue(elem) {
    const parent = elem.closest('.select-box')
    // 赋值到显示表单
    parent.querySelector('input[type="text"]').value = ''
    // 赋值到隐藏表单
    parent.querySelector('.visually-hidden').value = ''
    showClearBtn()
}


function showClearBtn() {
    document.querySelectorAll('.select-input').forEach(elem => {
        const closeBtn = elem.parentNode.querySelector('.icon-close')
        if (!closeBtn) return;
        closeBtn.style.display = elem.value ? 'unset' : 'none'
    })

}

let handleOnKeyupTimer = null

function handleInputOnKeyup(elem) {
    if (handleOnKeyupTimer) {
        clearTimeout(handleOnKeyupTimer);
    }
    handleOnKeyupTimer = setTimeout(() => {
        selectInputEvent(elem)
        handleOnKeyupTimer = null;
    }, 300);
}

function handleInputOnClick(elem) {
    selectInputEvent(elem)
}

function handleOnClick(elem) {
    const parent = elem.closest('.select-box')
    const inputElem = parent.querySelector('input[type="text"]')
    inputElem.value = "";
    selectInputEvent(inputElem)
}
