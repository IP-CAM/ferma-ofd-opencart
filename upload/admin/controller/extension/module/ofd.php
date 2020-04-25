<?

class ControllerExtensionModuleOfd extends Controller
{

    public function checksList()
    {
        $this->load->language('extension/module/ofd');
        $this->document->setTitle($this->language->get('doc_title'));
        $this->load->model('setting/setting');
        if(!$this->config->get('module_ofd_status')) {
            return new Action('error/not_found');
        }
        $this->load->model('extension/ofd');
        $data = array();
        $this->loadLang($data);
        if($this->request->server['REQUEST_METHOD']=='POST') {
            $result = true;
            if(isset($this->request->post['check'])&&is_array($this->request->post['check'])) {
                foreach($this->request->post['check'] as $check_id) {
                    $result = $this->model_extension_ofd->UpdateCheckStatus($check_id);
                }
            } else if(isset($this->request->get['check'])) {
                $result = $this->model_extension_ofd->UpdateCheckStatus($this->request->get['check']);
            }
            if($result) {
                $this->session->data['success'] = $this->language->get('text_success_upd');
            }
            $this->response->redirect($this->url->link('extension/module/ofd/checksList', 'user_token=' . $this->session->data['user_token'] . '', true));
        } 
        if (isset($this->session->data['error_warning_ofd'])) {
            $data['error_warning'] = $this->session->data['error_warning_ofd'];
            unset($this->session->data['error_warning_ofd']);
        } else {
            $data['error_warning'] = '';
        }
        if (isset($this->session->data['success'])) {
                $data['success'] = $this->session->data['success'];
                unset($this->session->data['success']);
        } else {
                $data['success'] = '';
        }
        if($this->model_extension_ofd->getCountPendingChecks()) {
            $this->model_extension_ofd->UpdateChecksStatus();
        }
        if (isset($this->request->get['sort'])) {
            $sort = $this->request->get['sort'];
        } else {
            $sort = 'sort_created_at';
        }
        if (isset($this->request->get['order'])) {
            $order = $this->request->get['order'];
        } else {
            $order = 'DESC';
        }
        $url = '';
        if ($order == 'ASC') {
            $url .= '&order=DESC';
        } else {
            $url .= '&order=ASC';
        }
        if (isset($this->request->get['page'])) {
            $page = $this->request->get['page'];
        } else {
            $page = 1;
        }
        if (isset($this->request->get['page'])) {
            $url .= '&page=' . $this->request->get['page'];
        }
        $data['sort_type'] = $this->url->link('extension/module/ofd/checksList', 'user_token=' . $this->session->data['user_token'] . '&sort=type' . $url, true);
        $data['sort_order_id'] = $this->url->link('extension/module/ofd/checksList', 'user_token=' . $this->session->data['user_token'] . '&sort=order_id' . $url, true);
        $data['sort_created_at'] = $this->url->link('extension/module/ofd/checksList', 'user_token=' . $this->session->data['user_token'] . '&sort=created_at' . $url, true);
        $url = '';
        if (isset($this->request->get['sort'])) {
            $url .= '&sort=' . $this->request->get['sort'];
        }
        if (isset($this->request->get['order'])) {
            $url .= '&order=' . $this->request->get['order'];
        }
        $checks_total = $this->model_extension_ofd->getCountChecks();        
        $pagination = new Pagination();
        $pagination->total = $checks_total;
        $pagination->page = $page;
        $pagination->limit = 10;
        $pagination->url = $this->url->link('extension/module/ofd/checksList', 'user_token=' . $this->session->data['user_token'] . $url . '&page={page}', true);
        $data['pagination'] = $pagination->render();
        $data['results'] = sprintf($this->language->get('text_pagination'), ($checks_total) ? (($page - 1) * 10) + 1 : 0, ((($page - 1) * 10) > ($checks_total - 10)) ? $checks_total : ((($page - 1) * 10) + 10), $checks_total, ceil($checks_total / 10));
        $data['sort'] = $sort;
        $data['order'] = $order;
        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/module/ofd/checksList', 'user_token=' . $this->session->data['user_token'], true)
        );        
        $checks = $this->model_extension_ofd->getChecksList(10,($page - 1) * 10,$sort,$order);
        foreach($checks->rows as $key=>$check) {
            $checks->rows[$key]['order_link'] = $this->url->link('sale/order/info', 'user_token=' . $this->session->data['user_token'] . '&order_id=' . $checks->rows[$key]['order_id'], true);
            $checks->rows[$key]['created_at'] = date('d.m.Y G:i:s',strtotime($checks->rows[$key]['created_at']));
            $checks->rows[$key]['update'] = $this->url->link('extension/module/ofd/UpdateChecksStatus', 'user_token=' . $this->session->data['user_token'] . '&order_id=' . $checks->rows[$key]['order_id']. '&check='.$checks->rows[$key]['id'], true);
            $checks->rows[$key]['type'] = $this->model_extension_ofd->getTextType($checks->rows[$key]['type']);
        }
        $data['action'] = $this->url->link('extension/module/ofd/checksList', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
        $data['ofd_inn'] = $this->config->get('module_ofd_inn');
        $data['heading_title'] = $this->language->get('heading_title');
        $data['text_edit'] = $this->language->get('text_checks_list');
        $data['checks'] = $checks->rows;
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $this->response->setOutput($this->load->view('extension/module/ofd_list', $data));
    }

