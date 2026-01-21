'use strict'

// 启用工具提示，鼠标悬浮的时候，显示提示信息
const tooltipTriggerList = document.querySelectorAll('[data-bs-toggle="tooltip"]')
const tooltipList = [...tooltipTriggerList].map(tooltipTriggerEl => {
    const rawTitle = tooltipTriggerEl.getAttribute('data-bs-title') ?? tooltipTriggerEl.getAttribute('title')
    if (rawTitle !== null) {
        tooltipTriggerEl.removeAttribute('data-bs-title')
        tooltipTriggerEl.removeAttribute('title')
        return new bootstrap.Tooltip(tooltipTriggerEl, {title: rawTitle})
    }
    return new bootstrap.Tooltip(tooltipTriggerEl)
})


// 复制文本到剪贴板并提示
const copyText = (text) => {
    if (text) {
        clipboard.writeText(text)
        showToast('复制成功')
    }
}


// 全局 loading 控制
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

// 简易 toast 提示
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

// 输入框值变化监听器集合：key => Set<handler>
const inputValueListeners = new Map()

// 解析监听 key：优先使用元素自身 id，未设置则向上查找
const resolveInputValueKey = (element) => {
    let current = element
    while (current && current !== document) {
        if (current.id) {
            return current.id
        }
        current = current.parentElement
    }
    return ''
}

const isFormValueElement = (element) => {
    return element instanceof HTMLInputElement
        || element instanceof HTMLTextAreaElement
        || element instanceof HTMLSelectElement
}

const lastInputValues = new WeakMap()
const pendingInputElements = new Map()
let flushInputPending = false

const flushInputChanges = () => {
    flushInputPending = false
    pendingInputElements.forEach((event, element) => {
        pendingInputElements.delete(element)
        if (!isFormValueElement(element)) {
            return
        }
        const nextValue = element.value
        const prevValue = lastInputValues.get(element)
        if (prevValue === nextValue) {
            return
        }
        lastInputValues.set(element, nextValue)
        const key = resolveInputValueKey(element)
        if (!key) {
            return
        }
        const listeners = inputValueListeners.get(key)
        if (!listeners || listeners.size === 0) {
            return
        }
        const payload = {
            key,
            value: nextValue,
            element,
            event
        }
        listeners.forEach((handler) => {
            try {
                handler(payload)
            } catch (error) {
                console.error(error)
            }
        })
    })
}

const queueInputChange = (element, event) => {
    if (!isFormValueElement(element)) {
        return
    }
    pendingInputElements.set(element, event)
    if (!flushInputPending) {
        flushInputPending = true
        Promise.resolve().then(flushInputChanges)
    }
}

const notifyInputValueChangeFromElement = (element, event) => {
    queueInputChange(element, event)
}

// 触发输入框值变化通知
const notifyInputValueChange = (event) => {
    const target = event.target
    notifyInputValueChangeFromElement(target, event)
}

// 注册输入框值变化监听器，返回取消函数
window.onInputValueChange = (key, handler) => {
    if (!key || typeof handler !== 'function') {
        return null
    }
    const keyStr = String(key)
    if (!inputValueListeners.has(keyStr)) {
        inputValueListeners.set(keyStr, new Set())
    }
    inputValueListeners.get(keyStr).add(handler)
    return () => {
        const set = inputValueListeners.get(keyStr)
        if (set) {
            set.delete(handler)
            if (set.size === 0) {
                inputValueListeners.delete(keyStr)
            }
        }
    }
}

// 监听 input/change，覆盖输入与选择变化
document.addEventListener('input', notifyInputValueChange)
document.addEventListener('change', notifyInputValueChange)

const observeFormValueChanges = () => {
    if (!document.body) {
        return
    }
    const observer = new MutationObserver((mutations) => {
        mutations.forEach((mutation) => {
            if (mutation.type !== 'attributes') {
                return
            }
            const target = mutation.target
            if (!isFormValueElement(target)) {
                return
            }
            if (mutation.attributeName === 'value'
                || mutation.attributeName === 'checked'
                || mutation.attributeName === 'selected') {
                notifyInputValueChangeFromElement(target, mutation)
            }
        })
    })
    observer.observe(document.body, {
        attributes: true,
        subtree: true,
        attributeFilter: ['value', 'checked', 'selected']
    })
}

const patchValueSetter = (proto, prop) => {
    const descriptor = Object.getOwnPropertyDescriptor(proto, prop)
    if (!descriptor || !descriptor.set) {
        return
    }
    Object.defineProperty(proto, prop, {
        get: descriptor.get,
        set(value) {
            descriptor.set.call(this, value)
            notifyInputValueChangeFromElement(this, null)
        }
    })
}

observeFormValueChanges()
patchValueSetter(HTMLInputElement.prototype, 'value')
patchValueSetter(HTMLTextAreaElement.prototype, 'value')
patchValueSetter(HTMLSelectElement.prototype, 'value')
