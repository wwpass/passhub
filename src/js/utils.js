function serverLog(msg) {
  /*
  $.ajax({
    url: 'serverlog.php',
    type: 'POST',
    data: {
    //  verifier: csrf,
      msg,
    },
    error: () => {},
    success: () => {},
  });
  */
}


/* canonical react code 
function serverLog(msg) {
  const data = {
    verifier: getVerifier(),
    msg
  };

  axios
  .post(`${getApiUrl()}serverlog.php`, data)
  .then(reply => {
    // do nothig, one way  
  })
  .catch(err => {
    // do nothig, one way  
  })
}
*/ 

export {
  serverLog,
};
