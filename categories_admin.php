<?php $theme->display('header');?>

<?php
	$all_categories = array();
	$all_categories = Vocabulary::get( 'categories' )->get_tree();
// 	Utils::debug( $all_categories );
?>
	<div class="container">

<?php	echo $form; ?>

	</div>

	<div class="container plugins activeplugins">
<?php
	if ( count( $all_categories) > 0 ) {
		$right = array();
		foreach ( $all_categories as $category ) {
			while ( count($right) > 0 && $right[count($right) - 1] < $category->mptt_right ) {
				array_pop($right);
			}
			$pad = count($right)*5 + 5;
			$rest = 95 - $pad;
			$titlelink = '<a href="' . URL::get( 'admin', 'page=posts' ) . "?search=category:{$category->term}\" title=\"" . 
					_t( "Manage content categorized '{$category->term_display}'" ) . "\">{$category->term_display}</a>";
			$dropbutton = '<ul class="dropbutton"><li><a href="" title="' . _t( "Rename or move '{$category->term_display}'" ) . '">' . 
					_t( "Edit" ) . '</a></li><li><a href="" title="' . _t( "Delete '{$category->term_display}'" ) . '">' . _t( "Delete" ) . '</a></li></ul>';
			echo "\n<div class='item plugin clear'><div class='head'><span class='pct{$pad}'>&nbsp;</span>\n<span class='pct{$rest} last'>$titlelink $dropbutton</span>\n</div></div>";
			$right[] = $category->mptt_right;
		}
	}
	else {
		_e( "<h2>No categories have been created yet</h2>" );
	}

?></div>

<?php $theme->display('footer'); ?>
