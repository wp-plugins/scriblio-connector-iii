<?php

/*
Plugin Name: Scriblio III Connector
Plugin URI: http://about.scriblio.net/
Description: Imports catalog content directly from a III web OPAC, no MaRC export/import needed.
Version: 2.7 b03
Author: Casey Bisson
Author URI: http://maisonbisson.com/blog/
*/
/* Copyright 2006 - 2009 Casey Bisson & Plymouth State University

	This program is free software; you can redistribute it and/or modify 
	it under the terms of the GNU General Public License as published by 
	the Free Software Foundation; either version 2 of the License, or 
	(at your option) any later version. 

	This program is distributed in the hope that it will be useful, 
	but WITHOUT ANY WARRANTY; without even the implied warranty of 
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the 
	GNU General Public License for more details. 

	You should have received a copy of the GNU General Public License 
	along with this program; if not, write to the Free Software 
	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA 02110-1301 USA 
*/ 

/*
	Includes contributions by K.T. Lam (lblkt@ust.hk), Head of Library Systems, The Hong Kong University of Science and Technology Library
	Purpose: to enhance Scriblio's CJK support and to make it works with HKUST's INNOPPAC.
	Date: 13 November 2007; 22 November 2007; 17 December 2007; 29 December 2007; 14 January 2008; 13 May 2008;
*/

// The importer 
class ScribIII_import { 
	var $importer_code = 'scribimporter_iii'; 
	var $importer_name = 'Scriblio III Catalog Importer'; 
	var $importer_desc = 'Imports catalog content directly from a III web OPAC, no MaRC export/import needed. <a href="http://about.scriblio.net/wiki/">Documentation here</a>.'; 
	 
	// Function that will handle the wizard-like behaviour 
	function dispatch() { 
		if (empty ($_GET['step'])) 
			$step = 0; 
		else 
			$step = (int) $_GET['step']; 

		// load the header
		$this->header();

		switch ($step) { 
			case 0 :
				$this->greet();
				break;
			case 1 : 
				$this->iii_settings(); 
				break;
			case 2 : 
				$this->iii_settings_delete(); 
				break;
			case 3 : 
				$this->iii_get_range(); 
				break;
			case 4:
				$this->iii_get_records(); 
				break; 
			case 5:
				$this->ktnxbye(); 
				break; 
		} 

		// load the footer
		$this->footer();
	} 

	function header() {
		echo '<div class="wrap">';
		screen_icon();
		echo '<h2>'.__('Scriblio III Importer').'</h2>';
	}

	function footer() {
		echo '</div>';
	}

	function ktnxbye() {
		?>
		<div class="narrow">
		<p><?php _e('All done') ?></p>
		</div>
		<?php
	}

	function greet() {
		// save submitted settings if they exist
		if( isset( $_POST['scrib_iii-delete'] ) && isset( $_POST['scrib_iii-sourceprefix'] )){
			$save_prefs = get_option('scriblio-importer-iii') or array();

			unset( $save_prefs[ str_pad( substr( preg_replace( '/[^a-z0-9]/', '', strtolower( $_POST['scrib_iii-sourceprefix'] )), 0, 2), 2, 'a', STR_PAD_LEFT ) ] );

			update_option('scriblio-importer-iii', $save_prefs);
		
		}else if( isset( $_POST['scrib_iii-sourceinnopac'] )){
			$save_prefs = get_option('scriblio-importer-iii') or array();

			$prefs['sourceinnopac'] = ereg_replace( '[^a-z|A-Z|0-9|-|\.]', '', $_POST['scrib_iii-sourceinnopac'] );
			$prefs['sourceprefix'] = str_pad( substr( preg_replace( '/[^a-z0-9]/', '', strtolower( $_POST['scrib_iii-sourceprefix'] )), 0, 2), 2, 'a', STR_PAD_LEFT );
			$prefs['convert_encoding'] = isset( $_POST['scrib_iii-convert_encoding'] );
			$prefs['bibrange_low'] = absint( $_POST['scrib_iii-bibrange_low'] );
			$prefs['bibrange_high'] = absint( $_POST['scrib_iii-bibrange_high'] );
			$prefs['require_import'] = $_POST['scrib_iii-require_import'];
			$prefs['reject_import'] = $_POST['scrib_iii-reject_import'];
			$prefs['capitalize_titles'] = isset( $_POST['scrib_iii-capitalize_titles'] );
			$prefs['require_availability'] = $_POST['scrib_iii-require_availability'];
			$prefs['reject_availability'] = $_POST['scrib_iii-reject_availability'];

			$save_prefs[ $prefs['sourceprefix'] ] = $prefs;

			update_option('scriblio-importer-iii', $save_prefs);

			wp_redirect( 'admin.php?import='. $this->importer_code .'&message=1' );
		}

		echo '<p>'.__('Use this importer to harvest records from an Innovative Interfaces Incorporated ILS. Select a connection below or add a new one.').'</p>';

		$save_prefs = get_option('scriblio-importer-iii');
		if( count( $save_prefs )){
			echo '<table class="widefat">';
			$style = '';
			foreach ($save_prefs as $id => $data) {
				$style = ('class="alternate"' == $style || 'class="alternate active"' == $style) ? '' : 'alternate';
		
				$title = "<a href='admin.php?import=$this->importer_code&amp;step=1&amp;sourceprefix={$data['sourceprefix']}' title='edit connection settings for {$data['sourceinnopac']}'>{$data['sourceprefix']} : {$data['sourceinnopac']}</a>";
		
				$edit = "<a href='admin.php?import=$this->importer_code&amp;step=1&amp;sourceprefix={$data['sourceprefix']}' title='edit connection settings for {$data['sourceinnopac']}'>edit</a>";
		
				$delete = "<a href='admin.php?import=$this->importer_code&amp;step=2&amp;sourceprefix={$data['sourceprefix']}' title='delete the connection to {$data['sourceinnopac']}'>delete</a>";
		
				$import = "<a href='admin.php?import=$this->importer_code&amp;step=3&amp;sourceprefix={$data['sourceprefix']}' title='import records from Innopac {$data['sourceinnopac']}'>import</a>";
		
				if ($style != '')
					$style = 'class="'.$style.'"';
				echo "
					<tr $style>
						<td class='import-system row-title'>$title</td>
						<td class='desc'>$edit | $import | $delete</td>
					</tr>";
			}
			echo '</table>';
		}else{
			echo '<p>'. __( 'No connections configured.' ) . " <a href='admin.php?import=$this->importer_code&amp;step=1' title='add a new connection'>". __( 'Create a new one?' ) .'</a></p>';
		}

		echo "<p><a href='admin.php?import=$this->importer_code&amp;step=1' title='add a new connection'>". __( 'Create a new connection' ) .'</a></p>';
	
	}

