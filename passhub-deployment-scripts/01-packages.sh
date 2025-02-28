# Step 1: Installation of Required Packages and Libraries
set -e
echo "Step 1: Installing Required Packages..."

appId=$1
tenantId=$2
clientSecret=$3
subdomainRegion=$4
username=$5

apt-get update
apt-get install -y nginx php-fpm php php-dev php-curl php-mbstring php-mail php-net-smtp php-ldap composer

# Mongodb Installation
curl -fsSL https://www.mongodb.org/static/pgp/server-8.0.asc | \
   sudo gpg -o /usr/share/keyrings/mongodb-server-8.0.gpg \
   --dearmor
echo "deb [ arch=amd64,arm64 signed-by=/usr/share/keyrings/mongodb-server-8.0.gpg ] https://repo.mongodb.org/apt/ubuntu noble/mongodb-org/8.0 multiverse" | sudo tee /etc/apt/sources.list.d/mongodb-org-8.0.list
apt-get update
apt-get install -y mongodb-org
apt-get install -y php8.3-mongodb
systemctl enable mongod.service
systemctl start mongod.service