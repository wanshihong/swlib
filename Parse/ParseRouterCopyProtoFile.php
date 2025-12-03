<?php
declare(strict_types=1);

namespace Swlib\Parse;


trait ParseRouterCopyProtoFile
{


    public function copyProtoFiles(array $attributes): void
    {
        $moveFiles = [];
        foreach ($attributes as $item) {
            $urlArr = explode('/', $item->url);
            array_pop($urlArr);// 去掉 url 方法名
            array_pop($urlArr); // 去掉控制器名称
            $dir = implode('/', $urlArr);
            $moveFiles[] = ['module' => $dir, 'file' => $this->_copyProtoFile($item->request)];
            $moveFiles[] = ['module' => $dir, 'file' => $this->_copyProtoFile($item->response)];
        }

        foreach ($moveFiles as $item) {
            $file = $item['file'];
            $module = $item['module'];

            if (empty($file)) {
                continue;
            }

            $dir = RUNTIME_DIR . "codes/ts/proto/$module/";
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
            copy(ROOT_DIR . "protos/$file", $dir . basename($file));
        }
    }

    private function _copyProtoFile(string $className): string
    {
        if (str_starts_with($className, 'Protobuf') && str_ends_with($className, 'Proto')) {
            $arr = explode('\\', $className);
            $fileName = array_pop($arr);
            array_pop($arr);
            $dir = array_pop($arr);
            $filePath = $dir . '/' . str_replace(['ListsProto', 'RequestProto', 'ResponseProto', 'Proto', '_proto'], '.proto', $fileName);
            if (str_starts_with($filePath, 'Protobuf')) {
                return str_replace('Protobuf/', '', $filePath);
            }
            return $filePath;
        }
        return '';
    }

}