    public function OFDregCheckManually()
    {
        $this->load->model('extension/ofd');
        $this->load->model('setting/setting');
        if(!$this->config->get('module_ofd_status')) {
            return new Action('error/not_found');
        }
        if($this->model_extension_ofd->OFDregCheckManually((int)$this->request->get['order_id'],$this->request->get['type'])) {
            $this->model_extension_ofd->UpdateChecksStatus();
        }
        $this->response->redirect($this->url->link('sale/order/info', 'user_token=' . $this->session->data['user_token'] . '&order_id=' . (int)$this->request->get['order_id'], true));
    }

    public function UpdateChecksStatus()
    {
        $this->load->model('extension/ofd');
        $this->load->language('extension/module/ofd');
        $this->load->model('setting/setting');
        if(!$this->config->get('module_ofd_status')) {
            return new Action('error/not_found');
        }
        $result = true;
        if(isset($this->request->post['check'])&&is_array($this->request->post['check'])) {
            foreach($this->request->post['check'] as $check_id) {
                $result = $this->model_extension_ofd->UpdateCheckStatus($check_id);
            }
        } else if(isset($this->request->get['check'])) {
            $result = $this->model_extension_ofd->UpdateCheckStatus($this->request->get['check']);
        }
        if($result) {
            $this->session->data['success'] = $this->language->get('text_success_upd');
        }
        $this->response->redirect($this->url->link('extension/module/ofd/checksList', 'user_token=' . $this->session->data['user_token'] . '', true));
    }

