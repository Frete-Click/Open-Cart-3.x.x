<?php
class ModelExtensionShippingFreteclick extends Model {

	private $quote_data = array();

	private $cep_destino;
	private $endereco_destino;

	private $cep_origem;
	private $endereco_origem;

	private $pais_destino;

	private $apiKey;

	private $mensagem_erro = array();

    // função responsável pelo retorno à loja dos valores finais dos valores dos fretes
    public function getQuote($address) {

		$this->load->language('extension/shipping/freteclick');

		$this->apiKey = $this->config->get('shipping_freteclick_key');

        $method_data = array();

        // obtém só a parte numérica do CEP
		$this->cep_origem = preg_replace ("/[^0-9]/", '', $this->config->get('shipping_freteclick_postcode'));
		$this->endereco_origem = $this->getAddress($this->cep_origem);

		$this->cep_destino = preg_replace ("/[^0-9]/", '', $address['postcode']);
		$this->endereco_destino = $this->getAddress($this->cep_destino);

        $this->pais_destino='BR';
		$this->load->model('localisation/country');
		$country_info = $this->model_localisation_country->getCountry($address['country_id']);
		if ($country_info) {
			$this->pais_destino = $country_info['iso_code_2'];
		}

		$fretes = $this->getResult();
		// print_r($fretes); exit;
		if (!empty($fretes['response']['data']['quote'])) {
			foreach ($fretes['response']['data']['quote'] as $frete) {
				$title = $frete['carrier-alias'] .' | '. $frete['deadline'] .' dia(s)';
				$text = $this->currency->format(
					$this->tax->calculate(
						$frete['total'], 
						$this->config->get('shipping_flat_tax_class_id'), 
						$this->config->get('config_tax')
					), 
					$this->session->data['currency']
				);
	
				$this->quote_data[$frete['quote-id']] = [
					'code' 			=> 'freteclick.'. $frete['quote-id'],
					'title' 		=> $title,
					'cost' 			=> round($frete['total'], 2),
					'tax_class_id' 	=> $this->config->get('shipping_flat_tax_class_id'),
					'text' 			=> $text,
				];
			}
		} else {
			
		}

		$method_data = [
			'code' 			=> 'freteclick',
			'title' 		=> $this->language->get('text_title'),
			'quote' 		=> $this->quote_data,
			'sort_order' 	=> $this->config->get('shipping_freteclick_sort_order'),
			'error'			=> false,
		];
		return $method_data;
    }

    // retorna a dimensão em centímetros
	private function getDimensaoEmCm($unidade_id, $dimensao){
		if(is_numeric($dimensao)){
			$length_class_product_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "length_class mc LEFT JOIN " . DB_PREFIX . "length_class_description mcd ON (mc.length_class_id = mcd.length_class_id) WHERE mcd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND mc.length_class_id =  '" . (int)$unidade_id . "'");

			if(isset($length_class_product_query->row['unit'])){
				if($length_class_product_query->row['unit'] == 'mm'){
					return $dimensao / 10;
				}
			}
		}

		return $dimensao;
	}

	// retorna o peso em quilogramas
	private function getPesoEmKg($unidade_id, $peso){

		if(is_numeric($peso)) {
			$weight_class_product_query = $this->db->query("SELECT * FROM " . DB_PREFIX . "weight_class wc LEFT JOIN " . DB_PREFIX . "weight_class_description wcd ON (wc.weight_class_id = wcd.weight_class_id) WHERE wcd.language_id = '" . (int)$this->config->get('config_language_id') . "' AND wc.weight_class_id =  '" . (int)$unidade_id . "'");

			if(isset($weight_class_product_query->row['unit'])){
				if($weight_class_product_query->row['unit'] == 'g'){
					return ($peso / 1000);
				}
			}
		}

		return $peso;
	}

	/**
	 * Envia os dados a API e recebe os dados de Frete
	 * @return Array
	 */
	private function getResult() {
		
		$fields = [
			'api-key' => $this->apiKey,
			'product-total-price' => 1500.0,
			'product-type' => 'Material de escritório',
			'product-package' => $this->getProducts(),
			'cep-origin' => $this->cep_origem,
			'street-origin' => $this->endereco_origem->logradouro,
			'address-number-origin' => '1',
			'complement-origin' => 'S1',
			'district-origin' => $this->endereco_origem->bairro,
			'city-origin' => $this->endereco_origem->cidade,
			'state-origin' => $this->endereco_origem->estado,
			'country-origin' => 'Brasil',
			'cep-destination' => $this->cep_destino,
			'street-destination' => $this->endereco_destino->logradouro,
			'address-number-destination' => '1',
			'complement-destination' => 'S1',
			'district-destination' => $this->endereco_destino->cidade,
			'city-destination' => $this->endereco_destino->cidade,
			'state-destination' => $this->endereco_destino->estado,
			'country-destination' => $this->pais_destino,
		];

		// print_r($fields); exit;

		$payload = http_build_query($fields);

		$url = "https://api.freteclick.com.br/sales/shipping-quote.json";

		// Prepare new cURL resource
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLINFO_HEADER_OUT, true);
		curl_setopt($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);

		$result = curl_exec($ch);

		if (!$result) {
			$this->log->write(curl_error($ch));
			$this->log->write($this->language->get('error_conexao'));
			$result = curl_exec($ch);

			if ($result) {
				$this->log->write($this->language->get('text_sucesso'));
			} else {
				$this->log->write(curl_error($ch));
				$this->log->write($this->language->get('error_reconexao'));
			}
		}

		curl_close($ch);

		return json_decode($result, true);
	}

	/**
	 * Lista os produtos que estão no carrinho para enviar ao Freteclick
	 * @return Array
	 */
	private function getProducts() {

		$produtos = $this->cart->getProducts();

		$items = [];
		foreach ($produtos as $key => $prod) {
			$items[$key] = [
				'qtd' => $prod['quantity'],
				'weight' => number_format($this->getPesoEmKg($prod['weight_class_id'], $prod['weight']) / $prod['quantity'], 2, ',', ''),
				'height' => number_format($this->getDimensaoEmCm($prod['length_class_id'], $prod['height']), 2, ',', ''),
				'width' => number_format($this->getDimensaoEmCm($prod['length_class_id'], $prod['width']), 2, ',', ''),
				'depth' => number_format($this->getDimensaoEmCm($prod['length_class_id'], $prod['length']), 2, ',', ''),
			];
		}
		// print_r($items); exit;
		return $items;
	}

	/**
	 * Busca o endereço na API do Postmon
	 * @param String $cep - CEP que deve ter o endereço buscado
	 * @return stdClass
	 */
	private function getAddress($cep)
	{
		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, 'https://api.postmon.com.br/v1/cep/'. $cep);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
		curl_setopt($ch, CURLOPT_TIMEOUT, 30);
		
		$result = curl_exec($ch);

		if (!$result) {
			$this->log->write(curl_error($ch));
			curl_close($ch);
			return false;	
		}
		curl_close($ch);

		$result = json_decode($result);
		return $result;
	}
}