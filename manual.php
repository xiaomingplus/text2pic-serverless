<?php


use Workerman\Worker;
use Workerman\Protocols\Http\Response;
use Upyun\Upyun;
use Upyun\Config;

require_once __DIR__ . '/vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();


// #### http worker ####
$http_worker = new Worker('http://0.0.0.0:80');

// 4 processes
$http_worker->count = 1;

// Emitted when data received
$http_worker->onMessage = function ($connection, $request) {
    //$request->get();
    //$request->post();
    //$request->header();
    //$request->cookie();
    //$request->session();
    //$request->uri();
    //$request->path();
    //$request->method();

    // Send data to client
    // $headers    = $request->header();
    $method     = $request->method();
    $path       = $request->path('path');
    $contentType = $request->header("Content-Type") or $request->header('content-type');
    $respBody = '{"code":200,"message":"Welcome to text2pic api."}';

    // get
    // $logger->info($path);
    if ($method === 'POST' and ($path === '/' or $path === "")) {
        $body       = $request->rawBody();
        $requestBodyType = 'form';
        if (strpos($contentType, 'application/json') === 0) {
            // json
            $requestBodyType = 'json';
        }
        $parsedBody = new stdClass();
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
        $text = property_exists($parsedBody, 'text') ? $parsedBody->text : "";
        $footer = property_exists($parsedBody, 'footer') ? $parsedBody->footer : "";
        $by = property_exists($parsedBody, 'by') ? $parsedBody->by : '由scuinfo.com根据热门程度自动生成，并不一定同意此观点! ' . "\n" . '微信关注scuinfo后可直接匿名发布内容到scuinfo.com';
        $transform = new Text2pic\Transform($by, '/tmp', 'http://docker.dev/uploads');
        $result = $transform->generate($text, $footer);
        // $result = array('code' => 200);

        if ($result['code'] == 200) {
            // 到这里图片已生成，下面是上传到upyun的代码

            $serviceConfig = new Config($_ENV['UPYUN_BUCKET'], $_ENV['UPYUN_USER'], $_ENV['UPYUN_PASSWORD']);

            $upyun = new UpYun($serviceConfig);
            try {
                $opts = array(
                    "Content-MD5" => md5(file_get_contents($result['data']['path']))
                );
                $filePath = $result['data']['path'];
                $fh = fopen($filePath, 'r');
                $fileName = '/uploads/' . md5($filePath) . '.jpg';
                $upyun->write($fileName, $fh);   // 上传图片，自动创建目录
                if (is_resource($fh)) {
                    fclose($fh);
                }
                unlink($filePath); //删除服务器的图片
                $result = array(
                    "code" => 200,
                    "message" => "ok",
                    "data" => array(
                        "url" => $_ENV['UPYUN_BASE_PIC_URL'] . $fileName
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

    $finalResponse = new Response(
        200,
        array(
            'Content-Type' => 'application/json',
        ),
        $respBody
    );
    $connection->send($finalResponse);
};

// Run all workers
Worker::runAll();
