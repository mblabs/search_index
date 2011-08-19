<?php
	
	require_once(EXTENSIONS . '/search_index/lib/class.search_index_administrationpage.php');
	
	class contentExtensionSearch_IndexSearchSuggestions extends SearchIndex_AdministrationPage {
		
		public function __actionIndex() {
			$suggestions = explode(',', $_POST['suggestions']);
			SearchIndex::saveQuerySuggestions($suggestions);
			$this->pageAlert(
				__(
					'Search suggestions updated at %s.',
					array(
						DateTimeObj::getTimeAgo(__SYM_TIME_FORMAT__)
					)
				),
				Alert::SUCCESS
			);
		}
		
		public function view() {
			parent::view();
			$this->setTitle(__('Symphony') . ' &ndash; ' . __('Search Index') . ' &ndash; ' . __('Search Suggestions'));
		}
		
		public function __viewIndex() {
			
			$this->setPageType('form');
			
			$this->appendSubheading(__('Search Index') . ' &rsaquo; ' . __('Search Suggestions'));
		
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings');
			$fieldset->appendChild(new XMLElement('legend', __('Search Suggestions')));
			$p = new XMLElement('p', __('These phrases are used to populate the auto-complete search box. They appear higher in the suggestion list than normal suggested words. You should add to this list with common phrases you find in your <a href="'.$this->uri.'/queries/'.'">query logs</a>.'));
			$p->setAttribute('class', 'help');
			$fieldset->appendChild($p);
						
			$label = Widget::Label('');
			$label->appendChild(Widget::Textarea(
				'suggestions',
				15, 40,
				implode(', ', SearchIndex::getQuerySuggestions())
			));
			$fieldset->appendChild($label);
			$fieldset->appendChild(new XMLElement('p', __('Separate multiple phrases with commas; e.g. Romeo and Juliet, Hamlet, The Tempest'), array('class'=>'help')));
			
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
						
			$this->Form->appendChild($div);
			
		}
			
	}