<?php

require_once __DIR__ . '/classes/PostcodeWizardHelper.php';


class PostcodeWizardLookupModuleFrontController extends ModuleFrontController
{
    public function initContent()
    {
        parent::initContent();
        header('Content-Type: application/json');

        $postcode = Tools::getValue('postcode');
        $houseNumber = Tools::getValue('houseNumber');
        $apiKey = Configuration::get('POSTCODEWIZARD_API_KEY');

        if (!$postcode || !$houseNumber || !$apiKey) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid parameters']);
            exit;
        }

        $client = new GuzzleHttp\Client();
        try {
            $res = $client->request('GET', PostcodeWizardHelper::getApiEndpoint() . 'lookup', [
                'query' => [
                    'postcode' => $postcode,
                    'houseNumber' => $houseNumber,
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
