<?php
class SnapinManager extends FOGManagerController {
    public function destroy($findWhere = array(), $whereOperator = 'AND', $orderBy = 'name', $sort = 'ASC', $compare = '=', $groupBy = false, $not = false) {
        if (empty($findWhere)) return parent::destroy($field);
        if (isset($findWhere['id'])) {
            $fieldWhere = $findWhere;
            $findWhere = array('snapinID'=>$findWhere['id']);
        }
        $this->getClass('SnapinJobManager')->cancel($this->getSubObjectIDs('SnapinTask',$findWhere,'jobID'));
        $this->getClass('SnapinTaskManager')->cancel($findWhere['snapinID']);
        $this->getClass('SnapinAssociationManager')->destroy($findWhere);
        return parent::destroy($fieldWhere);
    }
}
