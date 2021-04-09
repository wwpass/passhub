import {restartIdleTimers} from './timers'

let extensionId = 'bamjbfhfacpdkenilcibkmpdahkgfejh';
if (location.hostname === 'localhost') {
    extensionId = 'iiohcppkjekblgemjlbodlddgpbkdmdl';
}

if (navigator.userAgent.includes('Firefox')) {
    extensionId = 'b0549b956aa92bcf1ac541af73735f7b1b3bfff0@temporary-addon';
} 

function sendAdvise(s) {
    console.log('sendAdvise');
    console.log(s);
    try {
        chrome.runtime.sendMessage(extensionId, s,
        function(response) {
            if(!response) {
                if(chrome.runtime.lastError) {
                    console.log(chrome.runtime.lastError);
                } else  {
                    console.log('no response');
                }
            } else {
                console.log('sendAdvise got response');
                console.log(response);
            }
        });
    } catch(err) {
        console.log(err)
    }
};

let extensionPort;

function connect(findCb) {
    if ( typeof chrome == 'undefined') {
        return; 
    }

    try {
        extensionPort = chrome.runtime.connect(extensionId);
        extensionPort.onDisconnect.addListener((p) => {
            // FF way:
            /*if (p.error) {
                console.log(`Disconnected due to an error: ${p.error.message}`);
            }*/
            // Chrome 
            if(chrome.runtime.lastError) {
                console.log('Connection rintime.error');
                console.log(chrome.runtime.lastError);
            }
            extensionPort = null;
            console.log('disConnected');
        });
        extensionPort.onMessage.addListener(function(message,sender){
            restartIdleTimers();
            console.log('received');
            console.log(message);
            if(message.id === 'find') {
                try {
                    const loc = new URL(message.url);
                    const hostname = loc.hostname;
                    let advise = {
                        id: 'advise', 
                        hostname,
                        found: findCb(message.url)
                    };
                    // console.log(advise);
                    // extensionPort.postMessage(advise);
                    sendAdvise(advise);
                } catch(err) {
                    console.log('url error');
                    console.log(message.url);
                }
            }
        });
    } catch(err) {
        console.log(err)
    }
};

function sendCredentials(s) {
    // console.log('sendCredentials');
    // console.log(s);

    let url = s.url;
    if (url.search('://') == -1) {
        url = `http://${url}`;
    }
    s.url = url;

    if ( typeof chrome != 'undefined') {
        try {
            chrome.runtime.sendMessage(extensionId, s,
            function(response) {
                if(!response) {
                    if(chrome.runtime.lastError) {
                        console.log(chrome.runtime.lastError);
                    } else  {
                        console.log('no response');
                    }

                    window.open(url, "_blank");
                } else {
                    console.log(response);
                }
            });
        } catch(err) {
            console.log(err)
        }
    } else {
        window.open(url, "_blank");
    }
};

export {connect, sendCredentials, sendAdvise}
