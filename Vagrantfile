# -*- mode: ruby -*-
# vi: set ft=ruby :
#
# symlink this to your wordpress install document root eg
# ln -s ../wordpress.org/interserve-data-feed/trunk/Vagrantfile Vagrantfile
# edit your local /etc/hosts and add
# 192.168.33.13 wordpress.local
# the plugin will be in /var/www/plugin which is symlinked into the wordpress wp-content/plugins folder
#
# see https://github.com/weaveworks/guides/blob/master/nginx-ubuntu-simple/Vagrantfile
# for some nifty tricks with copying files
#
# if you have trouble with guest additions
# vagrant plugin install vagrant-vbguest
#
Vagrant.configure(2) do |config|
    config.vm.box = "ubuntu/bionic64"
    config.vm.network "private_network", ip: "192.168.33.13"
    config.vm.hostname = "wordpress.local"

    # needs to be www-data:www-data so apache can write to directories, needs to be 777 so phpStorm command line can write to cache directories. Dumb.
    config.vm.synced_folder ".", "/var/www/html", owner:"www-data", group:"www-data", create: true, mount_options:["dmode=777,fmode=777"]

    # make the plugin development directory available to virtualbox as /var/www/plugin
    config.vm.synced_folder "../wordpress.org/interserve-data-feed/trunk/", "/var/www/plugin", owner:"www-data", group:"www-data", create: true, mount_options:["dmode=777,fmode=777"]

    # Enable provisioning with a shell script.
    # based on https://gist.githubusercontent.com/JeffreyWay/9244714/raw/b9a0a436dac2c13f6d75c09589ab0f78e019dd6a/install.sh
    # which is from https://gist.github.com/JeffreyWay/af0ee7311abfde3e3b73
    config.vm.provision "shell", inline: <<-SHELL
        sudo add-apt-repository ppa:ondrej/php
        sudo add-apt-repository ppa:ondrej/apache2
        sudo apt-get update
        sudo apt-get upgrade -y
        sudo apt-get install -y geoip-database

        # mysql
        sudo debconf-set-selections <<< 'mysql-server mysql-server/root_password password rootpassword'
        sudo debconf-set-selections <<< 'mysql-server mysql-server/root_password_again password rootpassword'
        sudo apt-get install -y mysql-server

        echo "CREATE DATABASE if not exists wordpress" | mysql -uroot -prootpassword

        # apache2.4
        echo "Installing apache"
        sudo apt-get install -y apache2 apache2-bin libapache2-mod-php7.3
        sudo apt-get install -y php7.3 php7.3-cli php7.3-gd php7.3-mysql php7.3-zip php7.3-opcache php7.3-json php7.3-mbstring php7.3-curl php7.3-xml php7.3-imap php-apcu-bc php-common php7.3-common php7.3-geoip php-xdebug

        # composer
        #echo "Installing composer..."
        #if [ ! -f /usr/local/bin/composer ]; then
        #    sudo curl -sS https://getcomposer.org/installer | php
        #    sudo mv composer.phar /usr/local/bin/composer
        #fi

cat << EOF | sudo tee -a /etc/php/7.3/mods-available/xdebug.ini
xdebug.scream=1
xdebug.cli_color=1
xdebug.show_local_vars=1
xdebug.remote_enable=1
xdebug.remote_host=192.168.1.74
xdebug.remote_connect_back=1
xdebug.idekey="PHPSTORM"
EOF

        # configure php
        sudo sed -i "s/error_reporting = .*/error_reporting = E_ALL/" /etc/php/7.3/apache2/php.ini
        sudo sed -i "s/display_errors = .*/display_errors = On/" /etc/php/7.3/apache2/php.ini
        sudo sed -i "s/disable_functions = .*/disable_functions = /" /etc/php/7.3/cli/php.ini

        # phpmyadmin: is available as an alias /phpmyadmin
        sudo echo "phpmyadmin phpmyadmin/dbconfig-install boolean false" | sudo debconf-set-selections
        sudo echo "phpmyadmin phpmyadmin/reconfigure-webserver multiselect apache2" | sudo debconf-set-selections
        sudo apt-get install -y phpmyadmin
        sudo phpdismod mcrypt

        # put the plugin development directory into wordpress
        sudo ln -s /var/www/plugin /var/www/html/wp-content/plugins/isdata

        sudo a2enmod rewrite
        sudo apache2ctl restart
    SHELL
end
