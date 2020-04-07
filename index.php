<?php
extension_loaded('gd');
require_once __DIR__ . "/vendor/autoload.php";
require_once 'upyun-php-sdk/upyun.class.php';

use RingCentral\Psr7\Response;

$logger = $GLOBALS['fcLogger'];

try {
    // 仅开发环境需要
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
    $dotenv->required(['UPYUN_BUCKET', 'UPYUN_USER', 'UPYUN_PASSWORD', 'UPYUN_BASE_PIC_URL']);
    $dotenv->load();
} catch (Exception $e) {
}


/*
if you open the initializer feature, please implement the initializer function, as below:
function initializer($context) {
    echo 'initializing' . PHP_EOL;
}
*/


function handler($request, $context): Response
{
    /*
    $body       = $request->getBody()->getContents();
    $queries    = $request->getQueryParams();
    $method     = $request->getMethod();
    $headers    = $request->getHeaders();
    $path       = $request->getAttribute('path');
    $requestURI = $request->getAttribute('requestURI');
    $clientIP   = $request->getAttribute('clientIP');
    */
    $headers    = $request->getHeaders();
    $method     = $request->getMethod();
    $path       = $request->getAttribute('path');
    $contentType = $headers['Content-Type'] or $headers['content-type'];
    $respBody = '{"code":200,"message":"Welcome to text2pic api."}';
    // get
    // $logger->info($path);

    if ($method === 'POST' and ($path === '/' or $path === "")) {
        $body       = $request->getBody()->getContents();
        $requestBodyType = 'form';
        if (strpos($contentType[0], 'application/json') === 0) {
            // json
            $requestBodyType = 'json';
        }
        $parsedBody = array();
        if ($requestBodyType === 'json') {
            $parsedBody = json_decode($body);
        } else {
            parse_str($body, $parsedBody);
            $parsedBody = (object) $parsedBody;
        }

        // $logger->info("hello world");
        // $logger->info($body);
        // $logger->info(json_encode($headers));
        // $logger->info($method);
        // $logger->info(json_encode($parsedBody));
        // $logger->info(json_encode($contentType));
        // $logger->info($requestBodyType);
        // echo $body;

        $text = array_key_exists('text', $parsedBody) ? $parsedBody->text : "";
        $footer = array_key_exists('footer', $parsedBody) ? $parsedBody->footer : "";
        $by = array_key_exists('by', $parsedBody) ? $parsedBody->by : '由scuinfo.com根据热门程度自动生成，并不一定同意此观点! ' . "\n" . '微信关注scuinfo后可直接匿名发布内容到scuinfo.com';
        $transform = new Text2pic\Transform($by, '/tmp', 'http://docker.dev/uploads');
        $result = $transform->generate($text, $footer);
        // $result = array('code' => 200);

        if ($result['code'] == 200) {
            // 到这里图片已生成，下面是上传到upyun的代码
            $upyun = new UpYun(getenv('UPYUN_BUCKET'), getenv('UPYUN_USER'), getenv('UPYUN_PASSWORD'));
            try {
                $opts = array(
                    UpYun::CONTENT_MD5 => md5(file_get_contents($result['data']['path']))
                );
                $fh = fopen($result['data']['path'], 'rb');
                $fileName = '/uploads/' . md5($result['data']['path']) . '.jpg';
                $rsp = $upyun->writeFile($fileName, $fh, True, $opts);   // 上传图片，自动创建目录
                fclose($fh);
                unlink($result['data']['path']); //删除服务器的图片
                $result = array(
                    "code" => 200,
                    "message" => "ok",
                    "data" => array(
                        "url" => getenv('UPYUN_BASE_PIC_URL') . $fileName
                    )
                );
            } catch (Exception $e) {

                $result = array(
                    "code" => 2003,
                    "message" => $e->getMessage()
                );
            }
        } else {
            $result = array(
                "code" => 2002,
                "message" => $result['message']
            );
        }
        $respBody = json_encode($result, JSON_UNESCAPED_UNICODE);
    } else {
    }

    return new Response(
        200,
        array(
            'Content-Type' => 'application/json',
        ),
        $respBody
    );
}