	function iii_settings() {
		$prefs = get_option('scriblio-importer-iii');
		if( 
			isset( $_GET['sourceprefix'] ) 
			&& 
			is_array( $prefs[ preg_replace( '/[^a-z0-9]/', '', strtolower( $_GET['sourceprefix'] )) ] )
		)
				$prefs = $prefs[ preg_replace( '/[^a-z0-9]/', '', strtolower( $_GET['sourceprefix'] )) ];
		else
			$prefs = array();

?>
		<form name="myform" id="myform" action="admin.php?import=<?php echo $this->importer_code; ?>" method="post">

		<h3><?php _e('Innopac'); ?></h3>

		<table class="form-table">
		<tr valign="top">
		<th scope="row"><?php _e('The Innopac base hostname', 'scrib') ?></th>
		<td>
		<input name="scrib_iii-sourceinnopac" type="text" id="scrib_iii-sourceinnopac" value="<?php echo attribute_escape( $prefs['sourceinnopac'] ); ?>" /><br />example: lola.plymouth.edu
		</td>
		</tr>
		
		<tr valign="top">
		<th scope="row"><?php _e('The source prefix', 'scrib') ?></th>
		<td>
		<input name="scrib_iii-sourceprefix" type="text" id="scrib_iii-sourceprefix" value="<?php echo attribute_escape( $prefs['sourceprefix'] ); ?>" /><br />
		example: bb (must be two characters, a-z accepted)
		</td>
		</tr>
		
<?php if( function_exists( 'mb_convert_encoding' )){ ?>
		<tr>
		<th scope="row" class="th-full"><label for="scrib_iii-convert_encoding">Convert character encoding</label></th>
		<td><input type="checkbox" name="scrib_iii-convert_encoding" id="scrib_iii-convert_encoding" value="1" <?php if( !empty( $prefs['convert_encoding'] )) echo 'CHECKED'; ?> /><br />
<?php

		_e('Many III web OPACs use encodings other than <a href="http://en.wikipedia.org/wiki/UTF-8">UTF8</a>. This option will attempt to convert the characters to UTF8 so that accented and non-latin characters are properly represented. However, do not use this option if your web OPAC is configured to output UTF8 characters.');

		?>
		</td>
		</tr>
<?php } ?>
		</table>
		

		<h3><?php _e('Importing') ?></h3>

		<table class="form-table">
		<tr valign="top">
		<th scope="row"><?php _e('Bib record number range', 'scrib') ?></th>
		<td>
		<input name="scrib_iii-bibrange_low" type="text" id="scrib_iii-bibrange_low" value="<?php echo attribute_escape( $prefs['bibrange_low'] ); ?>" /> to <input name="scrib_iii-bibrange_high" type="text" id="scrib_iii-bibrange_high" value="<?php echo attribute_escape( $prefs['bibrange_high'] ); ?>" /><br />
		example: 1000000 to 1400000 (the Innopac's lowest bibnumber to the highest number you're likely to see in a few years)
		</td>
		</tr>

		<tr valign="top">
		<th scope="row"><?php _e('Harvest records with', 'scrib') ?></th>
		<td>
		<input name="scrib_iii-require_import" type="text" id="scrib_iii-require_import" value="<?php echo format_to_edit( $prefs['require_import'] ); ?>" /><br />
		example: My Library Location Name (optional; leave blank to harvest any record)<br />
		uses <a href="http://php.net/strpos">strpos</a> matching rules
		</td>
		</tr>
		
		<tr valign="top">
		<th scope="row"><?php _e('Ignore records with', 'scrib') ?></th>
		<td>
		<input name="scrib_iii-reject_import" type="text" id="scrib_iii-reject_import" value="<?php echo format_to_edit( $prefs['reject_import'] ); ?>" /><br />
		example: No Such Record<br />
		uses <a href="http://php.net/strpos">strpos</a> matching rules 
		</td>
		</tr>

		<tr>
		<th scope="row" class="th-full"><label for="scrib_iii-capitalize_titles">Capitalize titles</label></th>
		<td><input type="checkbox" name="scrib_iii-capitalize_titles" id="scrib_iii-capitalize_titles" value="1" <?php if( !empty( $prefs['capitalize_titles'] )) echo 'CHECKED'; ?> /></td>
		</tr>
		</table>


		<h3><?php _e('Availability') ?></h3>

		<table class="form-table">
		<tr valign="top">
		<th scope="row"><?php _e('Show items with', 'scrib') ?></th>
		<td>
		<input name="scrib_iii-require_availability" type="text" id="scrib_iii-require_availability" value="<?php echo format_to_edit( $prefs['require_availability'] ); ?>" /><br />
		example: My Library Location Name (optional; leave blank to harvest any record)<br />
		uses <a href="http://php.net/strpos">strpos</a> matching rules
		</td>
		</tr>
		
		<tr valign="top">
		<th scope="row"><?php _e('Ignore items with', 'scrib') ?></th>
		<td>
		<input name="scrib_iii-reject_availability" type="text" id="scrib_iii-reject_availability" value="<?php echo format_to_edit( $prefs['reject_availability'] ); ?>" /><br />
		example: No Such Record<br />
		uses <a href="http://php.net/strpos">strpos</a> matching rules 
		</td>
		</tr>
		</table>
		<?php

		echo '<p class="submit"><a href="admin.php?import='. $this->importer_code .'">Cancel</a> &nbsp; <input type="submit" name="next" value="'.__('Save').'" /></p>';
		echo '</form>';
	}

	function iii_settings_delete() {
		$prefs = get_option('scriblio-importer-iii');
		if( 
			isset( $_GET['sourceprefix'] ) 
			&& 
			is_array( $prefs[ preg_replace( '/[^a-z0-9]/', '', strtolower( $_GET['sourceprefix'] )) ] )
		){
			$prefs = $prefs[ preg_replace( '/[^a-z0-9]/', '', strtolower( $_GET['sourceprefix'] )) ];
?>
			<h3><?php _e('Really delete this connection?'); ?></h3>
			<p><?php echo $prefs['sourceinnopac']; ?></p>

			<form name="myform" id="myform" action="admin.php?import=<?php echo $this->importer_code; ?>" method="post">
	
			<input name="scrib_iii-sourceprefix" type="hidden" id="scrib_iii-sourceprefix" value="<?php echo attribute_escape( $prefs['sourceprefix'] ); ?>" />

			<input name="scrib_iii-delete" type="hidden" id="scrib_iii-delete" value="1" />
	
			<p class="submit"><a href="admin.php?import=<?php echo $this->importer_code; ?>">Cancel</a> &nbsp; <input type="submit" name="next" value="<?php _e('Delete'); ?>"/></p>
			</form>
<?php
		}else{
?>	
			<p class="submit"><?php _e('Um, what?'); ?> <a href="admin.php?import=<?php echo $this->importer_code; ?>"><?php _e('Try again'); ?></a></p>

<?php
		}
	}


	function iii_get_range( $record_start = FALSE, $record_end = FALSE ){
		global $wpdb, $scrib;

		$prefs = get_option('scriblio-importer-iii');
		if( 
			isset( $_GET['sourceprefix'] ) 
			&& 
			is_array( $prefs[ preg_replace( '/[^a-z0-9]/', '', strtolower( $_GET['sourceprefix'] )) ] )
		){
			$prefs = $prefs[ preg_replace( '/[^a-z0-9]/', '', strtolower( $_GET['sourceprefix'] )) ];

			if( !$record_start ){
				$harvested_max = $wpdb->get_var( 'SELECT SUBSTRING( source_id, 3 ) FROM '. $scrib->harvest_table .' WHERE source_id LIKE "'. $wpdb->escape( $prefs['sourceprefix'] ) .'%" ORDER BY source_id DESC LIMIT 1' );
	
				if( $harvested_max ){
					$record_start = ( 100 * absint( $harvested_max / 100 ));
	
					if( !$record_end )
						$record_end = $record_start + 1000;
				}else{
					$record_start = $prefs['bibrange_low'];
					$record_end = $prefs['bibrange_high'];
				}
			}

			echo '<form name="myform" id="myform" action="admin.php?import='. $this->importer_code .'&amp;step=4&amp;sourceprefix='. $_GET['sourceprefix'] .'" method="post">';
			?>
		
			<h3><?php _e('Importing records from:'); ?> <?php echo $prefs['sourceprefix']; ?> : <?php echo $prefs['sourceinnopac']; ?></h3>

			<table class="form-table">
			
			<tr valign="top">
			<th scope="row"><?php _e('Start with bib number', 'scrib') ?></th>
			<td>
			<input type="text" name="scrib_iii-record_start" id="scrib_iii-record_start" value="<?php echo attribute_escape( $record_start ); ?>" /><br />
			</td>
			</tr>
			
			<tr valign="top">
			<th scope="row"><?php _e('End', 'scrib') ?></th>
			<td>
			<input type="text" name="scrib_iii-record_end" id="scrib_iii-record_end" value="<?php echo attribute_escape( $record_end ); ?>" />
			</td>
			</tr>
			
			</table>
			<table class="form-table">
			
			<tr>
			<th scope="row" class="th-full">
			<label for="scrib_iii-debug"><input type="checkbox" name="scrib_iii-debug" id="scrib_iii-debug" value="1" = /> Debug mode</label>
			</th>
			</tr>
			<tr>
			</table>

			<p class="submit"><a href="admin.php?import=<?php echo $this->importer_code; ?>">Cancel</a> &nbsp; <input type="submit" name="next" value="<?php _e('Next &raquo;'); ?>"/></p>

			</form>
			<?php
		}else{
?>	
			<p class="submit"><?php _e('Um, what?'); ?> <a href="admin.php?import=<?php echo $this->importer_code; ?>"><?php _e('Try again'); ?></a></p>

<?php
		}
	}

