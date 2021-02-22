import $ from 'jquery';
import forge from 'node-forge';
import passhubCrypto from './crypto';
import passhub from './passhub';

function doRestoreXML(text) {
  // see https://gist.github.com/chinchang/8106a82c56ad007e27b1
  function xmlToJson(xml) {
    // Create the return object
    let obj = {};
    if (xml.nodeType == 1) { // element
      // do attributes
      if (xml.attributes.length > 0) {
        obj['@attributes'] = {};
        for (let j = 0; j < xml.attributes.length; j++) {
          const attribute = xml.attributes.item(j);
          obj['@attributes'][attribute.nodeName] = attribute.nodeValue;
        }
      }
    } else if (xml.nodeType == 3) { // text
      obj = xml.nodeValue;
    }

    // do children
    // If just one text node inside
    if (xml.hasChildNodes() && xml.childNodes.length === 1 && xml.childNodes[0].nodeType === 3) {
      obj = xml.childNodes[0].nodeValue;
    } else if (xml.hasChildNodes()) {
      for (let i = 0; i < xml.childNodes.length; i++) {
        const item = xml.childNodes.item(i);
        const { nodeName } = item;
        if (typeof obj[nodeName] == 'undefined') {
          if (item.nodeType != 3) {
            obj[nodeName] = xmlToJson(item);
          }
        } else {
          if (typeof obj[nodeName].push == 'undefined') {
            const old = obj[nodeName];
            obj[nodeName] = [];
            obj[nodeName].push(old);
          }
          obj[nodeName].push(xmlToJson(item));
        }
      }
    }
    return obj;
  }

  function make_array(property) {
    if (property === undefined) {
      return [];
    }
    if (Array.isArray(property)) {
      return property;
    }
    const result = [];
    result.push(property);
    return result;
  }

  function importEntry(entry) {
    const result = {};
    const strings = make_array(entry.String);
    for (let s = 0; s < strings.length; s++) {
      if ((strings[s].Key !== undefined) && (strings[s].Value !== undefined)) {
        result[strings[s].Key] = strings[s].Value;
      }
    }
    const cleartext = [
      (typeof result.Title === 'string') ? result.Title : 'unnamed',
      (typeof result.UserName === 'string') ? result.UserName : '',
      (typeof result.Password === 'string') ? result.Password : '',
      (typeof result.URL === 'string') ? result.URL : '',
      (typeof result.Notes === 'string') ? result.Notes : '',
    ];
    if (typeof result.TOTP === 'string') {
      cleartext.push(result.TOTP);
    }

    check_limits_on_import(cleartext); // raises exception

    const options = {};
    if ((typeof result.Note === 'string') && (result.Note === '1')) {
      options.note = 1;
    }
    if (entry.hasOwnProperty('Times') && entry.Times.hasOwnProperty('LastModificationTime')) {
      options.lastModified = entry.Times.LastModificationTime;
    }
    return { cleartext, options };
  }

  function getEntries(group) {
    const entries = make_array(group.Entry);
    const result = [];
    for (let e = 0; e < entries.length; e++) {
      result.push(importEntry(entries[e]));
    }
    return result;
  }

  function getFolders(group) {
    const groups = make_array(group.Group);
    const result = [];
    for (let i = 0; i < groups.length; i++) {
      result.push(importGroup(groups[i]));
    }
    return result;
  }

  function importGroup(group) {
    return {
      name: group.Name,
      entries: getEntries(group),
      folders: getFolders(group),
    };
  }

  const xmlDoc = $.parseXML(text);

  if (xmlDoc.firstElementChild.nodeName === 'KeePassFile') {
    const kpf = xmlDoc.firstElementChild;
    const result = xmlToJson(kpf);

    if ((result.Root.Group === undefined) || (Array.isArray(result.Root.Group) == true)) {
      throw new Error('not a KeePass or Passhub XML file');
    }
    const imported = importGroup(result.Root.Group);
    imported.name = result.Root.Group.Name;
    if (imported.name === undefined) {
      imported.name = 'KeePass';
    }
    return imported;
  }
  throw new Error('not a KeePass or Passhub XML file');
}

function check_limits_on_import(entry) {
  if (entry[0].length > 100) {
    throw new Error(`Too long title: ${entry[0]}`);
  }
  if (entry[1].length > 100) {
    throw new Error(`Too long username: ${entry[1]}`);
  }
  if (entry[2].length > 100) {
    throw new Error(`Too long password: ${entry[2]}`);
  }
  if (entry[3].length > 2048) {
    throw new Error(`Too long URL: ${entry[3]}`);
  }
  if (entry[4].length > 10000) {
    throw new Error(`{Too long notes: ${entry[4]}`);
  }
}

