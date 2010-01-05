<?php $theme->display('header');?>
 
<div class="container">
<?php 
	$all_categories = array();
	$all_categories = Vocabulary::get( 'categories' )->get_tree( 'term_display' );
	Utils::debug( $all_categories ); ?>
</div>
 
<?php $theme->display('footer'); ?>
