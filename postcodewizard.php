<?php
/**
 * PostcodeWizard Module
 *
 * @author    FlowEngine
 * @copyright Copyright (c) 2025 FlowEngine
 * @license   https://opensource.org/licenses/MIT MIT License
 */

require_once __DIR__ . '/classes/PostcodeWizardHelper.php';

use GuzzleHttp\Client;

if (!defined('_PS_VERSION_')) {
    exit;
}

class PostcodeWizard extends Module
{
    public function __construct()
    {
        $this->name = 'postcodewizard';
        $this->tab = 'front_office_features';
        $this->version = '1.0.0';
        $this->author = 'FlowEngine';
        $this->need_instance = 0;
        $this->bootstrap = true;

        parent::__construct();

        $this->displayName = $this->trans('PostcodeWizard', [], 'Modules.PostcodeWizard.Admin');
        $this->description = $this->trans('Zoek een adres of vul je postcode + huisnummer in, PostcodeWizard vult de rest automagisch aan.', [], 'Modules.PostcodeWizard.Admin');
        $this->ps_versions_compliancy = ['min' => '1.7.0.0', 'max' => _PS_VERSION_];
    }

    public function install()
    {
        return parent::install()
            && $this->registerHook('displayHeader')
            && Configuration::updateValue('POSTCODEWIZARD_MODE', 'lookup');
    }

    public function uninstall()
    {
        return parent::uninstall()
            && Configuration::deleteByName('POSTCODEWIZARD_API_KEY')
            && Configuration::deleteByName('POSTCODEWIZARD_MODE');
    }

    public function hookDisplayHeader()
    {
        if (!$this->context->controller || $this->context->controller->php_self !== 'order') {
            return;
        }

        if ($this->context->controller->php_self === 'order') {
            Media::addJsDef([
                'postcodewizard_api_key' => PostcodeWizardHelper::getApiKey(),
                'postcodewizard_mode' => PostcodeWizardHelper::getMode(),
            ]);

            $this->context->controller->addJS($this->_path . 'views/js/postcodewizard.js');
            $this->context->controller->addCSS($this->_path . 'views/css/postcodewizard.css');
        }
    }

