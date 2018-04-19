<?php
namespace PayPalBR\PayPal\Model;
use PayPal\Common\PayPalModel;

/**
 * Class RedirectUrls
 *
 * Set the ApplicationContext
 *
 * @package PayPalBR\PayPal\Model
 *
 * @property string applicationContext
 */

class PayPalApplicationContextModel extends PayPalModel {


    public function setApplicationContext($applicationContext) {
        $this->applicationContext = $applicationContext;
        return $this;
    }

    public function getApplicationContext() {
        return $this->applicationContext;
    }

}
