<?php
/**
 * PostcodeWizard Module
 *
 * @author    FlowEngine
 * @copyright Copyright (c) 2025 FlowEngine
 * @license   https://opensource.org/licenses/MIT MIT License
 */
if (!defined('_PS_VERSION_')) {
    exit;
}
class PostcodeWizardHelper
{
    public static function getApiEndpoint()
    {
        return 'https://api.postcodewizard.nl/';
    }

    public static function getApiKey()
    {
        return Configuration::get('POSTCODEWIZARD_API_KEY');
    }

    public static function getMode()
    {
        return Configuration::get('POSTCODEWIZARD_MODE');
    }

    public static function isAutocompleteMode()
    {
        return self::getMode() === 'autocomplete';
    }

    public static function isLookupMode()
    {
        return self::getMode() === 'lookup';
    }
}
