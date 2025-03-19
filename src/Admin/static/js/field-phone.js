'use strict'

function onPhoneInput(e){
    if (e.value.length > 11) { // 假设电话号码长度限制为11位
        e.value = e.value.slice(0, 11);
    }
}