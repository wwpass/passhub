echo "Step 3: Editing nginx Config File..."
set -e

appId=$1
tenantId=$2
clientSecret=$3
subdomainRegion=$4
username=$5

cat << TOP > /etc/nginx/sites-available/passhubconfig
server {
	listen 80;
	listen [::]:80;
#	http2 on; # starting from nginx 1.25

	root /var/www/passhubAzure;
#	server_name blank.cloudapp.azure.com www.blank.cloudapp.azure.com ext.blank.cloudapp.azure.com;
	server_name blank.cloudapp.azure.com;

	add_header X-Frame-Options "DENY";

	index index.html index.php;


        if (-f \$document_root/maintenance.html) {
            return 503;
        }

        error_page 503 @maintenance;
        location @maintenance {
            rewrite ^(.*)$ /maintenance.html break;
        }

        location ~ /(app|config|vendor|src|views) {
            deny all;
            return 404;
        }
        
	location / {
		try_files \$uri \$uri/ =404;
	}

	location ~ \.php$ {
		include snippets/fastcgi-php.conf;
		fastcgi_pass unix:/run/php/php8.3-fpm.sock;
	}
	
        location @extensionless-php {
            rewrite ^(.*)$ \$1.php last;
        }

        location /wsapp/ {
            proxy_pass http://localhost:3000;
            proxy_http_version 1.1;
            proxy_set_header Upgrade \$http_upgrade;
            proxy_set_header Connection "Upgrade";
            proxy_set_header Host \$host;
       }

    gzip on;
    gzip_types      text/plain application/xml application/javascript text/css;
    gzip_proxied    no-cache no-store private expired auth;
    gzip_min_length 1000;
    
    error_log /var/log/nginx/blank.cloudapp.azure.com-error.log;
    access_log /var/log/nginx/blank.cloudapp.azure.com-access.log;

    client_max_body_size 80M;
} 
TOP


sed -i "s/blank/$subdomainRegion/g" /etc/nginx/sites-available/passhubconfig

 
if [ ! -e /etc/nginx/sites-enabled/passhubconfig ]; then
    ln -s /etc/nginx/sites-available/passhubconfig /etc/nginx/sites-enabled/passhubconfig
fi


sudo service nginx restart
# nginx -s reload

# Add into /home/$username/.profile to run 04-certbot.sh so it runs on login,
echo "/var/www/passhubAzure/passhub-deployment-scripts/04-certbot.sh $username $subdomainRegion" >> /home/${username}/.profile
# Reference 04-certbot.sh for next steps..
