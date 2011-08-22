<?php
	
	require_once(TOOLKIT . '/class.administrationpage.php');
	require_once(EXTENSIONS . '/search_index/lib/class.search_index.php');
	
	class contentExtensionSearch_IndexMark_Use_As_Suggestion extends AdministrationPage {
		protected $_errors = array();
		
		public function __construct(&$parent){
			parent::__construct($parent);			
		}
						
		public function __viewIndex() {
			$keywords = trim($_GET['query']);
			SearchIndex::deleteQuerySuggestion($keywords);
			if($_GET['use_as_suggestion'] == 'yes') {
				SearchIndex::addQuerySuggestion($keywords);
			}
			exit();
			
		}
	}