const {createEditor, createToolbar} = window.wangEditor

const editorDom = document.querySelectorAll('.editor—wrapper');
if (editorDom) {
    editorDom.forEach(elem => {
        const editorContainer = elem.querySelector('.editor-container')
        const toolbarContainer = elem.querySelector('.toolbar-container')
        const textarea = document.getElementById(elem.dataset.id)
        const uploadUrl = elem.dataset.url


        const editorConfig = {
            MENU_CONF: {
                uploadImage: {
                    server: uploadUrl,
                    fieldName: 'file',
                }
            },
            placeholder: '请输入...',
            onChange(editor) {
                textarea.value = editor.getHtml()
            }
        }


        const editor = createEditor({
            selector: editorContainer,
            // 设置默认值
            html: textarea.value ? textarea.value : '',
            config: editorConfig,
            mode: 'default', // or 'simple'
        })

        const toolbarConfig = {}

        const toolbar = createToolbar({
            editor,
            selector: toolbarContainer,
            config: toolbarConfig,
            mode: 'default', // or 'simple'
        })

    })
}
