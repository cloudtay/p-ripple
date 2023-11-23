<?php

namespace Worker\Built\JsonRpc;

/**
 * Class JsonRpc
 * 该类不序列化,JsonRpc本身就是序列规则
 */
class JsonRpcBuild
{
    public string $version = '2.0';
    public string $method;
    public array  $params  = [];
    public int    $id;
    public mixed  $result;

    /**
     * @param int    $id
     * @param string $method
     * @param array  $params
     */
    public function __construct(int $id, string $method, mixed ...$params)
    {
        $this->id     = $id;
        $this->method = $method;
        $this->params = $params;
    }

    /**
     * @param int    $id
     * @param string $method
     * @param array  $params
     * @return JsonRpcBuild
     */
    public static function create(int $id, string $method, mixed ...$params): JsonRpcBuild
    {
        return new static($id, $method, ...$params);
    }

    /**
     * @param string $json
     * @return JsonRpcBuild|null
     */
    public static function parse(string $json): JsonRpcBuild|null
    {
        if (!$data = json_decode($json, true)) {
            return null;
        }
        return new JsonRpcBuild($data['id'], $data['method'], $data['params']);
    }

    /**
     * @return string
     */
    public function __toString(): string
    {
        return $this->build();
    }

    /**
     * @return string
     */
    public function build(): string
    {
        return json_encode([
            'version' => $this->version,
            'method'  => $this->method,
            'params'  => $this->params,
            'id'      => $this->id
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param mixed $result
     * @return string
     */
    public function result(mixed $result): string
    {
        $this->result = [
            'code'   => 0,
            'result' => $result
        ];
        return json_encode([
            'version' => $this->version,
            'result'  => $this->result,
            'id'      => $this->id
        ], JSON_UNESCAPED_UNICODE);
    }

    /**
     * @param int    $code
     * @param string $message
     * @return $this
     */
    public function error(int $code, string $message): string
    {
        $this->result = ['code' => $code, 'message' => $message];
        return json_encode([
            'version' => $this->version,
            'error'   => $this->result,
            'id'      => $this->id
        ], JSON_UNESCAPED_UNICODE);
    }
}
