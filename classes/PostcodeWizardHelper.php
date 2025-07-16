<?php

class PostcodeWizardHelper
{
    public static function getApiEndpoint() {
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
