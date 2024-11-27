<?php

namespace PayPalBR\PayPal\Logger;

use Magento\Framework\Filesystem\DriverInterface;
use Magento\Framework\Logger\Handler\Base;
use Monolog\Logger;

class Handler extends Base
{
    /**
     * @var int
     */
    protected $loggerType = Logger::INFO;

    /**
     * @var string
     */
    protected $fileName;

    /**
     * @var string
     */
    protected $filePath;

    /**
     * @var DriverInterface
     */
    protected $filesystem;

    /**
     * @param $fileName
     */
    public function setFileName($fileName)
    {
        $this->fileName = $fileName;
        $this->filePath = '/var/log/' . $this->fileName . '.log';
        parent::__construct(
            $this->filesystem,
            null,
            $this->filePath
        );
    }
}
