module.exports = {
  'Error page test': (browser) => {
    browser
      .url('http://localhost/passhub-cse/error_page.php?js=387&error=Can%27t%20decrypt%20client%20key')
      .waitForElementVisible('.phub-dialog h1')
      .waitForElementVisible('.phub-dialog div p')
      .waitForElementVisible('.phub-dialog button')
      .pause(1000)
      .end();
  },
};
