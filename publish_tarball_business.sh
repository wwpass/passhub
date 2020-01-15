#!/bin/bash

timestamp=$(date +%Y%m%d)

filelist=""
filelist+=" src/lang/"
filelist+=" config/config-sample.php"
filelist+=" config/template.xml"
filelist+=" config/wwpass_sp_ca.crt"
filelist+=" css/font/context-menu-icons.eot"
filelist+=" css/font/context-menu-icons.ttf"
filelist+=" css/font/context-menu-icons.woff"
filelist+=" css/font/context-menu-icons.woff2"
filelist+=" css/jquery.contextMenu.min.css"
filelist+=" css/bootstrap.min.css"
filelist+=" css/style-int.css"
filelist+=" css/style.css"
filelist+=" doc/images/AddButton.png"
filelist+=" doc/images/AddButtonMobile.png"
filelist+=" doc/images/BusinessShare.png"
filelist+=" doc/images/EntryMenu.png"
filelist+=" doc/images/EntryMenuMobile.png"
filelist+=" doc/images/ExportBusiness.png"
filelist+=" doc/images/ExportDialog.png"
filelist+=" doc/images/FolderMenuBusiness.png"
filelist+=" doc/images/FolderMenuMobile.png"
filelist+=" doc/images/IAM.png"
filelist+=" doc/images/ImportBusiness.png"
filelist+=" doc/images/ImportDialog.png"
filelist+=" doc/images/MobileOpenSafe.png"
filelist+=" doc/images/NewEntryDialog.png"
filelist+=" doc/images/NewFileDialog.png"
filelist+=" doc/images/NewNoteDialog.png"
filelist+=" doc/images/NewSafe.png"
filelist+=" doc/images/NewSafeButtonBusiness.png"
filelist+=" doc/images/WhiteList.png"
filelist+=" img/SVG2/sprite.svg"
filelist+=" img/landing/app_apple.svg"
filelist+=" img/landing/app_google.svg"
filelist+=" img/landing/bg_big.svg"
filelist+=" img/landing/bg_small.svg"
filelist+=" img/landing/logo.svg"
filelist+=" img/landing/pic_hand.svg"
filelist+=" img/landing/sprite.svg"
filelist+=" img/collapse_down.svg"
filelist+=" img/collapse_up.svg"
filelist+=" img/favicon.ico"
filelist+=" img/new_ph_logo.svg"
filelist+=" img/outline-https-24px.svg"
filelist+=" img/account.svg"
filelist+=" img/formentera-beach.jpeg"
filelist+=" js/dist/iam.js"
filelist+=" js/dist/index.js"
filelist+=" js/dist/item_form.js"
filelist+=" js/dist/login.js"
filelist+=" js/dist/new_file.js"
filelist+=" js/dist/timers.js"
filelist+=" js/dist/upsert_user.js"
filelist+=" js/jquery.min.js"
filelist+=" js/popper.min.js"
filelist+=" js/bootstrap.min.js"
filelist+=" js/jquery.csv.min.js"
filelist+=" js/password-generator.js"
filelist+=" js/landing.js"
filelist+=" src/db/SessionHandler.php"
filelist+=" src/db/file.php"
filelist+=" src/db/iam_ops.php"
filelist+=" src/db/item.php"
filelist+=" src/db/safe.php"
filelist+=" src/db/user.php"
# filelist+=" src/templates/modals/account.html"
filelist+=" src/templates/modals/create_vault.html"
filelist+=" src/templates/modals/delete_item.html"
filelist+=" src/templates/modals/delete_safe.html"
filelist+=" src/templates/modals/folder_ops.html"
filelist+=" src/templates/modals/gen_password.html"
filelist+=" src/templates/modals/idle_and_removal.html"
filelist+=" src/templates/modals/impex.html"
filelist+=" src/templates/modals/rename_file.html"
filelist+=" src/templates/modals/rename_vault.html"
filelist+=" src/templates/modals/safe_users.html"
filelist+=" src/templates/modals/share_by_mail.html"
filelist+=" src/templates/modals/show_creds.html"
filelist+=" src/templates/error_page.html"
filelist+=" src/templates/expired.html"
filelist+=" src/templates/feedback.html"
filelist+=" src/templates/feedback_action.html"
# filelist+=" src/templates/form_filled.html"
filelist+=" src/templates/help.html"
filelist+=" src/templates/iam.html"
filelist+=" src/templates/index.html"
filelist+=" src/templates/item_form.html"
filelist+=" src/templates/login.html"
filelist+=" src/templates/login_reg.html"
filelist+=" src/templates/message_page.html"
filelist+=" src/templates/new_file.html"
filelist+=" src/templates/notsupported.html"
# filelist+=" src/templates/progress.html"
filelist+=" src/templates/registration_action.html"
filelist+=" src/templates/request_mail.html"
filelist+=" src/templates/layout.html"
filelist+=" src/templates/upsert_user.html"
filelist+=" src/functions.php"
filelist+=" src/google_drive_files.php"
filelist+=" src/s3_files.php"
filelist+=" src/template.php"
filelist+=" src/localized-template.php"
filelist+=" account.php"
filelist+=" composer.json"
filelist+=" composer.lock"
filelist+=" create.php"
filelist+=" create_file.php"
filelist+=" create_user.php"
filelist+=" create_vault.php"
filelist+=" delete.php"
filelist+=" delete_safe.php"
filelist+=" delete_user.php"
filelist+=" edit.php"
filelist+=" edit_user.php"
filelist+=" error_page.php"
filelist+=" expired.php"
filelist+=" favicon.ico"
filelist+=" feedback.php"
filelist+=" feedback_action.php"
filelist+=" file_ops.php"
filelist+=" folder_ops.php"
filelist+=" form_filled.php"
filelist+=" get_user_data.php"
filelist+=" getticket.php"
filelist+=" help.php"
filelist+=" iam.php"
filelist+=" impex.php"
filelist+=" InstallingPassHubOnUbuntu18.04.md" 
filelist+=" index.php"
filelist+=" login.php"
filelist+=" logout.php"
filelist+=" maintenance_off.html"
filelist+=" move.php"
filelist+=" new.php"
filelist+=" newfile.php"
filelist+=" notsupported.php"
filelist+=" registration_action.php"
filelist+=" robots.txt"
filelist+=" safe_acl.php"
filelist+=" update.php"
filelist+=" update_ticket.php"
filelist+=" update_vault.php"


echo "Arch"

rm -f /tmp/passhub.business.$timestamp.tgz
tar czf /tmp/passhub.business.$timestamp.tgz --transform 's,^,passhub/,' $filelist
mv /tmp/passhub.business.$timestamp.tgz .

echo $filelist

echo "Done"
