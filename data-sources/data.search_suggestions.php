<?php
	
	require_once(EXTENSIONS . '/search_index/lib/class.search_index.php');
	require_once(TOOLKIT . '/class.datasource.php');
	require_once(TOOLKIT . '/class.fieldmanager.php');
	require_once(TOOLKIT . '/class.entrymanager.php');
	
	Class datasourcesearch_suggestions extends Datasource{
		
		public $dsParamROOTELEMENT = 'search-suggestions';
		public $dsParamLIMIT = '1';
		public $dsParamSTARTPAGE = '1';
		
		public function __construct(&$parent, $env=NULL, $process_params=true){
			parent::__construct($parent, $env, $process_params);
		}
		
		public static function sortWordDistance($a, $b) {
			return $a['distance'] > $b['distance'];
		}
		
		public function about(){
			return array(
					'name' => 'Search Index Suggestions',
					'author' => array(
							'name' => 'Nick Dunn',
							'website' => 'http://nick-dunn.co.uk'
						)
					);	
		}
		
		public function getSource(){
			return NULL;
		}
		
		public function allowEditorToParse(){
			return FALSE;
		}
		
		public function grab(&$param_pool) {
			
			$result = new XMLElement($this->dsParamROOTELEMENT);
			
		// Set up keywords
		/*-----------------------------------------------------------------------*/	

			$keywords = (string)$_GET['keywords'];
			
			$sort = (string)$_GET['sort'];
			if($sort == '' || $sort == 'alphabetical') {
				$sort = '`keyword` ASC';
			} elseif($sort == 'frequency') {
				$sort = '`frequency` DESC';
			}
			
			if(strlen($keywords) <= 2) return $result;
					
			
		// Set up sections
		/*-----------------------------------------------------------------------*/	
		
			if(isset($_GET['sections'])) {
				$param_sections = $_GET['sections'];
				// allow sections to be sent as an array if the user wishes (multi-select or checkboxes)
				if(is_array($param_sections)) implode(',', $param_sections);
			} else {
				$param_sections = '';
			}
			
			$sections = array();
			foreach(array_map('trim', explode(',', $param_sections)) as $handle) {
				$section = Symphony::Database()->fetchRow(0,
					sprintf(
						"SELECT `id`, `name` FROM `tbl_sections` WHERE handle = '%s' LIMIT 1",
						Symphony::Database()->cleanValue($handle)
					)
				);
				if ($section) $sections[$section['id']] = array('handle' => $handle, 'name' => $section['name']);
			}
		
		// Build SQL
		/*-----------------------------------------------------------------------*/	
			
			$sql_words = sprintf(
				"SELECT
					`keywords`.`keyword`,
					SUM(`entry_keywords`.`frequency`) AS `frequency`
				FROM
					`tbl_search_index_keywords` AS `keywords`
					INNER JOIN `tbl_search_index_entry_keywords` AS `entry_keywords` ON (`keywords`.`id` = `entry_keywords`.`keyword_id`)
					INNER JOIN `sym_entries` AS `entry` ON (`entry_keywords`.`entry_id` = `entry`.`id`)
				WHERE
					`keywords`.`keyword` LIKE '%s%%'
					%s
				GROUP BY `keywords`.`keyword`
				ORDER BY %s
				LIMIT 0, 15",
				Symphony::Database()->cleanValue($keywords),
				(count($sections) > 0) ? sprintf('AND `entry`.section_id IN (%s)', implode(',', array_keys($sections))) : NULL,
				$sort
			);
			
			$sql_phrases = sprintf(
				"SELECT
					SUBSTRING_INDEX(
						SUBSTRING(CONVERT(LOWER(data) USING utf8), LOCATE('%1\$s', CONVERT(LOWER(data) USING utf8))),
						' ',
						%2\$d
					) as keyword
				FROM
					sym_search_index_data
				WHERE
					LOWER(data) like '%3\$s'
					%4\$s
				GROUP BY
					SUBSTRING_INDEX(
						SUBSTRING(
							CONVERT(LOWER(data) USING utf8), LOCATE('%1\$s', CONVERT(LOWER(data) USING utf8))
						),
						' ',
						%2\$d
					)
				LIMIT
					0, 15",
				Symphony::Database()->cleanValue($keywords),
				((substr_count($keywords, ' ')) >= 3) ? 3 : substr_count($keywords, ' ') + 2,
				'%' . Symphony::Database()->cleanValue($keywords) . '%',
				(count($sections) > 0) ? sprintf('AND section_id IN (%s)', implode(',', array_keys($sections))) : NULL
			);

		
		// Run!
		/*-----------------------------------------------------------------------*/
			
			// single word, search the indivudual word index
			if(substr_count($keywords, ' ') == 0) {
				$words = Symphony::Database()->fetch($sql_words);
			}
			// phrase, look for this phrase in full index
			else {
				$words = Symphony::Database()->fetch($sql_phrases);
				
				foreach($words as $i => $word) {
					
					$words[$i]['frequency'] = 1;
					$words[$i]['keyword'] = SearchIndex::stripPunctuation($word['keyword']);
					
					// don't use if last word fails basic criteria, prevents something like
					// "symphony is a", should just be "symphony"
					$last_word = end(explode(' ', $word['keyword']));
					if(
						SearchIndex::strlen($last_word) >= (int)Symphony::Configuration()->get('max-word-length', 'search_index') ||
						SearchIndex::strlen($last_word) < (int)Symphony::Configuration()->get('min-word-length', 'search_index') ||
						SearchIndex::isStopWord($last_word)
					) {
						unset($words[$i]);
					}
				}
				
			}
			
			// get curated autosuggestions partial matching this query
			$autosuggest = SearchIndex::getQuerySuggestions($keywords, TRUE);
			
			foreach($autosuggest as $i => $word) {
				$result->appendChild(
					new XMLElement(
						'word',
						General::sanitize($word),
						array(
							'curated' => 'yes',
							'handle' => Lang::createHandle($word)
						)
					)
				);
				// store lowercase for uniqueness comparison later
				$autosuggest[$i] = strtolower($word);
			}
			
			foreach($words as $word) {
				// if already matched in the autosuggest output, do not repeat here
				if(in_array(strtolower($word['keyword']), $autosuggest)) continue;
				
				$result->appendChild(
					new XMLElement(
						'word',
						General::sanitize($word['keyword']),
						array(
							'frequency' => $word['frequency'],
							'handle' => Lang::createHandle($word['keyword'])
						)
					)
				);
			}
			
			return $result;
	
	}
}