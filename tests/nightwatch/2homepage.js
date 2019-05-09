module.exports = {
  'Login test': (browser) => {
    browser
      .url('http://localhost/passhub-cse/login.php')
      // .url('https://one1.passhub.net/login.php')
      // you have 20 seconds to scan QR code
      .waitForElementVisible('.is-table-row', 20000)
      .waitForElementVisible('.search_switch')
      .waitForElementVisible('a[href="help.php"]')
      .waitForElementVisible('a[href="feedback.php"]')
      .waitForElementVisible('a[href="logout.php"]')
      .pause(500)
      .waitForElementVisible('[data-target="#newSafe"]')
      .pause(500)
      .click('.btn.btn-vault')
      .waitForElementVisible('#SafeName_create')
      .setValue('#SafeName_create', 'nightwatch')
      .click('#create_new_vault_btn')
      .waitForElementNotVisible('#SafeName_create')
      .pause(2000)

      .waitForElementVisible('.hidden-xs.list-item-vault-active')
      .moveToElement('.hidden-xs.list-item-vault-active', 20, 10)
      .mouseButtonClick('left')
      .useXpath()
      .waitForElementVisible("//ul[contains(@class, 'safeMenu')]/li/span[text()='Users']")
      .click("//ul[contains(@class, 'safeMenu')]/li/span[text()='Users']")
      .useCss()
      .waitForElementVisible('#UserList')
      .pause(1000)
      .getText('#UserList span', (result) => {
        let verdict = false;
        if (result.value.search('Safe is not shared') !== -1) {
          verdict = true;
        } else if ((result.value.search('\(you\)') !== -1)) {
          verdict = true;
        }
// :nth-of-type(1)          
//          && (result.value.search('admin') !== -1)) {
//          verdict = true;
//        }
        console.log(result.value);
//        browser.pause();
        browser.assert.equal(verdict, true);
      })
      .click('#safeUsers .modal-footer button')
      .pause(1000)

      // add folder
      .click('.add_item_button')
      .useXpath()
      .waitForElementVisible("//ul[contains(@class, 'addItemButtonMenu')]/li/span[text()='Folder']")
      .click("//ul[contains(@class, 'addItemButtonMenu')]/li/span[text()='Folder']")
      .useCss()
      .waitForElementVisible('#newFolderName')
      .setValue('#newFolderName', '<b>night</b>Folder')
      .click('#newFolderBtn')
      .pause(1000)
/*
      .waitForElementVisible('.hidden-xs.list-item-vault.folder-click')
      .getText('.hidden-xs.list-item-vault.folder-click', (result) => {
        let target = '&lt;b&gt;night&lt;/b&gt;Folder';
        browser.assert.equal(result, target);
      })
      .pause()
*/
      /*
      // cancel adding Login entry
      .click('.add_item_button')
      .useXpath()
      .waitForElementVisible("//ul[contains(@class, 'addItemButtonMenu')]/li/span[text()='Login Entry']")
      .click("//ul[contains(@class, 'addItemButtonMenu')]/li/span[text()='Login Entry']")
      .useCss()
      .waitForElementVisible('#entry_form')
      .click('#cancel_button')
      .pause(2000)
      */

      // Rename safe
      .waitForElementVisible('.hidden-xs.list-item-vault-active')
      .moveToElement('.hidden-xs.list-item-vault-active', 20, 10)
      .mouseButtonClick('left')
      .useXpath()
      .waitForElementVisible("//ul[contains(@class, 'safeMenu')]/li/span[text()='Rename']")
      .click("//ul[contains(@class, 'safeMenu')]/li/span[text()='Rename']")
      .useCss()
      .waitForElementVisible('#SafeName_rename')
      .getValue('#SafeName_rename', (theValue) => {
        console.log(JSON.stringify(theValue));
        browser.assert.equal(theValue.value, 'nightwatch');
      })
      .clearValue('#SafeName_rename')
      .setValue('#SafeName_rename', 'daywatch')
      .click('#id_SafeRename_button')
      .pause(1000)

      // delete non-empty safe
      .waitForElementVisible('.hidden-xs.list-item-vault-active')
      .moveToElement('.hidden-xs.list-item-vault-active', 20, 10)
      .mouseButtonClick('left')
      .useXpath()
      .waitForElementVisible("//ul[contains(@class, 'safeMenu')]/li/span[text()='Delete']")
      .click("//ul[contains(@class, 'safeMenu')]/li/span[text()='Delete']")
      .useCss()
      .waitForElementVisible('#delete_safe_warning')
      .waitForElementVisible('#deleteSafeBtn')
      .click('#deleteSafeBtn')
      .waitForElementVisible('#not_empty_safe_warning')
      .click('#deleteSafeBtn')
      .waitForElementVisible('#not_empty_safe_stats')
      .waitForElementVisible('#deleteSafeCloseBtn')
      .click('#deleteSafeCloseBtn')
      .end();
  },
};

// .click('xpath', "//tr[@data-recordid]/td/div/span[text()='Search Text']")
// <button type="button" class="btn btn-primary " id="id_SafeRename_button" style="font-size: 14px;">Save</button>