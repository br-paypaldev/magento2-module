<?php
namespace PayPalBR\PayPal\Cron;

use Psr\Log\LoggerInterface;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Filesystem;

class Clean 
{
    protected $logger;
    protected $file;
    protected $fileSystem;

    public function __construct(
        LoggerInterface $logger,
        File $file,
        Filesystem $fileSystem
    ) {
        $this->logger = $logger;
        $this->file = $file;
        $this->fileSystem = $fileSystem;
    }

    /**
    * Write to system.log
    *
    * @return void
    */

    public function execute() 
    {
        $this->logger->info('Cron Remove Files PayPal Initial');
        $varDir = $this->fileSystem->getDirectoryRead(\Magento\Framework\App\Filesystem\DirectoryList::LOG);
        $varRootDir = $varDir->getAbsolutePath();
        $paypalDir = $varRootDir . 'paypalbr/';

        if (!$this->file->isDirectory($paypalDir)) {
            $this->file->createDirectory($paypalDir, $permissions = 0755);
        }

        $info = getdate();
        $date = $info['mday'];
        $month = $info['mon'];
        $year = $info['year'];
        $from_unix_time = mktime(0, 0, 0, $month, $date, $year);
        $day_before = strtotime("-60 days", $from_unix_time);
        $formattedLastDays = date('Y-m-d', $day_before);

        $SalesOrderPlaceAfter = 'paypal-SalesOrderPlaceAfter-'.$formattedLastDays.'.log';
        $this->deletedFiles($SalesOrderPlaceAfter, $paypalDir);
        
        $paypalplus = 'paypal_plus/paypalplus-'.$formattedLastDays.'.log';
        $this->deletedFiles($paypalplus, $paypalDir);

        $paypalExpressCheckout = 'paypal_expresscheckout/paypal-express-checkout-'.$formattedLastDays.'.log';
        $this->deletedFiles($paypalExpressCheckout, $paypalDir);

        $paypalLogin = 'paypal_login/paypal-login-'.$formattedLastDays.'.log';
        $this->deletedFiles($paypalLogin, $paypalDir);

        $paypalplusWebhook = 'webhook/paypal-webhook-'.$formattedLastDays.'.log';
        $this->deletedFiles($paypalplusWebhook, $paypalDir);

        $paypalplusRefund = 'paypalplus-refund-'.$formattedLastDays.'.log';
        $this->deletedFiles($paypalplusRefund, $paypalDir);

        $paypalconfig = 'paypalconfig-'.$formattedLastDays.'.log';
        $this->deletedFiles($paypalconfig, $paypalDir);
        

        $this->logger->info('Cron Remove Files PayPal End');
    }

    public function deletedFiles($fileName, $paypalDir)
    {
        try {
            if ($this->file->isExists($paypalDir . $fileName)) {
                $this->file->deleteFile($paypalDir . $fileName);
                $this->logger->info('deleted' . $fileName);
            }
        } catch (Exception $e) {
            $this->logger->info('error' . $e);
        }

        return $this;
    }

}