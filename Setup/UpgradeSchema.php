<?php
namespace PayPalBR\PayPal\Setup;

use Magento\Customer\Model\Customer;
use Magento\Customer\Setup\CustomerSetup;
use Magento\Customer\Setup\CustomerSetupFactory;
use Magento\Eav\Model\Entity\Attribute\Backend\ArrayBackend;
use Magento\Framework\Setup\ModuleContextInterface;
use Magento\Framework\Setup\ModuleDataSetupInterface;
use Magento\Framework\Setup\UpgradeDataInterface;
use Magento\Framework\Filesystem\Driver\File;
use Magento\Framework\Filesystem;

class UpgradeData implements UpgradeDataInterface
{

    /**
     * @var CustomerSetupFactory
     */
    private $customerSetupFactory;

    /**
     * @var file
     */
    private $file;

    /**
     * @var fileSystem
     */
    protected $fileSystem;

    public function __construct(
        CustomerSetupFactory $customerSetupFactory,
        File $file,
        Filesystem $fileSystem
    )
    {
        $this->customerSetupFactory = $customerSetupFactory;
        $this->file = $file;
        $this->fileSystem = $fileSystem;
    }

    /**
     * Upgrades data for a module
     *
     * @param ModuleDataSetupInterface $setup
     * @param ModuleContextInterface $context
     * @return void
     */
    public function upgrade(
        ModuleDataSetupInterface $setup, 
        ModuleContextInterface $context
    ){
        $dbVersion = $context->getVersion();

        $this->createDir();

        if (version_compare($context->getVersion(), "0.2.10", "<")) {
            $setup = $this->updateVersionZeroTwoTen($setup);
        }

        if (version_compare($dbVersion, '0.3.4', '<')) {
            $setup = $this->updateVersionZeroTreeFour($setup);
        }
    }

    protected function createDir()
    {
        $varDir = $this->fileSystem->getDirectoryRead(\Magento\Framework\App\Filesystem\DirectoryList::LOG);
        $varRootDir = $varDir->getAbsolutePath();
        $paypalDir = $varRootDir . 'paypalbr/';

        if (!$this->file->isDirectory($paypalDir)) {
            $this->file->createDirectory($paypalDir, $permissions = 0755);
        }

        $paypalPlusDir = $varRootDir . 'paypalbr/paypal_plus/';

        if (!$this->file->isDirectory($paypalPlusDir)) {
            $this->file->createDirectory($paypalPlusDir, $permissions = 0755);
        }

        $paypalExpressCheckoutDir = $varRootDir . 'paypalbr/paypal_expresscheckout/';

        if (!$this->file->isDirectory($paypalExpressCheckoutDir)) {
            $this->file->createDirectory($paypalExpressCheckoutDir, $permissions = 0755);
        }

        $paypalLoginDir = $varRootDir . 'paypalbr/paypal_login/';

        if (!$this->file->isDirectory($paypalLoginDir)) {
            $this->file->createDirectory($paypalLoginDir, $permissions = 0755);
        }

        $paypalWebhookDir = $varRootDir . 'paypalbr/webhook/';

        if (!$this->file->isDirectory($paypalWebhookDir)) {
            $this->file->createDirectory($paypalWebhookDir, $permissions = 0755);
        }
    }

    protected function updateVersionZeroTwoTen($setup)
    {
        $tableName = $setup->getTable('sales_order_status_state');

        if ($setup->getConnection()->isTableExists($tableName) == true) {
            $connection = $setup->getConnection();
            $where = ['state = ?' => 'pending_payment'];
            $data = ['visible_on_front' => 1];
            $connection->update($tableName, $data, $where);
        }

        return $setup;
    }

    protected function updateVersionZeroTreeFour($setup)
    {
        $setup->startSetup();

        $tableName = $setup->getTable('sales_order_status_state');
        $customerSetup = $this->customerSetupFactory->create(['setup' => $setup]);
        $attributeCode = 'remembered_card';
        $customerSetup->removeAttribute(\Magento\Customer\Model\Customer::ENTITY, $attributeCode);
        $customerSetup->addAttribute(
            'customer',
            'remembered_card', 
            [
                'label' => 'Remembered Card',
                'type' => 'varchar',
                'input' => 'text',
                'required' => false,
                'visible' => true,
                'system'=> false,
                'position' => 200,
                'sort_order' => 200,
                'user_defined' => false,
                'default' => '0',
            ]
        );

        $eavConfig = $customerSetup->getEavConfig()->getAttribute('customer', 'remembered_card');
        $eavConfig->setData('used_in_forms',['adminhtml_customer']);
        $eavConfig->save();
        $setup->endSetup();

        return $setup;
    }
}