<?php
	
	require_once(EXTENSIONS . '/search_index/lib/class.search_index_administrationpage.php');

	class contentExtensionSearch_IndexSessions extends SearchIndex_AdministrationPage {
		
		public function build($context) {
			if (isset($_POST['filter']['keyword']) != '') {
				redirect(Administration::instance()->getCurrentPageURL() . '?keywords=' . $_POST['keywords']);
			}
			parent::build($context);
		}
						
		public function view() {
			
			parent::view();
			
			// Set up page meta data
			/*-----------------------------------------------------------------------*/	
			
			$this->setPageType('table');
			$this->setTitle(__('Symphony') . ' &ndash; ' . __('Search Indexes'));
			$this->appendSubheading(
				__('Search Index') . " &rsaquo; " .
				(($filter_session_id) ? '<a href="'.Administration::instance()->getCurrentPageURL() . '?pg=1&amp;sort='.$sort_column.'&amp;order='.$sort_order.'&amp;keywords='.$filter_keywords.'&amp;session_id=">'.__('Logs').'</a> &rsaquo; Session ' . $filter_session_id : __('Logs') ).
				Widget::Anchor(
					__('Export CSV'),
					Administration::instance()->getCurrentPageURL(). '?view=export&amp;sort='.$sort_column.'&amp;order='.$sort_order.'&amp;keywords='.$filter_keywords,
					NULL,
					'button'
				)->generate()
			);
			
			
			// Get URL parameters, set defaults
			/*-----------------------------------------------------------------------*/	
			$sort = (object)$_GET['sort'];
			$filter = (object)$_GET['filter'];
			$pagination = (object)$_GET['pagination'];
			
			if (!isset($sort->column)) $sort->column = 'date';
			if (!isset($sort->direction)) $sort->direction = 'desc';
			if (!isset($filter->keywords)) $filter->keywords = '';
			if (!isset($filter->session_id)) $filter->session_id = '';
			if (!isset($filter->date_from)) $filter->date_from = date('Y-m-d', strtotime('last month'));
			if (!isset($filter->date_to)) $filter->date_to = date('Y-m-d', strtotime('today'));
			
			
			// Build pagination and fetch rows
			/*-----------------------------------------------------------------------*/
			$pagination->{'per-page'} = 10;
			$pagination->{'current-page'} = (@(int)$pagination->{'current-page'} > 1 ? (int)$pagination->{'current-page'} : 1);
			
			// get the logs!
			$rows = SearchIndexLogs::getSessions(
				$sort->column, $sort->direction,
				$pagination->{'current-page'}, $pagination->{'per-page'},
				$filter
			);
			
			// total number of unique query terms
			$pagination->{'total-entries'} = SearchIndexLogs::getTotalSessions($filter);
			
			$pagination->start = max(1, (($pagination->{'current-page'} - 1) * $pagination->{'per-page'}));
			$pagination->end = ($pagination->start == 1 ? $pagination->{'per-page'} : $pagination->start + count($rows));
			$pagination->{'total-pages'} = ceil($pagination->{'total-entries'} / $pagination->{'per-page'});

			// cache amended filters for use elsewhere
			$this->sort = $sort;
			$this->filter = $filter;
			$this->pagination = $pagination;
			
			/*$stats = array(
				'unique-users' => SearchIndexLogs::getStatsCount('unique-users', $filter),
				'unique-searches' => SearchIndexLogs::getStatsCount('unique-searches', $filter),
				'unique-terms' => SearchIndexLogs::getStatsCount('unique-terms', $filter),
				'average-results' => SearchIndexLogs::getStatsCount('average-results', $filter)
			);
			
			$filters = new XMLElement('div', NULL, array('class' => 'search-index-log-filters'));
			$label = new XMLElement('label', __('Filter searches containing the keywords %s', array(Widget::Input('keywords', $filter_keywords)->generate())));
			$filters->appendChild($label);
			$filters->appendChild(new XMLElement('input', NULL, array('type' => 'submit', 'value' => __('Filter'), 'name' => 'filter[keyword]')));
			
			$filters->appendChild(new XMLElement('p', sprintf(__('<strong>%s</strong> unique searches from <strong>%s</strong> unique users via <strong>%s</strong> distinct search terms. Each search yielded an average of <strong>%s</strong> results.', array($stats['unique-searches'], $stats['unique-users'], $stats['unique-terms'], $stats['average-results']))), array('class' => 'intro')));
			
			$this->Form->appendChild($filters);*/
			
			$tableHead = array();
			$tableBody = array();
			
			// append table headings
			$tableHead[] = $this->__buildColumnHeader(__('Date'), 'date', 'desc');
			$tableHead[] = $this->__buildColumnHeader(__('Query'), 'keywords', 'desc');
			$tableHead[] = $this->__buildColumnHeader(__('Results'), 'results', 'desc');
			$tableHead[] = $this->__buildColumnHeader(__('Depth'), 'depth', 'desc');
			
			if (!is_array($rows) or empty($rows)) {
				$tableBody = array(
					Widget::TableRow(array(Widget::TableData(__('None Found.'), 'inactive', null, count($tableHead))))
				);
			}
			
			else {
				
				$alt = FALSE;
				foreach ($rows as $row) {
					
					$r = array();
					$r[] = Widget::TableData($row['session_id'], 'inactive');
					$r[] = Widget::TableData($row['ip'], 'inactive');
					$r[] = Widget::TableData($row['user_agent'], 'inactive', NULL, 6);
					$tableBody[] = Widget::TableRow($r, 'session-meta ' . ($alt ? 'alt' : ''));
					
					$searches = SearchIndexLogs::getSessionSearches($row['session_id']);
					
					foreach($searches as $i => $search) {
						
						$r = array();
						
						$r[] = Widget::TableData(
							DateTimeObj::get(
								__SYM_DATETIME_FORMAT__,
								strtotime($search['date'])
							),
							'date'
						);

						$keywords = $search['keywords'];
						$keywords_class = '';
						if ($keywords == '') {
							$keywords = __('None');
							$keywords_class = 'inactive';
						}
						
						$r[] = Widget::TableData($keywords, $keywords_class . ' keywords');
						
						$r[] = Widget::TableData($search['results'], 'results');
						$r[] = Widget::TableData($search['page'], 'depth');
						
						$r[] = Widget::TableData('', NULL, NULL, 4);

						$tableBody[] = Widget::TableRow($r, 'search ' . ($alt ? 'alt' : '') . ($i == (count($searches) - 1) ? ' last' : ''));
						
					}
					
					
					
					$alt = !$alt;
					
				}
			}
			
			$table = Widget::Table(Widget::TableHead($tableHead), NULL, Widget::TableBody($tableBody), 'sessions');
			$this->Form->appendChild($table);
			
			// build pagination
			if ($pagination->{'total-pages'} > 1) {
				$this->Form->appendChild($this->__buildPagination($pagination));
			}

		}
	}
	
	
	
	// if($filter_view == 'export') {
	// 	
	// 	$file_path = sprintf('%s/search-index.log.%d.csv', TMP, time());
	// 	$csv = fopen($file_path, 'w');
	// 	
	// 	fputcsv($csv, array(__('Date'), __('Keywords'), __('Adjusted Keywords'), __('Results'), __('Depth'), __('Session ID')), ',', '"');
	// 	
	// 	foreach($rows as $row) {
	// 		fputcsv($csv, array(
	// 			$row['date'],
	// 			$row['keywords'],
	// 			$row['keywords_manipulated'],
	// 			$row['results'],
	// 			$row['depth'],
	// 			$row['session_id']
	// 		), ',', '"');
	// 	}
	// 	
	// 	fclose($csv);
	// 	
	// 	header('Content-type: application/csv');
	// 	header('Content-Disposition: attachment; filename="' . end(explode('/', $file_path)) . '"');
	// 	readfile($file_path);
	// 	unlink($file_path);
	// 	
	// 	exit;
	// 	
	// }
	// 
	