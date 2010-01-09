<?php $theme->display('header');?>

<?php
	$all_categories = array();
	$all_categories = Vocabulary::get( 'categories' )->get_tree();
// 	Utils::debug( $all_categories );
?>
	<div class="container">

<?php	echo $form; ?>

	</div>

	<div class="container">
<?php
	if ( count( $all_categories) > 0 ) {
		$right = array();
		foreach ( $all_categories as $category ) {
			while ( count($right) > 0 && $right[count($right) - 1] < $category->mptt_right ) {
				array_pop($right);
			}
			$pad = count($right)*5;
			$rest = 100 - $pad;
			echo "<div><span class='pct{$pad}'>&nbsp;</span><span class='pct{$rest} last'>{$category->term}</span></div>";
			$right[] = $category->mptt_right;
		}
	}
	else {
		_e( "<h2>No categories have been created yet</h2>" );
	}

?></div>

<?php $theme->display('footer'); ?>
