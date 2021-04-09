<?php
use PHPUnit\Framework\TestCase;

use \PassHub\Safe;

class SafeTest extends TestCase
{

    public $UserId8 = "5f2c641ac26c64688a7c4542";
    public $UserId11 = "5f2c6514c26c64688a7c4551";
    public $safeAdm = "5f2c641ac26c64688a7c4544";
    public $safeEditor = "5f2c641ac26c64688a7c454b";
    public $safeReadOnly = "5f2c641ac26c64688a7c454d";

    public $user11PrivateSafe = "5f2c6514c26c64688a7c4553";

    public $safeName = "Example Safe";
    public $userRole = "administrator";
    public $encryptedKeyCSE = "qwerty";

    public $safe;

    public function testConstruct()
    {
        $initArray = [
            "SafeID" => $this->user11PrivateSafe,
            "SafeName" => $this->safeName,
            "UserID" => $this->UserId11,
            "UserName" => "",
            "role" => $this->userRole,
            "encrypted_key_CSE" => "qwerty",
        ];
        $this->safe = new Safe((object)$initArray);

        $this->assertSame($this->user11PrivateSafe, $this->safe->id);
        $this->assertSame($this->safeName, $this->safe->name);
        // private $this->assertSame($this->UserId11, $this->safe->user_id);
        $this->assertSame("", $this->safe->user_name);
        $this->assertSame($this->userRole, $this->safe->user_role);
        $this->assertSame(1, $this->safe->user_count);

        $this->assertSame(true, $this->safe->isConfirmed());

    }
}
