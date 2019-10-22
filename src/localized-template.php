<?php

require_once('template.php');
require_once('lang/langhelper.php');

define('JSON_TO_ASSOCIATIVE_ARRAY', TRUE);
define('LIST_OF_KEYS_TO_LOCALIZE', array(
    'title',
    'heading',
    'h1_text',
    'advise',
    'incompatible_browser',
    'iOS_device'
));

class LocalizedTemplate
{
    private $template = NULL;
    private $pathFinder = NULL;
    private $localzedStrings = array();

    function __construct($template_path)
    {
        $this->pathFinder = Pathfinder::createDefault();
        $this->template = Template::factory($this->pathFinder->getPath($template_path));
        
        $localizedStringsPath = $this->pathFinder->getPath('localized_strings.json');
        if (file_exists($localizedStringsPath)) {
            $this->localzedStrings = json_decode(
                file_get_contents($localizedStringsPath),
                JSON_TO_ASSOCIATIVE_ARRAY
            );
        }
    }

    private function _getLocalizedStringFor($value) {
        $localizedValue = $value;
        if (array_key_exists($value, $this->localzedStrings)) {
            $localizedValue = $this->localzedStrings[$value];
        } else {
            if (defined('REPORT_MISSING_LOCALIZATION_STRINGS') and
                REPORT_MISSING_LOCALIZATION_STRINGS
            ) {
                trigger_error('Localized value not found: "'. $value . '"' . PHP_EOL, E_USER_NOTICE);
            }
        }

        return $localizedValue;
    }

    public function add($name, $value) {
        if (count($this->localzedStrings) != 0 and in_array($name, LIST_OF_KEYS_TO_LOCALIZE)) {
            $value = $this->_getLocalizedStringFor($value);
        }

        $this->template->add($name, $value);

        return $this;
    }

    public function generate() {
        return $this->template->generate();
    }

    public function render() {
        $this->template->render();
    }


    static function factory($template) {
        return new LocalizedTemplate($template);
    }
}
