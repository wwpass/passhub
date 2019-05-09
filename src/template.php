<?php

/**
 * template.php
 *
 * PHP version 7
 *
 * @category  Password_Manager
 * @package   PassHub
 * @author    Vladimir Korshunov <v.korshunov@wwpass.com>
 * @copyright 2016-2018 WWPass
 * @license   http://opensource.org/licenses/mit-license.php The MIT License
 */

class Template
{
    private $variables;
    private $template;

    function __construct($template_path)
    {
        $this->template = realpath($template_path);
        $this->variables = array();
    }

    static function factory($template_path)
    {
        $template = realpath($template_path);

        if (file_exists($template)) {
            return new Template($template);
        }

        throw new Exception("No such file or directory $template_path", 1);
    }

    public function add($name, $value)
    {
        $this->variables[$name] = $value;
        return $this;
    }

    public function generate()
    {
        foreach ($this->variables as $key => $value) {
            $$key = $value;
        }

        ob_start();

        require $this->template;
        $template = ob_get_contents();

        ob_end_clean();

        return $template;
    }

    public function render()
    {
        echo $this->generate();
    }
}
