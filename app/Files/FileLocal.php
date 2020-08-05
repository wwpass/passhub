<?php

/**
 *
 * file.php
 *
 * PHP version 7
 *
 * @category  Password_Manager
 * @package   PassHub
 * @author    Mikhail Vysogorets <m.vysogorets@wwpass.com>
 * @copyright 2017-2018 WWPass
 * @license   http://opensource.org/licenses/mit-license.php The MIT License
 */

namespace PassHub\Files;

class FileLocal extends File
{
    protected function upload($content) {

        $fname = FILE_DIR . '/' . $this->name;
        if ($fh = fopen($fname, 'w')) {
            fwrite($fh, $content);
            fclose($fh);
            return ["status" => "Ok"];
        }
        return ["status" => "Error 24: file"];
    }

    protected function download() {

        $fname = FILE_DIR . '/' . $this->name;
        if ($fh = fopen($fname, 'r')) {
            $data = fread($fh, filesize($fname));
            fclose($fh);
            return ["status" => "Ok", "data" => $data];
        }
        return ["status" => "Fail"];
    }

    public function delete() {
        $fname = FILE_DIR . '/' . $this->name;
        $result = unlink($fname);
        if ($result) {
            return ["status" => "Ok"];
        }
        return ["status" => "Fail"];
    }
}
