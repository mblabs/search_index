<?php
	
	require_once(TOOLKIT . '/class.administrationpage.php');
	require_once(EXTENSIONS . '/search_index/lib/class.search_index.php');
	
	class contentExtensionSearch_IndexMark_Use_As_Suggestion extends AdministrationPage {
		protected $_errors = array();
		
		public function __construct(&$parent){
			parent::__construct($parent);			
		}
						
		public function __viewIndex() {
			
			$keywords = $_GET['keywords'];
			$use_as_suggestion = $_GET['use_as_suggestion'];
			
			Symphony::Database()->query(sprintf(
				"UPDATE tbl_search_index_logs SET use_as_suggestion='%s' WHERE keywords='%s'",
				$use_as_suggestion, Symphony::Database()->cleanValue($keywords)
			));
			
			exit();
			
		}
	}