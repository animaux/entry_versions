<?php

require_once(TOOLKIT . '/class.datasource.php');
require_once(TOOLKIT . '/class.xsltprocess.php');

Class EntryVersionsManager {
	
	// saves an entry to disk
	public static function saveVersion($entry, $fields, $is_update) {
		
		// list existing versions of this entry
		$existing_versions = General::listStructure(MANIFEST . '/versions/' . $entry->get('id') . '/', '/.xml$/');
		//var_dump($existing_versions);
		// create folder
		if (!file_exists(MANIFEST . '/versions/' . $entry->get('id'))) {
			General::realiseDirectory(MANIFEST . '/versions/' . $entry->get('id'));
		}
		
		// max version number
		$existing_versions = $existing_versions ?? null;
		$new_version_number = $new_version_number ?? null;
		
		if (isset($existing_versions)) {
			$new_version_number = count($existing_versions['filelist']);

			$new_version_number++;

			//$is_update = false;

			if ($is_update) $new_version_number--;
			if ($new_version_number == 0) $new_version_number++;
		}
		
		// run custom DS to get the built XML of this entry
		//$ds = new EntryVersionsXMLDataSource(null, false);

		require_once(TOOLKIT . '/data-sources/class.datasource.section.php');

		$sectionDS = new SectionDatasource(array(), false);
		$sectionDS->setSource((string)$entry->get('section_id'));
		$sectionDS->dsParamINCLUDEDELEMENTS = array_keys($fields);
		$sectionDS->dsParamFILTERS['id'] = $entry->get('id');
		$sectionDS->dsParamROOTELEMENT = 'entries';
		$sectionDS->dsParamORDER = 'desc';
		$sectionDS->dsParamLIMIT = '1';
		$sectionDS->dsParamREDIRECTONEMPTY = 'no';
		$sectionDS->dsParamSORT = 'system:id';
		$sectionDS->dsParamSTARTPAGE = '1';	
		
		$param_pool = array();
		$entry_xml = $sectionDS->execute($param_pool);
	
		// get text value of the entry
		$proc = new XsltProcess;
		$data = $proc->process(
			$entry_xml->generate(),
			file_get_contents(EXTENSIONS . '/entry_versions/lib/entry-version.xsl'),
			array(
				'version' => $new_version_number,
				'created-by' => ((Administration::instance()->Author()) ? Administration::instance()->Author()->getFullName() : 'frontend user'),
				'created-date' => date('Y-m-d', time()),
				'created-time' => date('H:i', time()),
			)
		);
		
		$write = General::writeFile(MANIFEST . '/versions/' . $entry->get('id') . '/' . $new_version_number . '.xml', $data);
		// General::writeFile(MANIFEST . '/versions/' . $entry->get('id') . '/' . $new_version_number . '.dat', self::serializeEntry($entry));
		General::writeFile(MANIFEST . '/versions/' . $entry->get('id') . '/' . $new_version_number . '.dat', self::serializeEntry($entry));
		
		return $new_version_number;
		
	}
	
	public static function entryHistory($entry_id) {
		
		if (!$entry_id) return array();
		
		$entries = array();
		
		$files = General::listStructure(MANIFEST . '/versions/' . $entry_id . '/', '/.xml$/', false, 'desc');
		if (!is_array($files['filelist'])) $files['filelist'] = array();
		
		natsort($files['filelist']);
		$files['filelist'] = array_reverse($files['filelist']);

		
		foreach($files['filelist'] as $file) {
			$entry = new DomDocument();
			$entry->load($file);
			$entries[] = $entry;			
		}
		
		return $entries;
		
	}
	
	public static function getLatestVersion($entry_id) {
		
		$files = General::listStructure(MANIFEST . '/versions/' . $entry_id . '/', '/.xml$/', false, 'desc');
		if (isset($files)) {
			if (!is_array($files['filelist'])) $files['filelist'] = array();
			if (count($files['filelist']) == 0) return;
		
			natsort($files['filelist']);
			$files['filelist'] = array_reverse($files['filelist']);
		
			$file = reset($files['filelist']);
		
			$entry = new DomDocument();
			$entry->load($file);

			return $entry;
		}
		
	}
	
	public static function serializeEntry($entry) {
		$entry->findDefaultData();		
		$entry = array(
			'id' => $entry->get('id'),
			'author_id' => $entry->get('author_id'),
			'section_id' => $entry->get('section_id'),
			'creation_date' => $entry->get('creation_date'),
			'creation_date_gmt' => $entry->get('creation_date_gmt'),
			'data' => $entry->getData()
		);	
		return serialize($entry);
	}	
	
	// rebuild entry object from a previous version
	public static function unserializeEntry($entry_id, $version) {
		$entry = unserialize(
			file_get_contents(MANIFEST . '/versions/' . $entry_id . '/' . $version . '.dat')
		);
		
		$entryManager = new EntryManager(Symphony::Engine());		
		$new_entry = $entryManager->create();
		$new_entry->set('id', $entry['id']);
		$new_entry->set('author_id', $entry['author_id']);
		$new_entry->set('section_id', $entry['section_id']);
		$new_entry->set('creation_date', $entry['creation_date']);
		$new_entry->set('creation_date_gmt', $entry['creation_date_gmt']);
		
		foreach($entry['data'] as $field_id => $value) {
			$new_entry->setData($field_id, $value);
		}
		
		return $new_entry;
	}
	
}
