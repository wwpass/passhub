set dist=passhub-backend

rmdir /s /q %dist%

mkdir %dist%

mkdir %dist%\app

copy  *.php %dist%
copy composer.json %dist%

rem xcopy passhub.net\css %dist%\passhub.net\css /s /i
rem xcopy passhub.net\js %dist%\passhub.net\js /s /i

xcopy app %dist%\app /s /i
xcopy config %dist%\config /s /i
xcopy views %dist%\views /s /i
xcopy public %dist%\public /s /i

del %dist%\app\Azure.php
del %dist%\app\Company.php
del %dist%\app\CrowdStrikeSiem.php
del %dist%\app\GoogleIam.php
del %dist%\app\Group.php
del %dist%\app\Iam.php
del %dist%\app\LDAP.php
del %dist%\app\MicrosoftSentinelSiem.php
del %dist%\app\SAML.php

del %dist%\company.php
del %dist%\delete_user.php
del %dist%\edit_user.php
del %dist%\group.php
del %dist%\iam.php
del %dist%\oauth-callback.php
del %dist%\saml.php
del %dist%\ldap.php

del %dist%\views\login.twig

tar czf %dist%.tgz %dist%

scp %dist%.tgz mvv@trial:temp

rem ssh mvv@trial "cd temp; ls -l; ./cp-passhub-public-addon.sh"

rem rmdir /s /q %dist%

time /T