function doRestoreCSV(text) {
  // folders - array of folders at current level; path  - at the same level
  function findFolder(folders, path) {
    for (let f = 0; f < folders.length; f++) {
      if (folders[f].name == path[0]) {
        if (path.length === 1) {
          return folders[f];
        }
        path.shift();
        return findFolder(folders[f].folders, path);
      }
    }
    // not found
    const folder = { name: path[0], folders: [], entries: [] };
    folders.push(folder);
    if (path.length === 1) {
      return folder;
    }
    path.shift();
    return findFolder(folder.folders, path);
  }

  function addRecordToSafe(safe, record, path) {
    if (path.length === 0) {
      safe.entries.push(record);
      return;
    }
    const folder = findFolder(safe.folders, path);
    folder.entries.push(record);
  }

  function addRecord(safes, r) {
    const path = r.shift().split('/');
    check_limits_on_import(r); // raises exception
    for (let s = 0; s < safes.length; s++) {
      if (safes[s].name == path[0]) {
        path.shift();
        addRecordToSafe(safes[s], { cleartext: r, options: {} }, path);
        return;
      }
    }
    // no such safe
    const safe = { name: path[0], folders: [], entries: [] };
    safes.push(safe);
    path.shift();
    addRecordToSafe(safe, { cleartext: r, options: {} }, path);
  }
  let data;
  try {
    data = $.csv.toArrays(text);
  } catch (err) {
    throw new Error(`Corrupted or not a CSV file. ${err}`);
  }
  if (data.length < 2) {
    throw new Error('Bad file format: too short');
  }


  const safes = [];

  const titles = data.shift();

  if (titles.length === 1) { // dashline?
    const t = data.shift();
    data.unshift(t);
    if (t.length === 7) {
      data.forEach((e) => {
        if (e.length === 7) {
          const e1 = [titles[0], e[0], e[2], e[5], e[1], e[6]];
          addRecord(safes, e1);
        }
      });
      return safes;
    }
  }

  // url,username,password,extra,name,grouping,fav -- lastpass
  if ((titles.length === 7)
    && (titles[0] === 'url')
    && (titles[1] === 'username')
    && (titles[2] === 'password')
    && (titles[3] === 'extra')
    && (titles[4] === 'name')
    && (titles[5] === 'grouping')
    && (titles[6] === 'fav')) {

    data.forEach((e) => {
      addRecord(safes, ['lastpass', e[4], e[1], e[2], e[0], e[3]]);
    });
    return safes;
  }

  if ((titles.length === 4) // chrome
    && (titles[0] === 'name')
    && (titles[1] === 'url')
    && (titles[2] === 'username')
    && (titles[3] === 'password')) {
    // chrome
    data.forEach((e) => {
      const e1 = ['chrome', e[0], e[2], e[3], e[1], ''];
      addRecord(safes, e1);
    });
    return safes;
  }

  if ((titles.length === 9) // firefox
    && (titles[0] === 'url')
    && (titles[1] === 'username')
    && (titles[2] === 'password')
    && (titles[3] === 'httpRealm')
    && (titles[4] === 'formActionOrigin')
    && (titles[5] === 'guid')
    && (titles[6] === 'timeCreated')
    && (titles[7] === 'timeLastUsed')
    && (titles[8] === 'timePasswordChanged')
    ) {
    // firefox
    data.forEach((e) => {
      const url = new URL(e[0]);
      const hostname = url.hostname;
      const e1 = ['firefox', hostname, e[1], e[2], e[0], ''];
      addRecord(safes, e1);
    });
    return safes;
  }
  
    if (titles.length !== 6) {
    throw new Error('Unknown file format');
  }
  // KeePassX
  data.forEach((e) => {
    addRecord(safes, e);
  });
  return safes;
}

/*
function encrypt_safes(safes, aes_key) {
  const safes_encrypted = [];
  for (let s = 0; s < safes.length; s++) {
    const safe_enc = { name: encode_folder_name_GCM(safes[s].name, aes_key), entries: [] };
    for (let i = 0; i < safes[s].entries.length; i++) {
      safe_enc.entries.push(encode_item(safes[s].entries[i], aes_key));
    }
    safe_enc.folders = encrypt_safes(safes[s].folders, aes_key);
    safes_encrypted.push(safe_enc);
  }
  return safes_encrypted;
}
*/
//----------------------------------------------------------