	function iii_get_records(){
		global $wpdb, $scrib;

		$prefs = get_option('scriblio-importer-iii');
		if( 
			isset( $_GET['sourceprefix'] ) 
			&& 
			is_array( $prefs[ preg_replace( '/[^a-z0-9]/', '', strtolower( $_GET['sourceprefix'] )) ] )
		){
			$prefs = $prefs[ preg_replace( '/[^a-z0-9]/', '', strtolower( $_GET['sourceprefix'] )) ];

			$interval = 25;
			if( !$_REQUEST['scrib_iii-record_end'] || ( $_REQUEST['scrib_iii-record_end'] == $prefs['scrib_iii-record_start'] ))
				$_REQUEST['scrib_iii-debug'] = TRUE;
			if( !$_REQUEST['scrib_iii-record_end'] || ( $_REQUEST['scrib_iii-record_end'] - $_REQUEST['scrib_iii-record_start'] < $interval ))
				$interval = $_REQUEST['scrib_iii-record_end'] - $_REQUEST['scrib_iii-record_start'];
			if( $_REQUEST['scrib_iii-record_end'] - $_REQUEST['scrib_iii-record_start'] < 1 )
				$interval = 0;
	
			ini_set('memory_limit', '1024M');
			set_time_limit(0);
			ignore_user_abort(TRUE);
			error_reporting(E_ERROR);
	
			if( !empty( $_REQUEST['scrib_iii-debug'] )){
				$bibn = (int) $_REQUEST['scrib_iii-record_start'];
	
				echo '<h3>The III Record:</h3><pre>';			
				echo $this->iii_get_record( $prefs['sourceprefix'], $bibn );
				echo '</pre><h3>The Tags and Display Record:</h3><pre>';
	
				$test_pancake = $this->iii_parse_record( $prefs['sourceprefix'], $this->iii_get_record( $prefs['sourceprefix'], $bibn ), $bibn );
				print_r( $test_pancake );
				echo '</pre>';

				echo '<h3>The SourceID: '. $test_pancake['_sourceid'] .'</h3>';
				
				// bring back that form
				echo '<h2>'.__('III Options').'</h2>';
				$this->iii_get_range();
			
			}else{
				// import with status
	
				$count = 0;
				echo "<p>Reading a batch of $interval records from {$prefs['sourceinnopac']}. Please be patient.<br /><br /></p>";
				echo '<ol>';
				for( $bibn = (int) $_REQUEST['scrib_iii-record_start'] ; $bibn < ( $_REQUEST['scrib_iii-record_start'] + $interval ) ; $bibn++ ){
					if($record = $this->iii_get_record( $prefs['sourceprefix'] , $bibn )){
						$bibr = $this->iii_parse_record( $prefs['sourceprefix'], $record , $bibn );
						echo "<li>{$bibr['the_title']} {$bibr['_sourceid']}</li>";
						$count++;
					}
				}
				echo '</ol>';
				
//				$prefs['scrib_iii-warnings'] = array_merge($prefs['scrib_iii-warnings'], $this->warn);
//				$prefs['scrib_iii-errors'] = array_merge($prefs['scrib_iii-errors'], $this->error);
//				$prefs['scrib_iii-records_harvested'] = $prefs['scrib_iii-records_harvested'] + $count;
//				update_option('scrib_iiiimporter', $prefs);
	
				if( $bibn < $_REQUEST['scrib_iii-record_end'] ){
					$_REQUEST['scrib_iii-record_start'] = $_REQUEST['scrib_iii-record_start'] + $interval;
	
					$this->iii_get_range( $_REQUEST['scrib_iii-record_start'], $_REQUEST['scrib_iii-record_end'] );
					?>
					<div class="narrow"><p><?php _e("If your browser doesn't start loading the next page automatically click this link:"); ?> <a href="javascript:nextpage()"><?php _e("Next Records"); ?></a> </p>
					<script language='javascript'>
					<!--
		
					function nextpage() {
						document.getElementById('myform').submit();
					}
					setTimeout( "nextpage()", 1250 );
		
					//-->
					</script>
					</div>
	<?php
					echo '<pre>';
					print_r( $wpdb->queries );
					echo '<br /><br />';
					print_r( $scrib_import->queries );
					echo '</pre>';
					?><?php echo get_num_queries(); ?> queries. <?php timer_stop(1); ?> seconds. <?php
				}else{
					$this->iii_done();
					?>
					<script language='javascript'>
					<!--
						window.location='#complete';
					//-->
					</script>
					</div>
					<?php
				}
			}
		}
	}

	function iii_get_record( $prefix, $bibn ){
		$prefs = get_option('scriblio-importer-iii');
		$prefs = $prefs[ $prefix ];
		if( !is_array( $prefs ))
			return( FALSE );

		// get the regular web-view of the record and 
		// see if it matches the require/reject preferences
		$test_record = wp_remote_get('http://'. $prefs['sourceinnopac'] .'/record=b'. $bibn);
		if( is_wp_error( $test_record ))
			$test_record = array( 'body' => '');

		if( $prefs['require_import'] && !strpos( $test_record['body'], $prefs['require_import'] ))
			return(FALSE);

		if( $prefs['reject_import'] && strpos( $test_record['body'], $prefs['reject_import'] ))
			return(FALSE);

		unset( $test_record );

		// now get the MARC view of the record
		$recordurl = 'http://'. $prefs['sourceinnopac'] .'/search/.b'. $bibn .'/.b'. $bibn .'/1%2C1%2C1%2CB/marc~b'. $bibn;
		$record = wp_remote_get( $recordurl );
		if( is_wp_error( $record ))
			$record = array( 'body' => '');

		if( $prefs['convert_encoding'] && function_exists( 'mb_convert_encoding' ))
			$record = mb_convert_encoding( $record['body'], 'UTF-8', 'LATIN1, ASCII, ISO-8859-1, UTF-8');
		else
			$record = $record['body'];

		if( !empty( $record['body'] )){

			preg_match('/<pre>([^<]*)/', $record, $stuff);

			//Create Tag 999
			$strline = '';

			//Check exists of ERM resources
			$matchcount=preg_match('/<!-- BEGIN ERM RESOURCE TABLE -->/', $record, $stuffdummy1);
			if ($matchcount>0) {
				$strline .= "|fE-Resource|lONLINE RESOURCE";
			}

			//Capture Item Locations
			//e.g. "<!-- field 1 -->&nbsp; <a href="http://library.ust.hk/info/maps/blink/1f-archive.html">UNIVERSITY ARCHIVES</a>"
			$matchcount = preg_match_all( '/<!-- field 1 -->.*>(.+)</', $record, $matches, PREG_SET_ORDER );
			if ( 0 < $matchcount ) {
				foreach( $matches as $match ){
					$strline .= '|l'.strtoupper( $match[1] );
				}
			}

			if ( strlen( $strline ))
				return( $stuff[1].'999    '. $strline ."\n");
			else
				return( $stuff[1] );
//End HKUST Customization
		}
		$this->error = 'Host unreachable or no parsable data found for record number '. $bibn .'.';
		return( FALSE );
	}

