<?php

namespace Tests\http;

use Core\Map\WorkerMap;
use Generator;
use Illuminate\Support\Facades\DB;
use Illuminate\View\Factory;
use Support\Http\Request;
use Support\WebApplication\Route;
use Tests\rpc\TestWS;
use Worker\Built\JsonRpc\JsonRpcClient;
use Worker\Worker;

class Index
{
    /**
     * @param Request $request 实现了 CollaborativeFiberStd(纤程构建) 接口的请求对象
     * @return Generator 返回一个生成器
     */
    public static function index(Request $request): Generator
    {
        /**
         * 在发生异步操作之前,全局的静态属性都是安全的,但不建议使用静态属性
         * 你可以通过依赖中间件+依赖注入的特性,在中间件或其他地方-
         * 构建你需要的对象如 Session|Cache 或将Cookie注入你的无状态Service等
         */
        yield $request->respondBody('hello world');
        $data = DB::table('user')->where('id', 17)->first();

        /**
         * 你可以通过 WorkerMap::get 获取已经启动的Worker
         * 内置的Worker都是单例模式运行并以className命名
         * @var TestWs $ws
         */
        JsonRpcClient::getInstance()->call(
            'ws',
            'sendMessageToClients',
            "data:" . json_encode($data)
        );
    }


    /**
     * @param Request $request 实现了 CollaborativeFiberStd(纤程构建) 接口的请求对象
     * @param Factory $blade   WebApplication实现的依赖注入
     * @return Generator 返回一个生成器
     */
    public static function upload(Request $request, Factory $blade): Generator
    {
        if ($request->method === Route::GET) {
            yield $request->respondBody($blade->make('upload', [
                'title' => 'upload files'
            ])->render());
        } elseif ($request->upload) {
            yield $request->respondBody('文件上传中,请勿关闭页面.');
            $request->async(Request::EVENT_UPLOAD, function (array $info) {
                /**
                 * @var TestWs $ws
                 */
                JsonRpcClient::getInstance()->call(
                    'ws',
                    'sendMessageToClients',
                    '文件上传成功:' . json_encode($info)
                );
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
        yield $request->respondFile(__DIR__ . '/Index.php', 'Index.php');
        $request->await();
    }

    /**
     * @param Request $request
     * @return Generator
     */
    public static function data(Request $request): Generator
    {
        yield $request->respondJson(DB::table('user')->first());
        // TODO: 自己实现回滚去吧
//        /**
//         * PDOPool::class 是 PDOProxyPool的助手类,你可以直接静态方法操作代理池
//         */
//        $originData = PDOPool::get('DEFAULT')->query('select * from user where id = ?', [17]);
//
//        /**
//         * 你也可以直接使用 PDOProxyPool::class 提供的 instance 方法获取代理池
//         * 下面模拟了一次事务回滚
//         */
//        $pdoWorker = PDOProxyPool::instance()->get('DEFAULT');
//
//        $pdoWorker->transaction(function (PDOTransaction $transaction) use (&$updateData) {
//            $transaction->query('update user set `username` = ? where `id` = ?', ['changed', 17], []);
//            $updateData = $transaction->query('select * from `user` where id = ?', [17], []);
//
//            // 数据回滚
//            throw new RollbackException('');
//        });
//
//        $resultData = $pdoWorker->query('select * from user where id = ?', [17]);
//
//        yield $request->respondJson([
//            'origin' => $originData,
//            'update' => $updateData,
//            'result' => $resultData,
//        ]);
    }

    public static function fork(Request $request): Generator
    {
        yield $request->respondJson([
            'processId' => WorkerMap::get('http')->fork()
        ]);
    }

    public static function hello(Request $request): Generator
    {
        yield $request->respondBody('hello world');
    }
}