// import/restore functions
// encrypt folder tree
// { name'':, entries:[], folders[] }

function encrypt_folder(folder, aes_key) {
  const result = { entries: [], folders: [] };
  if (folder.hasOwnProperty('name')) { // new, imported
    result.name = passhubCrypto.encryptFolderName(folder.name, aes_key);
  }
  if (folder.hasOwnProperty('_id')) { // merged, restore
    result._id = folder._id;
  }
  for (let e = 0; e < folder.entries.length; e++) {
    result.entries.push(passhubCrypto.encryptItem(folder.entries[e].cleartext, aes_key, folder.entries[e].options));
  }
  for (let f = 0; f < folder.folders.length; f++) {
    result.folders.push(encrypt_folder(folder.folders[f], aes_key));
  }
  return result;
}

// converts folder into a safe: actually top-level folder with id 0 and a key
// {          name'':, entries:[], folders[] } to
// { key: '', name'':, entries:[], folders[] }

function createSafeFromFolder(folder, publicKey) {
  const aes_key = forge.random.getBytesSync(32);
  const encrypted_aes_key = publicKey.encrypt(aes_key, 'RSA-OAEP');
  const hex_encrypted_aes_key = forge.util.bytesToHex(encrypted_aes_key);
  const result = {};
  result.key = hex_encrypted_aes_key;
  result.name = folder.name;
  result.entries = [];
  for (let e = 0; e < folder.entries.length; e++) {
    result.entries.push(passhubCrypto.encryptItem(folder.entries[e].cleartext, aes_key, folder.entries[e].options));
  }
  result.folders = [];
  if ('folders' in folder) {
    for (let f = 0; f < folder.folders.length; f++) {
      result.folders.push(encrypt_folder(folder.folders[f], aes_key));
    }
  }
  return result;
}

function makeTree(flatSafe) {
  function getItems(folder_id, items) {
    const result = [];
    for (let i = 0; i < items.length; i++) {
      if (items[i].hasOwnProperty('folder')) {
        if (items[i].folder == folder_id) {
          result.push(items[i]);
        }
      } else if (folder_id == 0) {
        result.push(items[i]);
      }
    }
    return result;
  }

  function getFolders(folder_id, folders, items) {
    const result = [];
    for (let i = 0; i < folders.length; i++) {
      if (folders[i].parent == folder_id) {
        folders[i].items = getItems(folders[i]._id, items);
        folders[i].folders = getFolders(folders[i]._id, folders, items);
        result.push(folders[i]);
      }
    }
    return result;
  }

  const result = { id: flatSafe.id, key: flatSafe.key, name: flatSafe.name };

  result.items = getItems(0, flatSafe.items);
  result.folders = getFolders(0, flatSafe.folders, flatSafe.items);
  result.aes_key = flatSafe.key;
  return result;
}

