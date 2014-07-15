<?php

// Blackout - 11:02 AM 25/09/2011
class OutputManager
{
	public $name;
	public $menu = array();
	public $subMenu = array();
	public $data = array();
	public $templates = array();
	public $attributes = array();
	
	public function __construct($name, $menu, $subMenu, $data, $templates, $attributes)
	{
		$this->name = $name;
		$this->data = $data;
		$this->templates = $templates;
		$this->attributes = $attributes;
		$this->menu = $menu;
		$this->subMenu = $subMenu;
	}
	
	function __toString()
	{
		$results = $this->process();
		
		return ($results ? $results : 'Processing failed');
	}
	
	public function process()
	{
		try
		{
			// Error checking
			if (!count($this->templates))
				throw new Exception('Requires templates to process');
			
			// Variables
			$result = '';
			
			// Is AJAX Request?
			if ($GLOBALS['FOGCore']->isAJAXRequest())
				// JSON output
				$result = json_encode(array('data' => $this->data, 'templates' => $this->templates, 'attributes' => $this->attributes));
			else
			{
				// Regular request / include - HTML output
				if (count($this->data))
				{
					foreach ($this->data AS $rowData)
					{
						$result .= sprintf('<tr id="%s-%s" class="%s">%s</tr>%s',
							$this->name,
							$rowData['id'],
							(++$i % 2 ? 'alt1' : 'alt2'),
							$this->processRow($rowData, $this->templates, $this->attributes),
							"\n"
						);
					}
				
					$GLOBALS['FOGCore']->setMessage(sprintf('%s %ss found', count($this->data), $this->name));
				}
				else
				{
					$result .= sprintf('<tr><td colspan="%s" class="no-active-tasks">%s</td></tr>',
						count($this->templates),
						($this->data['error'] ? (is_array($this->data['error']) ? '<p>' . implode('</p><p>', $this->data['error']) . '</p>' : $this->data['error']) : _('No results found'))
					);
				}
			}
		
			// Return output
			return $result;
		}
		catch (Exception $e)
		{
			return $e->getMessage();
		}
	}
	
	public function processHeaderRow($templateData, $attributeData = array(), $wrapper = 'td')
	{
		// Loop data
		foreach ($templateData AS $i => $content)
		{
			// Create attributes data
			$attributes = array();
			foreach ((array)$attributeData[$i] as $attributeName => $attributeValue)
			{
				// Format into HTML attributes -> push into attributes array
				$attributes[] = sprintf('%s="%s"', $attributeName, $attributeValue);
			}

			// Push into results array
			$result[] = sprintf('<%s%s>%s</%s>',	$wrapper,
								(count($attributes) ? ' ' . implode(' ', $attributes) : ''),
								$content,
								$wrapper);
			
			// Reset
			unset($attributes);
		}
		
		// Return result
		return implode("\n", $result);
	}
	
	public function processRow($data, $templateData, $attributeData = array(), $wrapper = 'td')
	{
		// Loop template data
		foreach ($templateData AS $i => $template)
		{
			// Create find and replace arrays for data
			foreach ($data AS $dataName => $dataValue)
			{
				$dataFind[] = '#%' . $dataName . '%#';
				$dataReplace[] = $dataValue;
			}
			foreach (array('node', 'sub', 'tab') AS $extraData)
			{
				$dataFind[] = '#%' . $extraData . '%#';
				$dataReplace[] = $GLOBALS[$extraData];
			}
			// Remove any other data keys not found
			$dataFind[] = '#%\w+%#';
			$dataReplace[] = '';
			
			// Create attributes data
			$attributes = array();
			foreach ((array)$attributeData[$i] as $attributeName => $attributeValue)
			{
				// Format into HTML attributes -> push into attributes array
				$attributes[] = sprintf('%s="%s"', $attributeName, $attributeValue);
			}
			
			// Replace variables in template with data -> wrap in $wrapper -> push into $result
			$result[] = sprintf('<%s%s>%s</%s>',	$wrapper,
								(count($attributes) ? ' ' . implode(' ', $attributes) : ''),
								preg_replace($dataFind, $dataReplace, $template),
								$wrapper);
			
			// Reset
			unset($dataFind, $dataReplace);
		}
		
		// Return result
		return implode("\n", $result);
	}
}
