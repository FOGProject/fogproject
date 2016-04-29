<?php
class Schema extends FOGController {
    protected $databaseTable = 'schemaVersion';
    protected $databaseFields = array(
        'id' => 'vID',
        'version' => 'vValue',
    );
    public function drop_duplicate_data($dbname,$table = array(),$indexNeeded = false) {
        if (empty($dbname)) return;
        if (count($table) < 1) return;
        $tablename = $table[0];
        $indexes = (array)$table[1];
        $dropIndex = $table[2];
        if ($indexNeeded) {
            if (count($indexes) < 1) return;
            else if (count($indexes) === 1) $ending = sprintf('INDEX `%s`',array_shift($indexes));
            else $ending = sprintf('INDEX `%s` (`%s`)',array_shift($indexes),implode('`,`',$indexes));
        } else {
            $ending = sprintf('(`%s`)',implode('`,`',$indexes));
        }
        self::$DB->query("CREATE TABLE `$dbname`.`_$tablename` LIKE `$dbname`.`$tablename`");
        self::$DB->query("ALTER TABLE `$dbname`.`_$tablename` ADD UNIQUE $ending");
        self::$DB->query("INSERT IGNORE INTO `$dbname`.`_$tablename` SELECT * FROM `$dbname`.`$tablename`");
        self::$DB->query("DROP TABLE `$dbname`.`$tablename`");
        self::$DB->query("RENAME TABLE `$dbname`.`_$tablename` TO `$dbname`.`$tablename`");
        if ($dropIndex) self::$DB->query("ALTER TABLE `$dbname`.`$tablename` DROP INDEX `$dropIndex`");
    }
    public function export_db($backup_name = '',$remove_file = true) {
        $orig_exec_time = ini_get('max_execution_time');
        ini_set('max_execution_time',300);
        $file = '/tmp/fog_backup_tmp.sql';
        if (!$backup_name) $backup_name = sprintf('fog_backup_%s.sql',$this->formatTime('','Ymd_His'));
        $dump = self::getClass('Mysqldump');
        while (ob_get_level()) {
            flush();
            ob_flush();
            ob_end_flush();
        }
        ob_start();
        $dump->start($file);
        ob_end_clean();
        if (!file_exists($file) || !is_readable($file)) throw new Exception(_('Could not read tmp file.'));
        ob_start();
        if ($remove_file) {
            $fh = fopen($file,'rb');
            header('Content-Type: text/plain');
            header("Content-Disposition: attachment; filename=$backup_name");
            header('Cache-Control: private');
            while (feof($fh) === false) {
                echo fread($fh,4096);
                flush();
                ob_flush();
            }
            fclose($fh);
            unlink($file);
            return;
        }
        flush();
        ob_flush();
        ob_end_flush();
        ini_set('max_execution_time',$orig_exec_time);
        return $file;
    }
    public function import_db($file) {
        $mysqli = self::$DB->link();
        if (false === ($fh = fopen($file,'rb'))) throw new Exception(_('Error Opening DB File'));
        while (($line = fgets($fh)) !== false) {
            if (substr($line,0,2) == '--' || $line == '') continue;
            $tmpline .= $line;
            if (substr(trim($line),-1,1) == ';') {
                if (false === $mysqli->query($tmpline)) $error .= _('Error performing query').'\'<strong>'.$line.'\': '.$mysqli->error.'</strong><br/><br/>';
                $tmpline = '';
            }
        }
        fclose($fh);
        if ($error) return $error;
        return true;
    }
}
