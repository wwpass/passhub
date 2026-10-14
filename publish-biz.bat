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

tar czf %dist%.tgz %dist%

rem scp %dist%.tgz mvv@trial:temp

rem ssh mvv@trial "cd temp; ls -l; ./cp-passhub-public-addon.sh"

rem rmdir /s /q %dist%

time /T

