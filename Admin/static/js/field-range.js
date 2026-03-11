'use strict'

document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-range-field]').forEach(function (container) {
        const hiddenInput = document.getElementById(container.dataset.rangeField)
        if (!hiddenInput) {
            return
        }

        const syncValue = function () {
            const startInput = container.querySelector('[data-range-part="start"]')
            const endInput = container.querySelector('[data-range-part="end"]')
            if (!startInput || !endInput) {
                return
            }

            hiddenInput.value = `${startInput.value.trim()},${endInput.value.trim()}`
        }

        container.querySelectorAll('[data-range-part]').forEach(function (input) {
            input.addEventListener('input', syncValue)
            input.addEventListener('change', syncValue)
        })

        syncValue()
    })
})
