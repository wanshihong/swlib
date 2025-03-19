'use strict'

// 列表页面的 switch 切换功能
const listSwitch = document.querySelectorAll('.list-switch-input')
if (listSwitch && listSwitch.length) {
    listSwitch.forEach(elem => {
        elem.addEventListener('change', event => {
            const dataSet = event.target.dataset
            const field = dataSet.field
            const url = dataSet.url
            const enable = dataSet.enable
            const disabled = dataSet.disabled
            const priFieldName = dataSet.prifieldname
            const priFieldValue = dataSet.prifieldvalue

            const value = event.target.checked ? enable : disabled
            loading('show');
            fetch(url, {
                method: 'POST',
                body: JSON.stringify({
                    field: field,
                    value: value,
                    priFieldName: priFieldName,
                    priFieldValue: priFieldValue,
                })
            }).then(res => res.json()).then(data => {
                if (data.errno) {
                    alert(data.msg)
                }
            }).finally(() => {
                loading('hide');
            })

        })
    })
}