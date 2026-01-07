<?php

namespace Swlib\DevTool\Ctrls;

use Generate\ConfigEnum;
use Swlib\Controller\AbstractController;
use Swlib\Router\Router;
use Swlib\Utils\DevSslCert as DevSslCertUtil;

/**
 * 开发环境 SSL 证书下载控制器
 *
 * 提供一个 URL，方便在 iOS Safari 中下载并安装自签名证书。
 *
 * 路由示例：
 *   GET /dev-tool/dev-ssl-cert/ios
 */
class DevSslCert extends AbstractController
{

    /**
     * 下载 iOS 证书文件
     * GET /dev-tool/dev-ssl-cert/ios
     */
    #[Router(method: 'GET')]
    public function ios(): void
    {
        if (!$this->checkDevEnvironment()) {
            $this->response->status(403);
            $this->response->header('Content-Type', 'text/plain; charset=utf-8');
            $this->response->end('Dev SSL certificate is only available in development environment.');
            return;
        }

        $sslIosCertFile = DevSslCertUtil::getIosCertFile();

        if (!file_exists($sslIosCertFile)) {
            $this->response->status(404);
            $this->response->header('Content-Type', 'text/plain; charset=utf-8');
            $this->response->end('iOS SSL certificate not found. Please restart backend service to regenerate SSL certificates.');
            return;
        }

        $certContent = file_get_contents($sslIosCertFile);
        if ($certContent === false) {
            $this->response->status(500);
            $this->response->header('Content-Type', 'text/plain; charset=utf-8');
            $this->response->end('Failed to read iOS SSL certificate file.');
            return;
        }

        $this->response->header('Content-Type', 'application/x-x509-ca-cert');
        $this->response->header('Content-Disposition', 'attachment; filename="swlib_dev_ios.cer"');
        $this->response->header('Content-Length', (string)strlen($certContent));
        $this->response->end($certContent);
    }

    private function checkDevEnvironment(): bool
    {
        return ConfigEnum::APP_PROD === false;
    }
}
