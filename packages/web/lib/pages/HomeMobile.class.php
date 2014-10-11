<<<<<<< HEAD
<?php
/** Class Name: HomeMobile
	FOGPage lives in: {fogwebdir}/lib/fog
	Lives in: {fogwebdir}/lib/pages
	Description: This is an extension of the FOGPage Class
	This is the page constructed for the mobile front end.
	It creates the elements visible to the user from a
	lighter side of the house.

	Useful for:
	Dislaying FOG on mobile devices.
*/
class HomeMobile extends FOGPage
{
	var $name = '';
	var $node = 'homes';
	var $id = '';
	// Menu Items
	var $menu = array(
	);
	var $subMenu = array(
	);
	/** __construct($name = '')
		This just creates the default data
		to be used for hooking and such later on.
	*/
	public function __construct($name = '')
	{
		// Call parent constructor
		parent::__construct($name);
		// Header Data
		unset($this->headerData);
		// Attributes
		$this->attributes = array(
			array(),
		);
		// Templates
		$this->templates = array(
			'${page_desc}',
		);
	}
	/** index()
		This is the first display page for the mobile interface.
	*/
	public function index()
	{
		print "\n\t\t\t".'<h1>'._('Welcome to FOG Mobile').'</h1>';
		$this->data[] = array(
			'page_desc' => _("Welcome to FOG - Mobile Edition!  This light weight interface for FOG allows for access via mobile, low power devices."),
		);
		// Output
		$this->render();
	}
}
=======
<?php
/** Class Name: HomeMobile
	FOGPage lives in: {fogwebdir}/lib/fog
	Lives in: {fogwebdir}/lib/pages
	Description: This is an extension of the FOGPage Class
	This is the page constructed for the mobile front end.
	It creates the elements visible to the user from a
	lighter side of the house.

	Useful for:
	Dislaying FOG on mobile devices.
*/
class HomeMobile extends FOGPage
{
	var $name = 'Dashboard';
	var $node = 'homes';
	var $id = '';
	// Menu Items
	var $menu = array(
	);
	var $subMenu = array(
	);
	/** __construct($name = '')
		This just creates the default data
		to be used for hooking and such later on.
	*/
	public function __construct($name = '')
	{
		// Call parent constructor
		parent::__construct($name);
		// Header Data
		unset($this->headerData);
		// Attributes
		$this->attributes = array(
			array(),
		);
		// Templates
		$this->templates = array(
			'${page_desc}',
		);
	}
	/** index()
		This is the first display page for the mobile interface.
	*/
	public function index()
	{
		print "\n\t\t\t".'<h1>'._('Welcome to FOG Mobile').'</h1>';
		$this->data[] = array(
			'page_desc' => _("Welcome to FOG - Mobile Edition!  This light weight interface for FOG allows for access via mobile, low power devices."),
		);
		// Output
		$this->render();
	}
}
>>>>>>> dev-branch
