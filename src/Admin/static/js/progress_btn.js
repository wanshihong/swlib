// 创建一个隐藏的文件输入元素
const fileInput = document.createElement('input');
fileInput.type = 'file';
fileInput.accept = '.xlsx'; // 设置只接受 CSV 文件
fileInput.style.display = 'none';
document.body.appendChild(fileInput);

// 监听文件选择事件
fileInput.addEventListener('change', handleFileSelect, false);

let getProgressUrl, runUrl, completeUrl, ratioText = '完成';

// 文件选择处理函数
function handleFileSelect(event) {
    const file = event.target.files[0];
    if (file) {
        const formData = new FormData();
        formData.append('file', file);

        fetch(runUrl, {
            method: 'POST',
            body: formData
        }).then(function (response) {
            return response.json();
        }).then(function (data) {
            if (data.errno === 0) {
                let url = `${getProgressUrl}?msgId=${data.data.msgId}`
                localStorage.setItem('importRatioUrl', url)
                loading('show', '上传中')
                getRatio(url)
            } else {
                alert(data.msg)
            }

        }).catch(function (error) {
            console.log(error)
        });
    }
}

let lastProgressRatio = 0;
let lastProgressCount = 0;
let checkUploadTimer = null


function getRandomNumber(min, max) {
    return Math.floor(Math.random() * (max - min + 1)) + min;
}

function getRatio(url) {
    fetch(url, {
        method: 'GET',
    }).then(function (response) {
        return response.json();
    }).then(function (data) {
        clearInterval(checkUploadTimer)
        if (data.errno === 0) {
            if (data.data.progress > 0) {
                if (data.data.progress !== lastProgressRatio) {
                    lastProgressRatio = data.data.progress
                } else {
                    lastProgressCount++;
                }
                if (lastProgressCount > 50) {
                    alert(`导入文件失败，服务器处理已经中断；刷新页面后即可重新上传`)
                    localStorage.removeItem('importRatioUrl')
                    window.location.reload()
                } else {
                    loading('show', `${data.data.progress}${ratioText}`)
                }
            }
            if (data.data.progress === -1) {
                localStorage.removeItem('importRatioUrl')
                loading('hide');
                if (completeUrl) {
                    window.location.href = completeUrl;
                }
            } else {
                setTimeout(function () {
                    getRatio(url);
                }, getRandomNumber(1500, 4000))
            }

        } else {
            alert(data.msg)
        }

    }).catch(function (error) {
        console.log(error)
        if (checkUploadTimer) {
            clearInterval(checkUploadTimer)
        }
        checkUploadTimer = setInterval(function () {
            let importRatioUrl = localStorage.getItem('importRatioUrl')
            if (importRatioUrl) {
                getRatio(importRatioUrl)
            } else {
                clearInterval(checkUploadTimer)
            }
        }, 3000)
    });
}

function download() {
    fetch(runUrl, {
        method: 'GET',
    }).then(function (response) {
        return response.json();
    }).then(function (data) {
        if (data.errno === 0) {
            let url = `${getProgressUrl}?msgId=${data.data.msgId}`
            localStorage.setItem('importRatioUrl', url)
            loading('show', '导出中')
            getRatio(url)
        } else {
            alert(data.msg)
        }

    }).catch(function (error) {
        console.log(error)
    });
}


let importRatioUrl = localStorage.getItem('importRatioUrl')
if (importRatioUrl) {
    getRatio(importRatioUrl)

}


// 触发文件选择框
const btns = document.querySelectorAll('.progress_btn')
if (btns) {
    btns.forEach(btn => {
        btn.addEventListener('click', (e) => {
            console.log(e.target.dataset)
            getProgressUrl = e.target.dataset.getProgressUrl
            if (!getProgressUrl) {
                alert('请检查是否调用了 setProgressUrl 设置获取进度接口地址')
                return;
            }
            completeUrl = e.target.dataset.completeUrl
            runUrl = e.target.dataset.runUrl
            console.log(completeUrl)
            if (e.target.dataset.actionType === 'download') {
                download();
                ratioText = "% 已完成"
            } else {
                ratioText = "行已导入"
                fileInput.click();
            }
        })
    })

}
