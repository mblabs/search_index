<?php
	
	require_once(TOOLKIT . '/class.administrationpage.php');
	require_once(TOOLKIT . '/class.sectionmanager.php');
	require_once(TOOLKIT . '/class.entrymanager.php');
	
	require_once(EXTENSIONS . '/search_index/lib/class.search_index.php');
	
	class contentExtensionSearch_IndexReindex extends AdministrationPage {
		
		public function __viewIndex() {
			
			$section_id = (string)$_GET['section'];
			$index = SearchIndex::getIndex($section_id);
			
			// create a DS and filter on System ID of the current entry to build the entry's XML			
			$ds = new ReindexDataSource(Administration::instance(), NULL, FALSE);
			$ds->dsSource = $section_id;
			$ds->dsParamFILTERS = $index['filters'];
			
			$param_pool = array();
			$grab_xml = $ds->grab($param_pool);
			
			$xml = $grab_xml->generate();

			$dom = new DomDocument();
			$dom->loadXML($xml);
			$xpath = new DomXPath($dom);
			
			$entry_ids = array();
			foreach($xpath->query("//entry") as $entry) {
				$entry_ids[] = $entry->getAttribute('id');
			}
			
			SearchIndex::indexEntry($entry_ids, $ds->dsSource, FALSE);
			
			header('Content-type: text/xml');
			echo $xml;
			exit;

		}
	}