# op-billmanager5-domains
Plugin for Billmanager5 integration with openprovider

# System requirements

* Billmanager-ready OS (https://doc.ispsystem.com/index.php/BILLmanager_Installation_guide)
* PHP 5.4+ with [php-mysqli](https://www.php.net/manual/mysqli.installation.php), [php-curl](https://www.php.net/manual/curl.installation.php), [php-simplexml](https://www.php.net/manual/simplexml.installation.php), [php-xml](https://www.php.net/manual/dom.installation.php), [php-mbstring](https://www.php.net/manual/mbstring.installation.php)

# Installation

1. Clone or download op-billmanager5-domains files
2. Upload all downloaded files to your server and distribute them to Billmanager5 directories according to the following scheme: 

> mgr5/xml/ => [BILLMGR_PATH]/etc/xml/  
> mgr5/addon/ => [BILLMGR_PATH]/addon/  
> mgr5/proccesing/ => [BILLMGR_PATH]/proccessing/  
> 
> Note: BILLMGR_PATH almost always is /usr/local/mgr5

3. Mark "addon/domainauthcode.php" and "addon/pmopenprovider" as executables (chmod +x)
4. Create required tables by executing queries:
```sql
CREATE TABLE IF NOT EXISTS `processingcache` (
    `id` int(11) NOT NULL,
    `processingmodule` varchar(255) NOT NULL,
    `actualuntil` datetime NOT NULL,
    `key` varchar(255) NOT NULL,
    `value` text DEFAULT NULL
    ) ENGINE=InnoDB AUTO_INCREMENT=1 DEFAULT CHARSET=latin1;

ALTER TABLE `processingcache`
    ADD PRIMARY KEY (`id`);

```
5. Restart billmanager to commit changes (killall core)
6. Done


You can find the detailed guide to setting up the domain name provider modules by going to the link below:
https://doc.ispsystem.com/index.php/Providing_domain_names_to_clients

# FAQ

> When i'm trying to install plugin, billmanager returns an internal error

First of all, check that execution rights for "addon/domainauthcode.php" and "addon/pmopenprovider" (as per step 3) are set correctly. If it dosn't work, check php interpreter path (for example enter "whereis php" in command prompt). If it isn't "/usr/bin/php", changing the line "#/usr/bin/php" to "#[YOUR_PATH]" in "addon/domainauthcode.php" and "addon/pmopenprovider" should help. Finally check is required (from System requirements) PHP modules are installed using "php -i" command.