function mergeSafe(site, backup) {

  function mergeItems(site, backup) {
    function itemStatus(backup_item) {
      let found = false;
      for (let s = 0; s < site.items.length; s++) {
        const { cleartext } = site.items[s];
        if (cleartext[0] == backup_item.cleartext[0]) {
          if ((cleartext[1] == backup_item.cleartext[1]) 
            && (cleartext[2] == backup_item.cleartext[2])
            && (cleartext[3] == backup_item.cleartext[3]) 
            && (cleartext[4] == backup_item.cleartext[4])) {
            return 'equal';
          }
          found = true; // may be next with the same name
        }
      }
      return found ? 'different' : 'absent';
    }
    const result = [];
    for (let b = 0; b < backup.entries.length; b++) {
      const status = itemStatus(backup.entries[b]);
      if (status === 'absent') {
        result.push(backup.entries[b]); // cipher
        continue;
      } else if (status === 'equal') {
        continue;
      }
      const title = backup.entries[b].cleartext[0];
      for (let n = 1; ; n++) {
        if (n > 100) {
          alert('error 601');
          break;
        }
        backup.entries[b].cleartext[0] = `${title}(${n})`;
        const status1 = itemStatus(backup.entries[b]);
        if (status1 === 'absent') {
          result.push(backup.entries[b]); // cipher
          break;
        }
        if (status1 === 'equal') {
          break;
        }
      }
    }
    return result;
  }

  function mergeFolders(site, backup) {
    const result = [];
    if ('folders' in backup) {
      for (let b = 0; b < backup.folders.length; b++) {
        let found = false;
        for (let s = 0; s < site.folders.length; s++) {
          if (backup.folders[b].name == site.folders[s].cleartext[0]) {
            found = true;
            const items = mergeItems(site.folders[s], backup.folders[b]);
            const folders = mergeFolders(site.folders[s], backup.folders[b]);
            if ((items.length !== 0) || (folders.length !== 0)) {
              result.push({ _id: site.folders[s]._id, entries: items, folders }); // add folder name and id
            }
          }
        }
        if (!found) {
          result.push(backup.folders[b]);
        }
      }
    }
    return result;
  }

  const new_items = mergeItems(site, backup);
  const new_folders = mergeFolders(site, backup);
  if ((new_items.length === 0) && (new_folders.length === 0)) {
    return null;
  }

  return { id: site.id, items: new_items, folders: new_folders, eAesKey: site.aes_key };
  /*
  // cipher it!
  const result = { items: [], folders: [] };

  const aes_key = site.aes_key;
  for (let e = 0; e < new_items.length; e++) {
    result.items.push(encode_item(new_items[e].cleartext, aes_key, new_items[e].options));
  }
  result.folders = [];
  for (let f = 0; f < new_folders.length; f++) {
    result.folders.push(encrypt_folder(new_folders[f], aes_key));
  }
  return { id: site.id, entries: result.items, folders: result.folders };
  */
}

function merge_safes(site, backup) {
  function merge_items(site, backup) {
    function item_status(backup_item) {
      let found = false;
      for (let s = 0; s < site.items.length; s++) {
        const cleartext = site.items[s].cleartext;
        if (cleartext[0] == backup_item.cleartext[0]) {
          if ((cleartext[1] == backup_item.cleartext[1]) 
            && (cleartext[2] == backup_item.cleartext[2])
            && (cleartext[3] == backup_item.cleartext[3]) 
            && (cleartext[4] == backup_item.cleartext[4])) {
            return 'equal';
          }
          found = true; // may be next with the same name
        }
      }
      return found ? 'different' : 'absent';
    }
    const result = [];
    for (let b = 0; b < backup.entries.length; b++) {
      const status = item_status(backup.entries[b]);
      if (status === 'absent') {
        result.push(backup.entries[b]); // cipher
        continue;
      } else if (status === 'equal') {
        continue;
      }
      const title = backup.entries[b].cleartext[0];
      for (let n = 1; ; n++) {
        if (n > 100) {
          alert('error 601');
          break;
        }
        backup.entries[b].cleartext[0] = `${title}(${n})`;
        const status1 = item_status(backup.entries[b]);
        if (status1 === 'absent') {
          result.push(backup.entries[b]); // cipher
          break;
        }
        if (status1 === 'equal') {
          break;
        }
      }
    }
    return result;
  }

  function merge_folders(site, backup) {
    const result = [];
    if ('folders' in backup) {
      for (let b = 0; b < backup.folders.length; b++) {
        let found = false;
        for (let s = 0; s < site.folders.length; s++) {
          if (backup.folders[b].name == site.folders[s].cleartext[0]) {
            found = true;
            const items = merge_items(site.folders[s], backup.folders[b]);
            const folders = merge_folders(site.folders[s], backup.folders[b]);
            if ((items.length !== 0) || (folders.length !== 0)) {
              result.push({ _id: site.folders[s]._id, entries: items, folders }); // add folder name and id
            }
          }
        }
        if (!found) {
          result.push(backup.folders[b]);
        }
      }
    }
    return result;
  }

  const new_items = merge_items(site, backup);
  const new_folders = merge_folders(site, backup);
  if ((new_items.length === 0) && (new_folders.length === 0)) {
    return null;
  }
  // cipher it!
  const result = { items: [], folders: [] };

  const aes_key = site.aes_key;
  for (let e = 0; e < new_items.length; e++) {
    result.items.push(encode_item(new_items[e].cleartext, aes_key, new_items[e].options));
  }
  result.folders = [];
  for (let f = 0; f < new_folders.length; f++) {
    result.folders.push(encrypt_folder(new_folders[f], aes_key));
  }
  return { id: site.id, entries: result.items, folders: result.folders };
}

function findSafeByName(safes, name) {
  for (let i = 0; i < safes.length; i++) {
    if (safes[i].name === name) {
      return safes[i];
    }
  }
  return null;
}

