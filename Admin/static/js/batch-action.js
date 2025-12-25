/**
 * 批量操作功能
 */
(function () {
    // 获取DOM元素
    const selectAllCheckbox = document.getElementById('batch-select-all');
    const rowCheckboxes = document.querySelectorAll('.batch-row-checkbox');
    const batchActionSelect = document.getElementById('batch-action-select');
    const batchActionSubmit = document.getElementById('batch-action-submit');
    const selectedCountSpan = document.getElementById('batch-selected-count');

    // 如果没有批量操作相关元素，直接返回
    if (!selectAllCheckbox || !batchActionSelect) {
        return;
    }

    /**
     * 更新选中数量显示和按钮状态
     */
    function updateSelectedCount() {
        const checkedBoxes = document.querySelectorAll('.batch-row-checkbox:checked');
        const count = checkedBoxes.length;
        const totalCount = rowCheckboxes.length;

        // 更新选中数量显示
        if (selectedCountSpan) {
            selectedCountSpan.textContent = `已选 ${count} 条`;
        }

        // 更新全选复选框状态（支持半选状态）
        if (count === 0) {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = false;
        } else if (count === totalCount) {
            selectAllCheckbox.checked = true;
            selectAllCheckbox.indeterminate = false;
        } else {
            selectAllCheckbox.checked = false;
            selectAllCheckbox.indeterminate = true;
        }

        // 更新执行按钮状态
        if (batchActionSubmit) {
            batchActionSubmit.disabled = count === 0 || !batchActionSelect.value;
        }
    }

    /**
     * 全选/取消全选
     */
    selectAllCheckbox.addEventListener('change', function () {
        const isChecked = this.checked;
        rowCheckboxes.forEach(function (checkbox) {
            checkbox.checked = isChecked;
        });
        updateSelectedCount();
    });

    /**
     * 单个复选框变化
     */
    rowCheckboxes.forEach(function (checkbox) {
        checkbox.addEventListener('change', updateSelectedCount);
    });

    /**
     * 批量操作下拉框变化
     */
    batchActionSelect.addEventListener('change', function () {
        const checkedBoxes = document.querySelectorAll('.batch-row-checkbox:checked');
        if (batchActionSubmit) {
            batchActionSubmit.disabled = checkedBoxes.length === 0 || !this.value;
        }
    });

    /**
     * 创建批量操作确认弹窗
     */
    function createBatchConfirmModal() {
        const html = `
<div class="modal fade" id="batchConfirmModal" tabindex="-1" aria-labelledby="batchConfirmModalTitle" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content rounded-0 border-0 shadow">
            <div class="modal-header border-0">
                <h1 class="modal-title fs-5" id="batchConfirmModalTitle">确认操作</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body border-0" id="batchConfirmModalBody">
                确定要执行此操作吗？
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light rounded-0" data-bs-dismiss="modal">取消</button>
                <button type="button" id="batchConfirmBtn" class="btn btn-primary rounded-0">确认</button>
            </div>
        </div>
    </div>
</div>
`;
        let modal = document.getElementById('batchConfirmModal');
        if (!modal) {
            document.body.insertAdjacentHTML('beforeend', html);
        }
    }

    /**
     * 执行批量操作
     */
    function executeBatchAction(actionUrl, ids) {
        // 显示加载状态
        if (batchActionSubmit) {
            batchActionSubmit.disabled = true;
            batchActionSubmit.innerHTML = '<span class="spinner-border spinner-border-sm"></span> 执行中...';
        }

        // 发送请求
        fetch(actionUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: JSON.stringify({ids: ids})
        })
            .then(response => response.json())
            .then(data => {
                if (data.errno === 0) {
                    // 成功，刷新页面
                    window.location.reload();
                } else {
                    // 失败，显示错误信息
                    alert(data.msg || '操作失败');
                    // 恢复按钮状态
                    if (batchActionSubmit) {
                        batchActionSubmit.disabled = false;
                        batchActionSubmit.innerHTML = '<i class="bi bi-check2-circle"></i> 执行';
                    }
                }
            })
            .catch(error => {
                console.error('批量操作失败:', error);
                alert('操作失败，请重试');
                // 恢复按钮状态
                if (batchActionSubmit) {
                    batchActionSubmit.disabled = false;
                    batchActionSubmit.innerHTML = '<i class="bi bi-check2-circle"></i> 执行';
                }
            });
    }

    /**
     * 点击执行按钮
     */
    if (batchActionSubmit) {
        batchActionSubmit.addEventListener('click', function () {
            const actionUrl = batchActionSelect.value;
            if (!actionUrl) {
                alert('请选择批量操作');
                return;
            }

            // 获取选中的ID
            const checkedBoxes = document.querySelectorAll('.batch-row-checkbox:checked');
            if (checkedBoxes.length === 0) {
                alert('请选择要操作的数据');
                return;
            }

            const ids = Array.from(checkedBoxes).map(cb => cb.value);

            // 获取确认消息
            const selectedOption = batchActionSelect.options[batchActionSelect.selectedIndex];
            const confirmMessage = selectedOption.dataset.confirm || '确定要执行此操作吗？';

            // 创建并显示确认弹窗
            createBatchConfirmModal();
            const modalBody = document.getElementById('batchConfirmModalBody');
            if (modalBody) {
                modalBody.textContent = confirmMessage + ` (共 ${ids.length} 条数据)`;
            }

            const confirmBtn = document.getElementById('batchConfirmBtn');
            if (confirmBtn) {
                // 移除旧的事件监听器
                const newConfirmBtn = confirmBtn.cloneNode(true);
                confirmBtn.parentNode.replaceChild(newConfirmBtn, confirmBtn);

                // 添加新的事件监听器
                newConfirmBtn.addEventListener('click', function () {
                    // 关闭弹窗
                    const modal = bootstrap.Modal.getInstance(document.getElementById('batchConfirmModal'));
                    if (modal) {
                        modal.hide();
                    }
                    // 执行批量操作
                    executeBatchAction(actionUrl, ids);
                });
            }

            new bootstrap.Modal('#batchConfirmModal').show();
        });
    }
})();

