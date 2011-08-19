<?php
	
	require_once(EXTENSIONS . '/search_index/lib/class.search_index_administrationpage.php');
	
	class contentExtensionSearch_IndexStopwords extends SearchIndex_AdministrationPage {
		
		public function __actionIndex() {
			$stopwords = explode(',', $_POST['stopwords']);
			SearchIndex::saveStopWords($stopwords);
			$this->pageAlert(
				__(
					'Stop words updated at %1$s.',
					array(
						DateTimeObj::getTimeAgo(__SYM_TIME_FORMAT__)
					)
				),
				Alert::SUCCESS
			);
		}
		
		public function view() {
			parent::view();
			$this->setTitle(__('Symphony') . ' &ndash; ' . __('Search Index') . ' &ndash; ' . __('Stop Words'));
		}
		
		public function __viewIndex() {
			
			$this->setPageType('form');
			
			$this->appendSubheading(
				__('Search Index') . ' &rsaquo; ' . __('Stop Words') . 
				Widget::Anchor(__('Create New'), Administration::instance()->getCurrentPageURL().'new/', __('Create New'), 'create button')->generate()
			);
		
			$fieldset = new XMLElement('fieldset');
			$fieldset->setAttribute('class', 'settings');
			$fieldset->appendChild(new XMLElement('legend', __('Stop Words')));
			$p = new XMLElement('p', __('Stop words are common terms that will be ignored during both indexing and searching. You should supplement this list with common words you find in your <a href="'.$this->uri.'/queries/'.'">query logs</a>.'));
			$p->setAttribute('class', 'help');
			$fieldset->appendChild($p);
						
			$label = Widget::Label('');
			$label->appendChild(Widget::Textarea(
				'stopwords',
				15, 40,
				implode(', ', SearchIndex::getStopWords())
			));
			$fieldset->appendChild($label);
			$fieldset->appendChild(new XMLElement('p', __('Separate each word by a comma; e.g. who, what, when, where.'), array('class'=>'help')));
			
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