function encryptAdditions(safeAdditions, aesKey) {
  const result = { items: [], folders: [] };

  // const aes_key = site.aes_key;
  const newItems = safeAdditions.items; 
  for (let e = 0; e < newItems.length; e++) {
    result.items.push(passhubCrypto.encryptItem(newItems[e].cleartext, aesKey, newItems[e].options));
  }
  const newFolders = safeAdditions.folders;
  for (let f = 0; f < newFolders.length; f++) {
    result.folders.push(encrypt_folder(newFolders[f], aesKey));
  }
  return { id: safeAdditions.id, entries: result.items, folders: result.folders };
}

function importMerge(importedFolders, flatSafeArray, publicKey) {
  return passhub.decryptSafes(flatSafeArray)
    .then(() => {
      const restoreDiff = [];
      const encryptionPromises = [];
      for (let f = 0; f < importedFolders.length; f++) {
        const siteSafe = findSafeByName(flatSafeArray, importedFolders[f].name);
        if (!siteSafe) {
          restoreDiff.push(createSafeFromFolder(importedFolders[f], publicKey));
          continue;
        }
        const safeAdditions = mergeSafe(makeTree(siteSafe), importedFolders[f]);
        if (!safeAdditions) {
          continue;
        }
        encryptionPromises.push(
          passhubCrypto.decryptAesKey(siteSafe.key)
            .then((pKey) => {
              const eSafe = encryptAdditions(safeAdditions, pKey);
              return restoreDiff.push(eSafe);
            }),
        );
      }
      if (encryptionPromises.length) {
        return Promise.all(encryptionPromises)
          .then(() => restoreDiff);
      }
      return restoreDiff;
    });
}

function importTemplate(templateFolders, publicKey) {
  const result = [];
  for (let f = 0; f < templateFolders.length; f++) {
    result.push(createSafeFromFolder(templateFolders[f], publicKey));
  }
  return result;
}

export {
  doRestoreXML,
  doRestoreCSV,
  createSafeFromFolder,
  importMerge,
  importTemplate,
};

/*

function importMerge1(importedFolders, flatSafeArray, publicKey) {
  const restoreDiff = [];
  for (let f = 0; f < importedFolders.length; f++) {
    let found = false;
    for (let s = 0; s < flatSafeArray.length; s++) {
      if (flatSafeArray[s].name == importedFolders[f].name) {
        const safe = impex_decodeAndMakeTree(flatSafeArray[s]);
        found = true;
        const mergedSafe = merge_safes(safe, importedFolders[f]);
        if (mergedSafe != null) {
          restoreDiff.push(mergedSafe);
        }
      }
    }
    if (!found) {
      restoreDiff.push(createSafeFromFolder(importedFolders[f], publicKey));
    }
  }
  return restoreDiff;
}
function impex_decodeAndMakeTree(flatSafe) {
  function getItems(folder_id, items) {
    const result = [];
    for (let i = 0; i < items.length; i++) {
      if (items[i].hasOwnProperty('folder')) {
        if (items[i].folder == folder_id) {
          result.push(items[i]);
        }
      } else if (folder_id == 0) {
        result.push(items[i]);
      }
    }
    return result;
  }

  function getFolders(folder_id, folders, items) {
    const result = [];
    for (let i = 0; i < folders.length; i++) {
      if (folders[i].parent == folder_id) {
        folders[i].items = getItems(folders[i]._id, items);
        folders[i].folders = getFolders(folders[i]._id, folders, items);
        result.push(folders[i]);
      }
    }
    return result;
  }

  const result = { id: flatSafe.id, key: flatSafe.key, name: flatSafe.name };
  const encrypted_aes_key = forge.util.hexToBytes(flatSafe.key);
  const aes_key = forge_privateKey.decrypt(encrypted_aes_key, 'RSA-OAEP');

  for (let i = 0; i < flatSafe.items.length; i++) {
    flatSafe.items[i].cleartext = decode_item(flatSafe.items[i], aes_key);
  }
  for (let i = 0; i < flatSafe.folders.length; i++) {
    flatSafe.folders[i].cleartext = decode_folder(flatSafe.folders[i], aes_key);
  }

  result.items = getItems(0, flatSafe.items);
  result.folders = getFolders(0, flatSafe.folders, flatSafe.items);
  result.aes_key = flatSafe.aes_key;
  return result;
}
*/