	function iii_done(){
		$prefs = get_option('scrib_iiiimporter');

		// click next
		echo '<div class="narrow">';

		if(count($prefs['warnings'])){
			echo '<h3 id="warnings">Warnings</h3>';
			echo '<a href="#complete">bottom</a> &middot; <a href="#errors">errors</a>';
			echo '<ol><li>';
			echo implode($prefs['warnings'], '</li><li>');
			echo '</li></ol>';
		}

		if(count($prefs['errors'])){
			echo '<h3 id="errors">Errors</h3>';
			echo '<a href="#complete">bottom</a> &middot; <a href="#warnings">warnings</a>';
			echo '<ol><li>';
			echo implode($prefs['errors'], '</li><li>');
			echo '</li></ol>';
		}		

		echo '<h3 id="complete">'.__('Processing complete.').'</h3>';
		echo '<p>'. $prefs['records_harvested'] .' '.__('records harvested.').' with '. count($prefs['warnings']) .' <a href="#warnings">warnings</a> and '. count($prefs['errors']) .' <a href="#errors">errors</a>.</p>';
/*
		echo '<p>'.__('Continue to the next step to publish those harvested catalog entries.').'</p>';

		echo '<form action="admin.php?import=scribimporter&amp;step=3" method="post">';
		echo '<p class="submit"><input type="submit" name="next" value="'.__('Publish Harvested Records &raquo;').'" /> <br />'. __('(goes to default Scriblio importer)').'</p>';
		echo '</form>';
*/
		echo '</div>';
	}

	function iii_parse_row($lineray){
		$marcrow = array();
		unset($lineray[0]);
		foreach($lineray as $element){
			$count[$element{0}]++;
			$elementname = $element{0}.$count[$element{0}];
			$marcrow[$elementname] = trim( substr( $element, 1 ));
		}
		return($marcrow);
	}

