'use strict'

function buildEditUrl(baseUrl, priFieldName, priFieldValue) {
    const url = new URL(baseUrl, window.location.origin)
    url.searchParams.set(priFieldName, priFieldValue)
    return url.toString()
}

async function saveInlineText(inputElem, saveBtn) {
    const field = inputElem.dataset.field
    const priFieldName = inputElem.dataset.priFieldName
    const priFieldValue = inputElem.dataset.priFieldValue
    const baseUrl = inputElem.dataset.url

    if (!field || !priFieldName || !priFieldValue || !baseUrl) {
        alert('参数错误')
        return
    }

    const editUrl = buildEditUrl(baseUrl, priFieldName, priFieldValue)
    const formData = new URLSearchParams()
    formData.set(priFieldName, priFieldValue)
    formData.set(field, inputElem.value ?? '')

    saveBtn.disabled = true
    loading('show')
    try {
        const saveRes = await fetch(editUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
            },
            body: formData.toString()
        })

        if (!saveRes.ok) {
            throw new Error('save failed')
        }
        showToast('保存成功')
        inputElem.dataset.originalValue = inputElem.value ?? ''
        saveBtn.classList.add('d-none')
    } catch (e) {
        console.error(e)
        alert('保存失败')
    } finally {
        loading('hide')
        saveBtn.disabled = false
    }
}

document.addEventListener('click', function (event) {
    const target = event.target
    if (!(target instanceof HTMLElement)) {
        return
    }

    const saveBtn = target.closest('.list-text-inline-save-icon')
    if (!(saveBtn instanceof HTMLElement)) {
        return
    }

    const wrap = saveBtn.closest('.list-text-inline-wrap')
    if (!wrap) {
        return
    }
    const inputElem = wrap.querySelector('.list-text-inline-input')
    if (!(inputElem instanceof HTMLInputElement)) {
        return
    }
    saveInlineText(inputElem, saveBtn)
})

document.addEventListener('keydown', function (event) {
    if (event.key !== 'Enter') {
        return
    }
    const target = event.target
    if (!(target instanceof HTMLInputElement) || !target.classList.contains('list-text-inline-input')) {
        return
    }
    event.preventDefault()
    const wrap = target.closest('.list-text-inline-wrap')
    const saveBtn = wrap ? wrap.querySelector('.list-text-inline-save-icon') : null
    if (!(saveBtn instanceof HTMLElement) || saveBtn.classList.contains('d-none')) {
        return
    }
    saveInlineText(target, saveBtn)
})

document.addEventListener('input', function (event) {
    const target = event.target
    if (!(target instanceof HTMLInputElement) || !target.classList.contains('list-text-inline-input')) {
        return
    }
    const wrap = target.closest('.list-text-inline-wrap')
    const saveIcon = wrap ? wrap.querySelector('.list-text-inline-save-icon') : null
    if (!(saveIcon instanceof HTMLElement)) {
        return
    }
    const originalValue = target.dataset.originalValue ?? ''
    if ((target.value ?? '') !== originalValue) {
        saveIcon.classList.remove('d-none')
    } else {
        saveIcon.classList.add('d-none')
    }
})
