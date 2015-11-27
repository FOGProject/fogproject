<?php
class PrinterManager extends FOGManagerController {
    public function destroy($findWhere = array(), $whereOperator = 'AND', $orderBy = 'name', $sort = 'ASC', $compare = '=', $groupBy = false, $not = false) {
        if (empty($findWhere)) return parent::destroy($field);
        if (isset($findWhere['id'])) {
            $fieldWhere = $findWhere;
            $findWhere = array('printerID'=>$findWhere['id']);
        }
        $this->getClass('PrinterAssociationManager')->destroy($findWhere);
        return parent::destroy($fieldWhere);
    }
}