	function iii_parse_record( $sourceprefix, &$marcrecord, &$bibn ){
		global $scrib;

		$prefs = get_option('scriblio-importer-iii');
		$prefs = $prefs[ $sourceprefix ];
		if( !is_array( $prefs ))
			return( FALSE );

		$spare_keys = array( 'a', 'b', 'c', 'd', 'e', 'f', 'g' );
		$atomic = $subjtemp = array();
		
		$marcrecord = str_replace("\n       ", ' ', $marcrecord);
		
		$details = explode( "\n", $marcrecord );
		array_pop($details);
		array_shift($details);

		$details[0] = str_replace('LEADER ', '000    ', $details[0]);
		foreach($details as $line){		
			unset($lineray);
			unset($marc);

			$line = trim($line);

			//handle romanized tags with subfield 6 - to avoid using it as the main entry, so that 880 data is used instead
			$line = preg_replace('/^245(.*?\|6880-)/', '246\\1', $line);
			$line = preg_replace('/^1(\d\d.*?\|6880-)/', '7\\1', $line);
			$line = preg_replace('/^250(.*?\|6880-)/', '950\\1', $line);
			$line = preg_replace('/^260(.*?\|6880-)/', '960\\1', $line);

			//handle 880 tags with subfield 6
			$line = preg_replace('/^880(.*?)\|6(\d\d\d)-/', '\\2\\1|6880-', $line);

			//Remove subfield 6 containing "880-.."
			$line = preg_replace('/\|6880-.*?\|/', '|', $line);

			//Remove the extra space in $line in front of the first subfield delimiter
			$line = preg_replace('/^.{7} /', '\\1', $line);

			//Insert subfield delimiter and subfield code "a" if it is not present - for non-00X tags
			$line = preg_replace('/^([0][1-9]\d.{4})([^\|])/', '\\1|a\\2', $line);
			$line = preg_replace('/^([1-9]\d{2}.{4})([^\|])/', '\\1|a\\2', $line);

			//Construct $lineray
			if (substr($line,7,1)=="|") {
				$lineray = substr($line, 0, 3) . '|' . substr($line, 4, 2) . substr($line, 7);
			}else{
				$lineray = substr($line, 0, 3) . '|' . substr($line, 4, 2) . '|a' . substr($line, 7);
			}

			$lineray = explode('|', ereg_replace('\.$', '', $lineray));
			unset($lineray[1]);

			if($lineray[0] > 99)
				$line = trim( $line );

			// languages
			if( $lineray[0] == '008' ){
				$atomic['published'][0]['lang'][] = $scrib->meditor_sanitize_punctuation( substr( $lineray[2], 36,3 ));

			}else if( $lineray[0] == '041' ){
				$marc = $this->iii_parse_row( $lineray );
				foreach( $marc as $key => $val ){
					switch( $key[0] ){
						case 'a':
						case 'b':
						case 'c':
						case 'd':
						case 'e':
						case 'f':
						case 'g':
						case 'h':
							$atomic['published'][0]['lang'][] = $scrib->meditor_sanitize_punctuation( $val );
					}
				}



			// authors
			}else if(($lineray[0] == 100) || ($lineray[0] == 700) || ($lineray[0] == 110) || ($lineray[0] == 710) || ($lineray[0] == 111) || ($lineray[0] == 711)){
				$marc = $this->iii_parse_row($lineray);
				$temp = $marc['a1'];
				unset( $temp_role );
				if(($lineray[0] == 100) || ($lineray[0] == 700)){
					if($marc['d1'])
						$temp .= ' ' . $marc['d1'];
					if($marc['e1'])
						$temp_role = ' ' . $marc['e1'];
				}else if(($lineray[0] == 110) || ($lineray[0] == 710)){
					if ($marc['b1']) {
						$temp .= ' ' . $marc['b1'];
					}
				}else if(($lineray[0] == 111) || ($lineray[0] == 711)){
					if ($marc['n1']) {
						$temp .= ' ' . $marc['n1'];
					}
					if ($marc['d1']) {
						$temp .= ' ' . $marc['d1'];
					}
					if ($marc['c1']) {
						$temp .= ' ' . $marc['c1'];
					}
				}
				$temp = ereg_replace('[,|\.]$', '', $temp);
				$atomic['creator'][] = array( 'name' => $scrib->meditor_sanitize_punctuation( $temp ), 'role' => $temp_role ? $temp_role : 'Author' );

				//handle title in name
				$temp = '';
				if ($marc['t1']) {
					$temp .= ' ' . $marc['t1'];
				}
				if ($marc['n1']) {
					$temp .= ' ' . $marc['n1'];
				}
				if ($marc['p1']) {
					$temp .= ' ' . $marc['p1'];
				}
				if ($marc['l1']) {
					$temp .= ' ' . $marc['l1'];
				}
				if ($marc['k1']) {
					$temp .= ' ' . $marc['k1'];
				}
				if ($marc['f1']) {
					$temp .= ' ' . $marc['f1'];
				}
				$temp = ereg_replace('[,|\.]$', '', $temp);
				if (strlen($temp) >0) {
					$atomic['alttitle'][] = array( 'a' => $scrib->meditor_sanitize_punctuation( $temp ));
				}

			//Standard Numbers
			}else if($lineray[0] == 10){
				$marc = $this->iii_parse_row($lineray);
				$atomic['idnumbers'][] = array( 'type' => 'lccn', 'id' => $marc['a1'] );

			}else if($lineray[0] == 20){
				$marc = $this->iii_parse_row($lineray);
				$temp = trim($marc['a1']) . ' ';
				$temp = ereg_replace('[^0-9|x|X]', '', strtolower(substr($temp, 0, strpos($temp, ' '))));
				if( strlen( $temp ))
					$atomic['idnumbers'][] = array( 'type' => 'isbn', 'id' => $temp );

			}else if($lineray[0] == 22){
				$marc = $this->iii_parse_row($lineray);
				$temp = trim($marc['a1']) . ' ';
				$temp = ereg_replace('[^0-9|x|X|\-]', '', strtolower(substr($temp, 0, strpos($temp, ' '))));
				if( strlen( $temp ))
					$atomic['idnumbers'][] = array( 'type' => 'issn', 'id' => $temp );

				$temp = trim($marc['y1']) . ' ';
				$temp = ereg_replace('[^0-9|x|X|\-]', '', strtolower(substr($temp, 0, strpos($temp, ' '))));
				if( strlen( $temp ))
					$atomic['idnumbers'][] = array( 'type' => 'issn', 'id' => $temp );

				$temp = trim($marc['z1']) . ' ';
				$temp = ereg_replace('[^0-9|x|X|\-]', '', strtolower(substr($temp, 0, strpos($temp, ' '))));
				if( strlen( $temp ))
					$atomic['idnumbers'][] = array( 'type' => 'issn', 'id' => $temp );
			
			//Call Numbers
			}else if($lineray[0] == 50){
				$marc = $this->iii_parse_row($lineray);
				$atomic['callnumbers'][] = array( 'type' => 'lc', 'number' => implode( ' ', $marc ));
			}else if($lineray[0] == 82){
				$marc = $this->iii_parse_row($lineray);
				$atomic['callnumbers'][] = array( 'type' => 'dewey', 'number' => str_replace( '/', '', $marc['a1'] ));

			//Titles
			}else if($lineray[0] == 130){
				$marc = $this->iii_parse_row($lineray);
				$atomic['alttitle'][] = array( 'a' => $scrib->meditor_sanitize_punctuation( $marc['a1'] ));
			}else if($lineray[0] == 245){
				$marc = $this->iii_parse_row($lineray);
				$temp = trim(ereg_replace('/$', '', $marc['a1']) .' '. trim(ereg_replace('/$', '', $marc['b1']) .' '. trim(ereg_replace('/$', '', $marc['n1']) .' '. trim(ereg_replace('/$', '', $marc['p1'])))));
				$atomic['title'][] = array( 'a' => $scrib->meditor_sanitize_punctuation( $temp ));
				$atomic['attribution'][] = array( 'a' => $scrib->meditor_sanitize_punctuation( $marc['c1'] ));
			}else if($lineray[0] == 240){
				$marc = $this->iii_parse_row($lineray);
				$atomic['alttitle'][] = array( 'a' => $scrib->meditor_sanitize_punctuation( implode(' ', array_values( $marc ))));
			}else if($lineray[0] == 246){
				$marc = $this->iii_parse_row( $lineray );
				$temp = trim(ereg_replace('/$', '', $marc['a1']) .' '. trim(ereg_replace('/$', '', $marc['b1']) .' '. trim(ereg_replace('/$', '', $marc['n1']) .' '. trim(ereg_replace('/$', '', $marc['p1'])))));
				$atomic['alttitle'][] = array( 'a' => $scrib->meditor_sanitize_punctuation( $temp ));
			}else if(($lineray[0] > 719) && ($lineray[0] < 741)){
				$marc = $this->iii_parse_row($lineray);
				$temp = $marc['a1'];
				if ($marc['n1']) {
					$temp .= ' ' .$marc['n1'];
				}
				if ($marc['p1']) {
					$temp .= ' ' . $marc['p1'];
				}
				$temp = ereg_replace('[,|\.|;]$', '', $temp);
				if (strlen($temp) >0) {
					$atomic['alttitle'][] = array( 'a' => $scrib->meditor_sanitize_punctuation( $temp ));
				}

			//Edition
			}else if($lineray[0] == 250){
				$marc = $this->iii_parse_row($lineray);
				$atomic['published'][0]['edition'] = $scrib->meditor_sanitize_punctuation( implode(' ', $marc));

			//Dates and Publisher
			}else if($lineray[0] == 260){
				$marc = $this->iii_parse_row($lineray);
				if($marc['b1']){
					$atomic['published'][0]['publisher'][] = $scrib->meditor_sanitize_punctuation($marc['b1']);
				}

				if($marc['c1']){
					$temp ="";
					//match for year pattern, such as "1997"
					$matchcount=preg_match('/(\d\d\d\d)/',$marc['c1'], $matches);
					if ($matchcount>0) {
						$temp = $matches[1];
					}else {
						//match for mingguo year pattern  (in traditional chinese character)
						$matchcount=preg_match('/\xE6\xB0\x91\xE5\x9C\x8B(\d{2})/',$marc['c1'], $matches);
						if ($matchcount>0) {
							$temp = strval(intval($matches[1])+1911);
						} else {
							//match for mingguo year pattern (in simplified chinese character)
							$matchcount=preg_match('/\xE6\xB0\x91\xE5\x9B\xBD(\d{2})/',$marc['c1'], $matches);
							if ($matchcount>0) {
								$temp = strval(intval($matches[1])+1911);
							}
						}
					}
					if ($temp) {
						$atomic['published'][0]['cy'][] = $temp;
					}
				}
			}else if($lineray[0] == 5){
				$_acqdate[] = $line{7}.$line{8}.$line{9}.$line{10} .'-'. $line{11}.$line{12} .'-'. $line{13}.$line{14};
			}else if($lineray[0] == 8){
				$temp = intval(substr($line, 14, 4));
				if($temp)
					$atomic['published'][0]['cy'][] = preg_replace('/[^\d]/', '0' ,substr($line, 14, 4));
			
			//Subjects
			// tag 600 - Person
			}else if($lineray[0] == '600'){
				$marc = array_map( array( 'scrib', 'meditor_sanitize_punctuation' ), $this->iii_parse_row( $lineray ));

				$subjtemp = array();
				foreach( $marc as $key => $val ){
					switch( $key[0] ){
						case 'a':
						case 'q':
							$subjtemp[] = array( 'type' => 'person', 'val' => $val );
							break;

						case 'v':
							$subjtemp[] = array( 'type' => 'genre', 'val' => $val );
							break;

						case 'x':
							$subjtemp[] = array( 'type' => 'subject', 'val' => $val );
							break;

						case 'd':
						case 'y':
							$subjtemp[] = array( 'type' => 'time', 'val' => $val );
							break;

						case 'z':
							$subjtemp[] = array( 'type' => 'place', 'val' => $val );
							break;
					}
				}

			// tag 648 - Time
			}else if($lineray[0] == '648'){
				$marc = array_map( array( 'scrib', 'meditor_sanitize_punctuation' ), $this->iii_parse_row( $lineray ));

				$subjtemp = array();
				foreach( $marc as $key => $val ){
					switch( $key[0] ){
						case 'a':
						case 'y':
							$subjtemp[] = array( 'type' => 'time', 'val' => $val );
							break;

						case 'v':
							$subjtemp[] = array( 'type' => 'genre', 'val' => $val );
							break;

						case 'x':
							$subjtemp[] = array( 'type' => 'subject', 'val' => $val );
							break;

						case 'z':
							$subjtemp[] = array( 'type' => 'place', 'val' => $val );
							break;
					}
				}

			// tag 650 - Topical Terms
			}else if( $lineray[0] == '650' ){
				if( 6 == $line[5] )
					continue;

				$marc = array_map( array( 'scrib', 'meditor_sanitize_punctuation' ), $this->iii_parse_row( $lineray ));

				$subjtemp = array();
				foreach( $marc as $key => $val ){
					switch( $key[0] ){
						case 'a':
						case 'b':
						case 'x':
							$subjtemp[] = array( 'type' => 'subject', 'val' => $val );
							break;

						case 'c':
						case 'z':
							$subjtemp[] = array( 'type' => 'place', 'val' => $val );
							break;

						case 'd':
						case 'y':
							$subjtemp[] = array( 'type' => 'time', 'val' => $val );
							break;

						case 'v':
							$subjtemp[] = array( 'type' => 'genre', 'val' => $val );
							break;
					}
				}

			// tag 651 - Geography
			}else if($lineray[0] == '651'){
				$marc = array_map( array( 'scrib', 'meditor_sanitize_punctuation' ), $this->iii_parse_row( $lineray ));

				$subjtemp = array();
				foreach( $marc as $key => $val ){
					switch( $key[0] ){
						case 'a':
						case 'z':
							$subjtemp[] = array( 'type' => 'place', 'val' => $val );
							break;

						case 'v':
							$subjtemp[] = array( 'type' => 'genre', 'val' => $val );
							break;

						case 'e':
						case 'x':
							$subjtemp[] = array( 'type' => 'subject', 'val' => $val );
							break;

						case 'y':
							$subjtemp[] = array( 'type' => 'time', 'val' => $val );
							break;

						case 'z':
							$subjtemp[] = array( 'type' => 'place', 'val' => $val );
							break;
					}
				}

			// tag 654 - Topical Terms
			}else if($lineray[0] == '654'){
				$marc = array_map( array( 'scrib', 'meditor_sanitize_punctuation' ), $this->iii_parse_row( $lineray ));

				$subjtemp = array();
				foreach( $marc as $key => $val ){
					switch( $key[0] ){
						case 'a':
						case 'b':
						case 'c':
						case 'd':
						case 'f':
						case 'g':
						case 'h':
							$subjtemp[] = array( 'type' => 'subject', 'val' => $val );
							break;

						case 'y':
							$subjtemp[] = array( 'type' => 'time', 'val' => $val );
							break;

						case 'z':
							$subjtemp[] = array( 'type' => 'place', 'val' => $val );
							break;
					}
				}

			// tag 655 - Genre
			}else if($lineray[0] == '655'){
				$marc = array_map( array( 'scrib', 'meditor_sanitize_punctuation' ), $this->iii_parse_row( $lineray ));

				$subjtemp = array();
				foreach( $marc as $key => $val ){
					switch( $key[0] ){
						case 'a':
						case 'b':
						case 'c':
						case 'v':
							$subjtemp[] = array( 'type' => 'subject', 'val' => $val );
							break;

						case 'x':
							$subjtemp[] = array( 'type' => 'subject', 'val' => $val );
							break;

						case 'y':
							$subjtemp[] = array( 'type' => 'time', 'val' => $val );
							break;

						case 'z':
							$subjtemp[] = array( 'type' => 'place', 'val' => $val );
							break;
					}
				}

			// tag 662 - Geography
			}else if($lineray[0] == '662'){
				$marc = array_map( array( 'scrib', 'meditor_sanitize_punctuation' ), $this->iii_parse_row( $lineray ));

				$subjtemp = array();
				foreach( $marc as $key => $val ){
					switch( $key[0] ){
						case 'a':
						case 'b':
						case 'c':
						case 'd':
						case 'f':
						case 'g':
						case 'h':
							$subjtemp[] = array( 'type' => 'place', 'val' => $val );
							break;

						case 'e':
							$subjtemp[] = array( 'type' => 'subject', 'val' => $val );
							break;
					}
				}

			// everything else
			}else if(($lineray[0] > 599) && ($lineray[0] < 700)){
				if( 6 == $line[5] )
					continue;

				$marc = array_map( array( 'scrib', 'meditor_sanitize_punctuation' ), $this->iii_parse_row( $lineray ));

				$subjtemp = array();
				foreach( $marc as $key => $val ){
					switch( $key[0] ){
						case 'a':
						case 'b':
						case 'x':
							$subjtemp[] = array( 'type' => 'subject', 'val' => $val );
							break;

						case 'v':
						case 'k':
							$subjtemp[] = array( 'type' => 'genre', 'val' => $val );
							break;

						case 'y':
							$subjtemp[] = array( 'type' => 'time', 'val' => $val );
							break;

						case 'z':
							$subjtemp[] = array( 'type' => 'place', 'val' => $val );
							break;
					}
				}


			//URLs
			}else if($lineray[0] == 856){
				$marc = $this->iii_parse_row($lineray);
				unset($temp);
				$temp['href'] = $temp['title'] = str_replace(' ', '', $marc['u1']);
				$temp['title'] = trim( parse_url( $temp['href'] , PHP_URL_HOST ), 'www.' );
				if($marc['31'])
					$temp['title'] = $marc['31'];
				if($marc['z1'])
					$temp['title'] = $marc['z1'];
				$atomic['linked_urls'][] = array( 'name' => $temp['title'], 'href' => $temp['href'] );

			//Notes
//			}else if(($lineray[0] > 299) && ($lineray[0] < 400)){
//				$marc = $this->iii_parse_row($lineray);
//				$atomic['physdesc'][] = implode(' ', array_values($marc));

			}else if(($lineray[0] > 399) && ($lineray[0] < 490)){
				$marc = $this->iii_parse_row($lineray);
				$atomic['alttitle'][] = array( 'a' => $scrib->meditor_sanitize_punctuation( implode(' ', array_values( $marc ))));

			}else if(($lineray[0] > 799) && ($lineray[0] < 841)){
				$marc = $this->iii_parse_row($lineray);
				$atomic['alttitle'][] = array( 'a' => $scrib->meditor_sanitize_punctuation( implode(' ', array_values( $marc ))));

			}else if(($lineray[0] > 499) && ($lineray[0] < 600)){
				$line = substr($line, 9);
				if($lineray[0] == 504)
					continue;
				if($lineray[0] == 505){
					$atomic['text'][] = array( 'type' => 'contents', 'content' => ( '<ul><li>'. implode( "</li>\n<li>", array_map( array( 'scrib', 'meditor_sanitize_punctuation' ), explode( '--', str_replace( array( '|t', '|r' , '|g' ), ' ', preg_replace( '/-[\s]+-/', '--', $line )))) ) .'</li></ul>' ));
					continue;
				}

				//strip the subfield delimiter and codes
				$line = preg_replace('/\|[0-9|a-z]/', ' ', $line);
				$atomic['text'][] = array( 'type' => 'notes', 'content' => $scrib->meditor_sanitize_punctuation( $line ));
			}
			

			// pick up the subjects parsed above
			if( count( $subjtemp )){
				$temp = array();
				foreach( $subjtemp as $key => $val ){
					$temp[ $spare_keys[ $key ] .'_type' ] = $val['type']; 
					$temp[ $spare_keys[ $key ] ] = $val['val']; 
				}
				$atomic['subject'][] = $temp;
			}

			//Format
			if(($lineray[0] > 239) && ($lineray[0] < 246)){
				$marc = $this->iii_parse_row($lineray);
				$temp = ucwords(strtolower(str_replace('[', '', str_replace(']', '', $marc['h1']))));
				
				if(eregi('^book', $temp)){
					$atomic['format'][] = array( 'a' => 'Book' );

				}else if(eregi('^micr', $temp)){
					$atomic['format'][] = array( 'a' => 'Microform' );

				}else if(eregi('^electr', $temp)){
					$atomic['format'][] = array( 'a' => 'E-Resource' );

				}else if(eregi('^vid', $temp)){
					$atomic['format'][] = array( 'a' => 'Video' );
				}else if(eregi('^motion', $temp)){
					$atomic['format'][] = array( 'a' => 'Video' );

				}else if(eregi('^audi', $temp)){
					$atomic['format'][] = array( 'a' => 'Audio' );
					$format = 'Audio';
				}else if(eregi('^cass', $temp)){
					$atomic['format'][] = array( 'a' => 'Audio', 'b' => 'Cassette' );
				}else if(eregi('^phono', $temp)){
					$atomic['format'][] = array( 'a' => 'Audio', 'b' => 'Phonograph' );
				}else if(eregi('^record', $temp)){
					$atomic['format'][] = array( 'a' => 'Audio', 'b' => 'Phonograph' );
				}else if(eregi('^sound', $temp)){
					$atomic['format'][] = array( 'a' => 'Audio' );

				}else if(eregi('^carto', $temp)){
					$atomic['format'][] = array( 'a' => 'Map' );
				}else if(eregi('^map', $temp)){
					$atomic['format'][] = array( 'a' => 'Map' );
				}else if(eregi('^globe', $temp)){
					$atomic['format'][] = array( 'a' => 'Map' );
				}
			}

			if($lineray[0] == '008' && (substr($lineray[2], 22,1) == 'p' || substr($lineray[2], 22,1) == 'n')){
				$atomic['format'][] = array( 'a' => 'Journal' );
			}
/*
disabled for now, no records to test against
//Start HKUST Customization
			// Handle tag 999 - for locations and formats
			if ($lineray[0] == '999'){
				$marc = $this->iii_parse_row($lineray);
				foreach($marc as $key=>$subfield){
					if ( substr($key,0,1)=='l' ) {
						$atomic['loc'][] = $subfield;
					}else if( substr($key,0,1)=='f' ) {
						if( !$atomic['format'][0] ){
							$atomic['format'][0] = 'Book';
							$atomic['formats'][0] = 'Book';
						}
						$atomic['format'][] = $subfield;
						$atomic['formats'][] = $subfield;
					}
				}
				$atomic['loc']=array_unique($atomic['loc']);
				$atomic['format']=array_unique($atomic['format']);
				$atomic['formats']=array_unique($atomic['formats']);
			}
//End HKUST Customization
*/
		}
		// end the big loop



		// Records without _acqdates are reserves by course/professor
		// we _can_ import them, but they don't have enough info
		// to be findable or display well.
		if(!$_acqdate[0] && !$atomic['creator'][0]){
			$this->warn = 'Record number '. $bibn .' contains no catalog date or author info, skipped.';
			return( FALSE );
		}
		if(count( $atomic ) < 4){
			$this->warn = 'Record number '. $bibn .' has too little cataloging data, skipped.';
			return( FALSE );
		}

		// sanity check the pubyear
		foreach( array_filter( array_unique( $atomic['published'][0]['cy'] )) as $key => $temp )
			if( $temp > date('Y') + 2 )
				unset( $atomic['published'][0]['cy'][$key] );
		$atomic['published'][0]['cy'] = array_shift( $atomic['published'][0]['cy'] );
		if( empty( $atomic['published'][0]['cy'] ))
			$atomic['published'][0]['cy'] = date('Y') - 1;


		if(!$atomic['format'][0])
			$atomic['format'][0] = array( 'a' => 'Book' );

		if( $atomic['alttitle'] ){
			$atomic['title'] = array_merge( $atomic['title'], $atomic['alttitle'] );
			unset( $atomic['alttitle'] );
		}

		// clean up published
		if( isset( $atomic['published'][0]['lang'] ))
			$atomic['published'][0]['lang'] = array_shift( array_filter( $atomic['published'][0]['lang'] ));
		if( isset( $atomic['published'][0]['publisher'] ))
			$atomic['published'][0]['publisher'] = array_shift( array_filter( $atomic['published'][0]['publisher'] ));

		// unique the values
		foreach( $atomic as $key => $val )
			$atomic[ $key ] = $scrib->array_unique_deep( $atomic[ $key ] );

		// possibly capitalize titles
		if( $prefs['capitalize_titles'] )
			foreach( $atomic['title'] as $key => $val )
				$atomic['title'][ $key ]['a'] = ucwords( $val['a'] );

		// insert the sourceid
		$_sourceid = $prefs['sourceprefix'] . $bibn;
		$atomic['idnumbers'][] = array( 'type' => 'sourceid', 'id' => $_sourceid );

		// sanity check the _acqdate
		$_acqdate = array_unique($_acqdate);
		foreach( $_acqdate as $key => $temp )
			if( strtotime( $temp ) > strtotime( date('Y') + 2 ))
				unset( $_acqdate[$key] );
		$_acqdate = array_values( $_acqdate );
		if( !isset( $_acqdate[0] ))
			if( isset( $atomic['pubyear'][0] ))
				$_acqdate[0] = $atomic['pubyear'][0] .'-01-01';
			else
				$_acqdate[0] = ( date('Y') - 1 ) .'-01-01';
		$_acqdate = $_acqdate[0];

		if( !empty( $atomic['title'] ) && !empty( $_sourceid )){
			foreach( $atomic as $ak => $av )
				foreach( $av as $bk => $bv )
					if( is_array( $bv ))
						$atomic[ $ak ][ $bk ] = array_merge( $bv, array( 'src' => 'sourceid:'. $_sourceid ));

			$atomic = array( 'marcish' => $atomic );
			$atomic['_acqdate'] = $_acqdate;
			$atomic['_sourceid'] = $_sourceid;
			$atomic['_title'] = $atomic['marcish']['title'][0]['a'];
			$atomic['_idnumbers'] = $atomic['marcish']['idnumbers'];

			$scrib->import_insert_harvest( $atomic );
			return( $atomic );
		}else{
			$this->error = 'Record number '. $bibn .' couldn&#039;t be parsed.';
			return(FALSE);
		}

	}


