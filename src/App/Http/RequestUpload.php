<?php
declare(strict_types=1);

namespace App\Http;

use Core\Map\EventMap;
use Core\Output;
use Worker\Build;

/**
 * Http上传解析器
 */
class RequestUpload
{
    public const STATUS_ILLEGAL = -1; # 非法
    public const STATUS_WAIT = 0;     # 等待
    public const STATUS_TRAN = 1;     # 传输中

    /**
     * @var array
     */
    public array $files = array();

    /**
     * @var string
     */
    protected string $currentTransferFilePath;

    /**
     * @var mixed
     */
    protected mixed $currentTransferFile;

    /**
     * @var int
     */
    protected int $status;

    /**
     * @var string
     */
    protected string $buffer = '';

    /**
     * @var string
     */
    protected string $boundary;


    /**
     * @var RequestSingle
     */
    protected RequestSingle $requestSingle;

    /**
     * @param RequestSingle $requestSingle
     * @param string $boundary
     */
    public function __construct(RequestSingle $requestSingle, string $boundary)
    {
        $this->boundary = $boundary;
        $this->status = RequestUpload::STATUS_WAIT;
        $this->requestSingle = $requestSingle;
    }

    /**
     * 上下文推入
     * @param string $context
     * @return void
     */
    public function push(string $context): void
    {
        $this->buffer .= $context;
        while ($this->buffer !== '' && $this->status !== RequestUpload::STATUS_ILLEGAL) {
            try {
                if ($this->status === RequestUpload::STATUS_WAIT && !$this->parseFileInfo()) {
                    break;
                }
                if (!$this->processTransmitting()) {
                    break;
                }
            } catch (RequestSingleException $exception) {
                Output::printException($exception);
                $this->status = RequestUpload::STATUS_ILLEGAL;
                $this->requestSingle->statusCode = RequestFactory::INVALID;
            }
        }
    }

    /**
     * 解析文件信息
     * @return bool
     * @throws RequestSingleException
     */
    private function parseFileInfo(): bool
    {
        $headerEndPosition = strpos($this->buffer, "\r\n\r\n");
        if ($headerEndPosition === false) {
            return false;
        }

        $header = substr($this->buffer, 0, $headerEndPosition);
        $lines = explode("\r\n", $header);
        $boundaryLine = array_shift($lines);
        if ($boundaryLine !== '--' . $this->boundary) {
            return false;
        }
        $this->buffer = substr($this->buffer, $headerEndPosition + 4);
        $fileInfo = array();
        foreach ($lines as $line) {
            if (preg_match('/^Content-Disposition: form-data; name="([^"]+)"; filename="([^"]+)"$/i', $line, $matches)) {
                $fileInfo['name'] = $matches[1];
                $fileInfo['fileName'] = $matches[2];
            } elseif (preg_match('/^Content-Type: (.+)$/i', $line, $matches)) {
                $fileInfo['contentType'] = $matches[1];
            }
        }

        if (empty($fileInfo['name']) || empty($fileInfo['fileName'])) {
            throw new RequestSingleException('file name is empty');
        }

        $fileInfo['path'] = $this->createNewFile();
        $this->files[] = $fileInfo;
        $this->status = RequestUpload::STATUS_TRAN;
        return true;
    }

    /**
     * 创建新文件
     * @return string
     */
    private function createNewFile(): string
    {
        $this->currentTransferFilePath = HttpWorker::$uploadPath . FS . md5(strval(microtime(true)));
        $this->currentTransferFile = fopen($this->currentTransferFilePath, 'wb+');
        return $this->currentTransferFilePath;
    }

    /**
     * 处理传输中
     * @return bool
     */
    private function processTransmitting(): bool
    {
        $boundaryPosition = strpos($this->buffer, "\r\n--" . $this->boundary);
        if ($boundaryPosition !== false) {
            $remainingData = substr($this->buffer, $boundaryPosition + 2);
            $nextBoundaryPosition = strpos($remainingData, "\r\n--" . $this->boundary);
            if ($nextBoundaryPosition === false && !str_starts_with($remainingData, '--')) {
                return false;
            }
            $content = substr($this->buffer, 0, $boundaryPosition);
            $this->buffer = $remainingData;
            fwrite($this->currentTransferFile, $content);
            fclose($this->currentTransferFile);
            $this->status = RequestUpload::STATUS_WAIT;
            EventMap::push(Build::new(Request::EVENT_UPLOAD, current($this->files), $this->requestSingle->hash));
        } else {
            fwrite($this->currentTransferFile, $this->buffer);
            $this->buffer = '';
        }
        return true;
    }
}
