<?php
	
	require_once(EXTENSIONS . '/entry_versions/lib/class.entryversionsmanager.php');
	require_once(EXTENSIONS . '/entry_versions/lib/class.domconverter.php');
	
	Class fieldEntry_Versions extends Field{	
		
		public function __construct(){
			parent::__construct();
			$this->_name = __('Entry Versions');
			$this->_required = false;
		}
				
		public function processRawFieldData($data, &$status, &$message = null, $simulate = false, $entry_id = null) {	
			$status = self::__OK__;
			
			$author = 'frontend user';
			if(Symphony::Engine() instanceOf Administration) {
				$author = Administration::instance()->Author()->getFullName();
			}
			
			return array(
				'value' => '',
				'last_modified' => DateTimeObj::get('Y-m-d H:i:s', time()),
				'last_modified_author' => $author
			);
		}
		
		public function displaySettingsPanel(XMLElement &$wrapper, $errors = null) {
			
			$wrapper->appendChild(new XMLElement('h4', ucwords($this->name())));
			$wrapper->appendChild(Widget::Input('fields['.$this->get('sortorder').'][type]', $this->handle(), 'hidden'));
			if($this->get('id')) $wrapper->appendChild(Widget::Input('fields['.$this->get('sortorder').'][id]', $this->get('id'), 'hidden'));
			
			$div = new XMLElement('div');
			$div->setAttribute('class', 'group');
			
			$label = Widget::Label(__('Label'));
			$label->appendChild(Widget::Input('fields['.$this->get('sortorder').'][label]', $this->get('label'), null));
			
			if(isset($errors['label'])) $div->appendChild(Widget::wrapFormElementWithError($label, $errors['label']));
			else $div->appendChild($label);		
			
			$div->appendChild($this->buildLocationSelect($this->get('location'), 'fields['.$this->get('sortorder').'][location]'));
			
			$wrapper->appendChild($div);
			
			$order = $this->get('sortorder');
			
			$label = Widget::Label();
			$input = Widget::Input("fields[{$order}][hide_in_publish]", 'yes', 'checkbox');
			if ($this->get('show_in_publish') == 'no') $input->setAttribute('checked', 'checked');
			$label->setValue($input->generate() . ' ' . __('Hide version history list on entry page'));
			$wrapper->appendChild($label);
			
			$this->appendShowColumnCheckbox($wrapper);
			
		}
		
		function commit(){
			if(!parent::commit()) return false;			
			$id = $this->get('id');
			if($id === false) return false;			
			$fields = array();			
			$fields['field_id'] = $id;
			$fields['show_in_publish'] = ($this->get('hide_in_publish') == 'yes') ? 'no' : 'yes';
			Symphony::Database()->query("DELETE FROM `tbl_fields_".$this->handle()."` WHERE `field_id` = '$id' LIMIT 1");
			return Symphony::Database()->insert($fields, 'tbl_fields_' . $this->handle());			
		}
		
		function displayPublishPanel(XMLElement &$wrapper, $data = null, $flagWithError = null, $fieldnamePrefix = null, $fieldnamePostfix = null, $entry_id = null){					
			
			if ($this->get('show_in_publish') == 'no') return;
			
			$callback = Administration::instance()->getPageCallback();
			$entry_id = $callback['context']['entry_id'];
			
			$viewing_version = $_GET['version'];			
			$latest_version = EntryVersionsManager::getLatestVersion($entry_id);
			
			$container = new XMLElement('div', null, array('class' => 'frame'));

			if (!$entry_id || !$latest_version) {
				$container->appendChild(
					new XMLElement('p', __('Version 1 will be created when you save.'), array('class' => 'has-no-version'))
				);
				$wrapper->appendChild($container);
				return;
			}
			
			$revision_history = new XMLElement('ol');
			$revision_history->setAttribute('class', 'revisions');
			
			$i = 0;
			$entries = EntryVersionsManager::entryHistory($entry_id);
			foreach($entries as $entry) {
				
				$meta = $entry->documentElement;
				
				$href = URL . '/symphony' . $callback['pageroot'] . $callback['context']['page'] . '/' . $entry_id;
				if ($i != 0) {
					$href .= '/?version=' . $meta->getAttribute('version');
				}
				$dom_revision = new XMLElement(
					'a',
					'Version ' . $meta->getAttribute('version'),
					array(
						'href' => $href
					)
				);
				
				$timestamp = strtotime($meta->getAttribute('created-date') . ' ' . $meta->getAttribute('created-time'));
				
				$dom_created = new XMLElement(
					'span',
					'on ' . DateTimeObj::get(__SYM_DATE_FORMAT__, $timestamp) . ' ' . DateTimeObj::get(__SYM_TIME_FORMAT__, $timestamp),
					array('class' => 'date')
				);
				
				$dom_author = new XMLElement(
					'span',
					'by ' . $meta->getAttribute('created-by'),
					array('class' => 'author')
				);
				
				$dom_li = new XMLElement('li');
				if (!isset($viewing_version) && $i == 0) $dom_li->setAttribute('class', 'viewing');
				if (isset($viewing_version) && (int)$viewing_version == (int)$meta->getAttribute('version')) $dom_li->setAttribute('class', 'viewing');
				
				
				$dom_li->appendChild($dom_revision);
				$dom_li->appendChild($dom_author);
				$dom_li->appendChild($dom_created);				
				$revision_history->appendChild($dom_li);
				
				$i++;
				
			}
			
			$container->appendChild($revision_history);
			
			$wrapper->appendChild($container);
			
			
		}
		
		public function prepareTableValue($data, XMLElement $link = null, $entry_id = null) {
			
			$version = EntryVersionsManager::getLatestVersion($entry_id);
			
			if (!$version) return sprintf('<span class="inactive">%s</span>', __('Unversioned'));
			
			$meta = $version->documentElement;
			
			$timestamp = strtotime($meta->getAttribute('created-date') . ' ' . $meta->getAttribute('created-time'));
			$date = DateTimeObj::get(__SYM_DATE_FORMAT__, $timestamp) . ' ' . DateTimeObj::get(__SYM_TIME_FORMAT__, $timestamp);
			$author = $meta->getAttribute('created-by');
			
			return sprintf('Version %d <span class="inactive">on %s by %s</span>', $meta->getAttribute('version'), $date, $author);
		}
		
		public function appendFormattedElement(XMLElement &$wrapper, $data, $encode = false, $mode = null, $entry_id = null) {
			$entry_id = $wrapper->getAttribute('id');
			
			$versions = new XMLElement('versions');
			$entries = EntryVersionsManager::entryHistory($entry_id);
			foreach($entries as $entry) {				
				$versions->appendChild(DOMConverter::toXMLElement($entry));
			}
			
			$wrapper->appendChild($versions);
			
		}
				
		public function createTable(){
			
			return Symphony::Database()->query(
			
				"CREATE TABLE IF NOT EXISTS `tbl_entries_data_" . $this->get('id') . "` (
				  `id` int(11) unsigned NOT NULL auto_increment,
				  `entry_id` int(11) unsigned NOT NULL,
				  `value` double default NULL,
				  `last_modified` datetime default NULL,
				  `last_modified_author` varchar(255) default NULL,
				  PRIMARY KEY  (`id`),
				  KEY `entry_id` (`entry_id`),
				  KEY `value` (`value`)
				) TYPE=MyISAM;"
			
			);
		}
						
	}

?>