	function iii_availability( &$post_id, &$sourceid ){
		global $scrib;

		$prefs = get_option('scriblio-importer-iii');
		$prefs = $prefs[ substr( $sourceid, 0, 2 ) ];
		$bibn = substr( $sourceid, 2 );

		$this->iii_harvest_passive_single_schedule( $prefs['sourceprefix'], $bibn ); // updated the harvested record

		$cache = wp_cache_get( $sourceid , 'scrib_availability' );
		if( !is_array( $cache )){
			
			$raw = wp_remote_get( 'http://'. $prefs['sourceinnopac'] .'/record='. $bibn );
			if( is_wp_error( $raw ))
				return( '<li class="scrib_availability_iii">There was an error while connecting to the inventory system. <a href="http://'. $prefs['sourceinnopac'] .'/record='. $bibn .'">Click here to try for yourself</a>.</li>' );

			// detect deleted record
			if( strpos( $raw['body'], $prefs['reject_import'] )){
				global $wpdb;
	
				// set the post to draft (might oughta use a WP function instead of writing to DB)
				$wpdb->get_results( "UPDATE $wpdb->posts SET post_status = 'draft' WHERE ID = $post_id" );
	
				// clear the post/page cache
				clean_page_cache( $post_id );
				clean_post_cache( $post_id );

				// do the post transition
				wp_transition_post_status( 'draft', 'publish', $post_id );

				// tell the user the book isn't available
				return( 'This item is no longer available at this library.' );
			}

			// clean up all the damn comments and spaces
			$raw['body'] = preg_replace( '/<!--[^-]*-->/Usi', '', $raw['body']);
			$raw['body'] = preg_replace( '/&nbsp;/Usi', '', $raw['body']);

			// get the attached items and their availability
			preg_match_all( '/<tr[^>]*class="bibItemsEntry">(.*)<\/tr>/Usi', $raw['body'], $itemrows );
			foreach( $itemrows[1] as $item ){
				preg_match_all( '/<td[^>]*>(.*)<\/td>/Usi', $item, $matches );

				if( !empty( $prefs['reject_availability'] ) && strpos( $item, $prefs['reject_availability'] ))
					continue;

				if( !empty( $prefs['require_availability'] ))
					if( strpos( $item, $prefs['require_availability'] ))
						$items[ $matches[1][0] ][] = array( 'location' => trim( strip_tags( $matches[1][0] )), 'callnumber' => trim( strip_tags( $matches[1][1] )), 'status' => trim( strip_tags( $matches[1][2] )));
					else
						continue;
				else
					$items[ $matches[1][0] ][] = array( 'location' => trim( strip_tags( $matches[1][0] )), 'callnumber' => trim( strip_tags( $matches[1][1] )), 'status' => trim( strip_tags( $matches[1][2] )));
			}
			$items = array_values( $items );

			// get the periodical holdings table
			preg_match_all( '/<table[^>]*class="bibHoldings">(.*)<\/table>/Usi', $raw['body'], $holdings );
			if( !empty( $holdings[1][0] ))
				$holdings = strip_tags( '<table>' . $holdings[1][0] . '</table>', '<table><tr><td><th><hr>');
			else
				unset( $holdings );

			// Mike D, added 4-9-9
			// Parse record for evidence of a new purchase (order record)
			preg_match_all('/<tr[^>]*class="bibOrderEntry">(.*)<\/tr>/Usi', $raw, $orderrows);
			
			// Process each order entry, relevant for items showing "x copy/ies ordered" or "being processed"
			foreach ($orderrows[1] as $order)  // maybe this will never have more than 1 element? 
			{
				preg_match_all('/<td[^>]*>(.*)<\/td>/Usi', $order, $order_rec); // $order_rec now has cell data
					
				// if order record was shown, put that status in our orders array
				if (!empty($order_rec[1][0]))
				$orders[ $order_rec[1][0] ][] = trim(strip_tags($order_rec[1][0]));
			}
			
			$orders = array_values($orders); // Change array from associative to numerically indexed
			// End of Mike D

			// Mike D, changed 4-9-9 to include ", 'orders' => $orders"
			wp_cache_set( $sourceid , array('items' => $items, 'holdings' => $holdings, 'orders' => $orders, ) ,'scrib_availability', 86400 );
		}else{
			$items = $cache['items'];
			$holdings = $cache['holdings'];
			// Mike D, added 4-9-9: include order records in cache retrieval
			// If bib record was found in WP cache, retrieve order status
			$orders = $cache['orders'];
			// End of Mike D
		}


// todo: fix this to deal with the new items array
		if( $_REQUEST['textthis'] && $availability ){
			$smsavailability = array_filter( explode( "\n", str_replace( array( 'LOCATIONCALL #STATUS', '&nbsp;', '  ' ), '', strip_tags( str_replace( '</tr>', "\n", $availability )))));
			$more = '';
			if( count( $smsavailability ) > 3 )
				$more = ' (+'. ( count( $smsavailability ) - 3 ) .' more)';
			
			return( implode( array_slice( $smsavailability, 0, 3 ), "\n") . $more );
		}

		$return = '';
		if( isset( $holdings )){
			if( !is_singular() )
				$return .= '<li class="scrib_availability_iii"><a href="'. get_permalink( $post_id ) .'">Click for periodical holdings</a>.</li>';
			else
				$return .= '<li class="scrib_availability_iii">Periodical Holdings: <span class="tools"><span class="innopac"><a href="http://'. $prefs['sourceinnopac'] .'/record='. $bibn .'" rel="nofollow" title="view inventory record"><img src="'. $scrib->path_web .'/img/icons/information.png" width="16" height="16" alt="view inventory record." /></a></span><br />'. $holdings .'</li>';
		}

		if( is_array( $items )){
			foreach( $items as $loc_key => $location ){
				$return .= ( '<li class="scrib_availability_iii"><span class="location">'. $location[0]['location'] .'</span>');
				foreach( $location as $item_key => $item ){
					$return .= ( '<br /><span class="callnumber">'. $item['callnumber'] .'</span> (<span class="status">'. $item['status'] .'</span>) <span class="tools"><!--<span class="textthis"><a href="'. get_permalink( $post_id ) .'?textthis='. $prefs['sourceprefix'] .'_'. $loc_key .'_'. $item_key .'" rel="nofollow" title="text this item&#39;s location to your cellphone"><img src="'. $scrib->path_web .'/img/icons/phone.png" width="16" height="16" alt="text this item&#39;s location to your cellphone." /></a> </span><span class="reserve"><a href="http://'. $prefs['sourceinnopac'] .'/search?/.b'. $bibn .'/.b'. $bibn .'/1%2C1%2C1%2CB/request~b'. $bibn .'" rel="nofollow" title="reserve this item"><img src="'. $scrib->path_web .'/img/icons/cart_add.png" width="16" height="16" alt="reserve this item." /></a> </span>--><span class="innopac"><a href="http://'. $prefs['sourceinnopac'] .'/record='. $bibn .'" rel="nofollow" title="view inventory record"><img src="'. $scrib->path_web .'/img/icons/information.png" width="16" height="16" alt="view inventory record." /></a></span></span>');
				}
				$return .= ( '</li>');
			}
		}

		// Mike D, added 4-9-9: include order text in Scriblio record
		// Present order information, if there is any
		if ( is_array($orders) )  // $orders will be an array of string if the order entry markup was found
		{	
			foreach ($orders as $order_status)
			{
				$return .= '<li class="scrib_availability_iii"><span class="order">'.__($order_status[0]).'</span></li>'."\n";
			}
		}
		// End of Mike D

		return( $return );
	}

