import { updateTicket } from 'wwpass-frontend';
import $ from 'jquery';

let idleStart = new Date() / 1000;

function restartIdleTimers() {
  idleStart = new Date() / 1000;
}

function initTimers(times) {

  const maxTicketAge = times.ttl / 2 + 30;
  let ticketTimeStamp = new Date() / 1000 - times.ticketAge;

  document.onclick = () => {
    idleStart = new Date() / 1000;
  };
  document.onkeypress = () => {
    idleStart = new Date() / 1000;
  };

  function CheckIdleTime() {
    const secondsNow = new Date() / 1000;

    if ((secondsNow - ticketTimeStamp) > maxTicketAge) {
      ticketTimeStamp = new Date() / 1000;
      updateTicket('update_ticket.php');
    }
    if (((secondsNow - idleStart) >= times.idleTimeout) && !$('#idleModalLabel').is('visible')) {
      $('#idleModal').modal('show');
    }

    if ((secondsNow - idleStart) >= times.idleTimeout + 60) {
      document.location.href = 'logout.php';
    }
  }

  if (times.idleTimeout > 0) {
    window.setInterval(CheckIdleTime, 1000);
  }
}

// hack, sorry
if (typeof timersArgs !== "undefined") {
  initTimers(timersArgs);
}

export {restartIdleTimers}
