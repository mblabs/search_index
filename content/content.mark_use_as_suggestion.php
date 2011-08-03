<?php
	
	require_once(TOOLKIT . '/class.administrationpage.php');
	require_once(EXTENSIONS . '/search_index/lib/class.search_index.php');
	
	class contentExtensionSearch_IndexMark_Use_As_Suggestion extends AdministrationPage {
		protected $_errors = array();
		
		public function __construct(&$parent){
			parent::__construct($parent);			
		}
						
		public function __viewIndex() {
			
			$query = strtolower(trim($_GET['query']));
			$use_as_suggestion = $_GET['use_as_suggestion'];
			
			$suggestions = SearchIndex::getQuerySuggestions();
			
			foreach($suggestions as $i => $suggestion) {
				if($suggestion == $query) unset($suggestions[$i]);
			}
			
			if($use_as_suggestion == 'yes') $suggestions[] = $query;
			
			SearchIndex::saveQuerySuggestions($suggestions);
			
			exit();
			
		}
	}