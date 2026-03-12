# WWPass: Client Side Encrytpion HOWTO


The procedure is very close to the basic WWPass authentication with some modifications.

Overall architecture:

- Javascript in the browser (in the module "wwpass-frontend") sends request to the server (`ticketURL`) to get a WWPass ticket. 

- server URL, e.g /getticket.php communicates with WWPass SP frontend using WWPass certificates and returns the ticket to the wwpass module in the browser. 
- On seccessful authentication the javacscript module returns results to the server using a `callbackURL`


1. Login page 


```javascript
  WWPass.authInit({
    qrcode: '#qrcode',
    passkey: document.querySelector('#button--login'),
    ticketURL: `${urlBase}getticket.php`,
    callbackURL: `${urlBase}login.php`,
  });
```


2. ticket URL 

    // getticket.php, the code shown is for version 4 of the "wwpass/apiclient" php module 

    Note `client_key => true` in the getTicket call  -- till now it is the only addition for client encryption 

```php
try {
    $version4 = (intval(explode('.', WWPass\Connection::VERSION)) > 3);

    if ($version4) {
        $wwc = new WWPass\Connection(
            ['key_file' => WWPASS_KEY_FILE, 
            'cert_file' => WWPASS_CERT_FILE, 
            'ca_file' => WWPASS_CA_FILE]
        );
        $ticket = $wwc->getTicket(
            ['pin' => $pin_required,
            'client_key' => true,
            'ttl' => WWPASS_TICKET_TTL]
        );
    } else {
        $wwc = new WWPass\Connection(WWPASS_KEY_FILE, WWPASS_CERT_FILE, WWPASS_CA_FILE);
        $ticket = $wwc->getTicket(WWPASS_TICKET_TTL, $pin_required?'pc':'c');
    }
} catch (WWPass\Exception $e) {
    $err_msg = 'Caught WWPass exception: ' . $e->getMessage();
//     ....
} catch (Exception $e) {
    $err_msg = 'Caught exception: ' . $e->getMessage();
    // ... 
    // return 500
}

// Prevent caching.
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 01 Jan 1996 00:00:00 GMT');

// The JSON standard MIME header.
header('Content-type: application/json');

if ($version4) {
    $data = $ticket;
} else {
    $data = array("ticket" => $ticket, "ttl" => WWPASS_TICKET_TTL);
}

// Send the data.
echo json_encode($data);

```



3. processinng callback in the callbackURL: `${urlBase}login.php`,

The authentication results are sent in a `GET` request. In case when the ticket is issued for client side encryption, it has a 'c' flag in its body
The Server side now exchanges this first ticket to a new one, receives PUID and goes to the index page (authenticated)


```php 

if (array_key_exists('wwp_status', $_REQUEST) && ( $_REQUEST['wwp_status'] != 200)) {
    $_SESSION = [];
} else if (array_key_exists('wwp_ticket', $_REQUEST)) {
    if ((strpos($_REQUEST['wwp_ticket'], ':c:') == false) 
        && (strpos($_REQUEST['wwp_ticket'], ':pc:') == false) 
        && (strpos($_REQUEST['wwp_ticket'], ':cp:') == false)
    ) {
        // do nothing
    } else {

        $ticket = $_REQUEST['wwp_ticket'];
        $pin_required = defined('WWPASS_PIN_REQUIRED') ? WWPASS_PIN_REQUIRED : false;    

        try {
            $version4 = (intval(explode('.', WWPass\Connection::VERSION)) > 3);

            if ($version4) {
                $wwc = new WWPass\Connection(
                    ['key_file' => WWPASS_KEY_FILE, 
                    'cert_file' => WWPASS_CERT_FILE, 
                    'ca_file' => WWPASS_CA_FILE]
                );
                $new_ticket = $wwc->putTicket(
                    ['ticket' => $ticket,
                    'pin' =>  $pin_required,
                    'client_key' => true,
                    'ttl' => WWPASS_TICKET_TTL]
                );

// you probably do not need the next two lines
                $_SESSION['wwpass_ticket'] = $new_ticket['ticket'];
                $_SESSION['wwpass_ticket_renewal_time'] = time() + $new_ticket['ttl'] / 2;

                $puid = $wwc->getPUID(['ticket' => $ticket]);
                $puid = $puid['puid']; 
               

            } else { // version 3
                $wwc = new WWPass\Connection(WWPASS_KEY_FILE, WWPASS_CERT_FILE, WWPASS_CA_FILE);
                $new_ticket = $wwc->putTicket($ticket, WWPASS_TICKET_TTL, $pin_required ? 'pc' : 'c');
                $_SESSION['wwpass_ticket'] = $new_ticket;
                $_SESSION['wwpass_ticket_renewal_time'] = time() + WWPASS_TICKET_TTL/2;
                $puid = $wwc->getPUID($ticket);
            }
            
            $_SESSION['PUID'] = $puid;

            $_SESSION['wwpass_ticket_creation_time'] = time();

            // wwp_hw flag signals that hardware key was used 
            if (!isset($_REQUEST['wwp_hw'])) {
                $_SESSION['PasskeyLite'] = true;
            }

            // the user is authenticated now
            header("Location: index.php");
            exit();

        }  catch (Exception $e) {
            // report error $e->getMessage()
        }
    }
}

```


4. script on the authenticated page receives the new ticket from the server and gets access to the client-side encrytpion key (cseKey).
   
The key itself is a WebCryto object

```javascript
  return WWPass.cryptoPromise
    .getWWPassCrypto(data.ticket, "AES-CBC")
    .then( cseKey => {
        // you got it
    })
```
    