	function iii_availability_filter( &$content, &$post_id, &$idnumbers ){
		$prefs = get_option('scriblio-importer-iii');
		$connections = array_keys( $prefs );
		
		$return = '';
		
		foreach( $idnumbers['sourceid'] as $sourceid ){
			if( in_array( substr( $sourceid, 0, 2 ), $connections ))
				$return .= $this->iii_availability( $post_id, $sourceid );
		}

		return( $content . $return );
	}

	function iii_harvest_passive(){
		global $wpdb, $scrib, $bsuite;

		if ( get_option( 'scriblio-importer-iii-passvimport') > time() || !$bsuite->get_lock( 'scriblio-importer-iii-passvimport' ) )
			return( TRUE );

		$prefs = get_option('scriblio-importer-iii');
		$prefs = $prefs[ array_rand( $prefs ) ];

		$record_start = $wpdb->get_var( 'SELECT SUBSTRING( source_id, 3 ) FROM '. $scrib->harvest_table .' WHERE source_id LIKE "'. $wpdb->escape( $prefs['sourceprefix'] ) .'%" ORDER BY source_id DESC LIMIT 1' );

		if( !absint( $record_start ))
			$record_start = $prefs['bibrange_low'];

		$record_end = $record_start + 50;

		if( $prefs['bibrange_high'] > $record_end )
			$this->iii_harvest_range( $prefs['sourceprefix'], $record_start, $record_end );

		update_option( 'scriblio-importer-iii-passvimport', time() + 14400 );
	}

