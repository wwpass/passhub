module.exports = {
  'Login test' : function (browser) {
    browser
      .url('http://localhost/passhub-cse/login.php')
//      .url('https://one1.passhub.net/login.php')
//      .url('https://brillianta.passhub.net/login.php')
      .waitForElementVisible('body')
      .waitForElementVisible('.button--more')
      .waitForElementVisible('.heading--getStarted.button--getStarted')
      .click('.heading--getStarted.button--getStarted')
      .waitForElementVisible('.popup.landingContent--instruction')
      .waitForElementVisible('.button--store-ios')
      .waitForElementVisible('.button--store-google')
      .pause(500)
      .click('.landingContent--instruction .landingContent__icon--close')
      .waitForElementNotVisible('.popup.landingContent--instruction')
      .click('.button--more')
      .waitForElementVisible('.button--wide.button--getStarted')
      .click('.button--wide.button--getStarted')
      .waitForElementVisible('.popup.landingContent--instruction')
      .pause(500)
      .click('.landingContent--instruction .landingContent__icon--close')
      .waitForElementNotVisible('.popup.landingContent--instruction')
      .waitForElementVisible('.landingContent__icon--play')
      .click('.landingContent__icon--play')
      .waitForElementVisible('.promo__video--close')
      .pause(1000)
      .click('.promo__video--close')
      .waitForElementNotPresent('.promo__video--close')
      .pause(1000)
      .end();
  }
};
