all:
	@echo This make file is for developers.
	@echo run \"make update-web\" to copy the web sources to /var/www/fog/...


update-web:
	bin/update_web.bash .. /var/www/fog
	service apache2 restart
	service FOGImageReplicator restart
	service FOGMulticastManager restart
	service FOGScheduler restart