	function iii_harvest_passive_single_schedule( $prefix, $bibn ) { 
		global $wpdb, $scrib; 

		$prefs = get_option('scriblio-importer-iii');
		$prefs = $prefs[ $prefix ];
		if( !is_array( $prefs ))
			return( FALSE );

		if ( wp_cache_get( $prefs['sourceprefix'] . $bibn, 'scrib_harvested' ) > time())
			return( FALSE );

		if( ( absint( $wpdb->get_var( 'SELECT UNIX_TIMESTAMP( harvest_date ) FROM '. $scrib->harvest_table .' WHERE source_id = "'. $wpdb->escape( $prefs['sourceprefix'] . $bibn ) .'" LIMIT 1' )) + 2500000 ) > time() )
			return( FALSE );

//		wp_clear_scheduled_hook( 'scrib-importer-iii-harvest-single', 'ak', 999987 , 1000017 );

		wp_schedule_single_event( time() + rand( 60, 585 ) , 'scrib-importer-iii-harvest-single' , array( $prefs['sourceprefix'] . $bibn ));

		wp_cache_set( $bibr['_sourceid'], time() + 86400, 'scrib_harvested', time() + 86400 );
	} 

	function iii_harvest_passive_single( $sourceid ) {
		global $bsuite; 

		//error_log( "Updating a single record: ". $sourceid );

		if( !$bsuite->get_lock( 'scriblio-importer-iii-updsingle' )){
			wp_schedule_single_event( time() + rand( 60, 585 ) , 'scrib-importer-iii-harvest-single' , array( $sourceid ) );
			return( FALSE );
		}

		$prefs = get_option('scriblio-importer-iii');
		$prefs = $prefs[ substr( $sourceid, 0, 2 ) ];
		if( !is_array( $prefs ))
			return( FALSE );

		$bibn = substr( $sourceid, 2 );

		$min = absint( $bibn - 7 );
		$max = $bibn + 7;

		$this->iii_harvest_range( $prefs['sourceprefix'], $min, $max );
	} 

	function iii_harvest_range( $prefix, $bibn, $bibn_end ) {
		$bibn = absint( $bibn );
		$bibn_end = absint( $bibn_end );

		for( $bibn ; $bibn <= $bibn_end ; $bibn++ )
			$this->iii_parse_record( $prefix, $this->iii_get_record( $prefix , $bibn ) , $bibn );
	} 

	function init(){
		global $bsuite;

		add_filter( 'scrib_availability_excerpt', array( &$this, 'iii_availability_filter' ), 9, 3);
		add_filter( 'scrib_availability_content', array( &$this, 'iii_availability_filter' ), 9, 3);

		add_action('scrib-importer-iii-harvest-single', array( &$this, 'iii_harvest_passive_single' ));
		add_action('scrib-importer-iii-harvest-single', 'iii_harvest_passive_single' );

		if( $bsuite->loadavg < get_option( 'bsuite_load_max' )) // only do cron if load is low-ish
			add_filter('bsuite_interval', array( &$this, 'iii_harvest_passive' ));
	}

	// Default constructor 
	function ScribIII_import() {
		add_action( 'init', array( &$this, 'init' ));
	} 
} 

// Instantiate and register the importer 
$scribiii_import = new ScribIII_import();
include_once( ABSPATH . 'wp-admin/includes/import.php' ); 
if( function_exists( 'register_importer' )){ 
	register_importer( $scribiii_import->importer_code, $scribiii_import->importer_name, $scribiii_import->importer_desc, array( &$scribiii_import, 'dispatch' )); 
}