<?php
class CieloAPI {
    private $client_id;
    private $client_secret;
    private $base_url;
    private $oauth_url;
    private $access_token;
    
    public function __construct() {
        // Production environment
        $this->client_id = $_ENV['CIELO_CLIENT_ID'] ?? 'your_client_id';
        $this->client_secret = $_ENV['CIELO_CLIENT_SECRET'] ?? 'your_client_secret';
        $this->base_url = 'https://cieloecommerce.cielo.com.br/';
        $this->oauth_url = 'https://cieloecommerce.cielo.com.br/api/public/v2/token';
    }
    
    private function getAccessToken() {
        if ($this->access_token) {
            return $this->access_token;
        }
        
        // Log das credenciais para debug (sem expor valores sensíveis)
        error_log("Cielo OAuth Debug - Client ID configurado: " . (!empty($this->client_id) ? 'SIM' : 'NÃO'));
        error_log("Cielo OAuth Debug - Client Secret configurado: " . (!empty($this->client_secret) ? 'SIM' : 'NÃO'));
        error_log("Cielo OAuth Debug - URL: " . $this->oauth_url);
        
        // Verificar se as credenciais estão configuradas
        if (empty($this->client_id) || $this->client_id === 'your_client_id') {
            error_log("Cielo OAuth Error - Client ID não configurado");
            return false;
        }
        
        if (empty($this->client_secret) || $this->client_secret === 'your_client_secret') {
            error_log("Cielo OAuth Error - Client Secret não configurado");
            return false;
        }
        
        // Seguindo a documentação oficial da Cielo - usar Basic Auth
        $auth_header = base64_encode($this->client_id . ':' . $this->client_secret);
        
        $data = 'grant_type=client_credentials';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $this->oauth_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Basic ' . $auth_header
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        // Log da requisição
        error_log("Cielo OAuth Debug - Enviando requisição para token com Basic Auth");
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        // Log detalhado da resposta
        error_log("Cielo OAuth Debug - HTTP Code: " . $http_code);
        if ($curl_error) {
            error_log("Cielo OAuth Debug - cURL Error: " . $curl_error);
        }
        error_log("Cielo OAuth Debug - Response: " . $response);
        
        if ($http_code == 200 || $http_code == 201) {
            $result = json_decode($response, true);
            if (isset($result['access_token'])) {
                $this->access_token = $result['access_token'];
                error_log("Cielo OAuth Debug - Token obtido com sucesso");
                return $this->access_token;
            } else {
                error_log("Cielo OAuth Debug - Resposta não contém access_token: " . json_encode($result));
                return false;
            }
        } else {
            error_log("Cielo OAuth Error - HTTP Code: " . $http_code);
            error_log("Cielo OAuth Error - Response: " . $response);
            if ($curl_error) {
                error_log("Cielo OAuth Error - cURL Error: " . $curl_error);
            }
            return false;
        }
    }
    
