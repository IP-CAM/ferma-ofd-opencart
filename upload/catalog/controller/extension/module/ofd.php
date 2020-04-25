<?
class ControllerExtensionModuleOfd extends Controller
{
    public function ofd_main(&$route, &$data, &$output)
    {
        $this->load->model('extension/module/ofd');
        $this->load->model('setting/setting');
        if($this->config->get('module_ofd_status')) {
            $order_id = $data[0];
            $order = $this->model_checkout_order->getOrder($order_id);
            if($order&&($order['order_status_id'] == $this->config->get('module_ofd_order_status'))) {
                if($this->model_extension_module_ofd->OFDregCheckManually($order_id,'Income')) {
                    $this->model_extension_module_ofd->UpdateChecksStatus();
                }
            }
        }
    }
}