    public function getContent()
    {
        if (Tools::getValue('action') === 'testConnection' && Tools::getIsset('ajax')) {
            header('Content-Type: application/json');
            $apiKey = Tools::getValue('key') ?: PostcodeWizardHelper::getApiKey();

            if (!$apiKey) {
                echo json_encode(['success' => false, 'error' => 'Geen API-sleutel opgegeven.']);
                exit;
            }

            try {
                $client = new Client();
                $res = $client->request('GET', PostcodeWizardHelper::getApiEndpoint() . 'autocomplete', [
                    'query' => ['query' => 'Toverstraat 1, Baak'],
                    'headers' => [
                        'Accept' => 'application/json',
                        'x-api-key' => $apiKey,
                    ],
                    'timeout' => 5,
                ]);

                echo json_encode(['success' => $res->getStatusCode() === 200]);
            } catch (\Exception $e) {
                echo json_encode([
                    'success' => false,
                    'error' => 'Fout bij verbinding: ' . $e->getMessage(),
                ]);
            }

            exit;
        }

        $output = '<img src="' . $this->_path . 'logo.png" style="height:64px;margin-bottom:10px;">';

        Media::addJsDef([
            'pw_admin_token' => Tools::getAdminTokenLite('AdminModules'),
        ]);

        if (Tools::isSubmit('submit_postcodewizard')) {
            $apiKey = Tools::getValue('POSTCODEWIZARD_API_KEY');
            $mode = Tools::getValue('POSTCODEWIZARD_MODE');

            if (!empty($apiKey)) {
                try {
                    $client = new Client();
                    $res = $client->request('GET', PostcodeWizardHelper::getApiEndpoint() . 'autocomplete', [
                        'query' => ['query' => 'Toverstraat 1, Baak'],
                        'headers' => [
                            'Accept' => 'application/json',
                            'x-api-key' => $apiKey,
                        ],
                        'timeout' => 5,
                    ]);

                    if ($res->getStatusCode() !== 200) {
                        throw new \Exception('Onverwachte API-status: ' . $res->getStatusCode());
                    }
                } catch (\Exception $e) {
                    return $this->displayError($this->l('API-sleutel is ongeldig: ') . $e->getMessage()) . $this->renderForm();
                }

                Configuration::updateValue('POSTCODEWIZARD_API_KEY', $apiKey);
                Configuration::updateValue('POSTCODEWIZARD_MODE', $mode);

                $output .= $this->displayConfirmation($this->l('Instellingen opgeslagen.'));
            } else {
                $output .= $this->displayError($this->l('Vul een geldige API-sleutel in.'));
            }
        }

        $output .= '
            <script>
              document.addEventListener("DOMContentLoaded", function () {
                const btn = document.getElementById("pw-test-api");
                const statusBox = document.getElementById("pw-api-status");
                const apiKeyField = document.querySelector(\'[name="POSTCODEWIZARD_API_KEY"]\');
            
                if (!btn || !statusBox || !apiKeyField) return;
            
                btn.addEventListener("click", function (e) {
                  e.preventDefault();
                  const apiKey = apiKeyField.value.trim();
                  if (!apiKey) {
                    statusBox.innerHTML = \'<div class="alert alert-danger">Geen API-sleutel ingevuld</div>\';
                    return;
                  }
            
                  statusBox.innerHTML = "";
                  btn.textContent = "Aan het toveren...";
                  btn.classList.add("disabled");
            
                    fetch(
                        "index.php?controller=AdminModules" +
                          "&configure=postcodewizard" +
                          "&module_name=postcodewizard" +
                          "&ajax=1" +
                          "&action=testConnection" +
                          "&token=" + encodeURIComponent(pw_admin_token) +
                          "&key=" + encodeURIComponent(apiKey)
                    ).then(res => res.json())
                    .then(data => {
                        console.log(data);
                      if (data.success) {
                        statusBox.innerHTML = \'<div class="alert alert-success">Verbinding succesvol!</div>\';
                      } else {
                        statusBox.innerHTML = \'<div class="alert alert-danger">Verbinding mislukt: \' + (data.error || "Onbekende fout") + \'</div>\';
                      }
                    })
                    .catch((e) => {
                      statusBox.innerHTML = \'<div class="alert alert-danger">Verzoek mislukt</div>\';
                    })
                    .finally(() => {
                      btn.textContent = "Test de magie";
                      btn.classList.remove("disabled");
                    });
                });
              });
            </script>
        ';

        return $output . $this->renderForm();
    }

    protected function renderForm()
    {
        $default_lang = (int) Configuration::get('PS_LANG_DEFAULT');

        $fields_form[0]['form'] = [
            'legend' => [
                'title' => $this->l('PostcodeWizard Instellingen'),
            ],
            'input' => [
                [
                    'type' => 'text',
                    'label' => $this->l('API-sleutel'),
                    'name' => 'POSTCODEWIZARD_API_KEY',
                    'size' => 60,
                    'required' => true,
                    'desc' => '<a href="#" id="pw-test-api" class="btn btn-default btn-sm" style="margin-top:5px">' .
                        $this->l('Test de magie') . '</a>' .
                        '<div id="pw-api-status" style="margin-top:10px;"></div>',
                ],
                [
                    'type' => 'select',
                    'label' => $this->l('Zoekmodus'),
                    'name' => 'POSTCODEWIZARD_MODE',
                    'options' => [
                        'query' => [
                            ['id' => 'lookup', 'name' => 'Lookup (Postcode + huisnummer)'],
                            ['id' => 'autocomplete', 'name' => 'Autocomplete'],
                        ],
                        'id' => 'id',
                        'name' => 'name',
                    ],
                ],
            ],
            'submit' => [
                'title' => $this->l('Opslaan'),
                'class' => 'btn btn-default pull-right',
            ],
        ];

        $helper = new HelperForm();

        $helper->module = $this;
        $helper->name_controller = $this->name;
        $helper->token = Tools::getAdminTokenLite('AdminModules');
        $helper->currentIndex = AdminController::$currentIndex . '&configure=' . $this->name;
        $helper->default_form_language = $default_lang;
        $helper->allow_employee_form_lang = $default_lang;

        $helper->title = $this->displayName;
        $helper->show_toolbar = false;
        $helper->submit_action = 'submit_postcodewizard';
        $helper->fields_value['POSTCODEWIZARD_API_KEY'] = Configuration::get('POSTCODEWIZARD_API_KEY');
        $helper->fields_value['POSTCODEWIZARD_MODE'] = Configuration::get('POSTCODEWIZARD_MODE');

        return $helper->generateForm($fields_form);
    }
}
