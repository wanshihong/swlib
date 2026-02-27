'use strict';

(function () {
    const RANGE_SELECTOR = 'input[type="number"][data-validate-min], input[type="number"][data-validate-max]';
    const getNumeric = (value) => {
        if (value === '' || value === null || value === undefined) {
            return null;
        }
        const parsed = Number(value);
        return Number.isNaN(parsed) ? null : parsed;
    };

    const getLabel = (element) => {
        return element.dataset.fieldLabel || element.name || element.id || '该字段';
    };


    const toast = (message) => {
        showToast(message);
    };

    const buildRangeMessage = (label, min, max) => {
        if (min !== null && max !== null) {
            return `${label} 需在 ${min}~${max} 之间`;
        }
        if (min !== null) {
            return `${label} 需大于等于 ${min}`;
        }
        if (max !== null) {
            return `${label} 需小于等于 ${max}`;
        }
        return `${label} 的值不合法`;
    };

    const validateInput = (element) => {
        const rawValue = element.value;
        const min = getNumeric(element.dataset.validateMin);
        const max = getNumeric(element.dataset.validateMax);
        const label = getLabel(element);

        if (rawValue === '') {
            element.classList.remove('is-invalid');
            element.dataset.rangeInvalid = '';
            return true;
        }

        const value = getNumeric(rawValue);
        if (value === null) {
            element.classList.add('is-invalid');
            if (!element.dataset.toastShown) {
                toast(buildRangeMessage(label, min, max));
                element.dataset.toastShown = '1';
            }
            return false;
        }

        let invalid = false;
        if (min !== null && value < min) {
            invalid = true;
        }
        if (max !== null && value > max) {
            invalid = true;
        }

        if (invalid) {
            element.classList.add('is-invalid');
            if (!element.dataset.toastShown) {
                toast(buildRangeMessage(label, min, max));
                element.dataset.toastShown = '1';
            }
            return false;
        }

        element.classList.remove('is-invalid');
        element.dataset.toastShown = '';
        return true;
    };

    const handleSubmit = (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) {
            return;
        }
        const invalidInputs = form.querySelectorAll('input.is-invalid[type="number"][data-validate-min], input.is-invalid[type="number"][data-validate-max]');
        if (invalidInputs.length > 0) {
            event.preventDefault();
            toast('请修正输入后再提交');
            invalidInputs[0].focus();
        }
    };

    const bindInputs = () => {
        const inputs = Array.from(document.querySelectorAll(RANGE_SELECTOR));
        inputs.forEach((element) => {
            const listener = () => validateInput(element);
            element.addEventListener('input', listener);
            element.addEventListener('change', listener);
        });
    };

    const bindFormSubmit = () => {
        document.addEventListener('submit', (event) => {
            handleSubmit(event);
        }, true);
    };

    const init = () => {
        bindInputs();
        bindFormSubmit();
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
