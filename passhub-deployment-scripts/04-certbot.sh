
set -e

username=$1
subdomainRegion=$2

echo "Would you like to run Certbot to automatically recieve an SSL Certificate? (yes/no)"

read option

# if yes then continue
if [[ "$option" == "yes" ]]; then

    echo "Getting SSL Certificate..."
    if [ ! -e /usr/bin/certbot ]; then
        sudo snap install --classic certbot
        sudo ln -s /snap/bin/certbot /usr/bin/certbot
    fi

    sudo certbot --nginx -d ${subdomainRegion}.cloudapp.azure.com --register-unsafely-without-email

    # Afterwards remove the line from /home/$username/.profile that runs 04-certbot.sh on login
    if grep -q "var" /home/${username}/.profile; then
        sudo sed -i '/^\/var/d' /home/$username/.profile
    fi

    exit 0

# if no cancel out and tell them to add it to nginx configuration themselves
else 

    echo "Exiting.."
    echo "Please remember to retrieve a SSL Certificate yourself and place it into the nginx configuration as Passhub requires encrypted connections to function."
    
    # Afterwards remove the line from /home/$username/.profile that runs 04-certbot.sh on login
    if grep -q "var" /home/${username}/.profile; then
        sudo sed -i '/^\/var/d' /home/$username/.profile
    fi

    exit 0

fi

