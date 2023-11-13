<?php

namespace Tests;

use App\Http\Request;
use App\PDOProxy\PDOProxyPool;
use App\WebApplication\Plugins\Blade;
use App\WebApplication\Route;
use Core\Map\WorkerMap;
use Generator;

class Index
{
    /**
     * @param Request      $request      实现了 CollaborativeFiberStd(纤程构建) 接口的请求对象
     * @param PDOProxyPool $PDOProxyPool 内置Worker都支持自动依赖注入
     * @return Generator 返回一个生成器
     */
    public static function index(Request $request, PDOProxyPool $PDOProxyPool): Generator
    {
        /**
         * 在发生异步操作之前,全局的静态属性都是安全的,但不建议这么做
         * 你可以通过依赖中间件+依赖注入的特性,在中间件或其他地方-
         * 构建你需要的对象如 Session|Cache 或将Cookie注入你的无状态Service等
         */
        yield $request->respondBody('hello world');

        $data = $PDOProxyPool->get('DEFAULT')->query('select * from user where id = ?', [17]);

        /**
         * 你可以通过 WorkerMap::get 获取已经启动的Worker
         * 内置的Worker都是单例模式运行并以className命名
         * @var TestWs $ws
         */
        $ws = WorkerMap::get('ws');
        foreach ($ws->getClients() as $client) {
            $client->send("用户{$request->client->getAddress()} 访问了网站,取得数据:" . json_encode($data));
        }
    }


    /**
     * @param Request $request 实现了 CollaborativeFiberStd(纤程构建) 接口的请求对象
     * @param Blade   $blade   中间件实现的依赖注入
     * @return Generator 返回一个生成器
     */
    public static function upload(Request $request, Blade $blade): Generator
    {
        if ($request->method === Route::GET) {
            yield $request->respondBody($blade->render('upload', [
                'title' => 'upload files'
            ]));
        } elseif ($request->upload) {
            yield $request->respondBody('文件上传中,请勿关闭页面.');

            $request->async(Request::EVENT_UPLOAD, function (array $info) {
                /**
                 * @var TestWs $ws
                 */
                $ws = WorkerMap::get('ws');
                foreach ($ws->getClients() as $client) {
                    $client->send('文件上传成功:' . json_encode($info));
                }
            });

            // 一个请求的生命周期直至这个function结束为止,如果你定义了异步事件,请用该方法声明Worker禁止回收
            // 事件的处理器会对该请求的最终回收负责
            $request->await();
        }
    }

    /**
     * 下载文件
     * @param Request $request
     * @return Generator
     */
    public static function download(Request $request): Generator
    {
        yield $request->respondFile(__DIR__ . '/Index.php', 'index.php');
        $request->await();
    }

}
