'use strict'


// 分页选择每页显示条目数量
const selectElem = document.getElementById('select-page-size')
if (selectElem) {
    selectElem.addEventListener('change', event => {
        // 获取选中的值
        window.location.href = event.target.value
    }, false)
}