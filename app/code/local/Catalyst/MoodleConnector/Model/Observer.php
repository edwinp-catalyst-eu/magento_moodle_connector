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
* @category  Catalyst
* @package   Catalyst_MoodleConnector
* @author    Edwin Phillips <edwin.phillips@catalyst-eu.net>
* @copyright Copyright (c) 2014-16 Catalyst IT (http://catalyst-eu.net)
* @license   http://opensource.org/licenses/osl-3.0.php Open Software License (OSL 3.0)
*/
class Catalyst_MoodleConnector_Model_Observer
{
    public function __construct()
    {
    }

    /**
    * Calls Moodle webservice if order contains any Moodle courses
    *
    * @param Varien_Event_Observer $observer
    */
    public function updateMoodle(Varien_Event_Observer $observer)
    {

        $moodle_courses = array();
        $order_id = $observer->getEvent()->getOrder()->getId();
        $order = Mage::getModel('sales/order')->load($order_id);
        $ordered_items = $order->getAllItems();

        foreach ($ordered_items as $item) {
            $product_id = $item->getProductId();
            $products = Mage::getModel('catalog/product')
                    ->getCollection()
                    ->addAttributeToSelect('moodle_id')
                    ->addAttributeToSelect('moodle_instance')
                    ->addIdFilter($product_id);
            foreach($products as $product) {
                if ($product->moodle_id) {
                    $moodle_courses[] = array(
                        'course_id' => $product->moodle_id,
                        'moodle_instance' => $product->getAttributeText('moodle_instance')
                    );
                }
            }
        }

        Mage::log($moodle_courses);

        if ($moodle_courses &&
                (Mage::getStoreConfig('moodleconnector/moodle1settings/enabled')
                    || Mage::getStoreConfig('moodleconnector/moodle2settings/enabled'))) {
            $order_number = $order->getIncrementId();
            $customer = array();
            $customer['firstname'] = $order->getCustomerFirstname();
            $customer['lastname']  = $order->getCustomerLastname();
            $customer['email']     = $order->getCustomerEmail();
            $customer['city']      = $order->getShippingAddress()->getCity();
            $customer['country']   = $order->getShippingAddress()->getCountry();
        }

        $moodle1instance = array();
        if ($moodle_courses && Mage::getStoreConfig('moodleconnector/moodle1settings/enabled')) {
            foreach ($moodle_courses as $moodle_course) {
                if  ($moodle_course['moodle_instance'] == 'Instance1') {
                    $moodle1courses[] = array(
                        'course_id' => $moodle_course['course_id']
                    );
                }
            }
            Mage::log($moodle1courses);
            $baseurl1 = Mage::getStoreConfig('moodleconnector/moodle1settings/baseurl');
            $token1   = Mage::getStoreConfig('moodleconnector/moodle1settings/token');
            $url1     = "{$baseurl1}/webservice/xmlrpc/server.php?wstoken={$token1}";
            $data1    = xmlrpc_encode_request('local_magentoconnector_process_request',
                    array($order_number, $customer, $moodle1courses));
            $curl1 = new Varien_Http_Adapter_Curl();
            $curl1->setConfig(array('timeout' => 30, 'header' => false));
            $curl1->write(Zend_Http_Client::POST, $url1, CURL_HTTP_VERSION_1_1, array(), $data1);
            //$response = xmlrpc_decode($curl1->read());
            $status1 = $curl1->getInfo(CURLINFO_HTTP_CODE);
            $curl1->close();
            //if (($status1 != 200) || ($response != 1)) {
            if ($status1 != 200) {
                $result1 = array();
                $result1['success'] = false;
                $result1['error'] = true;
                $result1['error_messages'] = __('Moodle server (Instance 1) did not successfully process your request.');
                echo Mage::helper('core')->jsonEncode($result1);
                die;
            }
            
        }

        $moodle2instance = array();
        if ($moodle_courses && Mage::getStoreConfig('moodleconnector/moodle2settings/enabled')) {
            foreach ($moodle_courses as $moodle_course) {
                if ($moodle_course['moodle_instance'] == 'Instance2') {
                    $moodle2courses[] = array(
                        'course_id' => $moodle_course['course_id']
                    );
                }
            }
            Mage::log($moodle2courses);
            $baseurl2 = Mage::getStoreConfig('moodleconnector/moodle2settings/baseurl');
            $token2   = Mage::getStoreConfig('moodleconnector/moodle2settings/token');
            $url2     = "{$baseurl2}/webservice/xmlrpc/server.php?wstoken={$token2}";
            $data2    = xmlrpc_encode_request('local_magentoconnector_process_request',
                    array($order_number, $customer, $moodle2courses));
            $curl2 = new Varien_Http_Adapter_Curl();
            $curl2->setConfig(array('timeout' => 30, 'header' => false));
            $curl2->write(Zend_Http_Client::POST, $url2, CURL_HTTP_VERSION_1_1, array(), $data2);
            //$response = xmlrpc_decode($curl1->read());
            $status2 = $curl2->getInfo(CURLINFO_HTTP_CODE);
            $curl2->close();
            //if (($status1 != 200) || ($response != 1)) {
            if ($status2 != 200) {
                $result2 = array();
                $result2['success'] = false;
                $result2['error'] = true;
                $result2['error_messages'] = __('Moodle server (Instance 2) did not successfully process your request.');
                echo Mage::helper('core')->jsonEncode($result2);
                die;
            }
        }
    }
}
