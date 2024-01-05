<?php

namespace Tests\http\controller;

use Core\Map\WorkerMap;
use Facade\JsonRpc;
use Generator;
use Illuminate\Support\Facades\DB;
use PRipple;
use Support\Extends\Session\Session;
use Support\Http\Request;

class Index
{
    /**
     * @param Request $request
     * @return Generator
     */
    public static function index(Request $request): Generator
    {
        yield $request->respondBody('hello world');
    }

    /**
     * @param Request $request 实现了 CollaborativeFiberStd(纤程构建) 接口的请求对象
     * @return Generator 返回一个生成器
     */
    public static function info(Request $request): Generator
    {
        /**
         * 在发生异步操作之前,全局的静态属性都是安全的,但不建议使用静态属性
         * 你可以通过依赖中间件+依赖注入的特性,在中间件或其他地方构建你需要的对象
         * 如 Session|Cache或将Cookie注入你的无状态Service等
         */
        yield $request->respondJson([
            'code' => 0,
            'msg'  => 'success',
            'data' => [
                'processId'   => posix_getpid(),
                'rpcServices' => array_keys(JsonRpc::getInstance()->rpcServiceConnections),
                'configure'   => PRipple::getArgument()
            ],
        ]);
    }

    /**
     * @param Request $request
     * @return Generator
     */
    public static function data(Request $request): Generator
    {
        yield $request->respondJson(DB::table('user')->first());
    }

    /**
     * @param Request $request
     * @return Generator
     */
    public static function fork(Request $request): Generator
    {
        $pid = WorkerMap::get('http')?->fork();
        yield $request->respondJson([
            'code' => 0,
            'msg'  => 'success',
            'data' => [
                'pid' => $pid
            ],
        ]);
    }

    /**
     * @param Request $request
     * @return Generator
     */
    public static function notice(Request $request): Generator
    {
        if ($message = $request->query['message'] ?? null) {
            JsonRpc::call(['ws', 'sendMessageToAll'], $message);
            yield $request->respondJson([
                'code' => 0,
                'msg'  => 'success',
                'data' => [
                    'message' => $message
                ],
            ]);
        } else {
            yield $request->respondJson([
                'code' => 1,
                'msg'  => 'error',
                'data' => [
                    'message' => 'message is required'
                ],
            ]);
        }
    }

    /**
     * @param Request $request
     * @param Session $session
     * @return Generator
     */
    public static function login(Request $request, Session $session): Generator
    {
        if ($name = $request->query['name'] ?? null) {
            $session->set('name', $name);
            yield $request->respondJson([
                'code' => 0,
                'msg'  => 'success',
                'data' => [
                    'message' => 'login success,' . $name
                ],
            ]);
        } elseif ($name = $session->get('name')) {
            yield $request->respondJson([
                'code' => 0,
                'msg'  => 'success',
                'data' => [
                    'message' => 'hello,' . $name
                ],
            ]);
        } else {
            yield $request->respondJson([
                'code' => 1,
                'msg'  => 'error',
                'data' => [
                    'message' => 'name is required'
                ],
            ]);
        }
    }

    /**
     * @param Request $request
     * @param Session $session
     * @return Generator
     */
    public static function logout(Request $request, Session $session): Generator
    {
        $session->clear();
        yield $request->respondJson([
            'code' => 0,
            'msg'  => 'success',
            'data' => [
                'message' => 'logout success'
            ],
        ]);
    }
}
