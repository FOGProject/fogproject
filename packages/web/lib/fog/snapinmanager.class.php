<?php
class SnapinManager extends FOGManagerController {
    public function destroy($findWhere = array(), $whereOperator = 'AND', $orderBy = 'name', $sort = 'ASC', $compare = '=', $groupBy = false, $not = false) {
        if (empty($findWhere)) return parent::destroy($field);
        if (isset($findWhere['id'])) {
            $fieldWhere = $findWhere;
            $findWhere = array('snapinID'=>$findWhere['id']);
        }
        static::getClass('SnapinJobManager')->cancel(static::getSubObjectIDs('SnapinTask',$findWhere,'jobID'));
        static::getClass('SnapinTaskManager')->cancel($findWhere['snapinID']);
        static::getClass('SnapinAssociationManager')->destroy($findWhere);
        return parent::destroy($fieldWhere);
    }
}
