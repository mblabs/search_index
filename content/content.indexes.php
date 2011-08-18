<?php
	
	require_once(EXTENSIONS . '/search_index/lib/class.search_index_administrationpage.php');
	
	require_once(TOOLKIT . '/class.sectionmanager.php');
	require_once(TOOLKIT . '/class.entrymanager.php');
	
	require_once(EXTENSIONS . '/search_index/lib/class.entry_xml_datasource.php');
	require_once(EXTENSIONS . '/search_index/lib/class.reindex_datasource.php');
	
	class contentExtensionSearch_IndexIndexes extends SearchIndex_AdministrationPage {
		
		public function __construct(&$parent){
			parent::__construct($parent);
			$this->weightings = array(
				__('Highest'),
				__('High'),
				__('Medium (none)'),
				__('Low'),
				__('Lowest')
			);
		}
		
		public function __actionIndex() {
			$checked = @array_keys($_POST['items']);
			
			if (is_array($checked) and !empty($checked)) {
				switch ($_POST['with-selected']) {
					
					case 'delete':
						foreach ($checked as $section_id) {
							SearchIndex::deleteIndex($section_id);
						}						
						redirect("{$this->uri}/indexes/");
						break;
						
					case 're-index':
						redirect("{$this->uri}/indexes/?section=" . join(',', $checked));
						break;
				}
			}
		}
		
		public function __viewIndex() {
			$this->setPageType('table');
			$this->setTitle(__('Symphony') . ' &ndash; ' . __('Search Index') . ' &ndash; ' . __('Indexes'));
			
			$this->appendSubheading(
				__('Search Index') . " &rsaquo; " . __('Indexes') .
				Widget::Anchor(__('Create New'), Administration::instance()->getCurrentPageURL().'new/', __('Create New'), 'create button')->generate()
			);
			$this->Form->appendChild(new XMLElement('p', __('Configure how each of your sections are indexed. Choose which field text values to index, which entries to index, and the weighting of the section in search results.'), array('class' => 'intro')));
						
			$sm = new SectionManager(Administration::instance());
			$sections = $sm->fetch();
			$indexes = SearchIndex::getIndexes();
			
			$tableHead = array();
			$tableBody = array();
			
			$tableHead[] = array(__('Section'), 'col');
			$tableHead[] = array(__('Fields'), 'col');
			$tableHead[] = array(__('Weighting'), 'col');
			$tableHead[] = array(__('Index Size'), 'col');
			
			if (!is_array($indexes) or empty($indexes)) {
				$tableBody = array(
					Widget::TableRow(array(Widget::TableData(__('None Found.'), 'inactive', null, count($tableHead))))
				);
			}
			
			else {
				
				$re_index = explode(',', $_GET['section']);
				
				foreach ($sections as $section) {
					
					$index = SearchIndex::getIndex($section->get('id'));
					if(!$index) continue;
					
					$col_name = Widget::TableData(
						Widget::Anchor(
							$section->get('name'),
							"{$this->uri}/indexes/edit/{$index['section_id']}/"
						)
					);
					
					$col_name->appendChild(Widget::Input("items[{$index['section_id']}]", NULL, 'checkbox'));
					
					if ($index && isset($index['included_fields']) && count($index['included_fields'] > 0)) {
						$section_fields = $section->fetchFields();
						$fields = $index['included_fields'];
						$fields_list = '';
						foreach($section_fields as $section_field) {
							if (in_array($section_field->get('element_name'), array_values($fields))) {
								$fields_list .= $section_field->get('label') . ', ';
							}
						}
						$fields_list = trim($fields_list, ', ');
						$col_fields = Widget::TableData($fields_list);
					} else {
						$col_fields = Widget::TableData(__('None'), 'inactive');
					}
					
					if ($index) {
						if($index['weighting'] == '') $index['weighting'] = 2;
						$col_weighting = Widget::TableData($this->weightings[$index['weighting']]);
					} else {
						$col_weighting = Widget::TableData(__('None'), 'inactive');
					}
					
					$count_data = null;
					$count_class = null;
					
					if (isset($_GET['section']) && in_array($index['section_id'], $re_index)) {
						SearchIndex::deleteIndexedEntriesBySection($index['section_id']);
						$count_data = '<span class="to-re-index" id="section-'.$index['section_id'].'">' . __('Waiting to re-index...') . '</span>';
					}
					else {
						$count = Symphony::Database()->fetchVar('count', 0,
							sprintf(
								"SELECT COUNT(entry_id) as `count` FROM tbl_search_index_data WHERE `section_id`='%d'",
								$index['section_id']
							)
						);
						$count_data = $count . ' ' . (((int)$count == 1) ? __('entry') : __('entries'));
					}
					
					$col_count = Widget::TableData($count_data, $count_class . ' count-column');
					
					$tableBody[] = Widget::TableRow(array($col_name, $col_fields, $col_weighting, $col_count), 'section-' . $index['section_id']);

				}
			}
			
			$table = Widget::Table(
				Widget::TableHead($tableHead), null, 
				Widget::TableBody($tableBody),
				'selectable'
			);
			
			$this->Form->appendChild($table);
			
			$actions = new XMLElement('div');
			$actions->setAttribute('class', 'actions');
			
			$options = array(
				array(null, false, __('With Selected...')),
				array('re-index', false, __('Re-index Entries')),
				array('delete', false, __('Delete')),
			);
			
			$actions->appendChild(Widget::Select('with-selected', $options));
			$actions->appendChild(Widget::Input('action[apply]', __('Apply'), 'submit'));
			
			$this->Form->appendChild($actions);

		}
		
		public function __actionNew() {
			$this->__actionEdit();
		}
		
		public function __viewNew() {
			$this->__viewEdit();
		}
		
		public function __actionEdit() {
			
			$index = $_POST['fields'];
			$index_exists = SearchIndex::getIndex($index['section_id']);
			
			if(@array_key_exists('delete', $_POST['action'])) {
				SearchIndex::deleteIndex($this->id);
				redirect("{$this->uri}/indexes/");
			}
			
			$is_new = !is_array($index_exists);
			
			if (!is_array($index['filter'])) $index['filter'] = array($index['filter']);
			
			$filters = array();
			foreach($index['filter'] as $filter) {
				if (is_null($filter)) continue;
				$filters[key($filter)] = $filter[key($filter)];
			}
			unset($index['filter']);
			
			$index['filters'] = $filters;
			
			$sm = new SectionManager(Administration::instance());
			$section = $sm->fetch($this->id);
			$index['section_id'] = (int)$section->get('id');
			
			SearchIndex::saveIndex($index);
			
			redirect("{$this->uri}/indexes/");
		}
		
		public function __viewEdit() {
			
			$section_id = $this->id;
			$sm = new SectionManager(Administration::instance());
			
			$sections = $sm->fetch();
			$indexes = SearchIndex::getIndexes();
			
			$indexed_section_ids = array();
			foreach($indexes as $i) $indexed_section_ids[] = $i['section_id'];
			
			$section = $sm->fetch($section_id);
			$index = SearchIndex::getIndex($section_id);
			
			if (!is_array($index['included_fields'])) $index['included_fields'] = array($index['included_fields']);
			if (!is_array($index['filters'])) $index['filters'] = array($index['filters']);
			
			$this->setPageType('form');
			
			if($this->mode == 'new' && !$this->id) {
				
				$this->setTitle(__('Symphony') . ' &ndash; ' . __('Search Index') . ' &ndash; ' . __('New Index'));
				$this->appendSubheading(__('Search Index') . " &rsaquo; <a href=\"{$this->uri}/indexes/\">" . __('Indexes') . "</a> <span class='meta'>" . __('New Index') . "</span>");
				
				$fieldset = new XMLElement('fieldset');
				$fieldset->setAttribute('class', 'settings');
				$fieldset->appendChild(new XMLElement('legend', __('Essentials')));
				
				$p = new XMLElement('p', __('Choose a section to index.'));
				$p->setAttribute('class', 'help');
				$fieldset->appendChild($p);

				$group = new XMLElement('div');
				$group->setAttribute('class', 'group');

				$section_options = array();
				
				$section_options[] = array(
					NULL,
					FALSE,
					''
				);
				
				foreach($sections as $s) {
					$section_options[] = array(
						$s->get('id'),
						FALSE,
						$s->get('name') . ((in_array($s->get('id'), array_values($indexed_section_ids))) ? ' (' . __('already indexed') . ')' : ''),
						NULL,
						NULL,
						(in_array($s->get('id'), array_values($indexed_section_ids))) ? array('disabled' => 'disabled') : NULL
					);
				}

				$label = Widget::Label(__('Section'));
				$label->appendChild(Widget::Select(
					'fields[section_id]',
					$section_options
				));
				$group->appendChild($label);

				$fieldset->appendChild($group);
				$this->Form->appendChild($fieldset);
				
			} else {
				
				$this->setTitle(__('Symphony') . ' &ndash; ' . __('Search Index') . ' &ndash; ' . $section->get('name'));
				$this->appendSubheading(__('Search Index') . " &rsaquo; <a href=\"{$this->uri}/indexes/\">" . __('Indexes') . "</a> <span class='meta'>" . $section->get('name') . "</span>");
				
				$fields = array('fields' => $section->fetchFields(), 'section' => $section->get('id'));

				$fields_options = array();
				foreach($fields['fields'] as $f) {				
					$fields_options[] = array(
						$f->get('element_name'),
						in_array($f->get('element_name'), $index['included_fields']),
						$f->get('label')
					);
				}

				$fieldset = new XMLElement('fieldset');
				$fieldset->setAttribute('class', 'settings');
				$fieldset->appendChild(new XMLElement('legend', __('Included Fields')));
				$p = new XMLElement('p', __('Only the content of selected fields will be indexed.'));
				$p->setAttribute('class', 'help');
				$fieldset->appendChild($p);

				$group = new XMLElement('div');
				$group->setAttribute('class', 'group');

				$label = Widget::Label(__('Included Fields'));
				$label->appendChild(Widget::Select(
					'fields[included_fields][]',
					$fields_options,
					array('multiple'=>'multiple')
				));
				$group->appendChild($label);

				$weighting_options = array();
				if ($index['weighting'] == NULL) $index['weighting'] = 2;
				foreach($this->weightings as $i => $w) {
					$weighting_options[] = array(
						$i,
						($i == $index['weighting']),
						$w
					);
				}

				$label = Widget::Label(__('Weighting'));
				$label->appendChild(Widget::Select(
					'fields[weighting]',
					$weighting_options
				));
				$group->appendChild($label);

				$fieldset->appendChild($group);
				$this->Form->appendChild($fieldset);

				$fieldset = new XMLElement('fieldset');
				$fieldset->setAttribute('class', 'settings contextual ' . __('sections') . ' ' . __('authors') . ' ' . __('navigation') . ' ' . __('Sections') . ' ' . __('System'));
				$fieldset->appendChild(new XMLElement('legend', __('Index Filters')));
				$p = new XMLElement('p', __('Only entries that pass these filters will be indexed.'));
				$p->setAttribute('class', 'help');
				$fieldset->appendChild($p);

				$div = new XMLElement('div');
				$div->setAttribute('class', 'contextual ' . $section->get('id'));
				$h3 = new XMLElement('p', __('Filter %s by', array($section->get('name'))), array('class' => 'label'));
				$h3->setAttribute('class', 'label');
				$div->appendChild($h3);

				$ol = new XMLElement('ol');
				$ol->setAttribute('class', 'filters-duplicator');

				if(isset($index['filters']['id'])){
					$li = new XMLElement('li');
					$li->setAttribute('class', 'unique');
					$li->appendChild(new XMLElement('h4', __('System ID')));
					$label = Widget::Label(__('Value'));
					$label->appendChild(Widget::Input('fields[filter]['.$section->get('id').'][id]', General::sanitize($index['filters']['id'])));
					$li->appendChild($label);
					$ol->appendChild($li);				
				}

				$li = new XMLElement('li');
				$li->setAttribute('class', 'unique template');
				$li->appendChild(new XMLElement('h4', __('System ID')));
				$label = Widget::Label(__('Value'));
				$label->appendChild(Widget::Input('fields[filter]['.$section->get('id').'][id]'));
				$li->appendChild($label);
				$ol->appendChild($li);

				if(is_array($fields['fields']) && !empty($fields['fields'])){
					foreach($fields['fields'] as $input){

						if(!$input->canFilter()) continue;

						if(isset($index['filters'][$input->get('id')])){
							$wrapper = new XMLElement('li');
							$wrapper->setAttribute('class', 'unique');
							$input->displayDatasourceFilterPanel($wrapper, $index['filters'][$input->get('id')], $this->_errors[$input->get('id')], $section->get('id'));
							$ol->appendChild($wrapper);					
						}

						$wrapper = new XMLElement('li');
						$wrapper->setAttribute('class', 'unique template');
						$input->displayDatasourceFilterPanel($wrapper, NULL, NULL, $section->get('id'));
						$ol->appendChild($wrapper);

					}
				}

				$div->appendChild($ol);
				$fieldset->appendChild($div);
				$this->Form->appendChild($fieldset);

				$div = new XMLElement('div');
				$div->setAttribute('class', 'actions');
				$div->appendChild(
					Widget::Input('action[save]',
						($this->mode == 'new') ? __('Create Index') : __('Save Changes'),
						'submit', array(
							'accesskey' => 's'
						)
					)
				);
				
				if($this->mode == 'edit'){
					$button = new XMLElement('button', __('Delete'));
					$button->setAttributeArray(array('name' => 'action[delete]', 'class' => 'button confirm delete', 'title' => __('Delete this index'), 'accesskey' => 'd', 'data-message' => __('Are you sure you want to delete this index?')));
					$div->appendChild($button);
				}

				$this->Form->appendChild($div);
				
			}
			
		}
		
	}