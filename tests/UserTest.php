<?php
use PHPUnit\Framework\TestCase;

use \PassHub\User;
use \PassHub\DB;

define('DB_NAME', 'phub-unit');
define('MONGODB_CONNECTION_LINE', "mongodb://localhost:27017/" . DB_NAME);

define('WWPASS_LOGOUT_ON_KEY_REMOVAL', true);

/*
getEncryptedAesKey returns null if user has no access
Should we issue an Exception?
*/

class UserTest extends TestCase
{

    public $UserId8 = "5f2c641ac26c64688a7c4542";
    public $UserId11 = "5f2c6514c26c64688a7c4551";
    public $safeAdm = "5f2c641ac26c64688a7c4544";
    public $safeEditor = "5f2c641ac26c64688a7c454b";
    public $safeReadOnly = "5f2c641ac26c64688a7c454d";

    public $user11PrivateSafe = "5f2c6514c26c64688a7c4553";

    
    public function __construct() {
        $arg_list = func_get_args();
        parent::__construct(...$arg_list);
        $client = new \MongoDB\Client($uri = MONGODB_CONNECTION_LINE);
        $this->mng = $client->selectDatabase(DB_NAME);
        $this->user8 = new User($this->mng, $this->UserId8);
        $this->user11 = new User($this->mng, $this->UserId11);
        $this->notuser = new User($this->mng, "Y");
    }

    public function testGetUserRole()
    {
        $this->assertSame(User::ROLE_ADMINISTRATOR, $this->user11->getUserRole($this->safeAdm));
        $this->assertSame(User::ROLE_EDITOR, $this->user11->getUserRole($this->safeEditor));
        $this->assertSame(User::ROLE_READONLY, $this->user11->getUserRole($this->safeReadOnly));

        $this->assertSame(true, $this->user11->canRead($this->safeAdm));
        $this->assertSame(true, $this->user11->canRead($this->safeEditor));
        $this->assertSame(true, $this->user11->canRead($this->safeReadOnly));

        $this->assertSame(true, $this->user11->canWrite($this->safeAdm));
        $this->assertSame(true, $this->user11->canWrite($this->safeEditor));
        $this->assertSame(false, $this->user11->canWrite($this->safeReadOnly));
        
        $this->assertSame(true, $this->user11->isAdmin($this->safeAdm));
        $this->assertSame(false, $this->user11->isAdmin($this->safeEditor));
        $this->assertSame(false, $this->user11->isAdmin($this->safeReadOnly));

        $this->assertSame(false, $this->user8->getUserRole($this->user11PrivateSafe));
        $this->assertSame(false, $this->user8->isAdmin($this->user11PrivateSafe));
        $this->assertSame(false, $this->user8->canWrite($this->user11PrivateSafe));
        $this->assertSame(false, $this->user8->canRead($this->user11PrivateSafe));
    }

    public function testGetProfile()
    {
        unset($this->user11->profile);
        $this->user11->getProfile();
        $this->assertSame('object', gettype($this->user11->profile));

    }

    public function testGetProfileException()
    {
        $this->expectException(Exception::class);
        $this->notuser->getProfile();
    }

    public function testGetPublicKey()
    {
        unset($this->user11->profile);
        $pubKey = $this->user11->getPublicKey();
        $this->assertSame('string', gettype($pubKey));
    }

    public function testGetPublicKeyException()
    {
        $this->expectException(Exception::class);
        $this->notuser->getPublicKey();
    }


    public function testGetSafes()
    {
        $safes = $this->user11->getSafes();

        $this->assertSame('array', gettype($safes));
        $this->assertSame(true, count($safes) > 0);

        $safes = $this->notuser->getSafes();
        $this->assertSame(0, count($safes));
    }

    public function testGetData()
    {
        $_SESSION['wwpass_ticket'] = "theTicket";
        $data = $this->user11->getData();
        unset($_SESSION['wwpass_ticket']);

        $this->assertSame('array', gettype($data));
        $this->assertSame("Ok", $data["status"]);

    }

    public function testGetDataException() 
    {
        $this->expectException(Exception::class);
        $data = $this->notuser->getData();
    }

    public function testGetEncryptedAesKey()
    {
        $theKey = $this->user11->getEncryptedAesKey($this->safeAdm);
        $this->assertSame('string', gettype($theKey));

        $theKey = $this->user8->getEncryptedAesKey($this->user11PrivateSafe);
        $this->assertSame(null, $theKey);
    }
}
