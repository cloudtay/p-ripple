<?php
declare(strict_types=1);

namespace Cclilshy\PRipple\App\Http;

use Cclilshy\PRipple\Build;
use Cclilshy\PRipple\Service\Client;
use Cclilshy\PRipple\Std\TaskStd;

class Request extends TaskStd
{
    public const EVENT_UPLOAD = 'http.upload.complete';

    public string $host;
    public string $scheme;
    public string $url;
    public string $method;
    public bool $upload;
    public array $files = array();
    public string $path;
    public string $version;
    public string $header;
    public string $body;
    public array $headerArray = array();
    public array $post = array();
    public array $query = array();
    public Client $client;

    /**
     * @var callable $onUpload
     */
    private $asyncHandlers = array();

    /**
     * @param RequestSingle $requestSingle
     */
    public function __construct(RequestSingle $requestSingle)
    {
        $this->url = $requestSingle->url;
        $this->method = $requestSingle->method;
        if (($this->upload = $requestSingle->upload)) {
            $this->files = $requestSingle->uploadHandler->files;
        }
        $this->version = $requestSingle->version;
        $this->header = $requestSingle->header;
        $this->headerArray = $requestSingle->headers;
        $this->body = $requestSingle->body;
        $this->client = $requestSingle->client;

        $info = parse_url($this->url);
        if ($query = $info['query'] ?? null) {
            parse_str($query, $this->query);
        }
        $this->path = $info['path'];
        $this->host = $info['host'] ?? '';
        $this->scheme = $info['scheme'] ?? '';
        if (isset($this->headerArray['Content-Type']) && $this->headerArray['Content-Type'] === 'application/json') {
            $this->post = json_decode($this->body, true);
        } else {
            parse_str($this->body, $this->post);
        }
        parent::__construct();
    }

    /**
     * @param string $action
     * @param callable $callable
     * @return void
     */
    public function async(string $action, callable $callable): void
    {
        $this->asyncHandlers[$action] = $callable;
    }

    /**
     * @param Build $event
     * @return void
     */
    protected function handleEvent(Build $event): void
    {
        if ($uploadHandler = $this->asyncHandlers[$event->name]) {
            call_user_func_array($uploadHandler, [$event->data]);
        }
    }
}
