<?php
class ControllerExtensionShippingFreteclick extends Controller {

    private $error = array();

    public function index()
    {
        $this->load->language('extension/shipping/freteclick');

        $this->document->setTitle($this->language->get('heading_title'));

        $this->load->model('setting/setting');

        if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
            // var_dump($this->request->post); exit;

            $this->model_setting_setting->editSetting('shipping_freteclick', $this->request->post);

            $this->session->data['success'] = $this->language->get('text_success');

            $this->response->redirect($this->url->link('marketplace/extension', 'user_token='. $this->session->data['user_token'] .'&type=shipping', true));
        }

        if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->error['postcode'])) {
			$data['error_postcode'] = $this->error['postcode'];
		} else {
			$data['error_postcode'] = '';
		}

        $data = [];

        $data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_home'),
			'href' => $this->url->link('common/dashboard', 'user_token=' . $this->session->data['user_token'], true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('text_extension'),
			'href' => $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping', true)
		);

		$data['breadcrumbs'][] = array(
			'text' => $this->language->get('heading_title'),
			'href' => $this->url->link('extension/shipping/freteclick', 'user_token=' . $this->session->data['user_token'], true)
        );
        
        $data['action'] = $this->url->link('extension/shipping/freteclick', 'user_token=' . $this->session->data['user_token'], true);

        $data['cancel'] = $this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'] . '&type=shipping', true);
        
        if (isset($this->request->post['shipping_freteclick_status'])) {
            $data['shipping_freteclick_status'] = $this->request->post['shipping_freteclick_status'];
        } else {
            $data['shipping_freteclick_status'] = $this->config->get('shipping_freteclick_status');
        }
        
        if (isset($this->request->post['shipping_freteclick_postcode'])) {
            $data['shipping_freteclick_postcode'] = $this->request->post['shipping_freteclick_postcode'];
        } else {
            $data['shipping_freteclick_postcode'] = $this->config->get('shipping_freteclick_postcode');
        }
        
        if (isset($this->request->post['shipping_freteclick_key'])) {
            $data['shipping_freteclick_key'] = $this->request->post['shipping_freteclick_key'];
        } else {
            $data['shipping_freteclick_key'] = $this->config->get('shipping_freteclick_key');
        }

        $data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('extension/shipping/freteclick', $data));
    }

    protected function validate() {
        if (!$this->user->hasPermission('modify', 'extension/shipping/freteclick')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        return !$this->error;
    }
}