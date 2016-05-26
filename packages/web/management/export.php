<?php
require_once('../commons/base.inc.php');
if (!FOGCore::getClass('User')->password_validate($_POST['fogguiuser'],$_POST['fogguipass'],true)) die($foglang['InvalidLogin']);
$report = unserialize($_SESSION['foglastreport']);
if (!($report instanceof ReportMaker)) $report = FOGCore::getClass('ReportMaker');
$report->outputReport();
