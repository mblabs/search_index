<?php
	
	require_once(EXTENSIONS . '/search_index/lib/class.search_index_administrationpage.php');
	
	class contentExtensionSearch_IndexSynonyms extends SearchIndex_AdministrationPage {
		
		public function __actionIndex() {
			$checked = @array_keys($_POST['items']);
			
			if (is_array($checked) and !empty($checked)) {
				switch ($_POST['with-selected']) {
					case 'delete':
						foreach ($checked as $id) {
							SearchIndex::deleteSynonym($id);
						}
						redirect("{$this->uri}/synonyms/");
					break;
				}
			}
		}
		
		public function view() {
			parent::view();
			$this->setTitle(__('Symphony') . ' &ndash; ' . __('Search Index') . ' &ndash; ' . __('Synonyms'));
		}
		
		public function __viewIndex() {
			
			$this->setPageType('table');
			
			$this->appendSubheading(
				__('Search Index') . ' &rsaquo; ' . __('Synonyms') . 
				Widget::Anchor(__('Create New'), Administration::instance()->getCurrentPageURL().'new/', __('Create New'), 'create button')->generate()
			);
			$this->Form->appendChild(new XMLElement('p', __('Configure synonym expansion, so that common misspellings or variations of phrases can be normalised to a single phrase.'), array('class' => 'intro')));
						
			$tableHead = array();
			$tableBody = array();
			
			$tableHead[] = array(__('Word'), 'col');
			$tableHead[] = array(__('Synonyms'), 'col');
			
			$synonyms = SearchIndex::getSynonyms();
			
			if (!is_array($synonyms) or empty($synonyms)) {
				$tableBody = array(
					Widget::TableRow(array(Widget::TableData(__('None Found.'), 'inactive', null, count($tableHead))))
				);
			}
			
			else {
				
				foreach ($synonyms as $synonym) {					
					$col_word = Widget::TableData(
						Widget::Anchor(
							$synonym['word'],
							"{$this->uri}/synonyms/edit/{$synonym['id']}/"
						)
					);
					$col_word->appendChild(Widget::Input("items[{$synonym['id']}]", null, 'checkbox'));
					$col_synonyms = Widget::TableData($synonym['synonyms']);
					$tableBody[] = Widget::TableRow(array($col_word, $col_synonyms));
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
				array(NULL, FALSE, __('With Selected...')),
				array('delete', FALSE, __('Delete')),
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
			$synonym = $_POST['synonym'];
			
			if(@array_key_exists('delete', $_POST['action'])) {
				SearchIndex::deleteSynonym($this->id);
				redirect("{$this->uri}/synonyms/");
			}
			
			if($this->id) $synonym['id'] = $this->id;
			SearchIndex::saveSynonym($synonym);			
			redirect("{$this->uri}/synonyms/");
		}
		
		public function __viewEdit() {
			
			if($this->id) {
				$synonym = SearchIndex::getSynonym($this->id);
			}
			
			$this->setPageType('form');
			$this->appendSubheading(__('Search Index') . " &rsaquo; <a href=\"{$this->uri}/synonyms/\">" . __('Synonyms') . "</a>" . (!is_null($this->_synonym) ? ' <span class="meta">' . $this->_synonym['word'] . '</span>' : ''));
			
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings');
			$fieldset->appendChild(new XMLElement('legend', __('Replacement word')));
			$p = new XMLElement('p', __('Matching synonyms will be replaced with this word.'));
			$p->setAttribute('class', 'help');
			$fieldset->appendChild($p);
						
			$label = Widget::Label(__('Word'));
			$label->appendChild(Widget::Input(
				'synonym[word]',
				$synonym['word']
			));
			$fieldset->appendChild($label);
			$fieldset->appendChild(new XMLElement('p', __('e.g. United Kingdom'), array('class'=>'help')));
			
			$this->Form->appendChild($fieldset);
			
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings');
			$fieldset->appendChild(new XMLElement('legend', __('Synonyms')));
			$p = new XMLElement('p', __('These words will be replaced with the word above. Separate multiple words with commas.'));
			$p->setAttribute('class', 'help');
			$fieldset->appendChild($p);
						
			$label = Widget::Label(__('Synonyms'));
			$label->appendChild(Widget::Textarea(
				'synonym[synonyms]',
				5, 40,
				$synonym['synonyms']
			));
			$fieldset->appendChild($label);
			$fieldset->appendChild(new XMLElement('p', __('e.g. UK, Great Britain, GB'), array('class'=>'help')));
			
			$this->Form->appendChild($fieldset);
			
			$div = new XMLElement('div');
			$div->setAttribute('class', 'actions');
			$div->appendChild(
				Widget::Input('action[save]',
					__('Save Changes'),
					'submit', array(
						'accesskey'		=> 's'
					)
				)
			);
			
			if($this->mode == 'edit'){
				$button = new XMLElement('button', __('Delete'));
				$button->setAttributeArray(array('name' => 'action[delete]', 'class' => 'button confirm delete', 'title' => __('Delete this synonym'), 'accesskey' => 'd', 'data-message' => __('Are you sure you want to delete this synonym?')));
				$div->appendChild($button);
			}
						
			$this->Form->appendChild($div);
		}
		
	}