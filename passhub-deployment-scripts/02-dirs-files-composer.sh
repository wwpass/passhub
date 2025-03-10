echo "Step 2: Creating and Editing Directories and Files..."
# Step 2: Edit and Create Directories and files

appId=$1
tenantId=$2
clientSecret=$3
subdomainRegion=$4
username=$5

# Use Mike's Method
set -e
sed -i ' s/post_max_size = 8M/post_max_size = 30M/' /etc/php/8.3/fpm/php.ini
sed -i 's/upload_max_filesize = 2M/upload_max_filesize = 30M/' /etc/php/8.3/fpm/php.ini
sed -i 's/memory_limit = 128M/memory_limit = 256M/' /etc/php/8.3/fpm/php.ini

# Azure Does This Currently:
#tag=$(wget https://github.com/wwpass/passhub/releases/latest 2>&1 | grep Location)
# example output: Location: https://github.com/wwpass/passhub/releases/tag/v3.3.0 [following]

# from output start at tag then continue until first space
#tag=$(echo $tag | grep -o 'tag[^ ]*')

# example: tag/v3.3.0, then remove tag/
#tag=$(echo $tag | sed 's/^tag\///')

# then add version into wget
#wget https://github.com/wwpass/passhub/releases/download/${tag}/passhub.business.tgz

#tar -xvzf passhub.business.tgz -C /var/www/ 
#mv /var/www/passhub.business /var/www/passhubAzure
chown -R www-data:www-data /var/www/passhubAzure/

mkdir -p /var/log/passhub
chown www-data:www-data /var/log/passhub
mkdir -p /var/lib/passhub
chown www-data:www-data /var/lib/passhub

cp /var/www/passhubAzure/config/config-sample.php /var/www/passhubAzure/config/config.php

# Edit Azure Definition Config.php
sed -i "/application/s/XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX/$appId/" /var/www/passhubAzure/config/config.php
sed -i "/tenant/s/XXXXXXXX-XXXX-XXXX-XXXX-XXXXXXXXXXXX/$tenantId/" /var/www/passhubAzure/config/config.php
sed -i "/~/s/XXXXX~XXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXXX/$clientSecret/" /var/www/passhubAzure/config/config.php

# sed find 'AZURE' then keep going down until find */ then remove
#sed -i "/'AZURE'/,/\*\//{/\*\//d;}" /var/www/passhubAzure/config/config.php
# sed find EntraID then keep going down until find */ then remove
#sed -i "/EntraID/,/\/\*/{/\/\*/d;}" /var/www/passhubAzure/config/config.php

# Edit .crt and .key at top of Config.php
sed -i "s/\/yourcompany/\/$subdomainRegion.cloudapp.azure/g" /var/www/passhubAzure/config/config.php

echo "Step 2.5: Running PHP Composer..."
sudo -u www-data composer install --working-dir=/var/www/passhubAzure/ --ignore-platform-reqs