    public function createPaymentLink($amount, $installments, $description = 'Pagamento via Link') {
        $access_token = $this->getAccessToken();
        if (!$access_token) {
            return array(
                'success' => false,
                'error' => 'Erro ao obter token de acesso da Cielo'
            );
        }
        
        // Usar API direta de links de pagamento
        $url = $this->base_url . 'api/public/v1/products/';
        
        // Calculate final amount with interest
        $final_amount = $this->calculateFinalAmount($amount, $installments);
        
        $link_name = !empty($description) ? $description : 'Pagamento Digital';
        
        $data = array(
            'type' => 'Digital',
            'name' => $link_name,
            'description' => $link_name,
            'showDescription' => true,
            'price' => intval($final_amount * 100), // Amount in cents
            'expirationDate' => date('Y-m-d H:i:s', strtotime('+5 days')),
            'maxNumberOfInstallments' => $installments,
            'softDescriptor' => substr($link_name, 0, 13), // Máximo 13 caracteres
            'shipping' => array(
                'type' => 'WithoutShipping',
                'services' => null
            ),
            'settings' => array(
                'invoiceTemplateId' => null,
                'showShippingAddress' => false,
                'skipCart' => true,
                'enableCart' => false
            )
        );
        
        $headers = array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $access_token
        );
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code == 201 || $http_code == 200) {
            $result = json_decode($response, true);
            
            // Para API de links, o retorno direto já contém o link
            if (isset($result['shortUrl'])) {
                return array(
                    'success' => true,
                    'payment_id' => $result['id'],
                    'link' => $result['shortUrl'],
                    'status' => $result['status'] ?? 0
                );
            } elseif (isset($result['id'])) {
                // Se não tiver shortUrl, criar o link do produto
                $link_response = $this->createPaymentLinkFromProduct($result['id']);
                if ($link_response) {
                    return array(
                        'success' => true,
                        'payment_id' => $result['id'],
                        'link' => $link_response,
                        'status' => $result['status'] ?? 0
                    );
                }
            }
            
            return array(
                'success' => false,
                'error' => 'Link criado mas URL não encontrada',
                'raw_response' => $response
            );
        }
        
        // Log detalhado do erro
        error_log("Cielo API Error - HTTP Code: " . $http_code);
        error_log("Cielo API Error - Response: " . $response);
        error_log("Cielo API Error - Request Data: " . json_encode($data));
        error_log("Cielo API Error - Headers: " . json_encode($headers));
        
        $error_message = 'Erro ao criar produto na Cielo';
        
        // Tentar extrair mensagem de erro específica da Cielo
        $decoded_response = json_decode($response, true);
        if ($decoded_response && isset($decoded_response['message'])) {
            $error_message = $decoded_response['message'];
        } elseif ($decoded_response && isset($decoded_response['Message'])) {
            $error_message = $decoded_response['Message'];
        } elseif ($decoded_response && is_array($decoded_response)) {
            $error_message = 'Erro da API: ' . json_encode($decoded_response);
        }
        
        return array(
            'success' => false,
            'error' => $error_message,
            'http_code' => $http_code,
            'raw_response' => $response
        );
    }
    
    private function createPaymentLinkFromProduct($product_id) {
        $access_token = $this->getAccessToken();
        if (!$access_token) {
            return false;
        }
        
        $url = $this->base_url . 'api/public/v1/products/' . $product_id . '/links';
        
        $data = array(
            'type' => 'immediate',
            'quantity' => 1,
            'price' => null, // Usar o preço do produto
            'expirationDate' => date('Y-m-d\TH:i:s', strtotime('+30 days')),
            'settings' => array(
                'skipCart' => true,
                'enableCart' => false
            )
        );
        
        $headers = array(
            'Content-Type: application/json',
            'Authorization: Bearer ' . $access_token
        );
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($http_code == 201 || $http_code == 200) {
            $result = json_decode($response, true);
            return $result['shortUrl'] ?? $result['url'] ?? null;
        } else {
            error_log("Cielo Link Creation Error - HTTP Code: " . $http_code);
            error_log("Cielo Link Creation Error - Response: " . $response);
            error_log("Cielo Link Creation Error - Request Data: " . json_encode($data));
            return false;
        }
    }
    
    public function checkPaymentStatus($product_id) {
        $access_token = $this->getAccessToken();
        if (!$access_token) {
            return array('success' => false);
        }
        
        $url = $this->base_url . 'api/public/v1/products/' . $product_id . '/payments';
        
        $headers = array(
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/json'
        );
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        error_log("Cielo Payment Status Check - Response: " . $response);
        
        if ($http_code == 200) {
            $result = json_decode($response, true);
            
            // Se houver transações, retorna todas elas
            if (!empty($result)) {
                // Pega a transação mais recente para o status
                $lastTransaction = end($result);
                $status = $this->mapTransactionStatus($lastTransaction['status']);
                
                return array(
                    'success' => true,
                    'status' => $status,
                    'transactions' => $result
                );
            }
            
            // Se não houver transações, retorna status aguardando
            return array(
                'success' => true,
                'status' => 0,
                'transactions' => array()
            );
        }
        
        error_log("Cielo Payment Status Check Error - HTTP Code: " . $http_code);
        if ($curl_error) {
            error_log("Cielo Payment Status Check Error - cURL Error: " . $curl_error);
        }
        
        return array('success' => false);
    }
    
    private function mapTransactionStatus($status) {
        switch ($status) {
            case 2: // Pago
            case 'Paid':
                return 2;
            case 1: // Autorizado
            case 'Authorized':
                return 1;
            case 3: // Negado
            case 'Denied':
                return 3;
            case 10: // Cancelado
            case 'Voided':
                return 10;
            case 11: // Reembolsado
            case 'Refunded':
                return 11;
            case 12: // Pendente
            case 'Pending':
                return 12;
            case 13: // Abortado
            case 'Aborted':
                return 13;
            case 20: // Agendado
            case 'Scheduled':
                return 20;
            default:
                return 0; // Não finalizado
        }
    }
    
    private function calculateFinalAmount($amount, $installments) {
        if ($installments >= 4 && $installments <= 6) {
            return $amount * 1.04; // 4% interest
        }
        return $amount; // No interest for 1-3 installments
    }

    public function GetLinksInfo($link_id) {
        $access_token = $this->getAccessToken();
        if (!$access_token) {
            return array(
                'success' => false,
                'error' => 'Erro ao obter token de acesso da Cielo'
            );
        }

        // Log para debug
        error_log("GetLinksInfo - Buscando informações do link ID: " . $link_id);
        
        $url = $this->base_url . 'api/public/v1/products/' . $link_id;
        
        error_log("GetLinksInfo - URL: " . $url);
        
        $headers = array(
            'Authorization: Bearer ' . $access_token,
            'Content-Type: application/json'
        );
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        error_log("GetLinksInfo - HTTP Code: " . $http_code);
        error_log("GetLinksInfo - Response: " . $response);
        if ($curl_error) {
            error_log("GetLinksInfo - cURL Error: " . $curl_error);
        }
        
        if ($http_code == 200) {
            $result = json_decode($response, true);
            
            if ($result) {
                return array(
                    'success' => true,
                    'data' => array(
                        'id' => $result['id'],
                        'tipo_link' => $result['type'],
                        'data_expiracao' => $result['expirationDate'],
                        'url_completa' => $result['links'][0]['url'] ?? null,
                        'url_curta' => $result['shortUrl'] ?? null,
                        'status' => $result['status']
                    )
                );
            }
        }
        
        error_log("GetLinksInfo Error - HTTP Code: " . $http_code);
        error_log("GetLinksInfo Error - Response: " . $response);
        
        return array(
            'success' => false,
            'error' => 'Erro ao consultar informações do link',
            'http_code' => $http_code,
            'raw_response' => $response
        );
    }
}
?>
