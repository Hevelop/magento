<?php
class Riskified_Full_ResponseController extends Mage_Core_Controller_Front_Action
{
    public function getresponseAction()
    {
        $orderId = $_REQUEST['id'];
        $status = $_REQUEST['status'];
        Mage::log("Processing notification for id : $orderId, status : $status");
        if (empty($orderId) && empty($status)) {
          Mage::app()->getResponse()->setRedirect(Mage::getBaseUrl());
          Mage::app()->getResponse()->sendResponse();
          exit;
        }
        
        //generating local hash
        $data['status'] = $status;
        $data_string = 'id='.$orderId.'&status='.$status;
        $s_key = Mage::getStoreConfig('fullsection/full/key',Mage::app()->getStore());
        $localHash = hash_hmac('sha256', $data_string, $s_key);
            
        //generating hash 
        $headers = getallheaders();
        $riskiHash = $headers['X-Riskified-Hmac-Sha256'];
        
        if ($localHash != $riskiHash) {
          Mage::log("Hashes mismatch localHash : $localHash, riskiHash : $riskiHash");
          Mage::app()->getResponse()->setRedirect(Mage::getBaseUrl());
          Mage::app()->getResponse()->sendResponse();
          exit;
        }
        
           
        $observer = Mage::getModel('full/observer');
        $riskified_result = $observer->mapStatus($status);
        $order = Mage::getModel('sales/order');
        $status_control_active = Mage::helper('full')->getConfigStatusControlActive();
        if ($status_control_active){
          $order_model = $order->load($orderId);
          $order_model->setState($riskified_result["state"],$riskified_result["mage_status"], $riskified_result["comment"]);
          $order_model->save();
        }else{
          Mage::log("Ignoring notification status_control_active : $status_control_active");
        }
    }
}
    