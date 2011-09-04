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
		
		public static function sortFrequencyDesc($a, $b) {
			return $a < $b;
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
			
			$sql_indexed_words = sprintf(
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
				LIMIT 0, 25",
				Symphony::Database()->cleanValue($keywords),
				(count($sections) > 0) ? sprintf('AND `entry`.section_id IN (%s)', implode(',', array_keys($sections))) : NULL,
				$sort
			);
			
			$sql_indexed_phrases = sprintf(
				"SELECT
					SUBSTRING_INDEX(
						SUBSTRING(CONVERT(LOWER(`data`) USING utf8), LOCATE('%1\$s', CONVERT(LOWER(`data`) USING utf8))),
						' ',
						%2\$d
					) as `keyword`,
					COUNT(id) as `frequency`
				FROM
					tbl_search_index_data
				WHERE
					LOWER(`data`) LIKE '%3\$s'
					%4\$s
				GROUP BY
					`keyword`
				ORDER BY
					`frequency` DESC,
					`keyword` ASC
				LIMIT
					0, 25",
				Symphony::Database()->cleanValue($keywords),
				((substr_count($keywords, ' ')) >= 3) ? 3 : substr_count($keywords, ' ') + 2,
				'%' . Symphony::Database()->cleanValue($keywords) . '%',
				(count($sections) > 0) ? sprintf('AND `section_id` IN (%s)', implode(',', array_keys($sections))) : NULL
			);
			//echo $sql_phrases;die;
			
			$section_handles = array_map('reset', array_values($sections));
			natsort($section_handles);
			
			$sql_logged_phrases = sprintf(
				"SELECT
					SUBSTRING_INDEX(
						SUBSTRING(CONVERT(LOWER(`keywords`) USING utf8), LOCATE('%1\$s', CONVERT(LOWER(`keywords`) USING utf8))),
						' ',
						%2\$d
					) as `keyword`,
					COUNT(id) as `frequency`
				FROM
					tbl_search_index_logs
				WHERE
					LOWER(`keywords`) LIKE '%3\$s'
					%4\$s
					AND `results` > 0
				GROUP BY
					`keyword`
				ORDER BY
					`frequency` DESC,
					`keyword` ASC
				LIMIT
					0, 25",
				Symphony::Database()->cleanValue($keywords),
				((substr_count($keywords, ' ')) >= 3) ? 4 : substr_count($keywords, ' ') + 3,
				'%' . Symphony::Database()->cleanValue($keywords) . '%',
				(count($sections) > 0) ? sprintf("AND CONCAT(',', `sections`, ',') LIKE '%s'", '%,' . implode(',',$section_handles) . ',%') : NULL
			);
			//echo $sql_logged_phrases;die;

		
		// Run!
		/*-----------------------------------------------------------------------*/
			
			$indexed_words = Symphony::Database()->fetch($sql_indexed_words);
			$indexed_phrases = Symphony::Database()->fetch($sql_indexed_phrases);
			$logged_phrases = Symphony::Database()->fetch($sql_logged_phrases);
			
			$terms = array();
			foreach($indexed_words as $term) {
				$keyword = strtolower(SearchIndex::stripPunctuation($term['keyword']));
				$terms[$keyword] = (int)$term['frequency'];
			}
			foreach($indexed_phrases as $term) {
				$keyword = strtolower(SearchIndex::stripPunctuation($term['keyword']));
				if(isset($terms[$keyword])) {
					$terms[$keyword] += (int)$term['frequency'];
				} else {
					$terms[$keyword] = (int)$term['frequency'];
				}
			}
			foreach($logged_phrases as $term) {
				$keyword = strtolower(SearchIndex::stripPunctuation($term['keyword']));
				if(isset($terms[$keyword])) {
					$terms[$keyword] += (int)$term['frequency'];
				} else {
					$terms[$keyword] = (int)$term['frequency'];
				}
				// from search logs given heavier weighting
				$terms[$keyword] = $terms[$keyword] * 3;
				$terms['___' . $keyword] = $terms[$keyword] * 3;
				unset($terms[$keyword]);
			}
			
			uasort($terms, array('datasourcesearch_suggestions', 'sortFrequencyDesc'));
			
			$i = 0;
			foreach($terms as $term => $frequency) {
				if($i > 25) continue;
				
				$words = explode(' ', $term);
				$last_word = end($words);
				
				if(SearchIndex::isStopWord($last_word)) {
					continue;
				}
				if(SearchIndex::strlen($last_word) >= (int)Symphony::Configuration()->get('max-word-length', 'search_index') || SearchIndex::strlen($last_word) < (int)Symphony::Configuration()->get('min-word-length', 'search_index')) {
					continue;
				}
				
				$is_phrase = FALSE;
				if(preg_match('/^___/', $term)) {
					$term = trim($term, '_');
					$is_phrase = TRUE;
				}
				
				$result->appendChild(
					new XMLElement(
						'word',
						General::sanitize($term),
						array(
							'weighting' => $frequency,
							'handle' => Lang::createHandle($term)
						)
					)
				);
				
				$i++;
			}
			
			return $result;
	
	}
}