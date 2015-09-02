<?php
/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * DISCLAIMER
 *
 * Do not edit or add to this file if you wish to upgrade Magento to newer
 * versions in the future. If you wish to customize Magento for your
 * needs please refer to http://www.magentocommerce.com for more information.
 *
 * @category  design
 * @package   adminhtml_default_default
 * @author    Nosto Solutions Ltd <magento@nosto.com>
 * @copyright Copyright (c) 2013-2015 Nosto Solutions Ltd (http://www.nosto.com)
 * @license   http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */

/**
 * Info block to show the current configured currency formats for the viewed
 * store scope on the system config page.
 *
 * @category Nosto
 * @package  Nosto_Tagging
 * @author   Nosto Solutions Ltd <magento@nosto.com>
 */
class Nosto_Tagging_Block_Adminhtml_System_Config_Account_Currency_Formats extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    /**
     * @inheritdoc
     */
    protected function _construct()
    {
        parent::_construct();
        $this->setTemplate('nostotagging/system/config/account/currency/formats.phtml');
    }

    /**
     * @inheritdoc
     */
    protected function _getElementHtml(Varien_Data_Form_Element_Abstract $element)
    {
        return $this->_toHtml();
    }

    /**
     * Returns a list of Zend_Currency objects configured for the current stores locale.
     * These can be used to format the price string in the view file.
     *
     * @return Zend_Currency[] the currency objects.
     */
    public function getCurrencyFormats()
    {
        $formats = array();
        $storeId = $this->getRequest()->getParam('store');
        $store = Mage::app()->getStore($storeId);
        $currencyCodes = $store->getAvailableCurrencyCodes(true);
        if (is_array($currencyCodes) && count($currencyCodes) > 0) {
            $locale = $store->getConfig('general/locale/code');
            foreach ($currencyCodes as $currencyCode) {
                try {
                    $formats[] = new Zend_Currency($currencyCode, $locale);
                } catch (Zend_Exception $e) {
                    continue;
                }
            }
        }
        return $formats;
    }
}
