<?php

namespace recycle\Extends\Session;

class Session
{
    public string         $key;
    public array          $data      = [];
    public bool           $isChanged = false;
    public int            $startTime = 0;
    public int            $expire    = 0;
    public SessionManager $sessionManager;

    /**
     * @param string         $key
     * @param SessionManager $sessionManager
     */
    public function __construct(string $key, SessionManager $sessionManager)
    {
        $this->key            = $key;
        $this->sessionManager = $sessionManager;
        $this->startTime      = time();
    }

    /**
     *
     */
    public function __destruct()
    {
        $this->startTime = time();
        $this->save();
    }

    /**
     * 保存自身
     * @return void
     */
    public function save(): void
    {
        $this->sessionManager->save($this);
    }

    /**
     * 被反序列化后
     */
    public function __wakeup()
    {
        $this->isChanged = false;
    }

    /**
     * 设置一个键值
     * @param string $key
     * @param mixed  $value
     * @return void
     */
    public function set(string $key, mixed $value): void
    {
        $this->data[$key] = $value;
        $this->isChanged  = true;
    }

    /**
     * 获取一个键值
     * @param string $key
     * @param mixed  $default
     * @return mixed|null
     */
    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data[$key] ?? $default;
    }

    /**
     * 序列化自身
     * @return string
     */
    public function serialize(): string
    {
        return serialize($this);
    }

}
