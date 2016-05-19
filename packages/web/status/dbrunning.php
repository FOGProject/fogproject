<?php
require('../commons/base.inc.php');
echo json_encode(array(
    'running'=>(bool)$DB->link(),
    'redirect'=>(bool)$DB->link() && FOGCore::getClass('Schema',1)->get('version') == FOG_SCHEMA,
));