    public function index()
    {    
        
        $this->load->language('extension/module/ofd');
        $this->document->setTitle($this->language->get('doc_title'));
        $this->load->model('setting/setting');
        $this->load->model('extension/ofd');

        $this->load->model('setting/extension');
        $installed_modules = $this->model_setting_extension->getInstalled('module');
        if(!in_array('ofd', $installed_modules)) {
            return new Action('error/not_found');
        }
        $data = array();
        $errors = array();
        $this->loadLang($data);
        if($this->request->server['REQUEST_METHOD']=='POST') {
            $this->model_setting_setting->editSetting('module_ofd', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_ofd_success');
            $this->response->redirect($this->url->link('extension/module/ofd', 'user_token=' . $this->session->data['user_token'] . '', true));
        }     
        $this->model_extension_ofd->setAuthToken();
        if (isset($this->session->data['error_warning_ofd'])) {
            $errors[] = $this->session->data['error_warning_ofd'];
            unset($this->session->data['error_warning_ofd']);
        }
        if($this->config->get('module_ofd_client_login')=='') {
            $errors[] = $this->language->get('error_ofd_client_login');
        }
        if($this->config->get('module_ofd_client_pass')=='') {
            $errors[] = $this->language->get('error_ofd_client_pass');
        }
        if($this->config->get('module_ofd_inn')=='') {
            $errors[] = $this->language->get('error_ofd_ofd_inn');
        }
        if($this->config->get('module_ofd_nalog')=='') {
            $errors[] = $this->language->get('error_ofd_nalog');
        }
        if($this->config->get('module_ofd_nds')=='') {
            $errors[] = $this->language->get('error_ofd_nds');
        }    
        if (isset($this->session->data['success'])) {
                $data['success'] = $this->session->data['success'];
                unset($this->session->data['success']);
        } else {
                $data['success'] = '';
        }
        $data['errors_warning'] = $errors;

        $data['breadcrumbs'] = array();

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
        );

        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_extension'),
            'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true)
        );
 
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('extension/module/ofd', 'user_token=' . $this->session->data['user_token'], true)
        );
        $data['text_edit'] = $this->language->get('text_edit');
        $data['heading_title'] = $this->language->get('heading_title');
        $data['action'] = $this->url->link('extension/module/ofd', 'user_token=' . $this->session->data['user_token'], true);
        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=module', true);
        $this->load->model('localisation/order_status');
        $data['ofd_status'] = $this->config->get('module_ofd_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();
        $data['ofd_client_login'] = $this->config->get('module_ofd_client_login');
        $data['ofd_client_pass'] = $this->config->get('module_ofd_client_pass');
        $data['ofd_inn'] = $this->config->get('module_ofd_inn');
        $data['ofd_email'] = $this->config->get('module_ofd_email');
        $data['ofd_nalog'] = $this->config->get('module_ofd_nalog');
        $data['ofd_collapse'] = $this->config->get('module_ofd_collapse');
        $data['ofd_collapse_name'] = $this->config->get('module_ofd_collapse_name');
        $data['ofd_order_status'] = $this->config->get('module_ofd_order_status');
        $data['ofd_nds'] = $this->config->get('module_ofd_nds');
        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');
        $this->response->setOutput($this->load->view('extension/module/ofd_settings', $data));
    }

    private function loadLang(&$data)
    {
        $data['column_id'] = $this->language->get('column_id');
        $data['column_type'] = $this->language->get('column_type');
        $data['column_status'] = $this->language->get('column_status');
        $data['column_order_id'] = $this->language->get('column_order_id');
        $data['column_total'] = $this->language->get('column_total');
        $data['column_created_at'] = $this->language->get('column_created_at');
        $data['column_action'] = $this->language->get('column_action');
        $data['entry_ofd_client_login'] = $this->language->get('entry_ofd_client_login');
        $data['entry_ofd_client_pass'] = $this->language->get('entry_ofd_client_pass');
        $data['entry_ofd_inn'] = $this->language->get('entry_ofd_inn');
        $data['entry_ofd_email'] = $this->language->get('entry_ofd_email');
        $data['entry_ofd_nalog'] = $this->language->get('entry_ofd_nalog');
        $data['entry_ofd_collapse'] = $this->language->get('entry_ofd_collapse');
        $data['entry_ofd_collapse_name'] = $this->language->get('entry_ofd_collapse_name');
        $data['entry_ofd_order_status'] = $this->language->get('entry_ofd_order_status');
        $data['entry_ofd_nds'] = $this->language->get('entry_ofd_nds');
        $data['entry_settings_tab'] = $this->language->get('entry_settings_tab');
        $data['entry_checks_tab'] = $this->language->get('entry_checks_tab');    
        $data['entry_empty'] = $this->language->get('entry_empty');
        $data['entry_nalog_common'] = $this->language->get('entry_nalog_common');
        $data['entry_nalog_simplein'] = $this->language->get('entry_nalog_simplein');
        $data['entry_nalog_simpleinout'] = $this->language->get('entry_nalog_simpleinout');
        $data['entry_nalog_unified'] = $this->language->get('entry_nalog_unified');
        $data['entry_nalog_unifiedagricultural']   = $this->language->get('entry_nalog_unifiedagricultural');
        $data['entry_nalog_patent'] = $this->language->get('entry_nalog_patent');
        $data['entry_status'] = $this->language->get('entry_status');
        $data['entry_nds_vat0'] = $this->language->get('entry_nds_vat0');
        $data['entry_nds_vat10'] = $this->language->get('entry_nds_vat10');
        $data['entry_nds_vat18'] = $this->language->get('entry_nds_vat18');
        $data['text_success'] = $this->language->get('text_ofd_success');
        $data['text_checks_list'] = $this->language->get('text_checks_list');
        $data['button_update'] = $this->language->get('button_update');
    }

    public function install()
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `".DB_PREFIX."ofd_checks_list` (
              `id` varchar(36) NOT NULL,
              `type` varchar(100) NOT NULL,
              `status` varchar(100) DEFAULT NULL,
              `status_message` varchar(100) DEFAULT NULL,
              `order_id` int(11) NOT NULL,
              `total` float(10,2) NOT NULL,
              `FN` varchar(100) DEFAULT NULL,
              `RNM` varchar(100) DEFAULT NULL,
              `FDN` varchar(100) DEFAULT NULL,
              `FPD` varchar(100) DEFAULT NULL,
              `created_at` datetime NOT NULL,
              `updated_at` datetime NOT NULL,
              PRIMARY KEY (`id`)
             ) ENGINE=MyISAM DEFAULT CHARSET=utf8;");  
        $this->load->model('setting/event');
        $this->model_setting_event->addEvent('ofd', 'catalog/model/checkout/order/addOrder/after', 'extension/module/ofd/ofd_main');
        $this->model_setting_event->addEvent('ofd', 'catalog/model/checkout/order/editOrder/after', 'extension/module/ofd/ofd_main');
        $this->model_setting_event->addEvent('ofd', 'catalog/model/checkout/order/addOrderHistory/after', 'extension/module/ofd/ofd_main');
        $data['redirect'] = 'extension/extension/module';
        $this->load->controller('marketplace/modification/refresh', $data);
    }

    public function uninstall()
    {
        $this->db->query("DROP TABLE IF EXISTS `".DB_PREFIX."ofd_checks_list`");
        $this->load->model('setting/event');
        $this->model_setting_event->deleteEventByCode('ofd');
        $this->load->model('setting/setting');
        $this->model_setting_setting->deleteSetting('module_ofd');
        $data['redirect'] = 'extension/extension/module';
        $this->load->controller('marketplace/modification/refresh', $data);
    }
}