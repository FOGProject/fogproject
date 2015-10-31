<?php
class Schema extends FOGController {
    protected $databaseTable = 'schemaVersion';
    protected $databaseFields = array(
        'id' => 'vID',
        'version' => 'vValue',
    );
    public function export_db($tables = false, $backup_name = false) {
        $mysqli = $this->DB->link();
        $mysqli->select_db(DATABASE_NAME);
        $mysqli->query("SET NAMES 'utf8'");
        $queryTables = $mysqli->query('SHOW TABLES');
        while ($row = $queryTables->fetch_row()) $target_tables[] = $row[0];
        if ($tables !== false) $target_tables = array_intersect($target_tables,$tables);
        $content[] = '-- FOG MySQL Dump created '.$this->formatTime('','r')."\n\n";
        if ($tables === false) {
            $content[] = 'DROP DATABASE IF EXISTS `'.DATABASE_NAME."`;\n\n";
            $content[] = 'CREATE DATABASE IF NOT EXISTS `'.DATABASE_NAME."`;\n\n";
        }
        $content[] = 'USE `'.DATABASE_NAME."`;\n\n";
        foreach ($target_tables AS $i => &$table) {
            $result = $mysqli->query("SELECT * FROM `$table`");
            $fields_amount = $result->field_count;
            $rows_num = $mysqli->affected_rows;
            $res = $mysqli->query("SHOW CREATE TABLE `$table`");
            $TableMLine = $res->fetch_row();
            $content[] = "DROP TABLE IF EXISTS `$table`;";
            $content[] = "\n\n".$TableMLine[1].";\n\n";
            for ($i=0,$st_counter=0;$i<$fields_amount;$i++,$st_counter=0) {
                while ($row = $result->fetch_row()) {
                    if ($st_counter % 100 == 0 || $st_counter == 0) $content[] = "\nINSERT INTO `$table` VALUES";
                    $content[] = "\n(";
                    for ($j=0;$j<$fields_amount;$j++) {
                        $row[$j] = str_replace("\n","\\n",addslashes($row[$j]));
                        if (isset($row[$j])) $content[] ='"'.$row[$j].'"';
                        else $content[] = '""';
                        if ($j < ($fields_amount - 1)) $content[] = ',';
                    }
                    $content[] = ')';
                    if ((($st_counter + 1) % 100 == 0 && $st_counter != 0) || $st_counter+1 == $rows_num) $content[] = ';';
                    else $content[] = ',';
                    $st_counter++;
                }
                $content[] = "\n\n\n";
            }
        }
        return $content;
    }
    public function import_db($file) {
        $mysqli = $this->DB->link();
        if ($handle = fopen($file,'rb')) {
            while (($line = fgets($handle)) !== false) {
                if (substr($line,0,2) == '--' || $line == '') continue;
                $tmpline .= $line;
                if (substr(trim($line),-1,1) == ';') {
                    if (false === $mysqli->query($tmpline)) $error .= _('Error performing query').'\'<strong>'.$line.'\': '.$mysqli->error.'</strong><br/><br/>';
                    $tmpline = '';
                }
            }
            fclose($handle);
            if ($error) return $error;
            return true;
        } else throw new Exception(_('Error opening db file'));
    }
    public function send_file($content) {
        $backup_name = $backup_name ? $backup_name : 'fog_backup_'.$this->formatTime('','Ymd_His').'.sql';
        header('Content-Type: application/octet-stream');
        header("Content-Transfer-Encoding: Binary");
        header("Content-disposition: attachment; filename=\"$backup_name\"");
        echo implode($content);
        exit;
    }
}
