'use strict'

function uploadImage(url, name, accept) {

    return new Promise((resolve) => {
        if (!url) {
            alert('请调用 setUrl 设置文件上传路径;或者在 AdminConfig config 中全局配置上次路径')
            return
        }

        const input = document.createElement('input')
        input.type = 'file'
        input.accept = accept
        input.onchange = event => {
            const file = event.target.files[0]
            if (file) {
                const formData = new FormData()
                formData.append(name, file)

                loading('show');
                fetch(url, {
                    method: 'POST',
                    body: formData
                }).then(res => res.json()).then(res => {
                    const {errno, data, msg} = res;
                    if (errno === 0) {
                        const path = data.url
                        resolve(path);
                    } else {
                        alert(msg)
                    }
                }).finally(() => {
                    loading('hide');
                    input.remove()
                })
            }
        }
        input.click()
    })
}


function createImageModal() {
    const html = `
<div class="modal" tabindex="-1" id="modal-show-image-full">
  <div class="modal-dialog modal-fullscreen">
    <div class="modal-content" style="background: rgba(0,0,0,.2)">
      <div class="modal-body d-flex justify-content-center align-items-center">
        <button type="button" class="btn btn-danger btn-sm position-absolute top-0 end-0" data-bs-dismiss="modal" aria-label="Close">
            <i class="bi bi-x-lg"></i>
        </button>
        <img class="prev-image" id="modal-image" src="" alt="">
      </div>
    </div>
  </div>
</div>
`
    let elem = document.getElementById('modal-show-image-full')
    if (!elem) {
        document.body.insertAdjacentHTML('beforeend', html)
    }
}

function showImageFull(src) {
    createImageModal()
    const myModal = new bootstrap.Modal('#modal-show-image-full')
    document.getElementById('modal-image').src = src;
    myModal.show()
}


document.addEventListener('DOMContentLoaded', function () {
    const {createApp} = Vue

    document.body.querySelectorAll('.vue-image-app').forEach(elem => {
        const id = elem.id
        const config = pageConfig[id];
        const attributes = {};
        for (const [key, value] of Object.entries(config.attributes)) {
            if (key !== 'vueInit') {
                attributes[`data-${key}`] = value
            }
        }


        createApp({
            delimiters: ['[[', ']]'],
            data() {
                return {
                    config: config,
                    attributes: attributes,
                    paths: [],
                    value: '',
                }
            },
            created() {
                if (config.value) {
                    try {
                        this.paths = JSON.parse(config.value)
                        this.updateValue()
                    } catch (e) {
                        this.paths = config.value.split(',')
                        this.updateValue()
                    }
                }
            },
            methods: {
                upload() {
                    uploadImage(this.config.url, this.config.name, this.config.accept).then(path => {
                        console.log(path)
                        this.paths.push(path)
                        this.updateValue()
                    })
                },
                updateValue() {
                    if (this.paths.length > 1) {
                        this.value = this.paths.join(',')
                    } else {
                        this.value = this.paths[0]
                    }
                },
                show(index) {
                    showImageFull(this.paths[index])
                },
                del(index) {
                    this.paths.splice(index, 1)
                    this.updateValue()
                }
            }
        }).mount(`#${id}`)

    })


});

