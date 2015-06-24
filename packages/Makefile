all:
	@echo This make file is for developers.
	@echo run \"make update-web\" to copy the web sources to /var/www/fog/...
	@echo run \"make update-services\" to copy the services sources to /opt/fog/service/...


update-web:
	bin/update_web.bash .. /var/www/fog
	service apache2 restart
	service FOGImageReplicator restart
	service FOGMulticastManager restart
	service FOGScheduler restart
	service FOGSnapinReplicator restart

update-services:
	bin/update_services.bash .. /opt/fog/service
	service apache2 restart
	service FOGImageReplicator restart
	service FOGMulticastManager restart
	service FOGScheduler restart
	service FOGSnapinReplicator restart


