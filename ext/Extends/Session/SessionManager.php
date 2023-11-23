<?php

namespace recycle\Extends\Session;

use RuntimeException;

/**
 * Class Session
 */
class SessionManager
{
    private string $filePath;

    /**
     * SessionManager constructor.
     * @param string $filePath
     */
    public function __construct(string $filePath)
    {
        if (!is_dir($filePath)) {
            mkdir($filePath, 0755, true);
        }
        if (!is_dir($filePath)) {
            throw new RuntimeException('Session directory does not exist: ' . $filePath);
        }
        $this->filePath = $filePath;
    }

    /**
     * 通过Key构建Session
     * @param string $key
     * @return Session
     */
    public function buildSession(string $key): Session
    {
        $sessionFile = "{$this->filePath}/session_{$key}";
        if (file_exists($sessionFile)) {
            $session = unserialize(file_get_contents($sessionFile));
            if ($session instanceof Session) {
                if ($session->expire > 0 && $session->startTime + $session->expire < time()) {
                    unlink($sessionFile);
                    return new Session($key, $this);
                }
                return $session;
            } else {
                unlink($sessionFile);
            }
        }
        return new Session($key, $this);
    }

    /**
     * 保存Session
     * @param Session $session
     * @return void
     */
    public function save(Session $session): void
    {
        file_put_contents("{$this->filePath}/session_{$session->key}", $session->serialize());
    }
}
