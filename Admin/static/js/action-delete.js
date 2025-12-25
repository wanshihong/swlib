'use strict'

let deleteConfirmUrl = ''

/**
 * 执行删除
 */
function deleteAction() {
    if (deleteConfirmUrl) {
        window.location.href = deleteConfirmUrl
        deleteConfirmUrl = ''
    }
}

function createDeleteConfirmModal() {
    const html = `
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalTitle"
     aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content rounded-0 border-0 shadow">
            <div class="modal-header border-0">
                <h1 class="modal-title fs-5" id="deleteConfirmModalTitle">确认删除吗？</h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body border-0">
                删除后数据将不可恢复
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn btn-light rounded-0" data-bs-dismiss="modal">取消</button>
                <button type="button" onclick="deleteAction()" class="btn btn-danger rounded-0">确认</button>
            </div>
        </div>
    </div>
</div>
`
    let modal = document.getElementById('deleteConfirmModal')
    if (!modal) {
        document.body.insertAdjacentHTML('beforeend', html)
    }
}


// 删除按钮
function deleteConfirm(src) {
    createDeleteConfirmModal();
    deleteConfirmUrl = src;
    new bootstrap.Modal('#deleteConfirmModal').show()
}

