# Glop
Included here is code for running an instance of glop.me, including the IPFS-specific scripts.

## Requirements
Pomf's suggested setup was Nginx + PHP5.5 + MySQL, but was also confirmed to work with Apache 2.4
and newer PHP versions. glop.me runs a standard Ubuntu LAMP stack, but Nginx should still work (note that install locations/vhosts/etc. would need to be adjusted appropriately). Python is required for the snapshot utility.

## Install
1. Follow the instructions at https://ipfs.io/docs/install/ to install IPFS.
2. Set up the local IPFS repo:
````
mkdir /home/www-data
chown -R -v www-data /home/www-data
sudo -u www-data HOME=/home/www-data ipfs init
````
3. If you are using upstart, move runipfs.conf to /etc/init/, otherwise adapt it as appropriate for your system.
4. Set up the DB from schema.sql
5. Alter includes/settings.inc.php as appropriate
6. For the paste utility, you will need to either:
````
sudo -u www-data ipfs pin add -r QmazFHudWq91G7GxuWTpyRWZ1Pc2jg3wnwc2RrgVy5GSa3
````
OR if that doesn't work (i.e. glop.me is offline)
````
sudo -u www-data ipfs add -rq paste_content/
````
and move the hash to paste.php:34.
7. For the snapshot utility, move snapshot.py to /home/www-data/ and change the DB info as appropriate. You may also need to do 
````
sudo -u www-data ipfs object new unixfs-dir
sudo -u www-data ipfs pin add -r QmUNLLsPACCz1vLxQVkXqqLX5R1X345qqfHbsf67hvA3Nn
````
8. Finally, merge cron_entries with your server's crontab.
