<?php
class Module extends FOGController {
    protected $databaseTable = 'modules';
    protected $databaseFields = array(
        'id' => 'id',
        'name' => 'name',
        'shortName' => 'short_name',
        'description' => 'description',
        'isDefault' => 'default',
    );
    protected $databaseFieldsRequired = array(
        'name',
        'shortName',
    );
    public function isValid() {
        return ($this->get('id') && $this->get('name') && $this->get('shortName'));
    }
    public function destroy($field = 'id') {
        $this->getClass('ModuleAssociationManager')->destroy(array('moduleID' => $this->get('id')));
        return parent::destroy($field);
    }
}
