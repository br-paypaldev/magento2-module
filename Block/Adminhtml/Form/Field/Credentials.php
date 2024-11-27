<?php

namespace PayPalBR\PayPal\Block\Adminhtml\Form\Field;

use Magento\Backend\Block\Template\Context;
use PayPalBR\PayPal\Model\PayPalRequests;

class Credentials extends \Magento\Config\Block\System\Config\Form\Field
{
    /**
     * Path to block template
     */
    const WIZARD_TEMPLATE = 'PayPalBR_PayPal::system/config/credentials.phtml';

    protected $payPalRequests;

    public function __construct(
        Context $context,
        PayPalRequests $payPalRequests,
        array $data = []
    ) {
        parent::__construct($context, $data);

        $this->payPalRequests = $payPalRequests;
    }

    /**
     * Set template to itself
     *
     * @return $this
     */
    protected function _prepareLayout()
    {
        parent::_prepareLayout();
        if (!$this->getTemplate()) {
            $this->setTemplate(static::WIZARD_TEMPLATE);
        }
        return $this;
    }

    /**
     * Unset some non-related element parameters
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return string
     */
    public function render(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        return parent::render($element);
    }
    /**
     * Get the button and scripts contents
     *
     * @param \Magento\Framework\Data\Form\Element\AbstractElement $element
     * @return string
     */
    protected function _getElementHtml(\Magento\Framework\Data\Form\Element\AbstractElement $element)
    {
        $clientId = "AV92z45HAgFhhAmMcMEqaSZERahKwepNQbj5yQZ_49euaohM38e_kkwehjXwRHHFa7m2jjlTeAG7PiML";
        $secretId = "EL3XqIdaOxm5AOW_eJeIDJxOjrdiJ4_qwbkk_xG0awyaGaMX8xUQ6gYrZCP7lkNpPDwchkTn3e4UVP1G";

        $this->addData(
            [
                'redirect_url' => $this->payPalRequests->getCredentialsUrl(
                    [
                        "authorization" => base64_encode($clientId . ':' . $secretId),
                        "type" => "id"
                    ]
                )
            ]
        );
        return $this->_toHtml();
    }
}
