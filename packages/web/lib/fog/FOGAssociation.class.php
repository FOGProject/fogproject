<?php
abstract class FOGAssociation extends FOGBase {
	/** @var databaseTable
	  * Sets the databaseTable to perform lookups
	  */
	public $databaseTable = '';
	/** @var databaseFields
	  * The Fields the database contains
	  * using common for friendly names
	  */
	public $databaseFields = array();
	public function __construct() {
		/** FOGBase constructor
		  * Allows the rest of the base of fog to come
		  * with the object begin called
		  */
		parent::__construct();
		/** sets if to print controller debug information to screen/log/either/both*/
		$this->debug = false;
		/** sets if to print controller general information to screen/log/either/both*/
		$this->info = false;
		// Error checking
		if (!$this->isTableDefined()) throw new Exception(_('No database table defined for this item!');
		if (!count($this->databaseFields)) throw new Exception(_('No database fields defined for %s class!',get_class($this)));
		return $this;
	}
	/** istableDefined()
		Makes sur ethe table being called is defined in the database.  osID on hosts database table is not defined anymore.
		This would return false in that case.
	*/
	private function isTableDefined() {return (!empty($this->databaseTable) ? true : false);}
	// Name is returned if class is printed
	/** __toString()
		Returns the name of the class as a string.
	*/
	public function __toString() {return ($this->get('name') ? $this->get('name') : sprintf('%s #%s', get_class($this), $this->get('id')));}
}
/* Local Variables: */
/* indent-tabs-mode: t */
/* c-basic-offset: 4 */
/* tab-width: 4 */
/* End: */
