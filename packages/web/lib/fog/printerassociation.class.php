<?php
class PrinterAssociation extends FOGController {
    protected $databaseTable = 'printerAssoc';
    protected $databaseFields = array(
        'id' => 'paID',
        'hostID' => 'paHostID',
        'printerID' => 'paPrinterID',
        'isDefault' => 'paIsDefault',
        'anon1' => 'paAnon1',
        'anon2' => 'paAnon2',
        'anon3' => 'paAnon3',
        'anon4' => 'paAnon4',
        'anon5' => 'paAnon5',
    );
    protected $databaseFieldsRequired = array(
        'hostID',
        'printerID',
    );
    public function getHost() {
        return new Host($this->get('hostID'));
    }
    public function getPrinter() {
        return new Printer($this->get('printerID'));
    }
    public function isDefault() {
        return (bool)($this->get('isDefault') === 1);
    }
}
