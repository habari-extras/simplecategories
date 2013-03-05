<?php

class Category extends Term {
	private $_slug = null; // for caching slug to hopefully save work
	public function __get($name) {
		switch ($name) {
			case 'slug':
				if ($this->_slug !== null)
					return $this->_slug;
				else {
					$this->_slug = $this->term;
					$parent = $this->parent();
					do {
						if ($parent instanceof Term) {
							$this->_slug = $parent->term . '/' . $this->_slug;
							$parent = $parent->parent();
						}
					} while ($parent instanceof Term);
					return $this->_slug;
				}
				break;
			default:
				return parent::__get($name);
		}
	}
	
	public function get_url($trail = false) {
		return Site::get_url('habari', true) . SimpleCategories::URL_BASE . '/' . $this->slug . ($trail ? '/' : '');
	}
	
	public function out_url($trail = false) {
		echo $this->get_url($trail);
	}
}

?>