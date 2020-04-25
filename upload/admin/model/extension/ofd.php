<?php
define('OFD_TABLE_NAME','ofd_checks_list');
define('OFD_API_URL','https://ferma.ofd.ru/api/kkt/cloud');
define('OFD_AUTH_URL','https://ferma.ofd.ru/api/Authorization/CreateAuthToken');

class ModelExtensionOfd extends Model
{
    private function ofdGetOption($option)
    {
        return $this->config->get($option);
    }
    
    public function echoError($message)
    {
        $message = $this->language->get('text_error_ofd').$message;
        $this->session->data['error_warning_ofd'] = $message;
    }
    
     public function echoSuccess($message)
    {
        $message = $this->language->get('text_ofd').$message;
        $this->session->data['success_ofd'] = $message;
    }   
    
    private function checkSettings()
    {
        return (
            $this->ofdGetOption('module_ofd_client_login')&&
            $this->ofdGetOption('module_ofd_client_pass')&&
            $this->ofdGetOption('module_ofd_nalog')&&
            $this->ofdGetOption('module_ofd_inn')&&
            $this->ofdGetOption('module_ofd_nds')
            );
    }    
    
    private function checkToken()
    {
        if($this->ofdGetOption('module_ofd_token')&&($this->ofdGetOption('module_ofd_token_exp_date')>(time()-10))) {
            return $this->ofdGetOption('module_ofd_token');
        } else {
            return false;
        }
    }    

    public function setAuthToken()
    {
        if(!$this->checkSettings()) {
            $this->echoError($this->language->get('text_req_settings'));
            return false;
        };
        if($this->checkToken()) {
            return true;
        }
        $data = array(
                    "Login"         => $this->ofdGetOption('module_ofd_client_login'),
                    "Password"     => $this->ofdGetOption('module_ofd_client_pass'),
                );    
        $options = $this->getHTTPOpt($data);
        $context = stream_context_create($options);    
        set_error_handler(array($this, 'customErrorHandler'));
        try {    
            $result = file_get_contents(OFD_AUTH_URL, false, $context);
        } catch(Exception $e) {
            $this->echoError($e->getMessage());
            return false;
        }
        restore_error_handler();
        $result = json_decode($result);
        if(isset($result->Status)&&($result->Status=='Success')) {
            $this->load->model('setting/setting');
            $this->model_setting_setting->editSettingValue('module_ofd', 'module_ofd_token',$result->Data->AuthToken);
            $this->model_setting_setting->editSettingValue('module_ofd', 'module_ofd_token_exp_date', strtotime($result->Data->ExpirationDateUtc));
            $this->config->set('module_ofd_token',$result->Data->AuthToken);
            $this->config->set('module_ofd_token_exp_date',strtotime($result->Data->ExpirationDateUtc));
            return true;
        } else if(isset($result->Status)&&($result->Status=='Failed')) {
            $this->echoError($result->Error->Message);
            return false;
        } else {
            $this->echoError($this->language->get('text_some_error'));
            return false;
        }
    }

    private function getHTTPOpt($data)
    {
        $options = array(
                    "ssl"=>array(
                        "verify_peer"=>false,
                        "verify_peer_name"=>false,
                    ),
                    'http' => array(
                            'timeout' => 10,
                            'ignore_errors' => true,
                            'content' => json_encode($data),
                            'header'  => "Content-type: application/json\r\n".
                                         "Accept: application/json"."\r\n",
                                         "Content-Length: ".strlen(json_encode($data))."\r\n",
                            'method'  => 'POST',
                            )
            );    
        return $options;
    }

    public function customErrorHandler($errno, $errstr, $errfile, $errline, array $errcontext)
    {
        if (0 === error_reporting()) {
            return false;
        }
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    }

    private function sendDataToOFD($data)
    {
        if($this->setAuthToken()) {
            $options = $this->getHTTPOpt($data);
            $context = stream_context_create($options);        
            set_error_handler(array($this, 'customErrorHandler'));
            try {    
                $result = file_get_contents(OFD_API_URL."/receipt?AuthToken=".$this->ofdGetOption('module_ofd_token'), false, $context);
            } catch(Exception $e) {
                $this->echoError($e->getMessage());
                return false;
            }
            restore_error_handler();
            $result = json_decode($result);
            if(isset($result->Status)&&($result->Status=='Success')) {
                return $result->Data->ReceiptId;
            } else if(isset($result->Status)&&($result->Status=='Failed')) {
                $this->echoError($result->Error->Message);
                return false;
            } else {
                $this->echoError($this->language->get('text_some_error'));
                return false;
            }
        }
    }
    public function getCountChecks() 
    {
        $r = $this->db->query("SELECT COUNT(*) as `count` FROM `".DB_PREFIX.OFD_TABLE_NAME."` WHERE 1");
        return $r->row['count'];
    }

    public function getCountPendingChecks() 
    {
        $r = $this->db->query("SELECT COUNT(*) as `count` FROM `".DB_PREFIX.OFD_TABLE_NAME."` WHERE `status` IS NULL OR `status` <> 'CONFIRMED'");
        return $r->row['count'];
    }

