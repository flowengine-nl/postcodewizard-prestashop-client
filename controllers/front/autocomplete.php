<?php
/**
 * PostcodeWizard Module
 *
 * @author    FlowEngine
 * @copyright Copyright (c) 2025 FlowEngine
 * @license   https://opensource.org/licenses/MIT MIT License
 */

require_once __DIR__ . '/classes/PostcodeWizardHelper.php';

if (!defined('_PS_VERSION_')) {
    exit;
}
class PostcodeWizardAutocompleteModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();
        header('Content-Type: application/json');

        $query = Tools::getValue('query');
        $apiKey = Configuration::get('POSTCODEWIZARD_API_KEY');

        if (!$query || !$apiKey) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid parameters']);
            exit;
        }

        $client = new GuzzleHttp\Client();
        try {
            $res = $client->request('GET', PostcodeWizardHelper::getApiEndpoint() . 'autocomplete', [
                'query' => [
                    'query' => $query,
                ],
                'headers' => [
                    'Accept' => 'application/json',
                    'x-api-key' => $apiKey,
                ],
                'timeout' => 5,
            ]);

            echo $res->getBody();
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Request failed']);
        }

        exit;
    }
}
