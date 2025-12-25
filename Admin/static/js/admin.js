'use strict'

// 启用工具提示，鼠标悬浮的时候，显示提示信息
const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]')
const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => new bootstrap.Tooltip(tooltipTriggerEl))


const copyText = (text) => {
    if (text) {
        clipboard.writeText(text)
        showToast('复制成功')
    }
}


let loadingTimer;
const loading = (type = 'show', progress = '') => {
    let loadingElem = document.getElementById('loading')
    if (!loadingElem) {
        const html = `<div id="loading"><i class="icon bi bi-arrow-clockwise"></i><span>${progress}</span></div>`
        document.body.insertAdjacentHTML('beforeend', html)
        loadingElem = document.getElementById('loading');
    }
    if (type === 'show') {
        if (loadingElem.style.display !== 'flex') {
            // 太短时间就执行完成了，没必要显示 loading
            loadingTimer = setTimeout(() => {
                loadingElem.style.display = 'flex';
            }, 300);
        }

        if (progress) {
            loadingElem.querySelector('span').innerText = progress
        }
    } else {
        loadingElem.style.display = 'none';
        if (loadingTimer) {
            clearTimeout(loadingTimer);
            loadingTimer = null
        }
    }
}

const showToast = (msg) => {
    const newElement = document.createElement('div');
    newElement.className = 'admin-toast';
    newElement.innerText = msg;
    document.body.appendChild(newElement);

    // 监听动画结束事件
    newElement.addEventListener('animationend', function(event) {
        // 在动画结束后删除元素
        document.body.removeChild(newElement);
    });
}