    private function UpdateOldCheckInDB($check_id,$data)
    {
        $this->db->query(" 
            UPDATE `".DB_PREFIX.OFD_TABLE_NAME."`
            SET
                `status` = '".$data->StatusName."',
                `status_message` = '".$data->StatusMessage."',
                `updated_at` = NOW()
            WHERE `id` = '".$check_id."'
        ");
    }

    private function UpdateNewCheckInDB($check_id,$data)
    {
        $this->db->query(" 
            UPDATE `".DB_PREFIX.OFD_TABLE_NAME."`
            SET
                `status` = '".$data->StatusName."',
                `status_message` = '".$data->StatusMessage."',
                `FN` = '".$data->Device->FN."',
                `RNM` = '".$data->Device->RNM."',
                `FDN` = '".$data->Device->FDN."',
                `FPD` = '".$data->Device->FPD."',
                `updated_at` = NOW()
            WHERE `id` = '".$check_id."'
        ");
    }

    private function UpdateFailedCheckInDB($check_id)
    {
        //do nothing
    }    

    public function UpdateCheckStatus($check_id)
    {
        $check_id = $this->db->escape($check_id);
        $data = array();
        $data['Request']['ReceiptId'] = $check_id;
        if($data_ins = $this->UpdateNewCheckStatus($data)) {
            $this->UpdateNewCheckInDB($check_id,$data_ins);
            return true;
        } elseif($data_ins = $this->UpdateOldCheckStatus($data)) {
            $this->UpdateOldCheckInDB($check_id,$data_ins);
            return true;
        } else {
            $this->UpdateFailedCheckInDB($check_id);
            return false;
        }
    }

    public function UpdateChecksStatus()
    {
        $results = $this->db->query("SELECT * FROM `".DB_PREFIX.OFD_TABLE_NAME."` WHERE `status` IS NULL OR `status` <> 'CONFIRMED' OR `status` <> 'FAILED'");        
        foreach($results->rows as $result) {
            $data = array();
            $data['Request']['ReceiptId'] = $result['id'];
            if($data_ins = $this->UpdateNewCheckStatus($data)) {
                $this->UpdateNewCheckInDB($result['id'],$data_ins);
            } elseif($data_ins = $this->UpdateOldCheckStatus($data)) {
                $this->UpdateOldCheckInDB($result['id'],$data_ins);
            } else {
                $this->UpdateFailedCheckInDB($result['id']);
            }
        }    
    }

    private function UpdateOldCheckStatus($data)
    {
        if($this->setAuthToken()) {
            $options = $this->getHTTPOpt($data);
            $context = stream_context_create($options);        
            set_error_handler(array($this, 'customErrorHandler'));
            try {    
                $result = file_get_contents(OFD_API_URL."/list?AuthToken=".$this->ofdGetOption('module_ofd_token'), false, $context);
            } catch(Exception $e) {
                $this->echoError($e->getMessage());
                return false;
            }
            restore_error_handler();
            $result = json_decode($result);
            if(isset($result->Status)&&($result->Status=='Success')) {
                return $result->Data->ReceiptId;
            } else if(isset($result->Status)&&($result->Status=='Failed')) {
                $this->echoError($result->Error->Message);
                return false;
            } else {
                $this->echoError($this->language->get('text_some_error'));
                return false;
            }
            
        }
    }

    private function UpdateNewCheckStatus($data)
    {
        if($this->setAuthToken()) {
            $options = $this->getHTTPOpt($data);
            $context = stream_context_create($options);        
            set_error_handler(array($this, 'customErrorHandler'));
            try {    
                $result = file_get_contents(OFD_API_URL."/status?AuthToken=".$this->ofdGetOption('module_ofd_token'), false, $context);
            } catch(Exception $e) {
                $this->echoError($e->getMessage());
                return false;
            }
            restore_error_handler();
            $result = json_decode($result);
            if(isset($result->Status)&&($result->Status=='Success')) {
                return $result->Data;
            } else if(isset($result->Status)&&($result->Status=='Failed')) {
                $this->echoError($result->Error->Message);
                return false;
            } else {
                $this->echoError($this->language->get('text_some_error'));
                return false;
            }
        }
    }

    private function saveCheckInDB($check_id,$order_id,$data,$total)
    {
        try {    
            return $this->db->query("
                INSERT INTO `".DB_PREFIX.OFD_TABLE_NAME."`
                (`id`,`type`,`order_id`,`total`,`created_at`)
                VALUES ('".$check_id."','".$data['Request']['Type']."','".$order_id."','".$total."',NOW())
            ");    
        } catch(Exception $e) {
            $this->echoError($e->getMessage());
            return false;
        }
    }

    public function getChecksList($limit,$offset,$sort = 'created_at',$order = 'DESC')
    {
        $allowed_sort = array(
            'type',
            'order_id',
            'created_at',
        );
        $allowed_order = array(
            'DESC',
            'ASC',
        );
        $order = in_array($order,$allowed_order)?$order:'DESC';
        $sort = in_array($sort,$allowed_sort)?$sort:'created_at';
        $result = $this->db->query("SELECT * FROM `".DB_PREFIX.OFD_TABLE_NAME."` ORDER BY `".$sort."` ".$order." LIMIT ".(int)$offset.",".(int)$limit."");
        return $result;
    }

    private function prepareDataForOFD($order,$type)
    {
        $data = array();
        $order_items = $this->model_sale_order->getOrderProducts($order['order_id']);
        $data['Request']['Inn'] = $this->ofdGetOption('module_ofd_inn');
        $data['Request']['Type'] = $type;
        $data['Request']['InvoiceId'] = $order['order_id'].'-'.$type;
        $data['Request']['LocalDate'] = date('Y-m-d\TH:i:s');
        $data['Request']['CustomerReceipt'] = array(
                'TaxationSystem'    => $this->ofdGetOption('module_ofd_nalog'),
                'Email'                => $order['email'],
                'Phone'                => $order['telephone'],
                'Items'                => array(),
            );
        
        foreach ($order_items as $item_id => $item_data) {    
            $item_nds = false;
            array_push($data['Request']['CustomerReceipt']['Items'], 
                        array(    
                                'Label'    => $item_data['name'], 
                                'Price' => $item_data['price'], 
                                'Quantity' => $item_data['quantity'],
                                'Amount' => $item_data['total'], 
                                'Vat' => $item_nds?$item_nds:$this->ofdGetOption('module_ofd_nds'),
                            )
                );
        }
        $totals = $this->model_sale_order->getOrderTotals($order['order_id']);
        foreach($totals as $total) {
            if(($total['code'] == 'shipping')&&($total['value'])) {
                array_push($data['Request']['CustomerReceipt']['Items'], 
                        array(    
                                'Label'    => $total['title'], 
                                'Price' => $total['value'], 
                                'Quantity' => 1,
                                'Amount' => $total['value'], 
                                'Vat' => $this->ofdGetOption('module_ofd_nds'),
                            )
                    );    
            }
        }
        if($this->ofdGetOption('module_ofd_collapse')) {        
            $sum = 0;
            $pos_name = $this->ofdGetOption('module_ofd_collapse_name')?$this->ofdGetOption('module_ofd_collapse_name'):'Undefined';
            foreach($data['Request']['CustomerReceipt']['Items'] as $item) {
                $sum += $item['Amount'];
            }
            $data['Request']['CustomerReceipt']['Items'] = array(    
                                'Label'    => $pos_name, 
                                'Price' => $sum, 
                                'Quantity' => 1,
                                'Amount' => $sum, 
                                'Vat' => $this->ofdGetOption('module_ofd_nds'),
                            );
        }
        return $data;
    }

    public function getTextType($type)
    {
        if($type == 'IncomeReturn') {
            return $this->language->get('text_type_return');
        } else if($type == 'Income') {
            return $this->language->get('text_type_income');
        } else {
            return $this->language->get('text_type_undefined');
        }
    }

    public function checkExists($order_id,$type)
    {
        $exists = $this->db->query("SELECT `order_id` FROM `".DB_PREFIX.OFD_TABLE_NAME."` WHERE `order_id` = '".$order_id."' AND `type` = '".$type."' LIMIT 1");
        return $exists->row;
    }

    private function addOrderNote($comment,$order_id,$order_status_id,$notify = false)
    {
        $this->db->query("INSERT INTO " . DB_PREFIX . "order_history SET order_id = '" . (int)$order_id . "', order_status_id = '" . (int)$order_status_id . "', notify = '" . (int)$notify . "', comment = '" . $this->db->escape($comment) . "', date_added = NOW()");
    }

    public function OFDregCheckManually($order_id,$type)
    {
        $this->load->model('sale/order');
        $this->load->language('extension/module/ofd');
        $type = $this->db->escape($type);
        $order_id = intval($order_id);
        $order = $this->model_sale_order->getOrder($order_id);
        $temptype = $this->getTextType($type);
        if($order) {
            if($this->checkExists($order_id,$type)) {    
                    $this->echoError(sprintf($this->language->get('text_already_exists'),$temptype,$order_id));
                    return false;
            } else {
                $data = $this->prepareDataForOFD($order,$type);
                if($check_id = $this->sendDataToOFD($data)) {
                    if(!$this->saveCheckInDB($check_id,$order_id,$data,$order['total'])) {
                        $this->addOrderNote(sprintf($this->language->get('text_db_error'),$temptype,$order_id), $order_id, $order['order_status_id']);
                        $this->echoError(sprintf($this->language->get('text_db_error'),$temptype,$order_id));
                    }                    
                    $this->addOrderNote(sprintf($this->language->get('text_check_created'),$temptype,$this->currency->format($order['total'],$order['currency_code']),date('d.m.Y G:i')), $order_id, $order['order_status_id']);    
                    $this->echoSuccess(sprintf($this->language->get('text_check_ok'),$temptype,$order_id));
                    return true;
                } else {
                    return false;
                }
            }
        } else {
            $this->echoError(sprintf($this->language->get('text_order_not_found'),$order_id));
            return false;
        }
    }
}